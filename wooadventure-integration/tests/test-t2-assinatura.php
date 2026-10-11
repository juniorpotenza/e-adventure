<?php
// PHPUnit: usa stubs mínimos de WordPress/WooCommerce (sem WP_UnitTestCase). Não foi executado nesta sessão.
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
foreach ( array( 'MINUTE_IN_SECONDS' => 60, 'HOUR_IN_SECONDS' => 3600, 'MB_IN_BYTES' => 1048576 ) as $const => $value ) {
    if ( ! defined( $const ) ) {
        define( $const, $value );
    }
}

class WcaiTestJsonError extends Exception {
    public $data;
    public function __construct( $data, $status ) {
        parent::__construct( (string) $data, (int) $status );
        $this->data = $data;
    }
}

class WcaiTestOrder {
    public function get_id() { return 7; }
    public function get_order_key() { return 'wc_order_abc'; }
}

if ( ! class_exists( 'WCAI_Participants_DB' ) ) {
    class WCAI_Participants_DB {
        public static $lookups = 0;
        public static function get_by_order( $order_id ) {
            self::$lookups++;
            return array( array( 'id' => 5, 'cpf' => '012.345.678-90', 'reservation_id' => 9, 'nome_completo' => 'Ana', 'order_id' => 7 ) );
        }
    }
}
if ( ! class_exists( 'WCAI_Reservations' ) ) {
    class WCAI_Reservations {
        public static $status = 'confirmed';
        public static function get_by_id( $id ) {
            return (object) array( 'status' => self::$status );
        }
    }
}

if ( ! function_exists( 'check_ajax_referer' ) ) { function check_ajax_referer() { return true; } }
if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $v ) { return $v; } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( (string) $v ); } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'wp_salt' ) ) { function wp_salt() { return 'test-salt'; } }
if ( ! function_exists( 'apply_filters' ) ) { function apply_filters( $tag, $value ) { return $value; } }
if ( ! function_exists( 'wp_using_ext_object_cache' ) ) { function wp_using_ext_object_cache() { return false; } }
if ( ! function_exists( 'get_transient' ) ) { function get_transient( $k ) { return isset( $GLOBALS['wcai_transients'][ $k ] ) ? $GLOBALS['wcai_transients'][ $k ] : false; } }
if ( ! function_exists( 'set_transient' ) ) { function set_transient( $k, $v ) { $GLOBALS['wcai_transients'][ $k ] = $v; return true; } }
if ( ! function_exists( 'wc_get_order' ) ) { function wc_get_order( $id ) { return 7 === (int) $id ? new WcaiTestOrder() : false; } }
if ( ! function_exists( 'wp_send_json_error' ) ) { function wp_send_json_error( $data = null, $status = 200 ) { throw new WcaiTestJsonError( $data, $status ); } }

require_once dirname( __DIR__ ) . '/includes/class-assinatura.php';

class Test_T2_Assinatura extends TestCase {

    private $sut;

    protected function setUp(): void {
        $GLOBALS['wcai_transients'] = array();
        $_POST = array();
        $_COOKIE = array();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        WCAI_Participants_DB::$lookups = 0;
        WCAI_Reservations::$status = 'confirmed';
        $this->sut = ( new ReflectionClass( 'WCAI_Assinatura' ) )->newInstanceWithoutConstructor();
    }

    private function call( $method, array $args = array() ) {
        $m = new ReflectionMethod( 'WCAI_Assinatura', $method );
        $m->setAccessible( true );
        return $m->invokeArgs( $this->sut, $args );
    }

    private function check_pax( array $post ) {
        $_POST = $post;
        try {
            $this->sut->ajax_check_pax_deep();
        } catch ( WcaiTestJsonError $e ) {
            return $e;
        }
        $this->fail( 'Esperava wp_send_json_error.' );
    }

    public function test_same_error_for_missing_order_wrong_key_and_unlinked_cpf() {
        $missing = $this->check_pax( array( 'order_id' => 99, 'cpf' => '01234567890', 'order_key' => 'wc_order_abc' ) );
        $badkey  = $this->check_pax( array( 'order_id' => 7, 'cpf' => '01234567890', 'order_key' => 'errada' ) );
        $nokey   = $this->check_pax( array( 'order_id' => 7, 'cpf' => '01234567890' ) );
        $badcpf  = $this->check_pax( array( 'order_id' => 7, 'cpf' => '99999999999', 'order_key' => 'wc_order_abc' ) );

        foreach ( array( $badkey, $nokey, $badcpf ) as $other ) {
            $this->assertSame( $missing->data, $other->data );
            $this->assertSame( $missing->getCode(), $other->getCode() );
        }
    }

    public function test_eleventh_attempt_is_blocked_before_participant_lookup() {
        $post = array( 'order_id' => 7, 'cpf' => '99999999999', 'order_key' => 'wc_order_abc' );

        for ( $i = 1; $i <= 10; $i++ ) {
            $this->assertSame( 403, $this->check_pax( $post )->getCode() );
        }
        $lookups_before = WCAI_Participants_DB::$lookups;

        $this->assertSame( 429, $this->check_pax( $post )->getCode() );
        $this->assertSame( $lookups_before, WCAI_Participants_DB::$lookups );
    }

    public function test_pending_reservation_cannot_sign() {
        WCAI_Reservations::$status = 'pending';
        $this->assertFalse( $this->call( 'get_participant_for_order', array( new WcaiTestOrder(), '01234567890' ) ) );

        WCAI_Reservations::$status = 'confirmed';
        $found = $this->call( 'get_participant_for_order', array( new WcaiTestOrder(), '01234567890' ) );
        $this->assertSame( 5, $found['id'] );
    }

    public function test_expired_session_cookie_fails_and_valid_one_passes() {
        $cookie = function ( $expires ) {
            $payload = '7|5|' . $expires;
            return $payload . '|' . hash_hmac( 'sha256', $payload, 'test-salt' );
        };

        $_COOKIE['wcai_pax_session'] = $cookie( time() - 10 );
        $this->assertFalse( $this->call( 'valid_session_for_participant', array( 7, 5 ) ) );

        $_COOKIE['wcai_pax_session'] = $cookie( time() + 600 );
        $this->assertTrue( $this->call( 'valid_session_for_participant', array( 7, 5 ) ) );
    }

    public function test_forwarded_ip_ignored_unless_remote_is_trusted_proxy() {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
        $this->assertSame( '203.0.113.9', $this->call( 'client_ip' ) );
    }
}

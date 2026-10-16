<?php
// PHPUnit com stubs: separação de contexto do HMAC e ajax_check_order sem oráculo.
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
foreach ( array( 'MINUTE_IN_SECONDS' => 60, 'HOUR_IN_SECONDS' => 3600, 'MB_IN_BYTES' => 1048576 ) as $const => $value ) {
    if ( ! defined( $const ) ) {
        define( $const, $value );
    }
}

if ( ! class_exists( 'WcaiF6JsonError' ) ) {
    class WcaiF6JsonError extends Exception {
        public $data;
        public function __construct( $data, $status ) {
            parent::__construct( (string) $data, (int) $status );
            $this->data = $data;
        }
    }
}
if ( ! class_exists( 'WcaiF6JsonOk' ) ) {
    class WcaiF6JsonOk extends Exception {}
}
if ( ! class_exists( 'WcaiF6Order' ) ) {
    class WcaiF6Order {
        public function get_id() { return 7; }
        public function get_order_key() { return 'wc_order_abc'; }
    }
}
if ( ! class_exists( 'WCAI_Participants_DB' ) ) {
    class WCAI_Participants_DB {
        public static function get_by_order( $order_id ) {
            return array( array( 'id' => 5, 'cpf' => '01234567890', 'reservation_id' => 9, 'nome_completo' => 'Ana', 'order_id' => 7 ) );
        }
    }
}
if ( ! class_exists( 'WCAI_Reservations' ) ) {
    class WCAI_Reservations {
        public static $status = 'confirmed';
        public static function get_by_id( $id ) { return (object) array( 'status' => self::$status ); }
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
if ( ! function_exists( 'wc_get_order' ) ) {
    function wc_get_order( $id ) {
        $GLOBALS['wcai_order_lookups'] = ( isset( $GLOBALS['wcai_order_lookups'] ) ? $GLOBALS['wcai_order_lookups'] : 0 ) + 1;
        return 7 === (int) $id ? new WcaiF6Order() : false;
    }
}
if ( ! function_exists( 'wp_send_json_error' ) ) { function wp_send_json_error( $data = null, $status = 200 ) { throw new WcaiF6JsonError( $data, $status ); } }
if ( ! function_exists( 'wp_send_json_success' ) ) { function wp_send_json_success( $data = null ) { throw new WcaiF6JsonOk( 'ok' ); } }

require_once dirname( __DIR__ ) . '/includes/class-assinatura.php';

class Test_F6_Signature_Hardening extends TestCase {

    private $sut;

    protected function setUp(): void {
        $GLOBALS['wcai_transients']    = array();
        $GLOBALS['wcai_order_lookups'] = 0;
        $_POST   = array();
        $_COOKIE = array();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        WCAI_Reservations::$status = 'confirmed';
        $this->sut = ( new ReflectionClass( 'WCAI_Assinatura' ) )->newInstanceWithoutConstructor();
    }

    private function call( $method, array $args = array() ) {
        $m = new ReflectionMethod( 'WCAI_Assinatura', $method );
        $m->setAccessible( true );
        return $m->invokeArgs( $this->sut, $args );
    }

    // Retorna 'ok' ou array( mensagem, status ) do erro.
    private function check( array $post ) {
        $_POST = $post;
        try {
            $this->sut->ajax_check_order();
        } catch ( WcaiF6JsonOk $e ) {
            return 'ok';
        } catch ( WcaiF6JsonError $e ) {
            return array( $e->data, $e->getCode() );
        }
        $this->fail( 'ajax_check_order deve responder via wp_send_json_*.' );
    }

    public function test_hmac_value_signed_in_one_context_fails_in_another() {
        $payload = '7|5|' . ( time() + 600 );

        $this->assertNotSame( WCAI_Assinatura::hmac( 'pax-cookie', $payload ), WCAI_Assinatura::hmac( 'ticket-cookie', $payload ) );
        $this->assertNotSame( WCAI_Assinatura::hmac( 'pax-cookie', $payload ), WCAI_Assinatura::hmac( 'device', $payload ) );
        $this->assertNotSame( WCAI_Assinatura::hmac( 'pax-cookie', $payload ), hash_hmac( 'sha256', $payload, 'test-salt' ) );

        $_COOKIE['wcai_pax_session'] = $payload . '|' . WCAI_Assinatura::hmac( 'pax-cookie', $payload );
        $this->assertTrue( $this->call( 'valid_session_for_participant', array( 7, 5 ) ) );

        foreach ( array( 'ticket-cookie', 'device', 'ip' ) as $other ) {
            $_COOKIE['wcai_pax_session'] = $payload . '|' . WCAI_Assinatura::hmac( $other, $payload );
            $this->assertFalse( $this->call( 'valid_session_for_participant', array( 7, 5 ) ), $other );
        }

        // Esquema antigo (sem contexto) deixou de valer.
        $_COOKIE['wcai_pax_session'] = $payload . '|' . hash_hmac( 'sha256', $payload, 'test-salt' );
        $this->assertFalse( $this->call( 'valid_session_for_participant', array( 7, 5 ) ) );
    }

    public function test_device_hash_of_payload_cannot_forge_session_cookie() {
        // User-Agent é controlado pelo atacante: o hash de dispositivo do payload não serve de assinatura de cookie.
        $payload = '7|5|' . ( time() + 600 );
        $_COOKIE['wcai_pax_session'] = $payload . '|' . WCAI_Assinatura::hmac( 'device', $payload );

        $this->assertFalse( $this->call( 'valid_session_for_participant', array( 7, 5 ) ) );
    }

    public function test_check_order_gives_identical_answer_for_missing_wrong_key_and_not_eligible() {
        $missing = $this->check( array( 'order_id' => 99, 'order_key' => 'wc_order_abc' ) );
        $badkey  = $this->check( array( 'order_id' => 7, 'order_key' => 'errada' ) );
        $nokey   = $this->check( array( 'order_id' => 7 ) );

        WCAI_Reservations::$status = 'pending';
        $ineligible = $this->check( array( 'order_id' => 7, 'order_key' => 'wc_order_abc' ) );

        $this->assertIsArray( $missing );
        $this->assertSame( 403, $missing[1] );
        foreach ( array( $badkey, $nokey, $ineligible ) as $other ) {
            $this->assertSame( $missing, $other );
        }

        WCAI_Reservations::$status = 'confirmed';
        $this->assertSame( 'ok', $this->check( array( 'order_id' => 7, 'order_key' => 'wc_order_abc' ) ) );
    }

    public function test_check_order_rate_limit_runs_before_order_lookup() {
        $post = array( 'order_id' => 7, 'order_key' => 'errada' );

        for ( $i = 1; $i <= WCAI_Assinatura::RATE_LIMIT; $i++ ) {
            $this->assertSame( 403, $this->check( $post )[1] );
        }
        $lookups = $GLOBALS['wcai_order_lookups'];

        $this->assertSame( 429, $this->check( $post )[1] );
        $this->assertSame( $lookups, $GLOBALS['wcai_order_lookups'] );
    }
}

<?php
// PHPUnit: stubs mínimos de WordPress (sem WP_UnitTestCase). Não foi executado nesta sessão (PHP ausente).
// Processo separado: os stubs abaixo (guardados por class_exists) conflitam com os de outros testes.
// Cobre: apagador/exportador LGPD (achados 1 e 3), edição na Minha Conta (5) e guardian_required (6).
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }

if ( ! class_exists( 'WP_Error' ) ) {
    class WP_Error {
        public $code;
        public $message;
        public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
        public function get_error_message() { return $this->message; }
    }
}

if ( ! class_exists( 'WCAI_Participants_DB' ) ) {
    class WCAI_Participants_DB {
        public static $rows = array();
        public static $updates = array();
        public static function get_table_name() { return 'wp_wcai_participantes'; }
        public static function update( $id, $data ) { self::$updates[ $id ] = $data; return 1; }
        public static function get_by_order( $order_id ) {
            return array_values( array_filter( self::$rows, function ( $r ) use ( $order_id ) { return (int) $r['order_id'] === (int) $order_id; } ) );
        }
        public static function get_by_id( $id ) {
            foreach ( self::$rows as $r ) {
                if ( (int) $r['id'] === (int) $id ) { return $r; }
            }
            return null;
        }
    }
}

if ( ! class_exists( 'WCAI_Audit_Log' ) ) {
    class WCAI_Audit_Log {
        public static $entries = array();
        public static function log( $action, $type, $id = null, $context = array() ) { self::$entries[] = array( $action, $type, $id, $context ); }
    }
}

if ( ! class_exists( 'WCAI_Assinatura' ) ) {
    class WCAI_Assinatura {
        public static $deleted_qr = array();
        public static function delete_local_qr( $hash, $participant_id ) { self::$deleted_qr[] = array( $hash, $participant_id ); }
    }
}

if ( ! class_exists( 'WcaiF2Wpdb' ) ) {
    class WcaiF2Wpdb {
        public $prefix = 'wp_';
        public $acceptances = array();
        public $queries = array();
        public function prepare( $query, ...$args ) {
            $args = isset( $args[0] ) && is_array( $args[0] ) ? $args[0] : $args;
            return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
        }
        public function get_results( $query, $output = ARRAY_A ) { return $this->acceptances; }
        public function query( $query ) { $this->queries[] = $query; return 1; }
    }
}

if ( ! class_exists( 'WcaiF2Order' ) ) {
    class WcaiF2Order {
        public $id;
        public $meta;
        public $deleted = array();
        public $saved = false;
        public $quantities;
        public function __construct( $id, $meta = array(), $quantities = array( 5 ) ) { $this->id = $id; $this->meta = $meta; $this->quantities = $quantities; }
        public function get_id() { return $this->id; }
        public function get_meta( $key ) { return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : ''; }
        public function delete_meta_data( $key ) { $this->deleted[] = $key; unset( $this->meta[ $key ] ); }
        public function save() { $this->saved = true; }
        public function get_items() {
            return array_map( function ( $q ) { return new class( $q ) { private $q; public function __construct( $q ) { $this->q = $q; } public function get_quantity() { return $this->q; } }; }, $this->quantities );
        }
    }
}

if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $v ) { return $v instanceof WP_Error; } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( (string) $v ); } }
if ( ! function_exists( 'sanitize_email' ) ) { function sanitize_email( $v ) { return trim( (string) $v ); } }
if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $v ) { return $v; } }
if ( ! function_exists( 'apply_filters' ) ) { function apply_filters( $tag, $value ) { return $value; } }
if ( ! function_exists( 'add_filter' ) ) { function add_filter() {} }
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'wc_get_orders' ) ) { function wc_get_orders() { return array( 7 ); } }
if ( ! function_exists( 'wc_get_order' ) ) { function wc_get_order( $id ) { return isset( $GLOBALS['wcai_f2_orders'][ $id ] ) ? $GLOBALS['wcai_f2_orders'][ $id ] : false; } }
if ( ! function_exists( 'get_user_by' ) ) { function get_user_by() { return false; } }
if ( ! function_exists( 'get_posts' ) ) { function get_posts() { return array(); } }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta() { return ''; } }
if ( ! function_exists( 'wp_delete_post' ) ) { function wp_delete_post() {} }
if ( ! function_exists( 'wp_upload_dir' ) ) { function wp_upload_dir() { return array( 'error' => true ); } }

require_once dirname( __DIR__ ) . '/includes/class-utils.php';
require_once dirname( __DIR__ ) . '/includes/class-data-resolver.php';
require_once dirname( __DIR__ ) . '/includes/class-legal-acceptances.php';
require_once dirname( __DIR__ ) . '/includes/class-privacy.php';
require_once dirname( __DIR__ ) . '/includes/class-frontend-account.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Test_F2_Privacy_Account extends TestCase {

    const HOLDER_CPF    = '52998224725';
    const COMPANION_CPF = '11144477735';

    private function participant( $id, $cpf, $extra = array() ) {
        return array_merge(
            array(
                'id' => $id, 'order_id' => 7, 'nome_completo' => 'Pessoa ' . $id, 'cpf' => $cpf,
                'data_nascimento' => '1990-01-01', 'termo_assinado' => 0, 'created_at' => '2026-01-01 00:00:00',
                'ticket_hash' => '', 'checkin_time' => null, 'checkin_status' => 0,
            ),
            $extra
        );
    }

    private function setup_order( $rows, $meta = null, $quantities = array( 5 ) ) {
        $GLOBALS['wpdb'] = new WcaiF2Wpdb();
        $order = new WcaiF2Order( 7, null === $meta ? array( 'billing_cpf' => self::HOLDER_CPF ) : $meta, $quantities );
        $GLOBALS['wcai_f2_orders'] = array( 7 => $order );
        WCAI_Participants_DB::$rows = $rows;
        WCAI_Participants_DB::$updates = array();
        WCAI_Audit_Log::$entries = array();
        WCAI_Assinatura::$deleted_qr = array();

        return $order;
    }

    private function validate( $order, $rows ) {
        $m = new ReflectionMethod( 'WCAI_Frontend_Account', 'validate_frontend_rows' );
        $m->setAccessible( true );

        return $m->invoke( new WCAI_Frontend_Account(), $order, $rows );
    }

    private function row( $id, $cpf, $birth = '01/01/1990' ) {
        return array( 'id' => $id, 'nome_completo' => 'Pessoa ' . $id, 'cpf' => $cpf, 'data_nascimento' => $birth );
    }

    // --- 1. eraser: metas do pedido, QR e log sem PII ---

    public function test_eraser_clears_order_metas_deletes_qr_and_logs_without_pii() {
        $order = $this->setup_order( array(
            $this->participant( 1, self::HOLDER_CPF, array( 'ticket_hash' => 'abc123' ) ),
            $this->participant( 2, self::COMPANION_CPF, array( 'data_nascimento' => '2015-05-05' ) ),
        ) );

        $result = ( new WCAI_Privacy() )->erase( 'ana@example.com' );

        $this->assertTrue( $result['items_removed'] );
        $this->assertEqualsCanonicalizing( WCAI_Privacy::ORDER_PII_METAS, $order->deleted );
        $this->assertContains( '_additional_participants', $order->deleted );
        $this->assertTrue( $order->saved );
        $this->assertSame( array( array( 'abc123', 1 ) ), WCAI_Assinatura::$deleted_qr );

        $log = json_encode( WCAI_Audit_Log::$entries );
        $this->assertStringNotContainsString( self::HOLDER_CPF, $log );
        $this->assertStringNotContainsString( 'abc123', $log );
        $this->assertStringNotContainsString( 'Pessoa', $log );
    }

    // --- 3. só o titular é apagado; acompanhante permanece ---

    public function test_eraser_anonymises_only_the_holder_row() {
        $this->setup_order( array(
            $this->participant( 1, self::HOLDER_CPF ),
            $this->participant( 2, self::COMPANION_CPF ),
        ) );

        ( new WCAI_Privacy() )->erase( 'ana@example.com' );

        $this->assertArrayHasKey( 1, WCAI_Participants_DB::$updates );
        $this->assertSame( 'Anonimizado', WCAI_Participants_DB::$updates[1]['nome_completo'] );
        $this->assertArrayNotHasKey( 2, WCAI_Participants_DB::$updates );
    }

    public function test_holder_falls_back_to_lowest_id_when_order_has_no_billing_cpf() {
        $this->setup_order( array(
            $this->participant( 9, self::COMPANION_CPF ),
            $this->participant( 4, self::HOLDER_CPF ),
        ), array() );

        ( new WCAI_Privacy() )->erase( 'ana@example.com' );

        $this->assertArrayHasKey( 4, WCAI_Participants_DB::$updates );
        $this->assertArrayNotHasKey( 9, WCAI_Participants_DB::$updates );
    }

    public function test_exporter_gives_companions_name_only() {
        $this->setup_order( array(
            $this->participant( 1, self::HOLDER_CPF ),
            $this->participant( 2, self::COMPANION_CPF, array( 'nome_completo' => 'Crianca', 'data_nascimento' => '2015-05-05' ) ),
        ) );

        $result = ( new WCAI_Privacy() )->export( 'ana@example.com' );
        $companion = array_values( array_filter( $result['data'], function ( $i ) { return 'wcai-acompanhantes' === $i['group_id']; } ) );
        $holder    = array_values( array_filter( $result['data'], function ( $i ) { return 'wcai-participantes' === $i['group_id']; } ) );

        $this->assertCount( 1, $companion );
        $this->assertSame( array( 'Pedido', 'Nome' ), array_column( $companion[0]['data'], 'name' ) );
        $this->assertContains( 'Data de nascimento', array_column( $holder[0]['data'], 'name' ) );
        $json = json_encode( $companion );
        $this->assertStringNotContainsString( '2015-05-05', $json );
        $this->assertStringNotContainsString( self::COMPANION_CPF, $json );
    }

    // --- 5. Minha Conta: CPF/nascimento travados e limite de novas linhas ---

    public function test_cpf_and_birth_are_locked_when_participant_has_acceptance() {
        $order = $this->setup_order( array( $this->participant( 1, self::HOLDER_CPF ) ) );
        $GLOBALS['wpdb']->acceptances = array( array( 'id' => 3 ) );

        $this->assertNotSame( '', $this->validate( $order, array( $this->row( 1, self::COMPANION_CPF ) ) ) );
        $this->assertNotSame( '', $this->validate( $order, array( $this->row( 1, self::HOLDER_CPF, '02/02/1990' ) ) ) );
        $this->assertSame( '', $this->validate( $order, array( $this->row( 1, '529.982.247-25' ) ) ) );
    }

    public function test_cpf_is_locked_after_checkin() {
        $order = $this->setup_order( array( $this->participant( 1, self::HOLDER_CPF, array( 'checkin_time' => '2026-03-03 08:00:00', 'checkin_status' => 1 ) ) ) );

        $this->assertNotSame( '', $this->validate( $order, array( $this->row( 1, self::COMPANION_CPF ) ) ) );
    }

    public function test_cpf_is_editable_without_acceptance_or_checkin() {
        $order = $this->setup_order( array( $this->participant( 1, self::HOLDER_CPF ) ) );

        $this->assertSame( '', $this->validate( $order, array( $this->row( 1, self::COMPANION_CPF, '03/03/1991' ) ) ) );
    }

    public function test_new_rows_cannot_exceed_ticket_quantity() {
        $order = $this->setup_order( array( $this->participant( 1, self::HOLDER_CPF ), $this->participant( 2, self::COMPANION_CPF ) ), null, array( 2 ) );
        $new   = array( 'nome_completo' => 'Extra', 'cpf' => '12345678909', 'data_nascimento' => '01/01/1990' );

        $this->assertNotSame( '', $this->validate( $order, array( $this->row( 1, self::HOLDER_CPF ), $new ) ) );

        $order = $this->setup_order( array( $this->participant( 1, self::HOLDER_CPF ) ), null, array( 2 ) );
        $this->assertSame( '', $this->validate( $order, array( $this->row( 1, self::HOLDER_CPF ), $new ) ) );
    }

    // --- 6. guardian_required ---

    public function test_invalid_or_zero_birth_date_counts_as_minor() {
        foreach ( array( '0000-00-00', '', 'lixo', '2026-13-45' ) as $birth ) {
            $this->assertTrue( WCAI_Legal_Acceptances::guardian_required( array( 'data_nascimento' => $birth ) ), var_export( $birth, true ) );
        }
        $this->assertTrue( WCAI_Legal_Acceptances::guardian_required( array() ) );
    }

    public function test_valid_adult_and_minor_birth_dates() {
        $this->assertFalse( WCAI_Legal_Acceptances::guardian_required( array( 'data_nascimento' => '1990-01-01' ) ) );
        $this->assertTrue( WCAI_Legal_Acceptances::guardian_required( array( 'data_nascimento' => ( new DateTime( '-10 years' ) )->format( 'Y-m-d' ) ) ) );
    }
}

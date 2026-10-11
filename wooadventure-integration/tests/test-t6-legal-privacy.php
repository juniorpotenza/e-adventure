<?php
// PHPUnit: stubs mínimos de WordPress (sem WP_UnitTestCase). Não foi executado nesta sessão (PHP ausente).
// Processo separado: os stubs de WCAI_Participants_DB abaixo conflitam com os de outros testes.
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

class WP_Error {
    public $code;
    public $message;
    public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
    public function get_error_code() { return $this->code; }
    public function get_error_message() { return $this->message; }
}

class WCAI_Participants_DB {
    public static $updates = array();
    public static function get_table_name() { return 'wp_wcai_participantes'; }
    public static function update( $id, $data ) { self::$updates[ $id ] = $data; return 1; }
}

class WCAI_Audit_Log {
    public static $entries = array();
    public static function log( $action, $type, $id = null, $context = array() ) { self::$entries[] = array( $action, $type, $id, $context ); }
}

class WcaiFakeWpdb {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $insert_fails = false;
    public $inserted = array();
    public $queries = array();
    public $existing = null;
    public $participants = array();
    public function suppress_errors( $v = true ) { return false; }
    public function prepare( $query, ...$args ) {
        $args = isset( $args[0] ) && is_array( $args[0] ) ? $args[0] : $args;
        return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
    }
    public function insert( $table, $row ) {
        if ( $this->insert_fails ) { return false; }
        $this->inserted[] = $row;
        $this->insert_id = 99;
        return 1;
    }
    public function get_row( $query, $output = OBJECT ) { return $this->existing; }
    public function get_results( $query, $output = OBJECT ) {
        if ( false !== strpos( $query, 'wcai_participantes' ) ) { return $this->participants; }
        return array( array( 'id' => 3, 'document_version' => 'v1', 'document_date' => '2026-01-01', 'acceptance_method' => 'signature', 'guardian_name' => '', 'accepted_at' => '2026-02-02 10:00:00' ) );
    }
    public function query( $query ) { $this->queries[] = $query; return 1; }
    public function get_charset_collate() { return ''; }
}

if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $v ) { return $v instanceof WP_Error; } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( (string) $v ); } }
if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $v ) { return strtolower( (string) $v ); } }
if ( ! function_exists( 'sanitize_email' ) ) { function sanitize_email( $v ) { return trim( (string) $v ); } }
if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $v ) { return $v; } }
if ( ! function_exists( 'wp_salt' ) ) { function wp_salt() { return 'test-salt'; } }
if ( ! function_exists( 'current_time' ) ) { function current_time() { return '2026-03-03 12:00:00'; } }
if ( ! function_exists( 'apply_filters' ) ) { function apply_filters( $tag, $value ) { return $value; } }
if ( ! function_exists( 'add_filter' ) ) { function add_filter() {} }
if ( ! function_exists( 'wc_get_orders' ) ) { function wc_get_orders() { return array( 7 ); } }
if ( ! function_exists( 'get_user_by' ) ) { function get_user_by() { return false; } }
if ( ! function_exists( 'get_posts' ) ) { function get_posts() { return array( 55 ); } }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta() { return ''; } }
if ( ! function_exists( 'wp_delete_post' ) ) { function wp_delete_post( $id ) { $GLOBALS['wcai_deleted_posts'][] = $id; } }
if ( ! function_exists( 'wp_upload_dir' ) ) { function wp_upload_dir() { return array( 'error' => true ); } }

require_once dirname( __DIR__ ) . '/includes/class-utils.php';
require_once dirname( __DIR__ ) . '/includes/class-legal-acceptances.php';
require_once dirname( __DIR__ ) . '/includes/class-privacy.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Test_T6_Legal_Privacy extends TestCase {

    const VALID_GUARDIAN_CPF = '52998224725';

    private function participant( $years_old ) {
        return array(
            'id'              => 5,
            'cpf'             => '12345678909',
            'data_nascimento' => ( new DateTime( "-$years_old years -1 day" ) )->format( 'Y-m-d' ),
        );
    }

    private function row( $participant_id, $guardian_cpf = '' ) {
        return array(
            'participant_id' => $participant_id, 'reservation_id' => 9, 'order_id' => 7, 'departure_id' => 1,
            'document_id' => 2, 'document_version' => 'v1', 'document_hash' => str_repeat( 'a', 64 ),
            'document_title' => 'Termo', 'document_text' => 'Texto aceito', 'document_date' => '2026-01-01',
            'guardian_name' => 'Maria', 'guardian_cpf' => $guardian_cpf, 'acceptance_method' => 'signature', 'signature_post_id' => 0,
        );
    }

    private function insert( array $row ) {
        $m = new ReflectionMethod( 'WCAI_Legal_Acceptances', 'insert_acceptance' );
        $m->setAccessible( true );
        return $m->invoke( null, $row );
    }

    public function test_minor_without_valid_guardian_is_rejected() {
        $minor = $this->participant( 10 );

        $this->assertInstanceOf( WP_Error::class, WCAI_Legal_Acceptances::validate_guardian( $minor, '', '' ) );
        $this->assertInstanceOf( WP_Error::class, WCAI_Legal_Acceptances::validate_guardian( $minor, 'Maria', '11111111111' ) );
        $this->assertInstanceOf( WP_Error::class, WCAI_Legal_Acceptances::validate_guardian( $minor, 'Maria', '' ) );
        $this->assertInstanceOf( WP_Error::class, WCAI_Legal_Acceptances::validate_guardian( $minor, 'Maria', $minor['cpf'] ) );
        $this->assertTrue( WCAI_Legal_Acceptances::validate_guardian( $minor, 'Maria', self::VALID_GUARDIAN_CPF ) );
    }

    public function test_adult_needs_no_guardian() {
        $this->assertTrue( WCAI_Legal_Acceptances::validate_guardian( $this->participant( 30 ), '', '' ) );
    }

    public function test_duplicate_insert_returns_existing_acceptance() {
        $GLOBALS['wpdb'] = new WcaiFakeWpdb();
        $GLOBALS['wpdb']->insert_fails = true;
        $GLOBALS['wpdb']->existing = array( 'id' => 42 );

        $this->assertSame( 42, $this->insert( $this->row( 5 ) ) );
    }

    public function test_insert_stores_text_snapshot_and_participant_salted_guardian_hash() {
        $GLOBALS['wpdb'] = new WcaiFakeWpdb();

        $this->assertSame( 99, $this->insert( $this->row( 5, self::VALID_GUARDIAN_CPF ) ) );
        $this->insert( $this->row( 6, self::VALID_GUARDIAN_CPF ) );

        $rows = $GLOBALS['wpdb']->inserted;
        $this->assertSame( 'Texto aceito', $rows[0]['document_text'] );
        $this->assertSame( '2026-01-01', $rows[0]['document_date'] );
        $this->assertNotSame( $rows[0]['guardian_cpf_hash'], $rows[1]['guardian_cpf_hash'] );
    }

    public function test_exporter_returns_participant_acceptance_and_signature_for_email() {
        $GLOBALS['wpdb'] = new WcaiFakeWpdb();
        $GLOBALS['wpdb']->participants = array( array(
            'id' => 5, 'order_id' => 7, 'nome_completo' => 'Ana', 'cpf' => '12345678909',
            'data_nascimento' => '1990-01-01', 'termo_assinado' => 1, 'created_at' => '2026-01-01 00:00:00',
        ) );

        $result = ( new WCAI_Privacy() )->export( 'ana@example.com' );
        $groups = array_unique( array_column( $result['data'], 'group_id' ) );

        $this->assertTrue( $result['done'] );
        $this->assertEqualsCanonicalizing( array( 'wcai-participantes', 'wcai-aceites', 'wcai-assinaturas' ), $groups );
        $this->assertStringNotContainsString( '12345678909', wp_json_encode_stub( $result['data'] ) );
    }

    public function test_eraser_anonymises_participant_and_logs_without_pii() {
        $GLOBALS['wpdb'] = new WcaiFakeWpdb();
        $GLOBALS['wpdb']->participants = array( array( 'id' => 5, 'order_id' => 7, 'nome_completo' => 'Ana', 'cpf' => '12345678909' ) );
        $GLOBALS['wcai_deleted_posts'] = array();

        $result = ( new WCAI_Privacy() )->erase( 'ana@example.com' );

        $this->assertTrue( $result['items_removed'] );
        $this->assertSame( 'Anonimizado', WCAI_Participants_DB::$updates[5]['nome_completo'] );
        $this->assertSame( '', WCAI_Participants_DB::$updates[5]['cpf'] );
        $this->assertSame( array( 55 ), $GLOBALS['wcai_deleted_posts'] );
        $this->assertStringContainsString( 'guardian_cpf_hash = NULL', $GLOBALS['wpdb']->queries[0] );
        $this->assertStringNotContainsString( 'Ana', wp_json_encode_stub( WCAI_Audit_Log::$entries ) );
    }
}

function wp_json_encode_stub( $v ) { return json_encode( $v ); }

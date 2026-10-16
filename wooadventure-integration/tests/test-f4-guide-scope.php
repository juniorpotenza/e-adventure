<?php
// PHPUnit: stubs mínimos de WordPress (sem WP_UnitTestCase). Não foi executado nesta sessão (PHP ausente).
// Processo separado: os stubs abaixo (guardados por function_exists/class_exists) conflitam com os de outros testes.
// Cobre: escopo por guia (_wcai_guide_id) em ajax_get_day_details, ajax_get_events (+ chave de cache) e ajax_get_calendar_data.
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'ARRAY_A' ) ) { define( 'ARRAY_A', 'ARRAY_A' ); }
if ( ! defined( 'OBJECT' ) ) { define( 'OBJECT', 'OBJECT' ); }
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) { define( 'MINUTE_IN_SECONDS', 60 ); }

if ( ! class_exists( 'WcaiF4Json' ) ) {
    class WcaiF4Json extends Exception {
        public $payload;
        public $status;
        public function __construct( $payload, $status = 200 ) { parent::__construct( 'json' ); $this->payload = $payload; $this->status = $status; }
    }
}
if ( ! class_exists( 'WCAI_Capabilities' ) ) {
    class WCAI_Capabilities {
        const VIEW_MANIFEST = 'wcai_view_manifest';
        const MANAGE_DEPARTURES = 'wcai_manage_departures';
        const VIEW_SENSITIVE = 'wcai_view_sensitive';
    }
}
if ( ! class_exists( 'WCAI_Departures' ) ) { class WCAI_Departures { const POST_TYPE = 'wcai_departure'; } }
if ( ! class_exists( 'WCAI_Reservations' ) ) {
    class WCAI_Reservations {
        public static function get_table_name() { return 'wp_wcai_reservas'; }
        public static function get_reserved_quantity( $id ) { return 0; }
    }
}
if ( ! class_exists( 'WCAI_Participants_DB' ) ) { class WCAI_Participants_DB { public static function get_table_name() { return 'wp_wcai_participantes'; } } }

if ( ! class_exists( 'WcaiF4Wpdb' ) ) {
    class WcaiF4Wpdb {
        public $posts = 'wp_posts';
        public $postmeta = 'wp_postmeta';
        public $queries = array();
        public function prepare( $query, ...$args ) {
            $args = isset( $args[0] ) && is_array( $args[0] ) ? $args[0] : $args;
            return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
        }
        public function get_results( $query, $output = OBJECT ) { $this->queries[] = $query; return array(); }
    }
}

if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( (string) $v ); } }
if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $v ) { return $v; } }
if ( ! function_exists( 'check_ajax_referer' ) ) { function check_ajax_referer() { return 1; } }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can( $cap ) { return in_array( $cap, $GLOBALS['wcai_f4_caps'], true ); } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return $GLOBALS['wcai_f4_user']; } }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta( $id, $key ) { return isset( $GLOBALS['wcai_f4_meta'][ $id ][ $key ] ) ? $GLOBALS['wcai_f4_meta'][ $id ][ $key ] : ''; } }
if ( ! function_exists( 'get_post' ) ) { function get_post( $id ) { return (object) array( 'ID' => $id, 'post_type' => 'wcai_departure', 'post_status' => 'publish' ); } }
if ( ! function_exists( 'get_the_title' ) ) { function get_the_title( $id ) { return 'Saída ' . $id; } }
if ( ! function_exists( 'get_transient' ) ) { function get_transient( $k ) { return isset( $GLOBALS['wcai_f4_transients'][ $k ] ) ? $GLOBALS['wcai_f4_transients'][ $k ] : false; } }
if ( ! function_exists( 'set_transient' ) ) { function set_transient( $k, $v ) { $GLOBALS['wcai_f4_transients'][ $k ] = $v; return true; } }
if ( ! function_exists( 'wp_date' ) ) { function wp_date( $f, $ts = null ) { return gmdate( $f, $ts ?? 1767225600 ); } }
if ( ! function_exists( 'wp_send_json' ) ) { function wp_send_json( $d, $s = 200 ) { throw new WcaiF4Json( $d, $s ); } }
if ( ! function_exists( 'wp_send_json_error' ) ) { function wp_send_json_error( $d = null, $s = 200 ) { throw new WcaiF4Json( array( 'success' => false, 'data' => $d ), $s ); } }
if ( ! function_exists( 'wp_send_json_success' ) ) { function wp_send_json_success( $d = null, $s = 200 ) { throw new WcaiF4Json( array( 'success' => true, 'data' => $d ), $s ); } }

require_once dirname( __DIR__ ) . '/includes/agenda/trait-wcai-agenda-ajax.php';
require_once dirname( __DIR__ ) . '/includes/scanner/trait-wcai-scanner-data.php';

class WcaiF4Agenda {
    use WCAI_Agenda_Ajax;
    // Stub: a regra real vive em trait-wcai-agenda-ical.php (privado).
    private function get_canonical_departures_in_range( $start, $end ) {
        return array( (object) array( 'ID' => 101 ), (object) array( 'ID' => 202 ) );
    }
}

class WcaiF4Scanner {
    use WCAI_Scanner_Data;
}

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Test_F4_Guide_Scope extends TestCase {

    const GUIDE_A = 11;
    const GUIDE_B = 22;

    protected function setUp(): void {
        $GLOBALS['wcai_f4_transients'] = array();
        $GLOBALS['wcai_f4_meta'] = array(
            101 => array( '_wcai_guide_id' => (string) self::GUIDE_A, '_wcai_starts_at' => '2026-01-10T08:00' ),
            202 => array( '_wcai_guide_id' => (string) self::GUIDE_B, '_wcai_starts_at' => '2026-01-11T08:00' ),
        );
        $GLOBALS['wpdb'] = new WcaiF4Wpdb();
        $_POST = array();
    }

    private function as_user( $id, $manage ) {
        $GLOBALS['wcai_f4_user'] = $id;
        $GLOBALS['wcai_f4_caps'] = $manage ? array( WCAI_Capabilities::VIEW_MANIFEST, WCAI_Capabilities::MANAGE_DEPARTURES )
                                           : array( WCAI_Capabilities::VIEW_MANIFEST );
    }

    private function call( $obj, $method ) {
        try {
            $obj->$method();
        } catch ( WcaiF4Json $e ) {
            return $e;
        }
        $this->fail( 'Nenhuma resposta JSON enviada.' );
    }

    public function test_guide_cannot_read_day_details_of_another_guides_departure() {
        $this->as_user( self::GUIDE_A, false );
        $_POST['departure_id'] = 202;

        $res = $this->call( new WcaiF4Agenda(), 'ajax_get_day_details' );

        $this->assertSame( 403, $res->status );
        $this->assertSame( array(), $GLOBALS['wpdb']->queries, 'Nenhuma query antes da checagem de escopo.' );
    }

    public function test_guide_can_read_own_departure_and_admin_can_read_any() {
        $_POST['departure_id'] = 202;
        $this->as_user( self::GUIDE_B, false );
        $this->assertNotSame( 403, $this->call( new WcaiF4Agenda(), 'ajax_get_day_details' )->status );

        $this->as_user( 1, true );
        $this->assertNotSame( 403, $this->call( new WcaiF4Agenda(), 'ajax_get_day_details' )->status );
    }

    public function test_get_events_filters_by_guide_and_cache_keys_differ_by_scope() {
        $this->as_user( self::GUIDE_A, false );
        $guide_events = $this->call( new WcaiF4Agenda(), 'ajax_get_events' )->payload;
        $this->assertSame( array( '101' ), array_column( $guide_events, 'id' ) );

        $this->as_user( 1, true );
        $admin_events = $this->call( new WcaiF4Agenda(), 'ajax_get_events' )->payload;
        $this->assertSame( array( '101', '202' ), array_column( $admin_events, 'id' ) );

        $keys = array_keys( $GLOBALS['wcai_f4_transients'] );
        $this->assertCount( 2, $keys );
        $this->assertNotSame( $keys[0], $keys[1] );
        foreach ( $keys as $key ) {
            $this->assertStringStartsWith( 'wcai_events_', $key, 'Prefixo exigido por clear_calendar_cache_internal.' );
        }
    }

    public function test_calendar_query_is_scoped_to_guide_but_not_for_admin() {
        $_POST = array( 'month' => 1, 'year' => 2026 );

        $this->as_user( self::GUIDE_A, false );
        $this->call( new WcaiF4Scanner(), 'ajax_get_calendar_data' );
        $this->assertStringContainsString( "gm.meta_key = '_wcai_guide_id' AND gm.meta_value = '11'", $GLOBALS['wpdb']->queries[0] );

        $this->as_user( 1, true );
        $this->call( new WcaiF4Scanner(), 'ajax_get_calendar_data' );
        $this->assertStringNotContainsString( '_wcai_guide_id', $GLOBALS['wpdb']->queries[1] );
    }
}

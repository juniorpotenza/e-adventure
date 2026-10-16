<?php
// PHPUnit: stubs mínimos de WordPress (sem WP_UnitTestCase). Suite "f21"; processo separado (stubs guardados por class_exists).
// Cobre (rodada 4, privacidade): nome em title-case no modal e nascimento mascarado (só o ano) com flag "não alterado".
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! class_exists( 'WC_Order' ) ) {
    class WC_Order {}
}

if ( ! class_exists( 'WCAI_Participants_DB' ) ) {
    class WCAI_Participants_DB {
        public static $rows = array();
        public static $updates = array();
        public static $added = array();
        public static function get_by_order( $order_id ) { return self::$rows; }
        public static function get_by_id( $id ) {
            foreach ( self::$rows as $r ) {
                if ( (int) $r['id'] === (int) $id ) { return $r; }
            }
            return null;
        }
        public static function update( $id, $item ) { self::$updates[ $id ] = $item; }
        public static function add( $item ) { self::$added[] = $item; }
    }
}

if ( ! class_exists( 'WCAI_Legal_Acceptances' ) ) {
    class WCAI_Legal_Acceptances {
        public static $accepted = array();
        public static function get_by_participant( $id ) { return in_array( (int) $id, self::$accepted, true ) ? array( 'id' => 1 ) : array(); }
    }
}

if ( ! class_exists( 'WCAI_Reservations' ) ) {
    class WCAI_Reservations {
        public static function get_by_id( $id ) { return null; }
    }
}

if ( ! class_exists( 'WCAI_Settings' ) ) {
    class WCAI_Settings {
        public static function get_terms_url() { return 'https://site.test/termo/'; }
        public static function is_cpf_blocked( $cpf ) { return false; }
    }
}

if ( ! class_exists( 'WCAI_Assinatura' ) ) {
    class WCAI_Assinatura {
        public static function generate_local_qr( $hash, $id ) { return 'https://site.test/qr/' . $hash . '.png'; }
    }
}

if ( ! class_exists( 'WcaiF21Item' ) ) {
    class WcaiF21Item {
        public function get_id() { return 1; }
        public function get_quantity() { return 3; }
        public function get_variation_id() { return 0; }
        public function get_product_id() { return 99; }
        public function get_meta( $key, $single = true ) { return '_wcai_departure_id' === $key ? 5 : ''; }
    }
}

if ( ! class_exists( 'WcaiF21Order' ) ) {
    class WcaiF21Order extends WC_Order {
        public $status;
        public function __construct( $status = 'processing' ) { $this->status = $status; }
        public function get_id() { return 101; }
        public function get_meta( $key ) { return 'billing_cpf' === $key ? '52998224725' : ''; }
        public function get_payment_method() { return 'cod'; }
        public function get_status() { return $this->status; }
        public function get_order_key() { return 'wc_order_abc'; }
        public function get_order_number() { return '101'; }
        public function get_view_order_url() { return 'https://site.test/minha-conta/view-order/101/'; }
        public function get_customer_id() { return 7; }
        public function get_items() { return array( new WcaiF21Item() ); }
        public function get_formatted_order_total() { return 'R$ 100,00'; }
        public function get_billing_first_name() { return 'Titular'; }
        public function get_billing_last_name() { return 'Teste'; }
        public function add_order_note() {}
        public function update_meta_data() {}
        public function save() {}
    }
}

$GLOBALS['wcai_f20_order'] = null;
$GLOBALS['wcai_f20_open'] = array();
$GLOBALS['wcai_f20_downloads'] = array();
$GLOBALS['wcai_f20_options'] = array( 'wcai_contact_phone' => '+55 41 99808-0605' );

if ( ! function_exists( 'add_filter' ) ) { function add_filter() {} }
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'add_query_arg' ) ) { function add_query_arg( $k, $v, $url ) { return $url . '?' . $k . '=' . $v; } }
if ( ! function_exists( 'home_url' ) ) { function home_url( $path = '' ) { return 'https://site.test/teste' . $path; } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( (string) $v ); } }
if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $v ) { return $v; } }
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $v ) { return (string) $v; } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $v ) { return esc_html( $v ); } }
if ( ! function_exists( 'esc_attr__' ) ) { function esc_attr__( $v ) { return esc_attr( $v ); } }
if ( ! function_exists( '__' ) ) { function __( $v ) { return $v; } }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $v ) { return false; } }
if ( ! function_exists( 'is_wc_endpoint_url' ) ) { function is_wc_endpoint_url() { return false; } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $key, $default = false ) { return isset( $GLOBALS['wcai_f20_options'][ $key ] ) ? $GLOBALS['wcai_f20_options'][ $key ] : $default; } }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta( $id, $key ) { return array( '_wcai_starts_at' => '2026-10-20 09:00', '_wcai_meeting_point' => 'Portaria do parque' )[ $key ]; } }
if ( ! function_exists( 'wp_date' ) ) { function wp_date( $format, $ts ) { return gmdate( $format, $ts ); } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 7; } }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can() { return false; } }
if ( ! function_exists( 'wc_get_order_status_name' ) ) { function wc_get_order_status_name( $s ) { $names = array( 'completed' => 'Concluído', 'cancelled' => 'Cancelado' ); return isset( $names[ $s ] ) ? $names[ $s ] : $s; } }
if ( ! function_exists( 'wc_get_order' ) ) { function wc_get_order() { return $GLOBALS['wcai_f20_order']; } }
if ( ! function_exists( 'wc_get_orders' ) ) { function wc_get_orders() { return $GLOBALS['wcai_f20_open']; } }
if ( ! function_exists( 'wc_get_page_permalink' ) ) { function wc_get_page_permalink() { return 'https://site.test/loja/'; } }
if ( ! function_exists( 'wc_get_customer_available_downloads' ) ) { function wc_get_customer_available_downloads() { return $GLOBALS['wcai_f20_downloads']; } }
if ( ! function_exists( 'wp_nonce_field' ) ) { function wp_nonce_field() {} }

require_once dirname( __DIR__ ) . '/includes/class-utils.php';
require_once dirname( __DIR__ ) . '/includes/class-capabilities.php';
require_once dirname( __DIR__ ) . '/includes/class-data-resolver.php';
require_once dirname( __DIR__ ) . '/includes/class-frontend-account.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Test_F21_Account_Round4_Privacy extends TestCase {

    const HOLDER_CPF = '52998224725';
    const OTHER_CPF  = '11144477735';
    const GENERIC    = 'Dados do participante inválidos.';

    private function row( $id, $name, $cpf, $extra = array() ) {
        return array_merge(
            array(
                'id' => $id, 'order_id' => 101, 'nome_completo' => $name, 'cpf' => $cpf,
                'data_nascimento' => '1990-05-15', 'termo_assinado' => 0, 'ticket_hash' => '',
                'reservation_id' => 0, 'checkin_time' => null, 'checkin_status' => 0,
            ),
            $extra
        );
    }

    private function account() { return new WCAI_Frontend_Account(); }
    private function call( $method, $args = array() ) {
        $m = new ReflectionMethod( 'WCAI_Frontend_Account', $method );
        $m->setAccessible( true );
        return $m->invokeArgs( $this->account(), $args );
    }

    private function set_rows( $rows ) {
        WCAI_Participants_DB::$rows = $rows;
        WCAI_Participants_DB::$updates = array();
        WCAI_Legal_Acceptances::$accepted = array();
    }
    private function two_pending() {
        return array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ), $this->row( 2, 'Bia Lima', self::OTHER_CPF ) );
    }
    private function modal( $order, $is_locked = false ) {
        $GLOBALS['wcai_f20_order'] = $order;
        ob_start();
        $this->call( 'render_modal_html', array( 101, WCAI_Participants_DB::$rows, $is_locked, 'wcai-modal-101' ) );
        return ob_get_clean();
    }

    // --- 7. nome em title-case no modal ---

    public function test_modal_name_input_shows_title_case_for_all_lower_names_only() {
        $this->set_rows( array( $this->row( 1, 'valdeci potenza', self::HOLDER_CPF ), $this->row( 2, 'Bia de LIMA', self::OTHER_CPF ) ) );
        $html = $this->modal( new WcaiF21Order() );

        $this->assertStringContainsString( 'name="participantes[1][nome_completo]" value="Valdeci Potenza"', $html );
        $this->assertStringContainsString( 'name="participantes[2][nome_completo]" value="Bia de LIMA"', $html );
    }

    public function test_unchanged_display_name_keeps_the_stored_original_and_an_edit_wins() {
        $this->set_rows( array( $this->row( 1, 'valdeci potenza', self::HOLDER_CPF ) ) );
        $order = new WcaiF21Order();
        $same = $this->call( 'restore_unchanged_fields', array( $order, array( array( 'id' => '1', 'nome_completo' => 'Valdeci Potenza', 'cpf' => '529.***.***-25', 'nasc_unchanged' => '1' ) ) ) );
        $this->assertSame( 'valdeci potenza', $same[0]['nome_completo'] );

        $edited = $this->call( 'restore_unchanged_fields', array( $order, array( array( 'id' => '1', 'nome_completo' => 'Valdeci P. Potenza', 'cpf' => '529.***.***-25', 'nasc_unchanged' => '1' ) ) ) );
        $this->assertSame( 'Valdeci P. Potenza', $edited[0]['nome_completo'] );
    }

    // --- 8. nascimento mascarado ---

    public function test_modal_never_has_a_full_birth_date_for_editable_participants() {
        $this->set_rows( $this->two_pending() );
        $html = $this->modal( new WcaiF21Order() );

        $this->assertStringNotContainsString( '15/05/1990', $html );
        $this->assertSame( 2, substr_count( $html, 'value="**/**/1990" data-masked="1"' ) );
        $this->assertStringContainsString( 'name="participantes[1][nasc_unchanged]" value="1"', $html );
        $this->assertStringContainsString( 'name="participantes[2][nasc_unchanged]" value="1"', $html );
    }

    public function test_modal_birth_date_stays_editable_when_none_is_stored() {
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF, array( 'data_nascimento' => '' ) ) ) );
        $html = $this->modal( new WcaiF21Order() );

        $this->assertStringContainsString( 'name="participantes[1][data_nascimento]" value=""', $html );
        $this->assertStringNotContainsString( 'nasc_unchanged', $html );
    }

    public function test_unchanged_birth_flag_restores_the_stored_date_and_validates() {
        $this->set_rows( $this->two_pending() );
        $order = new WcaiF21Order();
        $rows = array( array( 'id' => '1', 'nome_completo' => 'Ana Souza', 'cpf' => '529.***.***-25', 'data_nascimento' => '**/**/1990', 'nasc_unchanged' => '1' ) );
        $data = $this->call( 'restore_unchanged_fields', array( $order, $rows ) );

        $this->assertSame( '15/05/1990', $data[0]['data_nascimento'] );
        $this->assertSame( self::HOLDER_CPF, $data[0]['cpf'] );
        $this->assertSame( '', $this->call( 'validate_frontend_rows', array( $order, $data ) ) );
    }

    public function test_typed_birth_date_is_accepted_and_beats_a_stale_flag() {
        $this->set_rows( $this->two_pending() );
        $order = new WcaiF21Order();
        $rows = array( array( 'id' => '1', 'nome_completo' => 'Ana Souza', 'cpf' => '529.***.***-25', 'data_nascimento' => '01/02/1991', 'nasc_unchanged' => '1' ) );
        $typed = $this->call( 'restore_unchanged_fields', array( $order, $rows ) );

        $this->assertSame( '01/02/1991', $typed[0]['data_nascimento'] );
        $this->assertSame( '', $this->call( 'validate_frontend_rows', array( $order, $typed ) ) );
    }

    public function test_masked_birth_without_a_stored_row_gets_the_generic_error() {
        $this->set_rows( $this->two_pending() );
        $rows = array( array( 'id' => '999', 'nome_completo' => 'Ana', 'cpf' => self::HOLDER_CPF, 'data_nascimento' => '**/**/1990' ) );

        $this->assertSame( self::GENERIC, $this->call( 'restore_unchanged_fields', array( new WcaiF21Order(), $rows ) ) );
    }
}

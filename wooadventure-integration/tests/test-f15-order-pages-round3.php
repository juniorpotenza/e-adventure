<?php
// PHPUnit: stubs mínimos de WordPress (sem WP_UnitTestCase). Suite "f15"; processo separado (stubs guardados por class_exists).
// Cobre (rodada 3 das páginas do cliente): CPF mascarado no modal e "não alterado" no servidor, nome em title-case,
// "Dados incompletos", botão principal oculto em pedido encerrado, filtro gettext, próximos passos compartilhados,
// renomeações de rótulos, aria-label da lista, URL relativa do termo e adiamento no tema de blocos.
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
        public static function get_by_order_item( $id ) { return null; }
    }
}

if ( ! class_exists( 'WCAI_Settings' ) ) {
    class WCAI_Settings {
        public static function get_terms_url() { return isset( $GLOBALS['wcai_f15_terms'] ) ? $GLOBALS['wcai_f15_terms'] : 'https://site.test/termo/'; }
        public static function is_cpf_blocked( $cpf ) { return false; }
    }
}

if ( ! class_exists( 'WCAI_Assinatura' ) ) {
    class WCAI_Assinatura {
        public static function generate_local_qr( $hash, $id ) { return 'https://site.test/qr/' . $hash . '.png'; }
    }
}

if ( ! class_exists( 'WcaiF15Item' ) ) {
    class WcaiF15Item {
        public function get_id() { return 1; }
        public function get_quantity() { return 3; }
        public function get_variation_id() { return 0; }
        public function get_product_id() { return 99; }
        public function get_meta( $key, $single = true ) { return '_wcai_departure_id' === $key ? 5 : ''; }
    }
}

if ( ! class_exists( 'WcaiF15Order' ) ) {
    class WcaiF15Order extends WC_Order {
        public $status; public $method;
        public function __construct( $status = 'processing', $method = 'cod' ) { $this->status = $status; $this->method = $method; }
        public function get_id() { return 101; }
        public function get_meta( $key ) { return 'billing_cpf' === $key ? '52998224725' : ''; }
        public function get_payment_method() { return $this->method; }
        public function get_status() { return $this->status; }
        public function get_order_key() { return 'wc_order_abc'; }
        public function get_order_number() { return '101'; }
        public function get_customer_id() { return 7; }
        public function get_items() { return array( new WcaiF15Item() ); }
        public function get_formatted_order_total() { return 'R$ 100,00'; }
        public function get_billing_first_name() { return 'Titular'; }
        public function get_billing_last_name() { return 'Teste'; }
        public function add_order_note() {}
        public function update_meta_data() {}
        public function save() {}
    }
}

$GLOBALS['wcai_f15_received'] = false;
$GLOBALS['wcai_f15_block_theme'] = false;
$GLOBALS['wcai_f15_meeting'] = 'Portaria do parque';
$GLOBALS['wcai_f15_options'] = array();
$GLOBALS['wcai_f15_order'] = null;

if ( ! function_exists( 'add_filter' ) ) { function add_filter() {} }
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'add_query_arg' ) ) { function add_query_arg( $k, $v, $url ) { return $url . '?' . $k . '=' . $v; } }
if ( ! function_exists( 'home_url' ) ) { function home_url( $path = '' ) { return 'https://site.test/teste' . $path; } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( (string) $v ); } }
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $v ) { return (string) $v; } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $v ) { return esc_html( $v ); } }
if ( ! function_exists( 'esc_attr__' ) ) { function esc_attr__( $v ) { return esc_attr( $v ); } }
if ( ! function_exists( '__' ) ) { function __( $v ) { return $v; } }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $v ) { return false; } }
if ( ! function_exists( 'is_wc_endpoint_url' ) ) { function is_wc_endpoint_url() { return $GLOBALS['wcai_f15_received']; } }
if ( ! function_exists( 'wp_is_block_theme' ) ) { function wp_is_block_theme() { return $GLOBALS['wcai_f15_block_theme']; } }
if ( ! function_exists( 'did_action' ) ) { function did_action() { return 0; } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $key, $default = false ) { return isset( $GLOBALS['wcai_f15_options'][ $key ] ) ? $GLOBALS['wcai_f15_options'][ $key ] : $default; } }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta( $id, $key ) { return array( '_wcai_starts_at' => '2026-10-20 09:00', '_wcai_meeting_point' => $GLOBALS['wcai_f15_meeting'] )[ $key ]; } }
if ( ! function_exists( 'wp_date' ) ) { function wp_date( $format, $ts ) { return gmdate( $format, $ts ); } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 7; } }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can() { return false; } }
if ( ! function_exists( 'wc_get_order_status_name' ) ) { function wc_get_order_status_name( $s ) { return $s; } }
if ( ! function_exists( 'wc_get_order' ) ) { function wc_get_order() { return $GLOBALS['wcai_f15_order']; } }
if ( ! function_exists( 'wp_nonce_field' ) ) { function wp_nonce_field() {} }

require_once dirname( __DIR__ ) . '/includes/class-utils.php';
require_once dirname( __DIR__ ) . '/includes/class-capabilities.php';
require_once dirname( __DIR__ ) . '/includes/class-data-resolver.php';
require_once dirname( __DIR__ ) . '/includes/class-frontend-account.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Test_F15_Order_Pages_Round3 extends TestCase {

    const HOLDER_CPF   = '52998224725';
    const OTHER_CPF    = '11144477735';

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

    private function modal( $order, $is_locked = false ) {
        $GLOBALS['wcai_f15_order'] = $order;
        ob_start();
        $this->call( 'render_modal_html', array( 101, WCAI_Participants_DB::$rows, $is_locked, 'wcai-modal-101' ) );
        return ob_get_clean();
    }

    // --- 6. CPF mascarado no modal ---

    public function test_modal_cpf_input_is_masked_and_flagged_and_never_holds_nine_digits() {
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ), $this->row( 2, 'Bia Lima', self::OTHER_CPF ) ) );
        $html = $this->modal( new WcaiF15Order() );

        $this->assertStringContainsString( 'value="529.***.***-25"', $html );
        $this->assertStringContainsString( 'value="111.***.***-35"', $html );
        // 2 CPFs + 2 nascimentos (só o ano), todos com data-masked.
        $this->assertSame( 4, substr_count( $html, 'data-masked="1"' ) );
        $this->assertStringNotContainsString( '982.247', $html );
        $this->assertStringNotContainsString( self::HOLDER_CPF, $html );
        preg_match_all( '/name="participantes\[\d+\]\[cpf\]" value="([^"]*)"/', $html, $m );
        $this->assertCount( 2, $m[1] );
        foreach ( $m[1] as $value ) {
            $this->assertSame( 0, preg_match( '/\d{9,}/', preg_replace( '/\D+/', '', $value ) ) );
        }
    }

    public function test_modal_cpf_invalid_stored_value_is_left_empty_and_editable() {
        $this->set_rows( array( $this->row( 1, 'Antigo', '00000000000', array( 'data_nascimento' => '' ) ) ) );
        $html = $this->modal( new WcaiF15Order() );

        $this->assertStringContainsString( 'class="wcai-mask-cpf" name="participantes[1][cpf]" value=""', $html );
        $this->assertStringNotContainsString( 'data-masked', $html );
    }

    public function test_modal_every_field_has_a_visible_label_and_no_orphan_table() {
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ) );
        $html = $this->modal( new WcaiF15Order() );

        $this->assertSame( 3, substr_count( $html, '<label for="wcai-modal-101-0-' ) );
        $this->assertStringContainsString( '>CPF</label>', $html );
        $this->assertStringContainsString( 'Data de nascimento</label>', $html );
        $this->assertStringContainsString( 'Salvar dados', $html );
        $this->assertStringNotContainsString( '<table', $html );
    }

    public function test_modal_never_holds_a_full_birth_date_locked_or_not() {
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ), $this->row( 2, 'Bia Lima', self::OTHER_CPF ) ) );
        WCAI_Legal_Acceptances::$accepted = array( 1 );
        $html = $this->modal( new WcaiF15Order() );

        $this->assertStringNotContainsString( '15/05/1990', $html );
        $this->assertStringContainsString( 'name="participantes[1][nasc_unchanged]" value="1"', $html );
        // Identidade livre: nascimento editável, mas mascarado (só o ano) até a pessoa digitar.
        $this->assertStringContainsString( 'name="participantes[2][data_nascimento]" value="**/**/1990" data-masked="1"', $html );
        $this->assertStringContainsString( 'name="participantes[2][nasc_unchanged]" value="1"', $html );
    }

    public function test_modal_on_closed_order_has_no_birth_date_no_save_button_and_no_flag() {
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ) );
        $html = $this->modal( new WcaiF15Order( 'completed' ), true );

        $this->assertStringNotContainsString( '15/05/1990', $html );
        $this->assertStringNotContainsString( 'nasc_unchanged', $html );
        $this->assertStringNotContainsString( 'Salvar dados', $html );
    }

    // --- 6. servidor: CPF mascarado = não alterado ---

    private function posted( $cpf, $extra = array() ) {
        return array( 1 => array_merge( array( 'id' => '1', 'nome_completo' => 'Ana Souza', 'cpf' => $cpf, 'data_nascimento' => '15/05/1990' ), $extra ) );
    }

    public function test_posted_masked_cpf_is_restored_to_the_stored_one_and_passes_validation() {
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ) );
        $order = new WcaiF15Order();
        $data = $this->call( 'restore_unchanged_fields', array( $order, $this->posted( '529.***.***-25' ) ) );

        $this->assertSame( self::HOLDER_CPF, $data[1]['cpf'] );
        $this->assertSame( '', $this->call( 'validate_frontend_rows', array( $order, $data ) ) );
    }

    public function test_saving_with_masked_cpf_keeps_the_stored_cpf_intact() {
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ) );
        $order = new WcaiF15Order();
        $this->call( 'process_save', array( $order, $this->posted( '529.***.***-25' ), 'Frontend' ) );

        $this->assertArrayNotHasKey( 'cpf', WCAI_Participants_DB::$updates[1] );

        $data = $this->call( 'restore_unchanged_fields', array( $order, $this->posted( '529.***.***-25' ) ) );
        $this->call( 'process_save', array( $order, $data, 'Frontend' ) );
        $this->assertSame( self::HOLDER_CPF, WCAI_Participants_DB::$updates[1]['cpf'] );
    }

    public function test_typed_cpf_replaces_the_stored_one_and_is_validated() {
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ) );
        $order = new WcaiF15Order();
        $data = $this->call( 'restore_unchanged_fields', array( $order, $this->posted( '111.444.777-35' ) ) );

        $this->assertSame( '111.444.777-35', $data[1]['cpf'] );
        $this->assertSame( '', $this->call( 'validate_frontend_rows', array( $order, $data ) ) );
        $bad = $this->call( 'restore_unchanged_fields', array( $order, $this->posted( '111.444.777-00' ) ) );
        $this->assertStringContainsString( 'CPF inválido', $this->call( 'validate_frontend_rows', array( $order, $bad ) ) );
    }

    public function test_masked_cpf_on_unknown_row_is_rejected_not_accepted() {
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ) );
        $order = new WcaiF15Order();
        $data = $this->call( 'restore_unchanged_fields', array( $order, array( array( 'nome_completo' => 'Novo', 'cpf' => '999.***.***-99', 'data_nascimento' => '15/05/1990' ) ) ) );

        $this->assertSame( 'Dados do participante inválidos.', $data );
    }

    public function test_masked_virtual_holder_row_resolves_to_the_billing_cpf() {
        $this->set_rows( array() );
        $order = new WcaiF15Order();
        $data = $this->call( 'restore_unchanged_fields', array( $order, array( array( 'vref' => 'virtual_titular', 'nome_completo' => 'Titular Teste', 'cpf' => '529.***.***-25', 'data_nascimento' => '15/05/1990' ) ) ) );

        $this->assertSame( self::HOLDER_CPF, $data[0]['cpf'] );
    }

    public function test_birth_date_flag_restores_stored_date_for_locked_and_unlocked_rows() {
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ) );
        $order = new WcaiF15Order();
        $flag = $this->posted( '529.***.***-25', array( 'data_nascimento' => '', 'nasc_unchanged' => '1' ) );

        $unlocked = $this->call( 'restore_unchanged_fields', array( $order, $flag ) );
        $this->assertSame( '15/05/1990', $unlocked[1]['data_nascimento'] );

        WCAI_Legal_Acceptances::$accepted = array( 1 );
        $locked = $this->call( 'restore_unchanged_fields', array( $order, $flag ) );
        $this->assertSame( '15/05/1990', $locked[1]['data_nascimento'] );
        $this->assertArrayNotHasKey( 'nasc_unchanged', $locked[1] );
        $this->assertSame( '', $this->call( 'validate_frontend_rows', array( $order, $locked ) ) );
    }

    // --- 7. nascimento do titular mascarado ---

    public function test_holder_birth_date_is_masked_to_the_year_in_customer_views() {
        $out = $this->account()->mask_cpf_in_additional_fields_block( '<dd>529.982.247-25</dd><dd>15/05/1990</dd>' );

        $this->assertSame( '<dd>529.***.***-25</dd><dd>**/**/1990</dd>', $out );
        $this->assertSame( '2026-12-10', $this->account()->mask_birthdate_in_html( '2026-12-10' ) );
    }

    // --- 11. nome em title-case ---

    public function test_display_name_fixes_only_all_lower_or_all_upper_names() {
        $this->assertSame( 'Maria da Silva', $this->call( 'display_name', array( 'maria da silva' ) ) );
        $this->assertSame( 'João dos Santos e Souza', $this->call( 'display_name', array( 'JOÃO DOS SANTOS E SOUZA' ) ) );
        $this->assertSame( 'Junior Potenza', $this->call( 'display_name', array( 'Junior Potenza' ) ) );
        $this->assertSame( 'Ana de Souza', $this->call( 'display_name', array( 'Ana de Souza' ) ) );
        $this->assertSame( 'Mc Donald', $this->call( 'display_name', array( 'Mc Donald' ) ) );
        $this->assertSame( 'De Souza', $this->call( 'display_name', array( 'DE SOUZA' ) ) );
        $this->assertSame( '', $this->call( 'display_name', array( '  ' ) ) );
    }
}

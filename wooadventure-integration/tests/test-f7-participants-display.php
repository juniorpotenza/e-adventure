<?php
// PHPUnit: stubs mínimos de WordPress (sem WP_UnitTestCase). Suite "f7"; processo separado (stubs guardados por class_exists).
// Cobre: filtro de nomes placeholder, fallback legado só com tabela vazia, nascimento oculto sem capability,
// nome travado após aceite, e-mail em texto puro e número do pedido intocado.
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

if ( ! class_exists( 'WCAI_Legal_Acceptances' ) ) {
    class WCAI_Legal_Acceptances {
        public static $accepted_ids = array();
        public static function get_by_participant( $id ) { return in_array( (int) $id, self::$accepted_ids, true ) ? array( 'id' => 1 ) : array(); }
    }
}

if ( ! class_exists( 'WcaiF7Item' ) ) {
    class WcaiF7Item {
        private $q;
        public function __construct( $q ) { $this->q = $q; }
        public function get_quantity() { return $this->q; }
        public function get_meta( $key, $single = true ) { return '_wcai_departure_id' === $key ? 5 : ''; }
        public function get_variation_id() { return 0; }
        public function get_product_id() { return 99; }
    }
}

if ( ! class_exists( 'WcaiF7Order' ) ) {
    class WcaiF7Order extends WC_Order {
        public $meta;
        public $quantities;
        public function __construct( $meta = array(), $quantities = array( 2 ) ) { $this->meta = $meta; $this->quantities = $quantities; }
        public function get_id() { return 7; }
        public function get_meta( $key ) { return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : ''; }
        public function get_billing_first_name() { return 'Titular'; }
        public function get_billing_last_name() { return 'Teste'; }
        public function get_order_key() { return 'wc_order_key'; }
        public function get_customer_id() { return 7; }
        public function get_status() { return 'processing'; }
        public function get_payment_method() { return 'bacs'; }
        public function get_items() { return array_map( function ( $q ) { return new WcaiF7Item( $q ); }, $this->quantities ); }
        public function add_order_note( $note ) {}
    }
}

$GLOBALS['wcai_f7_hooks'] = array();
$GLOBALS['wcai_f7_can_sensitive'] = false;

if ( ! function_exists( 'add_filter' ) ) { function add_filter( $tag ) { $GLOBALS['wcai_f7_hooks'][] = $tag; } }
if ( ! function_exists( 'add_action' ) ) { function add_action( $tag ) { $GLOBALS['wcai_f7_hooks'][] = $tag; } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( (string) $v ); } }
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $v ) { return (string) $v; } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $v ) { return esc_html( $v ); } }
if ( ! function_exists( '__' ) ) { function __( $v ) { return $v; } }
if ( ! function_exists( 'wp_nonce_field' ) ) { function wp_nonce_field() { echo '<input type="hidden" name="nonce">'; } }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can() { return $GLOBALS['wcai_f7_can_sensitive']; } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $key, $default = false ) { return $default; } }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta() { return ''; } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 7; } }
if ( ! function_exists( 'wc_get_order' ) ) { function wc_get_order() { return false; } }

require_once dirname( __DIR__ ) . '/includes/class-utils.php';
require_once dirname( __DIR__ ) . '/includes/class-capabilities.php';
require_once dirname( __DIR__ ) . '/includes/class-data-resolver.php';
require_once dirname( __DIR__ ) . '/includes/class-order.php';
require_once dirname( __DIR__ ) . '/includes/class-frontend-account.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Test_F7_Participants_Display extends TestCase {

    const HOLDER_CPF    = '52998224725';
    const COMPANION_CPF = '11144477735';

    private function row( $id, $name, $cpf, $extra = array() ) {
        return array_merge(
            array(
                'id' => $id, 'order_id' => 7, 'nome_completo' => $name, 'cpf' => $cpf,
                'data_nascimento' => ( new DateTime( '-40 years -10 days' ) )->format( 'Y-m-d' ),
                'termo_assinado' => 0, 'ticket_hash' => '', 'checkin_time' => null, 'checkin_status' => 0,
            ),
            $extra
        );
    }

    private function order( $rows, $meta = array(), $quantities = array( 2 ) ) {
        WCAI_Participants_DB::$rows = $rows;
        WCAI_Legal_Acceptances::$accepted_ids = array();
        return new WcaiF7Order( array_merge( array( 'billing_cpf' => self::HOLDER_CPF ), $meta ), $quantities );
    }

    private function call( $method, $args ) {
        $m = new ReflectionMethod( 'WCAI_Frontend_Account', $method );
        $m->setAccessible( true );
        return $m->invokeArgs( new WCAI_Frontend_Account(), $args );
    }

    private function names( $list ) {
        return array_column( $list, 'nome_completo' );
    }

    // --- 3. placeholders e fallback legado ---

    public function test_placeholder_names_are_filtered_from_the_list() {
        $order = $this->order( array(
            $this->row( 1, 'Ana Souza', self::HOLDER_CPF ),
            $this->row( 2, '.', self::COMPANION_CPF ),
            $this->row( 3, "'", '12345678909' ),
        ) );

        $this->assertSame( array( 'Ana Souza' ), $this->names( $this->call( 'get_participants_safe', array( $order ) ) ) );
    }

    public function test_placeholder_name_is_rejected_on_save() {
        $order = $this->order( array( $this->row( 1, 'Ana', self::HOLDER_CPF ) ) );
        $error = $this->call( 'validate_frontend_rows', array( $order, array( array( 'id' => 1, 'nome_completo' => '.', 'cpf' => self::HOLDER_CPF, 'data_nascimento' => '01/01/1980' ) ) ) );

        $this->assertStringContainsString( 'Nome inválido', $error );
    }

    public function test_legacy_meta_is_ignored_when_table_has_rows() {
        $legacy = array( 'visitante_2' => array( 'nome_completo' => 'Legado', 'cpf' => self::COMPANION_CPF, 'data_nascimento' => '01/01/1990' ) );
        $order = $this->order( array( $this->row( 1, 'Ana', self::HOLDER_CPF ) ), array( '_additional_participants' => $legacy ) );

        $this->assertSame( array( 'Ana' ), $this->names( $this->call( 'get_participants_safe', array( $order ) ) ) );
    }

    public function test_legacy_meta_is_used_when_table_is_empty() {
        $legacy = array( 'visitante_2' => array( 'nome_completo' => 'Legado', 'cpf' => self::COMPANION_CPF, 'data_nascimento' => '01/01/1990' ) );
        $order = $this->order( array(), array( '_additional_participants' => $legacy ) );

        $this->assertSame( array( 'Titular Teste', 'Legado' ), $this->names( $this->call( 'get_participants_safe', array( $order ) ) ) );
    }

    // --- 2. nascimento oculto sem capability ---

    private function render_admin( $order ) {
        ob_start();
        ( new WCAI_Frontend_Account() )->render_admin_content( $order );
        return ob_get_clean();
    }

    public function test_birth_date_is_hidden_without_capability_and_age_is_shown() {
        $GLOBALS['wcai_f7_can_sensitive'] = false;
        $birth = ( new DateTime( '-40 years -10 days' ) )->format( 'd/m/Y' );
        $html = $this->render_admin( $this->order( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ), array(), array( 1 ) ) );

        $this->assertStringNotContainsString( $birth, $html );
        $this->assertStringNotContainsString( 'wcai-mask-date', $html );
        $this->assertStringNotContainsString( self::HOLDER_CPF, $html );
        $this->assertStringContainsString( '529.***.***-25', $html );
        $this->assertStringContainsString( 'title="Data de nascimento restrita">40 anos', $html );
        $this->assertStringContainsString( 'min-width: 180px', $html );
    }

    public function test_birth_date_is_shown_with_capability() {
        $GLOBALS['wcai_f7_can_sensitive'] = true;
        $birth = ( new DateTime( '-40 years -10 days' ) )->format( 'd/m/Y' );
        $html = $this->render_admin( $this->order( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ), array(), array( 1 ) ) );

        $this->assertStringContainsString( 'value="' . $birth . '"', $html );
    }

    public function test_admin_warns_when_tickets_differ_from_participants() {
        $GLOBALS['wcai_f7_can_sensitive'] = false;
        $html = $this->render_admin( $this->order( array( $this->row( 1, 'Ana', self::HOLDER_CPF ) ), array(), array( 3 ) ) );

        $this->assertStringContainsString( 'Atenção: 3 ingressos, 1 participantes.', $html );
    }

    // --- 9. nome travado ---

    private function edit_row( $name ) {
        return array( array( 'id' => 1, 'nome_completo' => $name, 'cpf' => self::HOLDER_CPF, 'data_nascimento' => ( new DateTime( '-40 years -10 days' ) )->format( 'd/m/Y' ) ) );
    }

    public function test_name_is_locked_after_acceptance() {
        $order = $this->order( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ) );
        WCAI_Legal_Acceptances::$accepted_ids = array( 1 );

        $this->assertStringContainsString( 'nome não pode ser alterado', $this->call( 'validate_frontend_rows', array( $order, $this->edit_row( 'Outra Pessoa' ) ) ) );
        $this->assertSame( '', $this->call( 'validate_frontend_rows', array( $order, $this->edit_row( 'Ana Souza' ) ) ) );
    }

    public function test_name_is_editable_without_acceptance_or_checkin() {
        $order = $this->order( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ) );

        $this->assertSame( '', $this->call( 'validate_frontend_rows', array( $order, $this->edit_row( 'Ana S. Souza' ) ) ) );
    }

    public function test_name_is_locked_after_checkin() {
        $order = $this->order( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF, array( 'checkin_time' => '2026-03-03 08:00:00' ) ) ) );

        $this->assertNotSame( '', $this->call( 'validate_frontend_rows', array( $order, $this->edit_row( 'Outra Pessoa' ) ) ) );
    }

    // --- 7. e-mail ---

    private function email( $order, $plain ) {
        ob_start();
        ( new WCAI_Frontend_Account() )->render_participants_in_email( $order, false, $plain, null );
        return ob_get_clean();
    }

    public function test_plain_text_email_prints_text_lines_without_html() {
        $order = $this->order( array( $this->row( 1, 'Ana <b>Souza</b>', self::HOLDER_CPF ) ) );
        $out = $this->email( $order, true );

        $this->assertStringContainsString( 'Visitante 1: Ana Souza — 40 anos — CPF 529.***.***-25', $out );
        $this->assertSame( $out, strip_tags( $out ) );
    }

    public function test_html_email_is_a_compact_table_without_qr_or_actions() {
        $order = $this->order( array( $this->row( 1, 'Ana', self::HOLDER_CPF, array( 'ticket_hash' => 'abc' ) ) ) );
        $out = $this->email( $order, false );

        $this->assertStringContainsString( '<table', $out );
        $this->assertStringNotContainsString( '<img', $out );
        $this->assertStringNotContainsString( 'wa.me', $out );
        $this->assertStringNotContainsString( self::HOLDER_CPF, $out );
    }

    // --- 8. página do pedido: uma impressão por pedido ---

    public function test_order_view_renders_once_per_order() {
        $order = $this->order( array( $this->row( 1, 'Ana', self::HOLDER_CPF ) ) );
        $account = new WCAI_Frontend_Account();

        ob_start();
        $account->render_participants_on_order_view( $order );
        $first = ob_get_clean();
        ob_start();
        $account->render_participants_on_order_view( $order );
        $second = ob_get_clean();

        $this->assertStringContainsString( 'wcai-order-details-participants', $first );
        $this->assertSame( '', $second );
    }

    // --- 5. número do pedido ---

    public function test_order_number_filter_is_not_registered() {
        $GLOBALS['wcai_f7_hooks'] = array();
        new WCAI_Order();

        $this->assertNotContains( 'woocommerce_order_number', $GLOBALS['wcai_f7_hooks'] );
        $this->assertFalse( method_exists( 'WCAI_Order', 'custom_order_number' ) );
    }
}

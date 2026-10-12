<?php
// PHPUnit com stubs (sem WP). Suite "f11"; processo separado (stubs guardados por function_exists/class_exists).
// Cobre: dados de participantes so para dono/gestor/chave valida, flush do buffer de CPF sem hook de fechamento,
// consentimento obrigatorio no checkout classico e no bloco sem payload.
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! class_exists( 'WC_Order' ) ) { class WC_Order {} }
if ( ! class_exists( 'WP_REST_Request' ) ) {
    class WP_REST_Request {
        public $params;
        public function __construct( $params = array() ) { $this->params = $params; }
        public function get_param( $k ) { return isset( $this->params[ $k ] ) ? $this->params[ $k ] : null; }
    }
}

if ( ! class_exists( 'WCAI_Participants_DB' ) ) {
    class WCAI_Participants_DB {
        public static $rows = array();
        public static function get_by_order( $order_id ) { return self::$rows; }
        public static function add( $row ) {}
    }
}
if ( ! class_exists( 'WCAI_Legal_Acceptances' ) ) { class WCAI_Legal_Acceptances { public static function get_by_participant( $id ) { return array(); } } }
if ( ! class_exists( 'WCAI_Settings' ) ) {
    class WCAI_Settings {
        public static function get_terms_url() { return 'https://site.test/termo/'; }
        public static function get_product_ids() { return array( 99 ); }
        public static function is_cpf_blocked( $cpf ) { return false; }
    }
}
if ( ! class_exists( 'WCAI_Privacy' ) ) { class WCAI_Privacy { public static function consent_text() { return 'Texto de consentimento de teste'; } } }
if ( ! class_exists( 'WcaiF11Item' ) ) {
    class WcaiF11Item {
        public function get_product_id() { return 99; }
        public function get_variation_id() { return 0; }
        public function get_quantity() { return 1; }
        public function get_name() { return 'Passeio'; }
        public function get_meta( $key, $single = true ) { return 0; }
    }
}

if ( ! class_exists( 'WcaiF11Order' ) ) {
    class WcaiF11Order extends WC_Order {
        public $customer_id = 7; public $meta = array(); public $status = 'pending'; public $saved = false;
        public function get_id() { return 94; }
        public function get_customer_id() { return $this->customer_id; }
        public function get_order_key() { return 'wc_order_abc'; }
        public function get_meta( $key ) { return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : ''; }
        public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
        public function save() { $this->saved = true; }
        public function update_status( $status, $note = '' ) { $this->status = $status; }
        public function add_order_note() {}
        public function get_payment_method() { return 'bacs'; }
        public function get_status() { return 'processing'; }
        public function get_items() { return array( 1 => new WcaiF11Item() ); }
        public function get_billing_first_name() { return 'Titular'; }
        public function get_billing_last_name() { return 'Teste'; }
    }
}

if ( ! class_exists( 'WcaiF11Session' ) ) {
    class WcaiF11Session {
        public $data = array();
        public function get( $k ) { return isset( $this->data[ $k ] ) ? $this->data[ $k ] : null; }
        public function set( $k, $v ) { $this->data[ $k ] = $v; }
        public function __unset( $k ) { unset( $this->data[ $k ] ); }
    }
}
if ( ! class_exists( 'WcaiF11Woo' ) ) {
    class WcaiF11Woo {
        public static $instance;
        public $session; public $cart;
        public function __construct() {
            $this->session = new WcaiF11Session();
            $this->cart = new class { public $items = array(); public function get_cart() { return $this->items; } };
        }
    }
}

$GLOBALS['wcai_f11_user'] = 7;
$GLOBALS['wcai_f11_admin'] = false;
$GLOBALS['wcai_f11_notices'] = array();
$GLOBALS['wcai_f11_order'] = null;

if ( ! function_exists( 'WC' ) ) { function WC() { if ( ! WcaiF11Woo::$instance ) { WcaiF11Woo::$instance = new WcaiF11Woo(); } return WcaiF11Woo::$instance; } }
if ( ! function_exists( 'add_filter' ) ) { function add_filter() {} }
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'add_query_arg' ) ) { function add_query_arg( $k, $v, $url ) { return $url . '?' . $k . '=' . $v; } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( (string) $v ); } }
if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $v ) ); } }
if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $v ) { return $v; } }
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $v ) { return (string) $v; } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $v ) { return esc_html( $v ); } }
if ( ! function_exists( 'esc_attr__' ) ) { function esc_attr__( $v ) { return esc_attr( $v ); } }
if ( ! function_exists( '__' ) ) { function __( $v ) { return $v; } }
if ( ! function_exists( 'wp_nonce_field' ) ) { function wp_nonce_field() {} }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $v ) { return false; } }
if ( ! function_exists( 'is_wc_endpoint_url' ) ) { function is_wc_endpoint_url() { return false; } }
if ( ! function_exists( 'wc_get_order_status_name' ) ) { function wc_get_order_status_name( $s ) { return $s; } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return $GLOBALS['wcai_f11_user']; } }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can() { return $GLOBALS['wcai_f11_admin']; } }
if ( ! function_exists( 'wc_add_notice' ) ) { function wc_add_notice( $msg ) { $GLOBALS['wcai_f11_notices'][] = $msg; } }
if ( ! function_exists( 'wc_get_order' ) ) { function wc_get_order() { return $GLOBALS['wcai_f11_order']; } }
if ( ! function_exists( 'woocommerce_form_field' ) ) { function woocommerce_form_field( $key, $args ) { echo '<input name="' . $key . '" data-label="' . $args['label'] . '" data-required="' . (int) $args['required'] . '">'; } }

require_once dirname( __DIR__ ) . '/includes/class-utils.php';
require_once dirname( __DIR__ ) . '/includes/class-capabilities.php';
require_once dirname( __DIR__ ) . '/includes/class-data-resolver.php';
require_once dirname( __DIR__ ) . '/includes/class-frontend-account.php';
require_once dirname( __DIR__ ) . '/includes/class-checkout.php';
require_once dirname( __DIR__ ) . '/includes/checkout-block/trait-wcai-checkout-block-cart.php';
require_once dirname( __DIR__ ) . '/includes/checkout-block/trait-wcai-checkout-block-reservation.php';
require_once dirname( __DIR__ ) . '/includes/checkout-block/trait-wcai-checkout-block-validation.php';
require_once dirname( __DIR__ ) . '/includes/checkout-block/trait-wcai-checkout-block-participants.php';

if ( ! class_exists( 'WcaiF11Host' ) ) {
    class WcaiF11Host {
        const NAMESPACE = 'wcai-checkout';
        use WCAI_Checkout_Block_Cart;
        use WCAI_Checkout_Block_Reservation;
        use WCAI_Checkout_Block_Validation;
        use WCAI_Checkout_Block_Participants;
        public function capture( $order, $request ) { $this->capture_checkout_payload( $order, $request ); }
        public function run( $order ) { $this->reserve_order_items( $order ); }
    }
}

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Test_F11_Review_Fixes extends TestCase {

    private function row() {
        return array( 'id' => 1, 'order_id' => 94, 'nome_completo' => 'Ana Participante', 'cpf' => '11144477735',
            'data_nascimento' => '1990-01-01', 'termo_assinado' => 0, 'ticket_hash' => '', 'reservation_id' => 0 );
    }

    private function render( $order ) {
        WCAI_Participants_DB::$rows = array( $this->row() );
        ob_start();
        ( new WCAI_Frontend_Account() )->render_participants_on_order_view( $order );
        return ob_get_clean();
    }

    private function guest_order() {
        $GLOBALS['wcai_f11_user'] = 0;
        $order = new WcaiF11Order();
        $order->customer_id = 0; // 0 === 0 nao pode contar como dono
        return $order;
    }

    // --- Finding A: titularidade do pedido ---

    public function test_guest_without_key_sees_nothing() {
        unset( $_GET['key'] );

        $this->assertSame( '', $this->render( $this->guest_order() ) );
    }

    public function test_guest_with_wrong_key_sees_nothing() {
        $_GET['key'] = 'wc_order_errada';

        $this->assertSame( '', $this->render( $this->guest_order() ) );
    }

    public function test_guest_with_valid_key_sees_participants_and_sign_link() {
        $_GET['key'] = 'wc_order_abc';

        $html = $this->render( $this->guest_order() );
        $this->assertStringContainsString( 'Ana Participante', $html );
        $this->assertStringContainsString( 'key=wc_order_abc', $html );
    }

    public function test_other_logged_user_without_key_sees_nothing_but_admin_does() {
        $GLOBALS['wcai_f11_user'] = 8; // nao e o dono (7)
        unset( $_GET['key'] );
        $this->assertSame( '', $this->render( new WcaiF11Order() ) );

        $GLOBALS['wcai_f11_admin'] = true;
        $this->assertStringContainsString( 'Ana Participante', $this->render( new WcaiF11Order() ) );
    }

    public function test_owner_sees_participants_with_masked_cpf() {
        $html = $this->render( new WcaiF11Order() );

        $this->assertStringContainsString( 'Ana Participante', $html );
        $this->assertStringNotContainsString( '11144477735', $html );
        $this->assertStringContainsString( '111.***.***-35', $html );
    }

    public function test_orders_list_column_and_status_hide_participants_from_non_owner() {
        $GLOBALS['wcai_f11_user'] = 8;
        unset( $_GET['key'] );
        WCAI_Participants_DB::$rows = array( $this->row() );
        $account = new WCAI_Frontend_Account();

        ob_start();
        $account->column_content( new WcaiF11Order() );
        $col = ob_get_clean();
        ob_start();
        $account->append_status_column( new WcaiF11Order() );
        $status = ob_get_clean();

        $this->assertStringNotContainsString( 'Ana Participante', $col );
        $this->assertStringNotContainsString( 'wc_order_abc', $col );
        $this->assertStringNotContainsString( 'Termo pendente', $status );
    }

    // --- Finding A: buffer de mascara de CPF ---

    public function test_buffer_flushed_without_closing_hook_is_still_masked() {
        $account = new WCAI_Frontend_Account();
        $order = new WcaiF11Order();
        $order->meta = array( 'billing_cpf' => '52998224725' );
        $level = ob_get_level();

        ob_start(); // captura o que iria para o navegador
        $account->begin_cpf_mask_buffer( $order );
        echo '<dd>529.982.247-25</dd><dd>52998224725</dd><p>rodape</p>';
        ob_end_flush(); // flush de fim de requisicao: o hook de fechamento nunca disparou
        $out = ob_get_clean();

        $this->assertSame( $level, ob_get_level() );
        $this->assertSame( '<dd>529.***.***-25</dd><dd>529.***.***-25</dd><p>rodape</p>', $out );
    }

    public function test_buffer_closes_once_and_second_end_is_a_noop() {
        $account = new WCAI_Frontend_Account();
        $order = new WcaiF11Order();
        $level = ob_get_level();

        ob_start();
        $account->begin_cpf_mask_buffer( $order );
        $account->begin_cpf_mask_buffer( $order ); // segundo begin nao abre outro buffer
        $this->assertSame( $level + 2, ob_get_level() );
        echo 'x';
        $account->end_cpf_mask_buffer( $order );
        $account->end_cpf_mask_buffer( $order );
        $this->assertSame( $level + 1, ob_get_level() );
        $this->assertSame( 'x', ob_get_clean() );
    }

    // --- Finding B: checkout classico ---

    private function classic_cart( $product_id ) {
        WC()->cart->items = array( array( 'key' => 'k', 'product_id' => $product_id, 'variation_id' => 0, 'quantity' => 1 ) );
        $GLOBALS['wcai_f11_notices'] = array();
        $_POST = array();
    }

    public function test_classic_checkout_without_consent_is_rejected_for_tour_cart() {
        foreach ( array( null, '', '0', 'on', 'true', 1 ) as $value ) {
            $this->classic_cart( 99 );
            if ( null !== $value ) { $_POST['wcai_consent'] = $value; }
            ( new WCAI_Checkout() )->validate_consent();
            $this->assertCount( 1, $GLOBALS['wcai_f11_notices'], 'valor: ' . var_export( $value, true ) );
            $this->assertStringContainsString( 'consentimento', $GLOBALS['wcai_f11_notices'][0] );
        }
    }

    public function test_classic_checkout_accepts_checked_box_and_ignores_carts_without_tour() {
        $this->classic_cart( 99 );
        $_POST['wcai_consent'] = '1';
        ( new WCAI_Checkout() )->validate_consent();
        $this->assertSame( array(), $GLOBALS['wcai_f11_notices'] );

        $this->classic_cart( 5 ); // produto comum, sem consent
        ( new WCAI_Checkout() )->validate_consent();
        $this->assertSame( array(), $GLOBALS['wcai_f11_notices'] );
    }

    public function test_classic_checkout_renders_required_consent_checkbox_only_with_tour() {
        $checkout = new class { public function get_value( $k ) { return ''; } };

        $this->classic_cart( 99 );
        ob_start();
        ( new WCAI_Checkout() )->render_fields( $checkout );
        $html = ob_get_clean();
        $this->assertStringContainsString( 'name="wcai_consent"', $html );
        $this->assertStringContainsString( 'data-label="Texto de consentimento de teste"', $html );
        $this->assertStringContainsString( 'data-required="1"', $html );

        $this->classic_cart( 5 );
        ob_start();
        ( new WCAI_Checkout() )->render_fields( $checkout );
        $this->assertStringNotContainsString( 'wcai_consent', ob_get_clean() );
    }

    public function test_classic_order_without_consent_fails_closed() {
        $this->classic_cart( 99 );
        $order = new WcaiF11Order();
        $GLOBALS['wcai_f11_order'] = $order;

        ( new WCAI_Checkout() )->save_fields_and_notes( 94 );

        $this->assertSame( 'failed', $order->status );
        $this->assertArrayNotHasKey( '_wcai_third_party_consent', $order->meta );
    }

    public function test_classic_order_with_consent_stores_same_meta_as_block_path() {
        $this->classic_cart( 99 );
        $_POST['wcai_consent'] = '1';
        $order = new WcaiF11Order();
        $GLOBALS['wcai_f11_order'] = $order;

        ( new WCAI_Checkout() )->save_fields_and_notes( 94 );

        $this->assertSame( 'pending', $order->status );
        $meta = $order->meta['_wcai_third_party_consent'];
        $this->assertSame( hash( 'sha256', WCAI_Privacy::consent_text() ), $meta['text_sha256'] );
        $this->assertNotEmpty( $meta['accepted_at'] );
    }

    // --- Finding B: bloco / Store API ---

    private function request( $extensions ) { return new WP_REST_Request( array( 'extensions' => $extensions ) ); }

    private function assert_rejected( $order, $needle ) {
        try {
            ( new WcaiF11Host() )->run( $order );
            $this->fail( 'Pedido deveria ser recusado.' );
        } catch ( Exception $e ) {
            $this->assertStringContainsString( $needle, $e->getMessage() );
        }
        $this->assertArrayNotHasKey( '_wcai_third_party_consent', $order->meta );
    }

    public function test_store_api_without_extension_payload_is_rejected() {
        $host = new WcaiF11Host();
        $cases = array(
            array(),
            array( 'wcai-checkout' => array() ),
            array( 'wcai-checkout' => 'texto' ),
            array( 'wcai-checkout' => array( 'data' => '{}' ) ),
            array( 'wcai-checkout' => array( 'data' => 'nao-json' ) ),
            'string',
        );
        foreach ( $cases as $ext ) {
            WC()->session->data = array();
            $order = new WcaiF11Order();
            $host->capture( $order, $this->request( $ext ) );
            $this->assert_rejected( $order, 'não foram recebidos' );
        }
    }

    public function test_stale_session_payload_with_consent_is_not_reused_by_a_later_request_without_payload() {
        $host = new WcaiF11Host();
        $order = new WcaiF11Order();
        $payload = json_encode( array( 'consent' => true, 'items' => array( array( 'product_id' => 99, 'variation_id' => 0, 'departure_id' => 5 ) ) ) );

        $host->capture( $order, $this->request( array( 'wcai-checkout' => array( 'data' => $payload ) ) ) );
        $this->assertNotNull( WC()->session->get( 'wcai_checkout_payload_94' ) ); // pedido anterior falhou depois: sobra na sessao

        $host->capture( $order, $this->request( array() ) ); // POST direto sem extensions
        $this->assertNull( WC()->session->get( 'wcai_checkout_payload_94' ) );
        $this->assert_rejected( $order, 'não foram recebidos' );
    }

    public function test_store_api_payload_without_consent_is_rejected() {
        $order = new WcaiF11Order();
        $payload = json_encode( array( 'items' => array( array( 'product_id' => 99, 'variation_id' => 0, 'departure_id' => 5 ) ) ) );
        ( new WcaiF11Host() )->capture( $order, $this->request( array( 'wcai-checkout' => array( 'data' => $payload ) ) ) );

        $this->assert_rejected( $order, 'consentimento' );
    }
}

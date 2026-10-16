<?php
// PHPUnit com stubs (sem WP). Suite "f9"; processo separado (stubs guardados por function_exists/class_exists).
// Cobre: pedido recusado sem consent=true no payload do checkout, meta _wcai_third_party_consent (data ISO + sha256 do texto)
// e a barra de botões do wizard só quando o carrinho tem passeio.
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'absint' ) ) {
    function absint( $v ) { return abs( (int) $v ); }
}

if ( ! function_exists( 'esc_html' ) ) {
    function esc_html( $v ) { return $v; }
}

if ( ! class_exists( 'WCAI_Settings' ) ) {
    class WCAI_Settings {
        public static function get_product_ids() { return array( 99 ); }
    }
}

if ( ! class_exists( 'WCAI_Privacy' ) ) {
    class WCAI_Privacy {
        public static function consent_text() { return 'Texto de consentimento de teste'; }
    }
}

// Titular sem nome: a validação segue depois do aceite e falha aqui, o que prova que o aceite passou.
if ( ! class_exists( 'WCAI_Data_Resolver' ) ) {
    class WCAI_Data_Resolver {
        public static function get_billing_cpf( $order ) { return ''; }
        public static function get_billing_birthdate( $order ) { return ''; }
        public static function get_billing_name( $order ) { return ''; }
    }
}

if ( ! class_exists( 'WC_Order' ) ) {
    class WC_Order {}
}

if ( ! class_exists( 'WcaiF9Item' ) ) {
    class WcaiF9Item {
        public function get_product_id() { return 99; }
        public function get_variation_id() { return 0; }
    }
}

if ( ! class_exists( 'WcaiF9Order' ) ) {
    class WcaiF9Order extends WC_Order {
        public $meta = array();
        public $saved = false;
        public function get_id() { return 7; }
        public function get_items() { return array( 1 => new WcaiF9Item() ); }
        public function update_meta_data( $key, $value ) { $this->meta[ $key ] = $value; }
        public function save() { $this->saved = true; }
    }
}

if ( ! class_exists( 'WcaiF9Session' ) ) {
    class WcaiF9Session {
        public $data = array();
        public function get( $key ) { return isset( $this->data[ $key ] ) ? $this->data[ $key ] : null; }
        public function set( $key, $value ) { $this->data[ $key ] = $value; }
        public function __unset( $key ) { unset( $this->data[ $key ] ); }
    }
}

if ( ! class_exists( 'WcaiF9Woo' ) ) {
    class WcaiF9Woo {
        public static $instance;
        public $session;
        public $cart;
        public function __construct() {
            $this->session = new WcaiF9Session();
            $this->cart = new class {
                public $items = array();
                public function get_cart() { return $this->items; }
            };
        }
    }
}

if ( ! function_exists( 'WC' ) ) {
    function WC() {
        if ( ! WcaiF9Woo::$instance ) {
            WcaiF9Woo::$instance = new WcaiF9Woo();
        }
        return WcaiF9Woo::$instance;
    }
}

require_once dirname( __DIR__ ) . '/includes/checkout-block/trait-wcai-checkout-block-cart.php';
require_once dirname( __DIR__ ) . '/includes/checkout-block/trait-wcai-checkout-block-reservation.php';
require_once dirname( __DIR__ ) . '/includes/checkout-block/trait-wcai-checkout-block-validation.php';
require_once dirname( __DIR__ ) . '/includes/checkout-block/trait-wcai-checkout-block-participants.php';

if ( ! class_exists( 'WcaiF9Host' ) ) {
    class WcaiF9Host {
        const NAMESPACE = 'wcai-checkout';
        use WCAI_Checkout_Block_Cart;
        use WCAI_Checkout_Block_Reservation;
        use WCAI_Checkout_Block_Validation;
        use WCAI_Checkout_Block_Participants;

        public function run( $order ) { $this->reserve_order_items( $order ); }
        public function store( $order ) { $this->store_consent( $order ); }
    }
}

class Test_F9_Checkout_Consent extends TestCase {

    private function payload_session( $payload ) {
        WC()->session->data = array();
        WC()->session->set( 'wcai_checkout_payload_7', $payload );
    }

    private function items() {
        return array( array( 'product_id' => 99, 'variation_id' => 0, 'departure_id' => 5, 'adults' => 1, 'children' => 0, 'additional_participants' => array() ) );
    }

    public function test_order_without_consent_is_rejected_and_stores_nothing() {
        $order = new WcaiF9Order();
        $this->payload_session( array( 'items' => $this->items() ) );

        try {
            ( new WcaiF9Host() )->run( $order );
            $this->fail( 'Pedido sem consent deveria falhar.' );
        } catch ( Exception $e ) {
            $this->assertStringContainsString( 'consentimento', $e->getMessage() );
        }

        $this->assertArrayNotHasKey( '_wcai_third_party_consent', $order->meta );
        $this->assertFalse( $order->saved );
    }

    public function test_only_boolean_true_counts_as_consent() {
        foreach ( array( false, 'true', 1, 'yes', null ) as $value ) {
            $order = new WcaiF9Order();
            $this->payload_session( array( 'consent' => $value, 'items' => $this->items() ) );

            try {
                ( new WcaiF9Host() )->run( $order );
                $this->fail( 'consent ' . var_export( $value, true ) . ' deveria falhar.' );
            } catch ( Exception $e ) {
                $this->assertStringContainsString( 'consentimento', $e->getMessage() );
            }
        }
    }

    public function test_consent_true_passes_the_gate_and_validation_continues() {
        $order = new WcaiF9Order();
        $this->payload_session( array( 'consent' => true, 'items' => $this->items() ) );

        try {
            ( new WcaiF9Host() )->run( $order );
            $this->fail( 'Sem nome do titular deveria falhar depois do aceite.' );
        } catch ( Exception $e ) {
            $this->assertStringContainsString( 'nome do titular', $e->getMessage() );
        }
    }

    public function test_store_consent_writes_iso_timestamp_and_text_hash() {
        $order = new WcaiF9Order();
        ( new WcaiF9Host() )->store( $order );

        $meta = $order->meta['_wcai_third_party_consent'];

        $this->assertSame( hash( 'sha256', 'Texto de consentimento de teste' ), $meta['text_sha256'] );
        $this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $meta['accepted_at'] );
        $this->assertNotFalse( strtotime( $meta['accepted_at'] ) );
    }

    public function test_footer_is_added_only_when_cart_has_a_tour() {
        $host = new WcaiF9Host();

        WC()->cart->items = array();
        $this->assertSame( '<div>actions</div>', $host->render_checkout_footer( '<div>actions</div>' ) );

        WC()->cart->items = array( array( 'product_id' => 99, 'variation_id' => 0 ) );
        $html = $host->render_checkout_footer( '<div>actions</div>' );
        $this->assertStringStartsWith( '<div>actions</div>', $html );
        $this->assertStringContainsString( 'id="wcai-checkout-wizard-footer"', $html );
    }
}

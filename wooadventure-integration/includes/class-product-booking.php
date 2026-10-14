<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once __DIR__ . '/product-booking/trait-wcai-product-booking-departure.php';
require_once __DIR__ . '/product-booking/trait-wcai-product-booking-departure-info.php';
require_once __DIR__ . '/product-booking/trait-wcai-product-booking-admin.php';
require_once __DIR__ . '/product-booking/trait-wcai-product-booking-facts.php';
require_once __DIR__ . '/product-booking/trait-wcai-product-booking-assets.php';
require_once __DIR__ . '/product-booking/trait-wcai-product-booking-js-a.php';
require_once __DIR__ . '/product-booking/trait-wcai-product-booking-js-b.php';
require_once __DIR__ . '/product-booking/trait-wcai-product-booking-js-c.php';
require_once __DIR__ . '/product-booking/trait-wcai-product-booking-cart.php';

class WCAI_Product_Booking {
    use WCAI_Product_Booking_Departure, WCAI_Product_Booking_Departure_Info, WCAI_Product_Booking_Admin, WCAI_Product_Booking_Facts, WCAI_Product_Booking_Assets, WCAI_Product_Booking_Js_A, WCAI_Product_Booking_Js_B, WCAI_Product_Booking_Js_C, WCAI_Product_Booking_Cart;

    const FIELD = 'wcai_product_departure_id';
    const MAX_AJAX_QUANTITY = 100;
    const DEPARTURE_IDS_TTL = 60;

    // Limita a quantidade recebida pelo endpoint público (nopriv): 1..MAX_AJAX_QUANTITY.
    public static function clamp_quantity( $value ) {
        return max( 1, min( self::MAX_AJAX_QUANTITY, (int) $value ) );
    }

    // Bloco do carrinho: a quantidade do passeio = participantes; editar no stepper dessincronizaria a lista.
    public function filter_store_api_quantity_editable( $editable, $product = null, $cart_item = null ) {
        if ( ! $product || ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
            return $editable;
        }

        $parent_id = method_exists( $product, 'get_parent_id' ) ? $product->get_parent_id() : 0;

        return $this->is_target_product( $parent_id ? $parent_id : $product->get_id(), $product->get_id() ) ? false : $editable;
    }

    public function __construct() {
        add_action( 'woocommerce_before_add_to_cart_quantity', array( $this, 'render_departure_selector' ), 20 );
        add_action( 'wp_ajax_wcai_refresh_calendar_date', array( $this, 'ajax_refresh_calendar_date' ) );
        add_action( 'wp_ajax_nopriv_wcai_refresh_calendar_date', array( $this, 'ajax_refresh_calendar_date' ) );
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 5 );
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 4 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'render_cart_item_data' ), 10, 2 );
        add_filter( 'woocommerce_cart_item_quantity', array( $this, 'render_cart_item_quantity' ), 10, 3 );
        add_filter( 'woocommerce_store_api_product_quantity_editable', array( $this, 'filter_store_api_quantity_editable' ), 10, 3 );
        add_filter( 'woocommerce_add_to_cart_quantity', array( $this, 'filter_add_to_cart_quantity' ), 10, 3 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_data' ), 10, 4 );
        add_action( 'woocommerce_single_product_summary', array( $this, 'render_product_facts' ), 16 );
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
        add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_data' ) );
    }

}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/checkout-block/trait-wcai-checkout-block-setup.php';
require_once __DIR__ . '/checkout-block/trait-wcai-checkout-block-cart.php';
require_once __DIR__ . '/checkout-block/trait-wcai-checkout-block-reservation.php';
require_once __DIR__ . '/checkout-block/trait-wcai-checkout-block-validation.php';
require_once __DIR__ . '/checkout-block/trait-wcai-checkout-block-participants.php';

class WCAI_Checkout_Block {

    const NAMESPACE = 'wcai-checkout';
    const SCRIPT_HANDLE = 'wcai-checkout-block';

    use WCAI_Checkout_Block_Setup;
    use WCAI_Checkout_Block_Cart;
    use WCAI_Checkout_Block_Reservation;
    use WCAI_Checkout_Block_Validation;
    use WCAI_Checkout_Block_Participants;

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'woocommerce_init', array( $this, 'register_additional_checkout_fields' ), 20 );
        add_action( 'woocommerce_set_additional_field_value', array( $this, 'sync_identity_field' ), 10, 4 );
        add_action( 'woocommerce_blocks_loaded', array( $this, 'register_store_api' ) );
        add_filter( 'render_block_woocommerce/checkout-fields-block', array( $this, 'render_checkout_ui' ), 20 );
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'capture_checkout_payload' ), 10, 2 );
        add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'process_checkout' ), 10, 1 );
    }

}

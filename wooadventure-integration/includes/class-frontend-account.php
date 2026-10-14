<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/frontend-account/trait-wcai-frontend-account-view.php';
require_once __DIR__ . '/frontend-account/trait-wcai-frontend-account-modal.php';
require_once __DIR__ . '/frontend-account/trait-wcai-frontend-account-ajax.php';
require_once __DIR__ . '/frontend-account/trait-wcai-frontend-account-admin.php';
require_once __DIR__ . '/frontend-account/trait-wcai-frontend-account-save.php';
require_once __DIR__ . '/frontend-account/trait-wcai-frontend-account-assets.php';
require_once __DIR__ . '/frontend-account/trait-wcai-frontend-account-orderpage.php';

class WCAI_Frontend_Account {
    use WCAI_Frontend_Account_View;
    use WCAI_Frontend_Account_Modal;
    use WCAI_Frontend_Account_Ajax;
    use WCAI_Frontend_Account_Admin;
    use WCAI_Frontend_Account_Save;
    use WCAI_Frontend_Account_Assets;
    use WCAI_Frontend_Account_Order_Page;

    public function __construct() {
        // 1. Tabela Minha Conta (Coluna Personalizada)
        add_filter('woocommerce_my_account_my_orders_columns', array($this, 'add_column'));
        add_action('woocommerce_my_account_my_orders_column_participantes', array($this, 'column_content'));
        
        // 2. Coluna Status Híbrida (Aviso extra)
        add_action('woocommerce_my_account_my_orders_column_order-status', array($this, 'append_status_column'));

        // 3. Visualização do Pedido (Site e E-mail)
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_participants_on_order_view' ), 10, 1 );
        add_action( 'woocommerce_thankyou', array( $this, 'render_participants_on_thankyou' ), 20, 1 );
        // CPF do titular mascarado nos campos adicionais (bloco e template clássico).
        add_filter( 'render_block_woocommerce/order-confirmation-additional-fields', array( $this, 'mask_cpf_in_additional_fields_block' ) );
        // Tema de blocos (obrigado): lista de participantes e próximos passos entram no conteúdo da confirmação, não antes do <html>.
        add_filter( 'render_block_woocommerce/order-confirmation-totals-wrapper', array( $this, 'append_extras_to_confirmation_block' ) );
        add_filter( 'render_block_woocommerce/order-confirmation-additional-information', array( $this, 'append_extras_to_confirmation_block' ) );
        add_action( 'wp_footer', array( $this, 'flush_deferred_extras' ), 5 );
        add_filter( 'gettext', array( $this, 'fix_order_number_label' ), 10, 3 );
        add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'fix_orders_action_labels' ), 10, 2 );
        add_action( 'wp_head', array( $this, 'print_order_pages_css' ) );
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'begin_cpf_mask_buffer' ), 99, 1 );
        add_action( 'woocommerce_order_details_after_customer_details', array( $this, 'end_cpf_mask_buffer' ), PHP_INT_MAX, 1 );
        add_action( 'woocommerce_email_after_order_table', array( $this, 'render_participants_in_email' ), 10, 4 );

        // 4. Scripts e Modais
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts')); 
        add_action('wp_footer', array($this, 'modal_assets')); 
        add_action('wp_ajax_wcai_update_participants', array($this, 'ajax_update'));
        
        // 5. Backend (Admin)
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts')); 
        add_action('add_meta_boxes', array($this, 'register_admin_metabox'));
        add_action('woocommerce_process_shop_order_meta', array($this, 'admin_save'), 10, 2);
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'render_hpos_admin_content'), 20, 1);
        add_filter('woocommerce_hidden_order_itemmeta', array($this, 'hide_departure_itemmeta'));
        add_action('woocommerce_after_order_itemmeta', array($this, 'render_departure_itemmeta'), 10, 2);
    }

}

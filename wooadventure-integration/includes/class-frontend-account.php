<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/frontend-account/trait-wcai-frontend-account-view.php';
require_once __DIR__ . '/frontend-account/trait-wcai-frontend-account-modal.php';
require_once __DIR__ . '/frontend-account/trait-wcai-frontend-account-ajax.php';
require_once __DIR__ . '/frontend-account/trait-wcai-frontend-account-admin.php';
require_once __DIR__ . '/frontend-account/trait-wcai-frontend-account-save.php';
require_once __DIR__ . '/frontend-account/trait-wcai-frontend-account-assets.php';

class WCAI_Frontend_Account {
    use WCAI_Frontend_Account_View;
    use WCAI_Frontend_Account_Modal;
    use WCAI_Frontend_Account_Ajax;
    use WCAI_Frontend_Account_Admin;
    use WCAI_Frontend_Account_Save;
    use WCAI_Frontend_Account_Assets;

    public function __construct() {
        // 1. Tabela Minha Conta (Coluna Personalizada)
        add_filter('woocommerce_my_account_my_orders_columns', array($this, 'add_column'));
        add_action('woocommerce_my_account_my_orders_column_participantes', array($this, 'column_content'));
        
        // 2. Coluna Status Híbrida (Aviso extra)
        add_action('woocommerce_my_account_my_orders_column_order-status', array($this, 'append_status_column'));

        // 3. Visualização do Pedido (Site e E-mail)
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_participants_on_order_view' ), 10, 1 );
        add_action( 'woocommerce_email_after_order_table', array( $this, 'render_participants_on_order_view' ), 10, 1 );

        // 4. Scripts e Modais
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts')); 
        add_action('wp_footer', array($this, 'modal_assets')); 
        add_action('wp_ajax_wcai_update_participants', array($this, 'ajax_update'));
        
        // 5. Backend (Admin)
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts')); 
        add_action('add_meta_boxes', array($this, 'register_admin_metabox'));
        add_action('woocommerce_process_shop_order_meta', array($this, 'admin_save'), 10, 2);
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'render_hpos_admin_content'), 20, 1);
    }

}

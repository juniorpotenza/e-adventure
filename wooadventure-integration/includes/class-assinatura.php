<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/assinatura/trait-wcai-assinatura-shortcode.php';
require_once __DIR__ . '/assinatura/trait-wcai-assinatura-ajax.php';
require_once __DIR__ . '/assinatura/trait-wcai-assinatura-storage.php';
require_once __DIR__ . '/assinatura/trait-wcai-assinatura-migration.php';
require_once __DIR__ . '/assinatura/trait-wcai-assinatura-rate-limit.php';
require_once __DIR__ . '/assinatura/trait-wcai-assinatura-ticket.php';
require_once __DIR__ . '/assinatura/trait-wcai-assinatura-qr.php';
require_once __DIR__ . '/assinatura/trait-wcai-assinatura-session.php';
require_once __DIR__ . '/assinatura/trait-wcai-assinatura-admin.php';

class WCAI_Assinatura {


    const AJAX_NONCE = 'wcai_signature';
    const LOOKUP_ERROR = 'Dados não conferem. Verifique o pedido, a chave do pedido e o CPF.';
    const SESSION_ERROR = 'Sessão de assinatura inválida ou expirada.';
    const RATE_LIMIT = 10;
    // Um grupo atrás do mesmo IP assina vários termos no mesmo pedido: limite maior para salvar.
    const RATE_LIMIT_SAVE = 30;

    use WCAI_Assinatura_Shortcode;
    use WCAI_Assinatura_Ajax;
    use WCAI_Assinatura_Storage;
    use WCAI_Assinatura_Migration;
    use WCAI_Assinatura_Rate_Limit;
    use WCAI_Assinatura_Ticket;
    use WCAI_Assinatura_Qr;
    use WCAI_Assinatura_Session;
    use WCAI_Assinatura_Admin;

    public function __construct() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_filter( 'manage_wcai_assinatura_posts_columns', array( $this, 'set_custom_columns' ) );
        add_action( 'manage_wcai_assinatura_posts_custom_column', array( $this, 'custom_column_content' ), 10, 2 );
        add_action( 'add_meta_boxes', array( $this, 'add_details_metabox' ) );

        add_shortcode( 'wcai_painel_assinatura', array( $this, 'render_shortcode' ) );
        add_shortcode( 'wcai_script_validacao', array( $this, 'render_validation_script_shortcode' ) );

        add_action( 'wp_ajax_wcai_check_order_only', array( $this, 'ajax_check_order' ) );
        add_action( 'wp_ajax_nopriv_wcai_check_order_only', array( $this, 'ajax_check_order' ) );
        add_action( 'wp_ajax_wcai_autocheck_pax', array( $this, 'ajax_check_pax_deep' ) );
        add_action( 'wp_ajax_nopriv_wcai_autocheck_pax', array( $this, 'ajax_check_pax_deep' ) );
        add_action( 'wp_ajax_wcai_salvar_assinatura_final', array( $this, 'ajax_save_signature' ) );
        add_action( 'wp_ajax_nopriv_wcai_salvar_assinatura_final', array( $this, 'ajax_save_signature' ) );
        add_action( 'admin_post_wcai_signature_image', array( $this, 'serve_signature_image' ) );
    }

    const MIGRATE_BATCH = 200;
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/agenda/trait-wcai-agenda-ical.php';
require_once __DIR__ . '/agenda/trait-wcai-agenda-admin.php';
require_once __DIR__ . '/agenda/trait-wcai-agenda-ajax.php';
require_once __DIR__ . '/agenda/trait-wcai-agenda-tracker.php';

class WCAI_Agenda {

    use WCAI_Agenda_Ical, WCAI_Agenda_Admin, WCAI_Agenda_Ajax, WCAI_Agenda_Tracker;

    // FullCalendar fixado em 5.11.3. Hashes sha384 calculados com curl + openssl sobre os arquivos do jsDelivr.
    const FULLCALENDAR_SRI = array(
        'fullcalendar-css'     => 'sha384-39yVKLsD9lMelmY+ij49KZgE+Mfk6hjdUPNE8yKHqdMPceLXzhlCJAK81xlD5jDj',
        'fullcalendar-js'      => 'sha384-5/vsv56401Wf+RP3yE5/aIKW4wutk4nLY3HjueTXN0rA+DmweMtrYaN6RSjdv31b',
        'fullcalendar-locales' => 'sha384-laTCIy/Sc61b2bifRPvpvrJ70lBZOoJsyCNfEdP1udxBhtsMJCnrybjVv2/ZVHfj',
    );

    public function __construct() {
        // 1. Menus e Scripts
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_filter( 'script_loader_tag', array( $this, 'add_sri_attributes' ), 10, 2 );
        add_filter( 'style_loader_tag', array( $this, 'add_sri_attributes' ), 10, 2 );

        // 2. AJAX Painel
        add_action( 'wp_ajax_wcai_get_calendar_events', array( $this, 'ajax_get_events' ) );
        add_action( 'wp_ajax_wcai_get_day_details', array( $this, 'ajax_get_day_details' ) );
        add_action( 'wp_ajax_wcai_clear_cache', array( $this, 'ajax_clear_cache' ) );
        
        // 3. Shortcode de Sucesso (MODIFICADO: GERA O TICKET + E-MAIL)
        add_shortcode( 'wcai_confirma_assinatura', array( $this, 'render_success_tracker' ) );

        // 4. Integração iCal (AGRUPADA)
        add_action( 'init', array( $this, 'handle_ical_feed' ), 1 ); 
        
        // Manutenção
        add_action( 'woocommerce_process_shop_order_meta', array( $this, 'clear_calendar_cache_internal' ) );
        add_action( 'woocommerce_order_status_changed', array( $this, 'clear_calendar_cache_internal' ) );
        add_action( 'wcai_reservation_changed', array( $this, 'clear_calendar_cache_internal' ) );
        add_action( 'wcai_reservations_changed', array( $this, 'clear_calendar_cache_internal' ) );
        add_action( 'admin_init', array( $this, 'db_auto_repair_column' ) );
        add_action( 'admin_post_wcai_reset_key', array($this, 'admin_reset_key') );
    }

    public function ajax_clear_cache() {
        check_ajax_referer( 'wcai_clear_calendar_cache', 'nonce' );
        if ( ! current_user_can( WCAI_Capabilities::VIEW_MANIFEST ) ) {
            wp_send_json_error( array( 'message' => 'Acesso negado.' ), 403 );
        }

        $this->clear_calendar_cache_internal();
        wp_send_json_success();
    }
    public function db_auto_repair_column() {}
    public function clear_calendar_cache_internal() {
        global $wpdb;
        $option_names = $wpdb->get_col( "SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_transient_wcai_events_%'" );
        foreach ( $option_names as $option_name ) {
            $transient_name = substr( $option_name, strlen( '_transient_' ) );
            if ( $transient_name !== '' ) {
                delete_transient( $transient_name );
            }
        }
    } 
}

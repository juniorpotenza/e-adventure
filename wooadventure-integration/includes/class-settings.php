<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/settings/trait-wcai-settings-page.php';
require_once __DIR__ . '/settings/trait-wcai-settings-sanitize.php';

class WCAI_Settings {
    use WCAI_Settings_Page, WCAI_Settings_Sanitize;


    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_filter( 'option_page_capability_wcai_settings_group', array( $this, 'get_settings_capability' ) );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Configurações Adventure',
            'Adventure Config',
            WCAI_Capabilities::MANAGE_SETTINGS,
            'wcai-settings',
            array( $this, 'settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'wcai_settings_group', 'wcai_carta_oferta', array( 'sanitize_callback' => array( $this, 'sanitize_carta_oferta' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_trigger_status', array( 'sanitize_callback' => array( $this, 'sanitize_status' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_product_ids', array( 'sanitize_callback' => array( $this, 'sanitize_product_ids' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_blocked_cpfs', array( 'sanitize_callback' => array( $this, 'sanitize_blocked_cpfs' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_date_meta_key', array( 'sanitize_callback' => array( $this, 'sanitize_meta_key' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_event_date_source', array( 'sanitize_callback' => array( $this, 'sanitize_event_date_source' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_event_date_meta_key', array( 'sanitize_callback' => array( $this, 'sanitize_meta_key' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_seq_enabled', array( 'sanitize_callback' => array( $this, 'sanitize_seq_enabled' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_seq_prefix', array( 'sanitize_callback' => array( $this, 'sanitize_text' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_seq_suffix', array( 'sanitize_callback' => array( $this, 'sanitize_text' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_seq_width', array( 'sanitize_callback' => array( $this, 'sanitize_seq_width' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_seq_next', array( 'sanitize_callback' => array( $this, 'sanitize_seq_next' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_calendar_statuses', array( 'sanitize_callback' => array( $this, 'sanitize_calendar_statuses' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_operation_name', array( 'sanitize_callback' => array( $this, 'sanitize_operation_name' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_terms_url', array( 'sanitize_callback' => 'esc_url_raw' ) );
        register_setting( 'wcai_settings_group', 'wcai_cancellation_policy', array( 'sanitize_callback' => array( $this, 'sanitize_cancellation_policy' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_contact_phone', array( 'sanitize_callback' => array( $this, 'sanitize_contact_phone' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_accent_color', array( 'sanitize_callback' => array( $this, 'sanitize_accent_color' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_delete_data_on_uninstall', array( 'sanitize_callback' => array( $this, 'sanitize_delete_data' ) ) );
    }


    public static function get_product_ids() {
        $ids = array_map( 'absint', array_map( 'trim', explode( ',', get_option( 'wcai_product_ids', '' ) ) ) );
        return array_values( array_filter( $ids ) );
    }

    public static function is_cpf_blocked( $cpf ) {
        $cpf = preg_replace( '/\D+/', '', (string) $cpf );
        if ( ! $cpf ) {
            return false;
        }

        $blocked = preg_split( '/[\s,;]+/', (string) get_option( 'wcai_blocked_cpfs', '' ), -1, PREG_SPLIT_NO_EMPTY );
        $blocked = array_map( static function ( $blocked_cpf ) {
            return preg_replace( '/\D+/', '', $blocked_cpf );
        }, $blocked );

        return in_array( $cpf, $blocked, true );
    }

    public static function get_operation_name() { return get_option( 'wcai_operation_name', 'Operação' ); }
    public static function get_terms_url() { return get_option( 'wcai_terms_url', '' ); }
    public static function get_cancellation_policy() { return (string) get_option( 'wcai_cancellation_policy', '' ); }
    public static function get_contact_phone() { return (string) get_option( 'wcai_contact_phone', '' ); }
    // Hex válido (#abc ou #aabbcc) ou '' (= mantém o padrão #111); nunca devolve valor sem validar.
    public static function get_accent_color() { return self::clean_hex( get_option( 'wcai_accent_color', '' ) ); }
    public static function get_carta_oferta() { return get_option( 'wcai_carta_oferta', '' ); }
    public static function get_date_meta_key() { return get_option( 'wcai_event_date_meta_key', get_option( 'wcai_date_meta_key', 'tour_date' ) ); }
    public static function get_event_date_source() { return get_option( 'wcai_event_date_source', 'departure' ); }
    public static function get_event_date_meta_key() { return self::get_date_meta_key(); }
    public static function get_token() { return self::get_carta_oferta(); }
    public static function get_trigger_status() { return get_option( 'wcai_trigger_status', 'completed' ); }

    public static function get_calendar_statuses() {
        $statuses = get_option( 'wcai_calendar_statuses' );
        return ( empty( $statuses ) || ! is_array( $statuses ) ) ? array( 'wc-processing', 'wc-completed' ) : $statuses;
    }

    public static function is_seq_enabled() { return get_option( 'wcai_seq_enabled' ) === 'yes'; }
    public static function get_seq_prefix() { return get_option( 'wcai_seq_prefix', '' ); }
    public static function get_seq_suffix() { return get_option( 'wcai_seq_suffix', '' ); }
    public static function get_seq_width() { return intval( get_option( 'wcai_seq_width', 6 ) ); }
    public static function get_seq_next() { return intval( get_option( 'wcai_seq_next', 1 ) ); }
    public static function update_seq_next( $next ) { update_option( 'wcai_seq_next', intval( $next ) ); }
}

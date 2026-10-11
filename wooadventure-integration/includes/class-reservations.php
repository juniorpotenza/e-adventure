<?php
if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/reservations/trait-wcai-reservations-lifecycle.php';
require_once __DIR__ . '/reservations/trait-wcai-reservations-schema.php';
require_once __DIR__ . '/reservations/trait-wcai-reservations-departures.php';
require_once __DIR__ . '/reservations/trait-wcai-reservations-write.php';

class WCAI_Reservations {
    const HOLD_MINUTES = 15;
    const MAX_CONFIRM_RETRIES = 5;
    use WCAI_Reservations_Lifecycle, WCAI_Reservations_Schema, WCAI_Reservations_Departures, WCAI_Reservations_Write;

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wcai_reservations';
    }

    public static function register_hooks() {
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'confirm_order' ) );
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'confirm_order' ) );
        add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'cancel_order' ) );
        add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'cancel_order' ) );
        add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'release_order' ) ); // 'expired', não 'cancelled': o item pode ser reenviado
        add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedule' ) );
        add_action( 'wcai_expire_holds', array( __CLASS__, 'expire_holds' ) );
        add_action( 'wcai_retry_confirm', array( __CLASS__, 'confirm_order' ), 10, 2 );
    }

    public static function add_cron_schedule( $schedules ) {
        $schedules['wcai_five_minutes'] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'A cada 5 minutos' );
        return $schedules;
    }

}

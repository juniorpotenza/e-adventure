<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Reservations {
    const HOLD_MINUTES = 15;

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wcai_reservations';
    }

    public static function register_hooks() {
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'confirm_order' ) );
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'confirm_order' ) );
        add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'cancel_order' ) );
        add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'cancel_order' ) );
        add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'cancel_order' ) );
    }

    public static function confirm_order( $order_id ) {
        self::update_status_for_order( $order_id, 'confirmed' );
    }

    public static function cancel_order( $order_id ) {
        self::update_status_for_order( $order_id, 'cancelled' );
    }

    public static function create_table() {
        global $wpdb;
        $table = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            departure_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned DEFAULT NULL,
            order_item_id bigint(20) unsigned DEFAULT NULL,
            quantity int(10) unsigned NOT NULL,
            status varchar(20) NOT NULL,
            expires_at datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY order_item (order_item_id),
            KEY departure_status (departure_id, status),
            KEY hold_expiration (status, expires_at),
            KEY order_id (order_id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public static function get_reserved_quantity( $departure_id ) {
        global $wpdb;
        $table = self::get_table_name();
        $now = current_time( 'mysql', true );
        $sql = "SELECT COALESCE(SUM(quantity), 0) FROM $table WHERE departure_id = %d AND (status IN ('pending', 'confirmed') OR (status = 'hold' AND expires_at > %s))";
        return absint( $wpdb->get_var( $wpdb->prepare( $sql, $departure_id, $now ) ) );
    }

    public static function get_available_quantity( $departure_id ) {
        $capacity = absint( get_post_meta( $departure_id, '_wcai_capacity', true ) );
        return max( 0, $capacity - self::get_reserved_quantity( $departure_id ) );
    }

    public static function create( $departure_id, $quantity, $status = 'hold', $order_id = 0, $order_item_id = 0 ) {
        global $wpdb;
        $departure_id = absint( $departure_id );
        $quantity = absint( $quantity );
        $order_id = absint( $order_id );
        $order_item_id = absint( $order_item_id );
        $allowed_statuses = array( 'hold', 'pending', 'confirmed' );

        if ( ! $departure_id || ! $quantity || ! in_array( $status, $allowed_statuses, true ) || WCAI_Departures::POST_TYPE !== get_post_type( $departure_id ) ) {
            return new WP_Error( 'wcai_invalid_reservation', 'Reserva inválida.' );
        }
        if ( 'open' !== get_post_meta( $departure_id, '_wcai_departure_status', true ) && 'confirmed' !== get_post_meta( $departure_id, '_wcai_departure_status', true ) ) {
            return new WP_Error( 'wcai_departure_unavailable', 'Esta saída não está disponível para reservas.' );
        }

        $lock_name = 'wcai_departure_' . $departure_id;
        $locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, 5 ) );
        if ( 1 !== $locked ) {
            return new WP_Error( 'wcai_reservation_locked', 'Não foi possível reservar a vaga. Tente novamente.' );
        }

        try {
            if ( $quantity > self::get_available_quantity( $departure_id ) ) {
                return new WP_Error( 'wcai_capacity_exceeded', 'Não há vagas suficientes nesta saída.' );
            }

            $table = self::get_table_name();
            $now = current_time( 'mysql', true );
            $expires_at = 'hold' === $status ? gmdate( 'Y-m-d H:i:s', time() + ( self::HOLD_MINUTES * MINUTE_IN_SECONDS ) ) : null;
            $inserted = $wpdb->insert(
                $table,
                array(
                    'departure_id' => $departure_id,
                    'order_id'     => $order_id ?: null,
                    'order_item_id'=> $order_item_id ?: null,
                    'quantity'     => $quantity,
                    'status'       => $status,
                    'expires_at'   => $expires_at,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ),
                array( '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s' )
            );
            if ( false === $inserted ) {
                return new WP_Error( 'wcai_reservation_failed', 'Não foi possível registrar a reserva.' );
            }

            WCAI_Audit_Log::log( 'reservation_created', 'reservation', $wpdb->insert_id, array( 'departure_id' => $departure_id, 'quantity' => $quantity, 'status' => $status ) );
            return $wpdb->insert_id;
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
        }
    }

    public static function update_status_for_order( $order_id, $status ) {
        global $wpdb;
        $status = sanitize_key( $status );
        if ( ! in_array( $status, array( 'pending', 'confirmed', 'cancelled', 'refunded' ), true ) ) return false;

        return $wpdb->update(
            self::get_table_name(),
            array( 'status' => $status, 'expires_at' => null, 'updated_at' => current_time( 'mysql', true ) ),
            array( 'order_id' => absint( $order_id ) ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );
    }
}

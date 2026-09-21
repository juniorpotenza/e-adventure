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

    public static function get_by_id( $reservation_id ) {
        global $wpdb;
        $table = self::get_table_name();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", absint( $reservation_id ) ) );
    }

    public static function get_by_order_item( $order_item_id ) {
        global $wpdb;
        $table = self::get_table_name();
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE order_item_id = %d", absint( $order_item_id ) ) );
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

    public static function is_booking_open( $departure_id ) {
        $departure_id = absint( $departure_id );
        $starts_at = WCAI_Data_Resolver::get_departure_start( $departure_id );

        if ( ! $departure_id || ! $starts_at ) {
            return false;
        }

        $start_timestamp = WCAI_Data_Resolver::parse_timestamp( $starts_at );

        if ( ! $start_timestamp ) {
            return false;
        }

        $cutoff_value = absint( get_post_meta( $departure_id, '_wcai_booking_cutoff_value', true ) );
        $cutoff_unit = get_post_meta( $departure_id, '_wcai_booking_cutoff_unit', true ) ?: 'hours';

        $multiplier = 'days' === $cutoff_unit ? DAY_IN_SECONDS : HOUR_IN_SECONDS;
        $closing_timestamp = $start_timestamp - ( $cutoff_value * $multiplier );

        return current_time( 'timestamp' ) < $closing_timestamp;
    }

    public static function get_booking_cutoff_label( $departure_id ) {
        $value = absint( get_post_meta( $departure_id, '_wcai_booking_cutoff_value', true ) );
        $unit = get_post_meta( $departure_id, '_wcai_booking_cutoff_unit', true ) ?: 'hours';
        $starts_at = WCAI_Data_Resolver::get_departure_start( $departure_id );

        if ( ! $value || ! $starts_at ) {
            return '';
        }

        $start_timestamp = WCAI_Data_Resolver::parse_timestamp( $starts_at );

        if ( ! $start_timestamp ) {
            return '';
        }

        $seconds = 'days' === $unit ? DAY_IN_SECONDS : HOUR_IN_SECONDS;
        $cutoff = $start_timestamp - ( $value * $seconds );

        return wp_date( 'd/m/Y H:i', $cutoff );
    }

    public static function get_open_departures_for_product( $product_id, $variation_id = 0 ) {
        $product_ids = array_filter( array_unique( array( absint( $product_id ), absint( $variation_id ) ) ) );
        if ( empty( $product_ids ) ) return array();

        return get_posts( array(
            'post_type'      => WCAI_Departures::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'meta_key'       => '_wcai_starts_at',
            'order'          => 'ASC',
            'meta_query'     => array(
                'relation' => 'AND',
                array( 'key' => '_wcai_product_id', 'value' => $product_ids, 'compare' => 'IN' ),
                array( 'key' => '_wcai_departure_status', 'value' => array( 'open', 'confirmed' ), 'compare' => 'IN' ),
                array( 'key' => '_wcai_starts_at', 'value' => current_time( 'Y-m-d\TH:i' ), 'compare' => '>=', 'type' => 'CHAR' ),
            ),
        ) );
    }

    public static function has_open_departures_for_product( $product_id, $variation_id = 0 ) {
        return ! empty( self::get_open_departures_for_product( $product_id, $variation_id ) );
    }

    public static function is_available_for_product( $departure_id, $product_id, $variation_id, $quantity ) {
        $departure_id = absint( $departure_id );
        $departure_product = absint( get_post_meta( $departure_id, '_wcai_product_id', true ) );
        if ( ! in_array( $departure_product, array( absint( $product_id ), absint( $variation_id ) ), true ) ) return false;

        $departure = get_post( $departure_id );
        return $departure && WCAI_Departures::POST_TYPE === $departure->post_type && 'publish' === $departure->post_status && in_array( get_post_meta( $departure_id, '_wcai_departure_status', true ), array( 'open', 'confirmed' ), true ) && self::is_booking_open( $departure_id ) && self::get_available_quantity( $departure_id ) >= absint( $quantity );
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

        if ( ! self::is_booking_open( $departure_id ) ) {
            return new WP_Error( 'wcai_booking_closed', 'O período de compra desta saída já foi encerrado.' );
        }

        $lock_name = 'wcai_departure_' . $departure_id;
        $locked = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, 5 ) );
        if ( 1 !== $locked ) {
            return new WP_Error( 'wcai_reservation_locked', 'Não foi possível reservar a vaga. Tente novamente.' );
        }

        try {
            if ( $order_item_id ) {
                $existing = self::get_by_order_item( $order_item_id );

                if ( $existing ) {
                    if ( absint( $existing->departure_id ) !== $departure_id || absint( $existing->quantity ) !== $quantity ) {
                        return new WP_Error( 'wcai_reservation_conflict', 'Este item do pedido já possui uma reserva incompatível.' );
                    }

                    if ( in_array( $existing->status, array( 'hold', 'pending', 'confirmed' ), true ) ) {
                        return absint( $existing->id );
                    }

                    return new WP_Error( 'wcai_reservation_closed', 'A reserva deste item já foi encerrada.' );
                }
            }

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
            do_action( 'wcai_reservation_changed', $departure_id );
            return $wpdb->insert_id;
        } finally {
            $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
        }
    }

    public static function update_status_for_order( $order_id, $status ) {
        global $wpdb;
        $status = sanitize_key( $status );
        if ( ! in_array( $status, array( 'pending', 'confirmed', 'cancelled', 'refunded' ), true ) ) return false;

        $updated = $wpdb->update(
            self::get_table_name(),
            array( 'status' => $status, 'expires_at' => null, 'updated_at' => current_time( 'mysql', true ) ),
            array( 'order_id' => absint( $order_id ) ),
            array( '%s', '%s', '%s' ),
            array( '%d' )
        );

        if ( false !== $updated ) {
            do_action( 'wcai_reservations_changed', absint( $order_id ) );
        }

        return $updated;
    }
}

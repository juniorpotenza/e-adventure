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
        add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedule' ) );
        add_action( 'wcai_expire_holds', array( __CLASS__, 'expire_holds' ) );
    }

    public static function add_cron_schedule( $schedules ) {
        $schedules['wcai_five_minutes'] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => 'A cada 5 minutos' );
        return $schedules;
    }

    // Pagamento confirmado: cada reserva vira 'confirmed' dentro do lock da saída, revalidando a vaga.
    public static function confirm_order( $order_id ) {
        global $wpdb;
        $order_id = absint( $order_id );
        $table = self::get_table_name();
        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE order_id = %d AND status <> 'confirmed'", $order_id ) );

        foreach ( $ids as $reservation_id ) {
            $result = self::confirm_reservation( absint( $reservation_id ) );

            if ( is_wp_error( $result ) ) {
                $order = wc_get_order( $order_id );
                if ( $order ) $order->add_order_note( 'Reserva #' . absint( $reservation_id ) . ' não confirmada: ' . $result->get_error_message() );
                WCAI_Audit_Log::log( 'reservation_confirm_refused', 'reservation', absint( $reservation_id ), array( 'order_id' => $order_id, 'reason' => $result->get_error_code() ) );
            }
        }

        do_action( 'wcai_reservations_changed', $order_id );
    }

    private static function confirm_reservation( $reservation_id ) {
        global $wpdb;
        $reservation = self::get_by_id( $reservation_id );
        if ( ! $reservation ) return new WP_Error( 'wcai_invalid_reservation', 'Reserva inválida.' );

        $departure_id = absint( $reservation->departure_id );
        if ( ! self::lock_departure( $departure_id ) ) {
            return new WP_Error( 'wcai_reservation_locked', 'Não foi possível confirmar a vaga. Tente novamente.' );
        }

        try {
            $reservation = self::get_by_id( $reservation_id ); // relê dentro do lock
            if ( ! $reservation ) return new WP_Error( 'wcai_invalid_reservation', 'Reserva inválida.' );
            if ( 'confirmed' === $reservation->status ) return true;

            // Só 'pending' e 'hold' não vencido já ocupam vaga; os demais precisam revalidar a capacidade.
            $counted = 'pending' === $reservation->status || ( 'hold' === $reservation->status && $reservation->expires_at > current_time( 'mysql', true ) );
            if ( ! $counted && absint( $reservation->quantity ) > self::get_available_quantity( $departure_id ) ) {
                return new WP_Error( 'wcai_capacity_exceeded', 'Não há vagas suficientes nesta saída.' );
            }

            $updated = $wpdb->update(
                self::get_table_name(),
                array( 'status' => 'confirmed', 'expires_at' => null, 'updated_at' => current_time( 'mysql', true ) ),
                array( 'id' => absint( $reservation_id ) ),
                array( '%s', '%s', '%s' ),
                array( '%d' )
            );
            if ( false === $updated ) return new WP_Error( 'wcai_reservation_failed', 'Não foi possível confirmar a reserva.' );

            do_action( 'wcai_reservation_changed', $departure_id );
            return true;
        } finally {
            self::unlock_departure( $departure_id );
        }
    }

    private static function lock_departure( $departure_id ) {
        global $wpdb;
        return 1 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', 'wcai_departure_' . absint( $departure_id ), 5 ) );
    }

    private static function unlock_departure( $departure_id ) {
        global $wpdb;
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'wcai_departure_' . absint( $departure_id ) ) );
    }

    // Cron 'wcai_expire_holds': 'hold' vencido vira 'expired' e deixa de contar vaga.
    public static function expire_holds() {
        global $wpdb;
        $table = self::get_table_name();
        $now = current_time( 'mysql', true );
        $departure_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT departure_id FROM $table WHERE status = 'hold' AND expires_at <= %s", $now ) );

        foreach ( $departure_ids as $departure_id ) {
            $departure_id = absint( $departure_id );
            if ( ! self::lock_departure( $departure_id ) ) continue; // próxima execução tenta de novo

            try {
                $wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'expired', updated_at = %s WHERE departure_id = %d AND status = 'hold' AND expires_at <= %s", $now, $departure_id, $now ) );
                do_action( 'wcai_reservation_changed', $departure_id );
            } finally {
                self::unlock_departure( $departure_id );
            }
        }
    }

    // Libera as reservas ainda não pagas de um pedido que falhou no checkout.
    public static function release_order( $order_id ) {
        global $wpdb;
        $order_id = absint( $order_id );
        $table = self::get_table_name();
        $wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'cancelled', expires_at = NULL, updated_at = %s WHERE order_id = %d AND status IN ('hold', 'pending')", current_time( 'mysql', true ), $order_id ) );
        do_action( 'wcai_reservations_changed', $order_id );
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

    public static function get_group_summary( $departure_id ) {
        $departure_id = absint( $departure_id );
        $capacity = absint( get_post_meta( $departure_id, '_wcai_capacity', true ) );
        $minimum = absint( get_post_meta( $departure_id, '_wcai_minimum_capacity', true ) );
        $reserved = self::get_reserved_quantity( $departure_id );
        $available = max( 0, $capacity - $reserved );
        $remaining_to_minimum = $minimum > 0 ? max( 0, $minimum - $reserved ) : 0;
        $formation_percent = $minimum > 0 ? min( 100, (int) round( ( $reserved / $minimum ) * 100 ) ) : 100;

        if ( ! $minimum ) {
            $status = 'open';
            $label = 'Reservas abertas';
            $detail = '';
        } elseif ( $reserved < $minimum ) {
            $status = 'forming';
            $label = 'Em formação';
            $detail = sprintf(
                'Faltam %d participante%s para atingir o mínimo de %d.',
                $remaining_to_minimum,
                1 === $remaining_to_minimum ? '' : 's',
                $minimum
            );
        } else {
            $status = 'minimum_met';
            $label = 'Mínimo atingido';
            $detail = sprintf(
                'O mínimo de %d participante%s já foi atingido.',
                $minimum,
                1 === $minimum ? '' : 's'
            );
        }

        return array(
            'capacity'            => $capacity,
            'minimum'             => $minimum,
            'reserved'             => $reserved,
            'available'           => $available,
            'remaining_to_minimum'=> $remaining_to_minimum,
            'formation_percent'   => $formation_percent,
            'status'              => $status,
            'label'               => $label,
            'detail'              => $detail,
        );
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

        if ( empty( $product_ids ) ) {
            return array();
        }

        $departures = get_posts(
            array(
                'post_type' => WCAI_Departures::POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'meta_value',
                'meta_key' => '_wcai_starts_at',
                'order' => 'ASC',
                'meta_query' => array(
                    'relation' => 'AND',
                    array( 'key' => '_wcai_product_id', 'value' => $product_ids, 'compare' => 'IN' ),
                    array( 'key' => '_wcai_departure_status', 'value' => array( 'open', 'confirmed' ), 'compare' => 'IN' ),
                ),
            )
        );

        $now = current_time( 'timestamp' );

        return array_values(
            array_filter(
                $departures,
                static function( $departure ) use ( $now ) {
                    $start = WCAI_Data_Resolver::get_departure_start( $departure->ID );
                    $timestamp = WCAI_Data_Resolver::parse_timestamp( $start );

                    return $timestamp && $timestamp >= $now && self::is_booking_open( $departure->ID );
                }
            )
        );
    }

    public static function get_calendar_departures_for_product( $product_id, $variation_id = 0 ) {
        $product_ids = array_filter( array_unique( array( absint( $product_id ), absint( $variation_id ) ) ) );

        if ( empty( $product_ids ) ) {
            return array();
        }

        $departures = get_posts(
            array(
                'post_type'      => WCAI_Departures::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'meta_value',
                'meta_key'       => '_wcai_starts_at',
                'order'          => 'ASC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_wcai_product_id',
                        'value'   => $product_ids,
                        'compare' => 'IN',
                    ),
                    array(
                        'key'     => '_wcai_departure_status',
                        'value'   => array( 'open', 'confirmed', 'full' ),
                        'compare' => 'IN',
                    ),
                ),
            )
        );

        $now = current_time( 'timestamp' );
        $limit = $now + ( 370 * DAY_IN_SECONDS );

        return array_values(
            array_filter(
                $departures,
                static function( $departure ) use ( $now, $limit ) {
                    $start = WCAI_Data_Resolver::get_departure_start( $departure->ID );
                    $timestamp = WCAI_Data_Resolver::parse_timestamp( $start );

                    return $timestamp && $timestamp >= $now && $timestamp <= $limit;
                }
            )
        );
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
            $table = self::get_table_name();
            $now = current_time( 'mysql', true );
            $expires_at = 'hold' === $status ? gmdate( 'Y-m-d H:i:s', time() + ( self::HOLD_MINUTES * MINUTE_IN_SECONDS ) ) : null;

            if ( $order_item_id ) {
                $existing = self::get_by_order_item( $order_item_id );

                if ( $existing ) {
                    if ( absint( $existing->departure_id ) !== $departure_id || absint( $existing->quantity ) !== $quantity ) {
                        return new WP_Error( 'wcai_reservation_conflict', 'Este item do pedido já possui uma reserva incompatível.' );
                    }

                    $is_live_hold = 'hold' === $existing->status && $existing->expires_at > $now;

                    if ( in_array( $existing->status, array( 'pending', 'confirmed' ), true ) || $is_live_hold ) {
                        return absint( $existing->id );
                    }

                    // Hold vencido (ou já 'expired') no reenvio do checkout: revalida a vaga e reativa.
                    if ( in_array( $existing->status, array( 'hold', 'expired' ), true ) ) {
                        if ( $quantity > self::get_available_quantity( $departure_id ) ) {
                            return new WP_Error( 'wcai_capacity_exceeded', 'Não há vagas suficientes nesta saída.' );
                        }

                        $revived = $wpdb->update(
                            $table,
                            array( 'status' => $status, 'expires_at' => $expires_at, 'updated_at' => $now ),
                            array( 'id' => absint( $existing->id ) ),
                            array( '%s', '%s', '%s' ),
                            array( '%d' )
                        );
                        if ( false === $revived ) {
                            return new WP_Error( 'wcai_reservation_failed', 'Não foi possível registrar a reserva.' );
                        }

                        do_action( 'wcai_reservation_changed', $departure_id );
                        return absint( $existing->id );
                    }

                    return new WP_Error( 'wcai_reservation_closed', 'A reserva deste item já foi encerrada.' );
                }
            }

            if ( $quantity > self::get_available_quantity( $departure_id ) ) {
                return new WP_Error( 'wcai_capacity_exceeded', 'Não há vagas suficientes nesta saída.' );
            }

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

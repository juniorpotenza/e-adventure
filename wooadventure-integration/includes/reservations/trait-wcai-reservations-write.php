<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Reservations_Write {

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
                        if ( $is_live_hold && 'hold' === $status ) {
                            $wpdb->update( $table, array( 'expires_at' => $expires_at, 'updated_at' => $now ), array( 'id' => absint( $existing->id ) ), array( '%s', '%s' ), array( '%d' ) ); // renova o hold reenviado
                        }
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

            $reservation_id = (int) $wpdb->insert_id; // antes do log: o log faz seu próprio insert e troca insert_id
            WCAI_Audit_Log::log( 'reservation_created', 'reservation', $reservation_id, array( 'departure_id' => $departure_id, 'quantity' => $quantity, 'status' => $status ) );
            do_action( 'wcai_reservation_changed', $departure_id );
            return $reservation_id;
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

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Reservations_Lifecycle {

    // Pagamento confirmado: cada reserva vira 'confirmed' dentro do lock da saída, revalidando a vaga.
    // $attempt > 0 só vem do evento 'wcai_retry_confirm' (o hook de status passa 1 argumento).
    public static function confirm_order( $order_id, $attempt = 0 ) {
        global $wpdb;
        $order_id = absint( $order_id );
        $attempt = absint( $attempt );
        $table = self::get_table_name();
        $order = wc_get_order( $order_id );

        // Retry de um pedido que deixou de estar pago (ex.: cancelado depois): não confirma.
        if ( $attempt && ( ! $order || ! $order->has_status( array( 'processing', 'completed' ) ) ) ) return;

        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, order_item_id FROM $table WHERE order_id = %d AND status <> 'confirmed'", $order_id ) );
        // shortcut: sem o pedido carregado, não filtra itens órfãos; só ocorre com pedido inexistente.
        $item_ids = $order ? array_map( 'absint', array_keys( $order->get_items() ) ) : null;
        $retry = false;

        foreach ( $rows as $row ) {
            $reservation_id = absint( $row->id );
            // Item recriado/removido no pedido: a reserva órfã não pode ser confirmada.
            if ( null !== $item_ids && $row->order_item_id && ! in_array( absint( $row->order_item_id ), $item_ids, true ) ) continue;

            $result = self::confirm_reservation( $reservation_id );

            if ( is_wp_error( $result ) ) {
                if ( $order ) $order->add_order_note( 'Reserva #' . $reservation_id . ' não confirmada: ' . $result->get_error_message() );
                WCAI_Audit_Log::log( 'reservation_confirm_refused', 'reservation', $reservation_id, array( 'order_id' => $order_id, 'reason' => $result->get_error_code() ) );

                if ( 'wcai_reservation_locked' === $result->get_error_code() ) {
                    $retry = true;
                } elseif ( 'wcai_capacity_exceeded' === $result->get_error_code() && $order ) {
                    $order->update_status( 'on-hold', 'Pedido pago sem vaga disponível: revisar manualmente.' );
                    do_action( 'wcai_paid_without_seat', $order_id, $reservation_id );
                }
            }
        }

        if ( $retry && $attempt < self::MAX_CONFIRM_RETRIES ) {
            wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'wcai_retry_confirm', array( $order_id, $attempt + 1 ) );
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
    // Hold de pedido já pago (processing/completed/on-hold) não expira: a confirmação está pendente.
    public static function expire_holds() {
        global $wpdb;
        $table = self::get_table_name();
        $now = current_time( 'mysql', true );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, departure_id, order_id FROM $table WHERE status = 'hold' AND expires_at <= %s", $now ) );
        $ids_by_departure = array();

        foreach ( $rows as $row ) {
            $order = $row->order_id ? wc_get_order( absint( $row->order_id ) ) : false;
            if ( $order && $order->has_status( array( 'processing', 'completed', 'on-hold' ) ) ) continue;
            $ids_by_departure[ absint( $row->departure_id ) ][] = absint( $row->id );
        }

        foreach ( $ids_by_departure as $departure_id => $ids ) {
            if ( ! self::lock_departure( $departure_id ) ) continue; // próxima execução tenta de novo

            try {
                $placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
                $wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'expired', updated_at = %s WHERE id IN ($placeholders) AND status = 'hold' AND expires_at <= %s", array_merge( array( $now ), $ids, array( $now ) ) ) );
                do_action( 'wcai_reservation_changed', $departure_id );
            } finally {
                self::unlock_departure( $departure_id );
            }
        }
    }

    // Libera as reservas ainda não pagas de um pedido que falhou no checkout.
    // 'expired' (não 'cancelled'): o reenvio do mesmo item revive a reserva em create().
    public static function release_order( $order_id ) {
        global $wpdb;
        $order_id = absint( $order_id );
        $table = self::get_table_name();
        $wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'expired', expires_at = NULL, updated_at = %s WHERE order_id = %d AND status IN ('hold', 'pending')", current_time( 'mysql', true ), $order_id ) );
        do_action( 'wcai_reservations_changed', $order_id );
    }

    // Itens do pedido recriados com novos ids: hold/pending dos ids antigos viram 'expired'.
    public static function expire_orphans( $order_id, $item_ids ) {
        global $wpdb;
        $order_id = absint( $order_id );
        $item_ids = array_values( array_filter( array_map( 'absint', (array) $item_ids ) ) );
        if ( ! $order_id || ! $item_ids ) return;

        $table = self::get_table_name();
        $placeholders = implode( ',', array_fill( 0, count( $item_ids ), '%d' ) );
        $wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'expired', expires_at = NULL, updated_at = %s WHERE order_id = %d AND status IN ('hold', 'pending') AND order_item_id NOT IN ($placeholders)", array_merge( array( current_time( 'mysql', true ), $order_id ), $item_ids ) ) );
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
}

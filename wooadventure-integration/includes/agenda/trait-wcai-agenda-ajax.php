<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Agenda_Ajax {

    public function ajax_get_events() {
        check_ajax_referer( 'wcai_calendar_nonce', 'nonce' );
        if ( ! current_user_can( WCAI_Capabilities::VIEW_MANIFEST ) ) {
            wp_send_json_error( array( 'message' => 'Acesso negado.' ), 403 );
        }

        $start = isset( $_POST['start'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['start'] ) ), 0, 10 ) : wp_date( 'Y-m-01' );
        $end   = isset( $_POST['end'] ) ? substr( sanitize_text_field( wp_unslash( $_POST['end'] ) ), 0, 10 ) : wp_date( 'Y-m-t' );
        $cache_key = 'wcai_events_' . md5( $start . '|' . $end );
        $cached = get_transient( $cache_key );

        if ( false !== $cached ) {
            wp_send_json( $cached );
        }

        $events = array();
        $departures = $this->get_canonical_departures_in_range( $start, $end );

        foreach ( $departures as $departure ) {
            $departure_id = absint( $departure->ID );
            $starts_at = get_post_meta( $departure_id, '_wcai_starts_at', true );
            if ( empty( $starts_at ) ) {
                continue;
            }

            $status = get_post_meta( $departure_id, '_wcai_departure_status', true ) ?: 'draft';
            $capacity = absint( get_post_meta( $departure_id, '_wcai_capacity', true ) );
            $reserved = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_reserved_quantity( $departure_id ) : 0;
            $available = max( 0, $capacity - $reserved );
            $title = get_the_title( $departure_id ) ?: 'Saída #' . $departure_id;

            $status_labels = array(
                'open' => 'Aberta',
                'full' => 'Lotada',
                'confirmed' => 'Confirmada',
                'cancelled' => 'Cancelada',
                'completed' => 'Concluída',
            );
            $status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : ucfirst( $status );
            $event_title = $title . ' — ' . $reserved . '/' . $capacity . ' pax';

            if ( 'cancelled' === $status ) {
                $event_title .= ' · CANCELADA';
            }

            $background = 'open' === $status ? '#3788d8' : '#6c757d';
            if ( 'confirmed' === $status ) {
                $background = '#155724';
            } elseif ( 'full' === $status ) {
                $background = '#d69e2e';
            } elseif ( 'cancelled' === $status ) {
                $background = '#dc3545';
            } elseif ( 'completed' === $status ) {
                $background = '#6c757d';
            }

            $events[] = array(
                'id' => (string) $departure_id,
                'title' => $event_title,
                'start' => $starts_at,
                'allDay' => false,
                'backgroundColor' => $background,
                'borderColor' => $background,
                'extendedProps' => array(
                    'departure_id' => $departure_id,
                    'status' => $status,
                    'status_label' => $status_label,
                    'capacity' => $capacity,
                    'reserved' => $reserved,
                    'available' => $available,
                ),
            );
        }

        usort( $events, static function( $a, $b ) {
            return strcmp( $a['start'], $b['start'] );
        } );

        set_transient( $cache_key, $events, 15 * MINUTE_IN_SECONDS );
        wp_send_json( $events );
    }

    public function ajax_get_day_details() {
        check_ajax_referer( 'wcai_calendar_nonce', 'nonce' );
        if ( ! current_user_can( WCAI_Capabilities::VIEW_MANIFEST ) ) {
            wp_send_json_error( array( 'message' => 'Acesso negado.' ), 403 );
        }

        $departure_id = isset( $_POST['departure_id'] ) ? absint( $_POST['departure_id'] ) : 0;
        $departure = $departure_id ? get_post( $departure_id ) : false;

        if ( ! $departure || WCAI_Departures::POST_TYPE !== $departure->post_type || 'publish' !== $departure->post_status ) {
            wp_send_json_error( array( 'message' => 'Saída não encontrada.' ), 404 );
        }

        $starts_at = get_post_meta( $departure_id, '_wcai_starts_at', true );
        $status = get_post_meta( $departure_id, '_wcai_departure_status', true ) ?: 'draft';
        $capacity = absint( get_post_meta( $departure_id, '_wcai_capacity', true ) );
        $reserved = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_reserved_quantity( $departure_id ) : 0;
        $available = max( 0, $capacity - $reserved );

        global $wpdb;
        $participants_table = WCAI_Participants_DB::get_table_name();
        $reservations_table = WCAI_Reservations::get_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.*, r.status AS reservation_status
                 FROM $participants_table p
                 INNER JOIN $reservations_table r ON r.id = p.reservation_id
                 WHERE r.departure_id = %d
                   AND r.status = 'confirmed'
                 ORDER BY p.nome_completo ASC, p.id ASC",
                $departure_id
            ),
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            wp_send_json_success(
                array(
                    'html' => '<p>Nenhum participante vinculado a esta saída.</p>',
                )
            );
        }

        $can_view_sensitive = current_user_can( WCAI_Capabilities::VIEW_SENSITIVE );
        $status_labels = array(
            'pending' => __( 'Pendente', 'wooadventure-integration' ),
            'confirmed' => __( 'Confirmada', 'wooadventure-integration' ),
            'hold' => __( 'Em espera', 'wooadventure-integration' ),
            'expired' => __( 'Expirada', 'wooadventure-integration' ),
        );

        ob_start();
        ?>
        <div style="margin-bottom:15px;background:#eef2f7;padding:10px;border-radius:8px;">
            <strong><?php echo esc_html( get_the_title( $departure_id ) ?: 'Saída #' . $departure_id ); ?></strong><br>
            <span><?php echo esc_html( $starts_at ); ?></span> ·
            <span><?php echo esc_html( $reserved . '/' . $capacity . ' pax' ); ?></span> ·
            <span><?php echo esc_html( $available . ' vagas disponíveis' ); ?></span> ·
            <span><?php echo esc_html( ucfirst( $status ) ); ?></span>
        </div>
        <table class="wcai-table">
            <thead>
                <tr>
                    <th style="width:30px">T.</th>
                    <th>Pedido</th>
                    <th>Participante</th>
                    <?php if ( $can_view_sensitive ) : ?><th>CPF</th><th>Nascimento</th><?php endif; ?>
                    <th>Reserva</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $rows as $row ) :
                $order_number = $row['order_id'] ? wc_get_order( $row['order_id'] ) : false;
                $order_label = $order_number ? $order_number->get_order_number() : $row['order_id'];
                $reservation_label = isset( $status_labels[ $row['reservation_status'] ] ) ? $status_labels[ $row['reservation_status'] ] : $row['reservation_status'];
                $nasc = $row['data_nascimento'];
                if ( $can_view_sensitive && ! empty( $nasc ) && '0000-00-00' !== $nasc ) {
                    $date = DateTime::createFromFormat( 'Y-m-d', $nasc );
                    if ( $date ) {
                        $nasc = $date->format( 'd/m/Y' );
                    }
                }
                $signed = ! empty( $row['termo_assinado'] );
            ?>
                <tr>
                    <td style="text-align:center;"><?php echo $signed ? '<span title="Assinado">✅</span>' : '<span title="Pendente" style="opacity:0.3">⚠️</span>'; ?></td>
                    <td>
                        <?php if ( $row['order_id'] ) : ?>
                            <a href="<?php echo esc_url( get_edit_post_link( $row['order_id'] ) ); ?>" target="_blank">#<?php echo esc_html( $order_label ); ?></a>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?php echo esc_html( $row['nome_completo'] ); ?></td>
                    <?php if ( $can_view_sensitive ) : ?>
                        <td><?php echo esc_html( $row['cpf'] ); ?></td>
                        <td><?php echo esc_html( $nasc ); ?></td>
                    <?php endif; ?>
                    <td><?php echo esc_html( $reservation_label ); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div style="margin-top:15px;text-align:right"><button class="button button-primary" onclick="window.print()">🖨️ Imprimir</button></div>
        <?php
        wp_send_json_success( array( 'html' => ob_get_clean() ) );
    }

}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Scanner_Data {

    public function ajax_sync_data() {
        check_ajax_referer( 'wcai_scanner_nonce', 'nonce' );

        if ( ! current_user_can( WCAI_Capabilities::CHECK_IN ) ) {
            wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
        }

        global $wpdb;
        $table = WCAI_Participants_DB::get_table_name();
        $queue = isset( $_POST['queue'] ) && is_array( $_POST['queue'] ) ? wp_unslash( $_POST['queue'] ) : array();

        if ( ! empty( $queue ) ) {
            foreach ( $queue as $item ) {
                if ( ! is_array( $item ) ) {
                    continue;
                }

                $mode = isset( $item['mode'] ) ? sanitize_key( $item['mode'] ) : '';
                $pax_id = isset( $item['id'] ) ? absint( $item['id'] ) : 0;

                if ( ! $pax_id || ! in_array( $mode, array( 'in', 'out' ), true ) ) {
                    continue;
                }

                if ( ! $this->participant_is_in_active_manifest( $pax_id ) ) {
                    continue;
                }

                $now = current_time( 'mysql' );

                if ( 'in' === $mode ) {
                    $wpdb->query( $wpdb->prepare(
                        "UPDATE $table SET checkin_status=1, checkin_time=%s WHERE id=%d AND checkin_status=0",
                        $now,
                        $pax_id
                    ) );
                } else {
                    $wpdb->query( $wpdb->prepare(
                        "UPDATE $table SET checkin_status=2, checkout_time=%s WHERE id=%d AND checkin_status=1",
                        $now,
                        $pax_id
                    ) );
                }
            }
        }

        $date_query = isset( $_POST['date_query'] ) ? sanitize_text_field( wp_unslash( $_POST['date_query'] ) ) : '';

        if ( $date_query ) {
            if ( ! current_user_can( WCAI_Capabilities::VIEW_MANIFEST ) || ! self::is_date_allowed( $date_query, current_time( 'Y-m-d' ) ) ) {
                wp_send_json_error( array( 'message' => 'Forbidden' ), 403 );
            }

            wp_send_json_success( array( 'specific_date' => $this->get_pax_by_date( $date_query ) ) );
        }

        $today = current_time( 'Y-m-d' );
        $tomorrow = wp_date( 'Y-m-d', current_datetime()->modify( '+1 day' ) );

        wp_send_json_success( array(
            'full_manifest' => array_merge( $this->get_pax_by_date( $today ), $this->get_pax_by_date( $tomorrow ) ),
        ) );
    }

    private function participant_is_in_active_manifest( $participant_id ) {
        global $wpdb;
        $participants = WCAI_Participants_DB::get_table_name();
        $reservations = WCAI_Reservations::get_table_name();
        $today = current_time( 'Y-m-d' );
        $after_tomorrow = wp_date( 'Y-m-d', current_datetime()->modify( '+2 days' ) );

        $guide_join = $this->guide_scope_join();

        $sql = "SELECT COUNT(*)
                FROM $participants p
                INNER JOIN $reservations r ON r.id = p.reservation_id
                INNER JOIN {$wpdb->posts} d ON d.ID = r.departure_id
                INNER JOIN {$wpdb->postmeta} dm ON dm.post_id = d.ID AND dm.meta_key = '_wcai_starts_at'
                {$guide_join}
                WHERE p.id = %d
                  AND r.status = 'confirmed'
                  AND d.post_type = %s
                  AND d.post_status = 'publish'
                  AND dm.meta_value >= %s
                  AND dm.meta_value < %s";

        return (bool) $wpdb->get_var( $wpdb->prepare(
            $sql,
            absint( $participant_id ),
            WCAI_Departures::POST_TYPE,
            $today . 'T00:00',
            $after_tomorrow . 'T00:00'
        ) );
    }

    /**
     * Data permitida na consulta por data: hoje +/- 1 dia (YYYY-MM-DD).
     */
    public static function is_date_allowed( $date, $today ) {
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $date ) ) {
            return false;
        }

        $d = DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
        $t = DateTimeImmutable::createFromFormat( '!Y-m-d', (string) $today );

        if ( ! $d || ! $t || $d->format( 'Y-m-d' ) !== $date ) {
            return false;
        }

        return $t->diff( $d )->days <= 1;
    }

    private function get_operation_name() {
        $name = sanitize_text_field( (string) get_option( 'wcai_operation_name', 'Operação' ) );

        return '' !== $name ? $name : 'Operação';
    }

    // Quem não gerencia saídas só enxerga as saídas em que é o guia (_wcai_guide_id).
    private function guide_scope_join() {
        global $wpdb;

        if ( current_user_can( WCAI_Capabilities::MANAGE_DEPARTURES ) ) {
            return '';
        }

        return $wpdb->prepare(
            "INNER JOIN {$wpdb->postmeta} gm ON gm.post_id = d.ID AND gm.meta_key = '_wcai_guide_id' AND gm.meta_value = %s",
            (string) get_current_user_id()
        );
    }

    private function get_pax_by_date( $date ) {
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
            return array();
        }

        global $wpdb;
        $participants = WCAI_Participants_DB::get_table_name();
        $reservations = WCAI_Reservations::get_table_name();
        $guide_join = $this->guide_scope_join();

        $sql = "SELECT
                    p.id,
                    p.ticket_hash AS hash,
                    p.nome_completo AS nome,
                    p.order_id AS pedido,
                    p.reservation_id,
                    p.checkin_status AS status,
                    p.checkin_time,
                    p.checkout_time,
                    r.departure_id,
                    r.status AS reservation_status,
                    dm.meta_value AS starts_at
                FROM $participants p
                INNER JOIN $reservations r ON r.id = p.reservation_id
                INNER JOIN {$wpdb->posts} d ON d.ID = r.departure_id
                INNER JOIN {$wpdb->postmeta} dm ON dm.post_id = d.ID AND dm.meta_key = '_wcai_starts_at'
                {$guide_join}
                WHERE r.status = 'confirmed'
                  AND d.post_type = %s
                  AND d.post_status = 'publish'
                  AND LEFT(dm.meta_value, 10) = %s
                ORDER BY dm.meta_value ASC, p.nome_completo ASC, p.id ASC";

        $rows = $wpdb->get_results(
            $wpdb->prepare( $sql, WCAI_Departures::POST_TYPE, $date )
        );

        foreach ( $rows as $p ) {
            $p->tour_time = strlen( $p->starts_at ) >= 16 ? substr( $p->starts_at, 11, 5 ) : '';
            $p->data_agendada = wp_date( 'd/m', strtotime( $date . ' 12:00:00' ) ) . ' ' . $p->tour_time;
            $p->entry_time = $p->checkin_time ? wp_date( 'H:i', strtotime( $p->checkin_time ) ) : '';
        }

        return $rows;
    }
    public function ajax_get_calendar_data() {
        check_ajax_referer( 'wcai_scanner_nonce', 'nonce' );

        if ( ! current_user_can( WCAI_Capabilities::VIEW_MANIFEST ) ) {
            wp_send_json_error( array(), 403 );
        }

        global $wpdb;
        $month = max( 1, min( 12, absint( $_POST['month'] ?? 0 ) ) );
        $year = absint( $_POST['year'] ?? 0 );

        if ( ! $year ) {
            $year = absint( wp_date( 'Y' ) );
        }

        $month_start = sprintf( '%04d-%02d-01', $year, $month );
        $month_end = wp_date( 'Y-m-t', strtotime( $month_start ) );

        $sql = "SELECT
                    LEFT(dm.meta_value, 10) AS agenda_date,
                    COUNT(DISTINCT d.ID) AS departure_count
                FROM {$wpdb->posts} d
                INNER JOIN {$wpdb->postmeta} dm ON dm.post_id = d.ID AND dm.meta_key = '_wcai_starts_at'
                {$this->guide_scope_join()}
                WHERE d.post_type = %s
                  AND d.post_status = 'publish'
                  AND dm.meta_value BETWEEN %s AND %s
                  AND EXISTS (
                      SELECT 1
                      FROM {$wpdb->postmeta} sm
                      WHERE sm.post_id = d.ID
                        AND sm.meta_key = '_wcai_departure_status'
                        AND sm.meta_value NOT IN ('draft', 'cancelled')
                  )
                GROUP BY LEFT(dm.meta_value, 10)
                ORDER BY agenda_date ASC";

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                $sql,
                WCAI_Departures::POST_TYPE,
                $month_start . 'T00:00',
                $month_end . 'T23:59'
            )
        );

        $counts = array();
        foreach ( $rows as $row ) {
            $counts[ $row->agenda_date ] = max( 1, absint( $row->departure_count ) );
        }

        wp_send_json_success( $counts );
    }

}

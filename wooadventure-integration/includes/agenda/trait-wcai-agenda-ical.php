<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Agenda_Ical {

    // =========================================================================
    // PARTE A: ICAL FEED (ORIGINAL AGRUPADO - MANTIDO)
    // =========================================================================

    public function handle_ical_feed() {
        if ( isset( $_GET['wcai_action'] ) && 'ical' === sanitize_key( wp_unslash( $_GET['wcai_action'] ) ) ) {
            $stored_key = trim( (string) get_option( 'wcai_ical_secret_key' ) );
            $request_key = isset( $_GET['key'] ) ? trim( (string) wp_unslash( $_GET['key'] ) ) : '';

            if ( empty( $stored_key ) || empty( $request_key ) || ! hash_equals( $stored_key, $request_key ) ) {
                wp_die( 'Acesso Negado (Chave Inválida)', '403', 403 );
            }

            @set_time_limit( 0 );
            while ( ob_get_level() ) {
                ob_end_clean();
            }

            $debug = isset( $_GET['debug'] );
            $eol   = "\r\n";

            if ( $debug ) {
                header( 'Content-Type: text/html; charset=utf-8' );
                echo '<h1>Agenda iCal operacional</h1><pre>';
            } else {
                header( 'Content-Type: text/calendar; charset=utf-8' );
                header( 'Content-Disposition: attachment; filename="agenda_operacional.ics"' );
                header( 'Cache-Control: private, no-store, max-age=0' );
                echo 'BEGIN:VCALENDAR' . $eol;
                echo 'VERSION:2.0' . $eol;
                echo 'PRODID:-//WooAdventure//Operational//PT' . $eol;
                echo 'CALSCALE:GREGORIAN' . $eol;
                echo 'METHOD:PUBLISH' . $eol;
                echo 'X-WR-CALNAME:Agenda Operacional' . $eol;
            }

            $start_req  = wp_date( 'Y-m-d', strtotime( '-2 months' ) );
            $end_req    = wp_date( 'Y-m-d', strtotime( '+18 months' ) );
            $departures = $this->get_canonical_departures_in_range( $start_req, $end_req );
            $timezone   = wp_timezone();
            $utc        = new DateTimeZone( 'UTC' );
            $count      = 0;

            foreach ( $departures as $departure ) {
                $starts_at = get_post_meta( $departure->ID, '_wcai_starts_at', true );
                if ( empty( $starts_at ) ) {
                    continue;
                }

                try {
                    $dt_start = new DateTime( $starts_at, $timezone );
                } catch ( Exception $e ) {
                    continue;
                }

                $duration = absint( get_post_meta( $departure->ID, '_wcai_duration_minutes', true ) );
                $duration = $duration > 0 ? $duration : 180;
                $dt_end   = clone $dt_start;
                $dt_end->modify( '+' . $duration . ' minutes' );

                $reserved = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_reserved_quantity( $departure->ID ) : 0;
                $capacity = absint( get_post_meta( $departure->ID, '_wcai_capacity', true ) );
                $available = max( 0, $capacity - $reserved );
                $status = get_post_meta( $departure->ID, '_wcai_departure_status', true ) ?: 'draft';
                $meeting = get_post_meta( $departure->ID, '_wcai_meeting_point', true );
                $guide_id = absint( get_post_meta( $departure->ID, '_wcai_guide_id', true ) );
                $guide = $guide_id ? get_the_author_meta( 'display_name', $guide_id ) : '';

                $title = get_the_title( $departure->ID ) ?: 'Saída #' . $departure->ID;
                $summary = $title . ' — ' . $reserved . ' pax / ' . $capacity . ' vagas';
                $description = 'Status: ' . $status . "\n" .
                    'Reservados: ' . $reserved . "\n" .
                    'Disponíveis: ' . $available;

                if ( $meeting ) {
                    $description .= "\nPonto de encontro: " . $meeting;
                }
                if ( $guide ) {
                    $description .= "\nGuia: " . $guide;
                }

                $dt_start->setTimezone( $utc );
                $dt_end->setTimezone( $utc );
                $uid = 'departure-' . absint( $departure->ID ) . '@' . wp_parse_url( home_url(), PHP_URL_HOST );

                if ( $debug ) {
                    echo esc_html( $starts_at . ' — ' . $summary ) . "\n";
                } else {
                    echo 'BEGIN:VEVENT' . $eol;
                    echo 'UID:' . $this->ical_escape( $uid ) . $eol;
                    echo 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ) . $eol;
                    echo 'DTSTART:' . $dt_start->format( 'Ymd\THis\Z' ) . $eol;
                    echo 'DTEND:' . $dt_end->format( 'Ymd\THis\Z' ) . $eol;
                    echo 'SUMMARY:' . $this->ical_escape( $summary ) . $eol;
                    echo 'DESCRIPTION:' . $this->ical_escape( $description ) . $eol;
                    echo 'END:VEVENT' . $eol;
                }

                $count++;
            }

            if ( $debug ) {
                echo "\nTotal de saídas: " . absint( $count ) . '</pre>';
                exit;
            }

            if ( 0 === $count ) {
                echo 'BEGIN:VEVENT' . $eol;
                echo 'UID:empty' . $eol;
                echo 'DTSTAMP:' . gmdate( 'Ymd\THis\Z' ) . $eol;
                echo 'DTSTART:' . gmdate( 'Ymd\THis\Z' ) . $eol;
                echo 'SUMMARY:Sem saídas cadastradas' . $eol;
                echo 'END:VEVENT' . $eol;
            }

            echo 'END:VCALENDAR';
            exit;
        }
    }

    private function get_canonical_departures_in_range( $start, $end ) {
        $start = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $start ) ? $start : wp_date( 'Y-m-01' );
        $end   = preg_match( '/^\d{4}-\d{2}-\d{2}$/', $end ) ? $end : wp_date( 'Y-m-t' );

        $departures = get_posts( array(
            'post_type'      => WCAI_Departures::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'meta_key'       => '_wcai_starts_at',
            'order'          => 'ASC',
            'meta_query'     => array(
                array(
                    'key'     => '_wcai_starts_at',
                    'value'   => array( $start . 'T00:00', $end . 'T23:59' ),
                    'compare' => 'BETWEEN',
                    'type'    => 'CHAR',
                ),
            ),
        ) );

        return array_values( array_filter( $departures, static function( $departure ) {
            $status = get_post_meta( $departure->ID, '_wcai_departure_status', true );
            return 'draft' !== $status;
        } ) );
    }

    private function ical_escape( $value ) {
        $value = str_replace( "\\", "\\\\", (string) $value );
        $value = str_replace( array( "\r\n", "\r", "\n" ), "\\n", $value );
        return str_replace( array( ';', ',' ), array( "\\;", "\\," ), $value );
    }

}

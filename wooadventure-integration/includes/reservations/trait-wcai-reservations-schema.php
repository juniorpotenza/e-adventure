<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Reservations_Schema {

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
}

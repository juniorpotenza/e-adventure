<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Participants_DB {

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wcai_participantes';
    }

    public static function create_table() {
        global $wpdb;
        $table_name = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id bigint(20) NOT NULL,
            item_id bigint(20) DEFAULT 0,
            reservation_id bigint(20) unsigned DEFAULT NULL,
            customer_id bigint(20) DEFAULT 0,
            nome_completo varchar(255) NOT NULL,
            cpf varchar(20) NOT NULL,
            data_nascimento date NOT NULL,
            ticket_hash varchar(64) DEFAULT NULL,
            checkin_status tinyint(1) DEFAULT 0,
            checkin_time datetime DEFAULT NULL,
            checkout_time datetime DEFAULT NULL,
            termo_assinado tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY order_id (order_id),
            KEY item_id (item_id),
            KEY reservation_id (reservation_id),
            KEY cpf (cpf),
            KEY ticket_hash (ticket_hash)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    private static function prepare_date( $date ) {
        if ( empty( $date ) ) return '0000-00-00';

        if ( strpos( $date, '-' ) !== false ) {
            return $date;
        }

        if ( strpos( $date, '/' ) !== false && class_exists( 'WCAI_Utils' ) ) {
            return WCAI_Utils::convert_date_to_db( $date );
        }

        return $date;
    }

    public static function add( $data ) {
        global $wpdb;

        $defaults = array(
            'order_id'      => 0,
            'item_id'       => 0,
            'reservation_id'=> 0,
            'customer_id'   => get_current_user_id(),
            'nome_completo' => '',
            'cpf'           => '',
            'data_nascimento' => '',
        );

        $data = wp_parse_args( $data, $defaults );

        $data['cpf'] = preg_replace( '/[^0-9]/', '', $data['cpf'] );
        $data['data_nascimento'] = self::prepare_date( $data['data_nascimento'] );
        $data['reservation_id'] = absint( $data['reservation_id'] );

        if ( $data['reservation_id'] && $data['cpf'] ) {
            $existing = $wpdb->get_var(
                $wpdb->prepare(
                    'SELECT id FROM ' . self::get_table_name() . ' WHERE reservation_id = %d AND cpf = %s LIMIT 1',
                    $data['reservation_id'],
                    $data['cpf']
                )
            );

            if ( $existing ) {
                return absint( $existing );
            }
        }

        $inserted = $wpdb->insert( self::get_table_name(), $data );

        return false === $inserted ? false : absint( $wpdb->insert_id );
    }

    public static function update( $id, $data ) {
        global $wpdb;

        if ( isset( $data['cpf'] ) ) {
            $data['cpf'] = preg_replace( '/[^0-9]/', '', $data['cpf'] );
        }

        if ( isset( $data['data_nascimento'] ) ) {
            $data['data_nascimento'] = self::prepare_date( $data['data_nascimento'] );
        }

        $format = array();
        foreach ( $data as $key => $value ) {
            $format[] = is_numeric( $value ) ? '%d' : '%s';
        }

        return $wpdb->update(
            self::get_table_name(),
            $data,
            array( 'id' => absint( $id ) ),
            $format,
            array( '%d' )
        );
    }

    public static function get_by_id( $participant_id ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::get_table_name() . ' WHERE id = %d',
                absint( $participant_id )
            ),
            ARRAY_A
        );
    }

    public static function get_by_reservation( $reservation_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table WHERE reservation_id = %d ORDER BY id ASC",
                absint( $reservation_id )
            ),
            ARRAY_A
        );
    }

    public static function get_by_reservation_and_cpf( $reservation_id, $cpf ) {
        global $wpdb;

        $cpf = preg_replace( '/\D/', '', (string) $cpf );

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::get_table_name() . ' WHERE reservation_id = %d AND REPLACE(REPLACE(cpf, ".", ""), "-", "") = %s ORDER BY id ASC LIMIT 1',
                absint( $reservation_id ),
                $cpf
            ),
            ARRAY_A
        );
    }

    public static function backfill_reservation_links() {
        global $wpdb;
        $participants = self::get_table_name();
        $reservations = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_table_name() : '';

        if ( empty( $reservations ) ) {
            return;
        }

        $wpdb->query(
            "UPDATE $participants p
             INNER JOIN $reservations r
                ON r.order_id = p.order_id
               AND r.order_item_id = p.item_id
             SET p.reservation_id = r.id
             WHERE p.reservation_id IS NULL
                OR p.reservation_id = 0"
        );
    }

    public static function get_by_order( $order_id ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $table WHERE order_id = %d", absint( $order_id ) ),
            ARRAY_A
        );
    }

    public static function get_by_hash( $hash ) {
        global $wpdb;
        $table = self::get_table_name();

        return $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $table WHERE ticket_hash = %s", $hash ),
            ARRAY_A
        );
    }

    public static function delete_by_order( $order_id ) {
        global $wpdb;

        return $wpdb->delete(
            self::get_table_name(),
            array( 'order_id' => absint( $order_id ) ),
            array( '%d' )
        );
    }
}

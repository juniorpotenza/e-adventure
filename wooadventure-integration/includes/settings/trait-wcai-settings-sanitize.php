<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Settings_Sanitize {

    public function sanitize_text( $value ) {
        return sanitize_text_field( (string) $value );
    }

    public function sanitize_carta_oferta( $value ) {
        $value = trim( (string) $value );

        if ( '' === $value ) {
            return (string) get_option( 'wcai_carta_oferta', '' );
        }

        return sanitize_text_field( $value );
    }

    public function get_settings_capability() {
        return WCAI_Capabilities::MANAGE_SETTINGS;
    }

    public function sanitize_status( $value ) {
        $value = sanitize_key( $value );
        $statuses = array_keys( wc_get_order_statuses() );
        $statuses = array_map( static function( $status ) {
            return str_replace( 'wc-', '', $status );
        }, $statuses );

        return in_array( $value, $statuses, true ) ? $value : 'completed';
    }

    public function sanitize_product_ids( $value ) {
        $ids = array_filter( array_map( 'absint', preg_split( '/[\\s,;]+/', (string) $value ) ) );
        return implode( ',', array_unique( $ids ) );
    }

    public function sanitize_meta_key( $value ) {
        $value = sanitize_key( $value );
        return $value ?: 'tour_date';
    }

    public function sanitize_event_date_source( $value ) {
        $value = sanitize_key( $value );
        return in_array( $value, array( 'departure', 'order_meta', 'item_meta' ), true ) ? $value : 'departure';
    }

    // uninstall.php lê a opção como booleano: desligado grava string vazia.
    public function sanitize_delete_data( $value ) {
        return 'yes' === $value ? 'yes' : '';
    }

    public function sanitize_seq_enabled( $value ) {
        return 'yes' === $value ? 'yes' : 'no';
    }

    public function sanitize_seq_width( $value ) {
        return min( 12, max( 1, absint( $value ) ) );
    }

    public function sanitize_seq_next( $value ) {
        return max( 1, absint( $value ) );
    }

    public function sanitize_calendar_statuses( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $allowed = array_keys( wc_get_order_statuses() );
        $clean = array();

        foreach ( $value as $status ) {
            $status = sanitize_key( $status );
            if ( in_array( $status, $allowed, true ) ) {
                $clean[] = $status;
            }
        }

        return array_values( array_unique( $clean ) );
    }

    public function sanitize_operation_name( $value ) {
        $value = sanitize_text_field( (string) $value );
        return '' !== $value ? $value : 'Operação';
    }

    public function sanitize_cancellation_policy( $value ) {
        return mb_substr( sanitize_textarea_field( (string) $value ), 0, 1000 );
    }

    // Só dígitos e símbolos de telefone; o valor vira link tel: na página do passeio.
    public function sanitize_contact_phone( $value ) {
        return trim( mb_substr( preg_replace( '/[^0-9+().\s-]/', '', (string) $value ), 0, 30 ) );
    }

    public function sanitize_accent_color( $value ) {
        return self::clean_hex( $value );
    }

    private static function clean_hex( $value ) {
        $value = trim( (string) $value );

        return preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', $value ) ? strtolower( $value ) : '';
    }

    public function sanitize_blocked_cpfs( $value ) {
        $cpfs = preg_split( '/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
        $valid_cpfs = array();

        foreach ( $cpfs as $cpf ) {
            $cpf = preg_replace( '/\D+/', '', $cpf );
            if ( WCAI_Utils::is_valid_cpf( $cpf ) ) {
                $valid_cpfs[] = $cpf;
            }
        }

        return implode( "\n", array_unique( $valid_cpfs ) );
    }
}

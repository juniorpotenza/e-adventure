<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Data_Resolver {

    public static function get_departure_id_for_order_item( $item ) {
        if ( ! $item || ! is_object( $item ) ) {
            return 0;
        }

        $departure_id = absint( $item->get_meta( '_wcai_departure_id', true ) );

        if ( $departure_id ) {
            return $departure_id;
        }

        if ( class_exists( 'WCAI_Reservations' ) ) {
            $reservation = WCAI_Reservations::get_by_order_item( $item->get_id() );
            if ( $reservation ) {
                return absint( $reservation->departure_id );
            }
        }

        return 0;
    }

    public static function get_departure_for_order_item( $item ) {
        $departure_id = self::get_departure_id_for_order_item( $item );

        if ( ! $departure_id || ! class_exists( 'WCAI_Departures' ) || WCAI_Departures::POST_TYPE !== get_post_type( $departure_id ) ) {
            return false;
        }

        return get_post( $departure_id );
    }

    public static function get_departure_start( $departure_id ) {
        $departure_id = absint( $departure_id );

        if ( ! $departure_id ) {
            return '';
        }

        return (string) get_post_meta( $departure_id, '_wcai_starts_at', true );
    }

    public static function get_event_datetime_for_order_item( $order, $item ) {
        if ( ! $order || ! is_object( $item ) ) {
            return '';
        }

        $source = class_exists( 'WCAI_Settings' ) ? WCAI_Settings::get_event_date_source() : 'departure';

        if ( 'departure' === $source ) {
            $start = self::get_departure_start( self::get_departure_id_for_order_item( $item ) );
            if ( $start ) {
                return $start;
            }
        }

        if ( 'order_meta' === $source ) {
            $key = class_exists( 'WCAI_Settings' ) ? WCAI_Settings::get_event_date_meta_key() : 'tour_date';
            $value = $key ? $order->get_meta( $key ) : '';
            if ( $value ) {
                return (string) $value;
            }
        }

        if ( 'item_meta' === $source ) {
            $key = class_exists( 'WCAI_Settings' ) ? WCAI_Settings::get_event_date_meta_key() : 'tour_date';
            $value = $key ? $item->get_meta( $key, true ) : '';
            if ( $value ) {
                return (string) $value;
            }
        }

        // Compatibilidade: uma instalação antiga pode continuar usando tour_date.
        $legacy_order = $order->get_meta( 'tour_date' );
        if ( $legacy_order ) {
            return (string) $legacy_order;
        }

        $legacy_item = $item->get_meta( 'tour_date', true );
        if ( $legacy_item ) {
            return (string) $legacy_item;
        }

        $start = self::get_departure_start( self::get_departure_id_for_order_item( $item ) );
        return $start ?: '';
    }

    public static function get_event_date( $order, $item ) {
        $datetime = self::get_event_datetime_for_order_item( $order, $item );

        if ( ! $datetime ) {
            return '';
        }

        $timestamp = self::parse_timestamp( $datetime );

        return $timestamp ? wp_date( 'Y-m-d', $timestamp ) : '';
    }

    public static function get_event_time( $order, $item ) {
        $datetime = self::get_event_datetime_for_order_item( $order, $item );

        if ( ! $datetime ) {
            return '';
        }

        $timestamp = self::parse_timestamp( $datetime );

        return $timestamp ? wp_date( 'H:i:s', $timestamp ) : '';
    }

    public static function parse_timestamp( $value ) {
        if ( $value instanceof DateTimeInterface ) {
            return $value->getTimestamp();
        }

        $value = trim( (string) $value );

        if ( '' === $value ) {
            return 0;
        }

        $timezone = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
        $formats = array( 'Y-m-d\\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i', 'Y-m-d' );

        foreach ( $formats as $format ) {
            $date = DateTimeImmutable::createFromFormat( $format, $value, $timezone );
            if ( $date instanceof DateTimeImmutable ) {
                return $date->getTimestamp();
            }
        }

        $timestamp = strtotime( $value );
        return $timestamp ? $timestamp : 0;
    }

    public static function get_billing_cpf( $order ) {
        if ( ! $order || ! is_object( $order ) ) {
            return '';
        }

        $value = $order->get_meta( 'billing_cpf' );

        if ( ! $value ) {
            $value = $order->get_meta( '_billing_cpf' );
        }

        return preg_replace( '/\\D+/', '', (string) $value );
    }

    public static function get_billing_birthdate( $order ) {
        if ( ! $order || ! is_object( $order ) ) {
            return '';
        }

        $value = $order->get_meta( 'billing_birthdate' );

        if ( ! $value ) {
            $value = $order->get_meta( '_billing_birthdate' );
        }

        return sanitize_text_field( (string) $value );
    }

    public static function get_billing_name( $order ) {
        if ( ! $order || ! is_object( $order ) ) {
            return '';
        }

        return trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
    }
}

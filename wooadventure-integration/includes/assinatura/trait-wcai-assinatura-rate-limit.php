<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Assinatura_Rate_Limit {

    private static function client_ip() {
        $remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $trusted = (array) apply_filters( 'wcai_trusted_proxies', array() );

        // X-Forwarded-For só vale quando REMOTE_ADDR é um proxy confiável.
        if ( $remote && in_array( $remote, $trusted, true ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $hops = array_reverse( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) ) );

            foreach ( $hops as $hop ) {
                if ( filter_var( $hop, FILTER_VALIDATE_IP ) && ! in_array( $hop, $trusted, true ) ) {
                    return $hop;
                }
            }
        }

        return $remote ? $remote : 'unknown';
    }

    // Contadores separados por ação ('check', 'autocheck', 'save'): uma ação não esgota o limite das outras.
    private function signature_rate_limited( $action, $order_id, $identifier = '' ) {
        $ip = self::client_ip();
        $order_id = absint( $order_id );
        $identifier = preg_replace( '/\D/', '', (string) $identifier );
        $limit = 'save' === $action ? self::RATE_LIMIT_SAVE : self::RATE_LIMIT;

        $keys = array(
            'wcai_sig_rate_' . $action . '_order_' . md5( $ip . '|' . $order_id ),
        );

        if ( '' !== $identifier ) {
            $keys[] = 'wcai_sig_rate_' . $action . '_identifier_' . md5( $ip . '|' . $order_id . '|' . $identifier );
        }

        $limited = false;

        foreach ( $keys as $key ) {
            if ( self::bump_rate_counter( $key ) > $limit ) {
                $limited = true;
            }
        }

        return $limited;
    }

    private static function bump_rate_counter( $key ) {
        $ttl = 10 * MINUTE_IN_SECONDS;

        if ( wp_using_ext_object_cache() ) {
            wp_cache_add( $key, 0, 'wcai_rate', $ttl );
            $count = wp_cache_incr( $key, 1, 'wcai_rate' );

            if ( false !== $count ) {
                return (int) $count;
            }
        }

        // shortcut: sem object cache persistente o contador é transient (não atômico); instalar Redis/Memcached.
        $count = absint( get_transient( $key ) ) + 1;
        set_transient( $key, $count, $ttl );

        return $count;
    }
}

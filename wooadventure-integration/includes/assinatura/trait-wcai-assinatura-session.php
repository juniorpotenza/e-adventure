<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Assinatura_Session {

    private function set_ticket_session_cookie( $order_id, $participant_id, $signature_post_id ) {
        $expires = time() + 5 * MINUTE_IN_SECONDS;
        $payload = absint( $order_id ) . '|' . absint( $participant_id ) . '|' . absint( $signature_post_id ) . '|' . $expires;
        $signature = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );

        setcookie(
            'wcai_ticket_session',
            $payload . '|' . $signature,
            array(
                'expires' => $expires,
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );
    }

    private function set_session_cookie( $order_id, $participant_id ) {
        $expires = time() + HOUR_IN_SECONDS;
        $payload = absint( $order_id ) . '|' . absint( $participant_id ) . '|' . $expires;
        $signature = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
        $value = $payload . '|' . $signature;

        setcookie(
            'wcai_pax_session',
            $value,
            array(
                'expires'  => $expires,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );
    }

    private function valid_session_for_participant( $order_id, $participant_id ) {
        if ( empty( $_COOKIE['wcai_pax_session'] ) ) {
            return false;
        }

        $parts = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE['wcai_pax_session'] ) ) );

        if ( 4 !== count( $parts ) ) {
            return false;
        }

        $payload = absint( $parts[0] ) . '|' . absint( $parts[1] ) . '|' . absint( $parts[2] );
        $expected = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );

        return absint( $parts[0] ) === absint( $order_id)
            && absint( $parts[1] ) === absint( $participant_id )
            && absint( $parts[2] ) >= time()
            && hash_equals( $expected, $parts[3] );
    }
}

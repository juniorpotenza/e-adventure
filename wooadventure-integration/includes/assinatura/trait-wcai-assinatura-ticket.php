<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Assinatura_Ticket {

    private function generate_and_save_ticket( $participant, $signature_post_id = 0, $guardian_name = '', $guardian_cpf = '' ) {
        if (
            empty( $participant['id'] ) ||
            ! class_exists( 'WCAI_Participants_DB' ) ||
            ! class_exists( 'WCAI_Legal_Acceptances' )
        ) {
            return new WP_Error( 'wcai_signature_dependencies_missing', 'Não foi possível concluir a assinatura.' );
        }

        $hash = ! empty( $participant['ticket_hash'] ) ? $participant['ticket_hash'] : '';

        if ( ! $hash ) {
            try {
                $hash = bin2hex( random_bytes( 32 ) );
            } catch ( Exception $exception ) {
                $hash = hash( 'sha256', wp_generate_uuid4() . '|' . wp_salt( 'auth' ) );
            }
        }

        $previous_hash = isset( $participant['ticket_hash'] ) ? (string) $participant['ticket_hash'] : '';
        $previous_signed = ! empty( $participant['termo_assinado'] );

        $updated = WCAI_Participants_DB::update(
            $participant['id'],
            array(
                'ticket_hash'   => $hash,
                'termo_assinado' => 1,
            )
        );

        if ( false === $updated ) {
            return new WP_Error( 'wcai_signature_participant_update', 'Não foi possível atualizar o participante.' );
        }

        $qr_url = self::generate_local_qr( $hash, absint( $participant['id'] ) );

        if ( is_wp_error( $qr_url ) ) {
            WCAI_Participants_DB::update(
                $participant['id'],
                array(
                    'ticket_hash'   => $previous_hash,
                    'termo_assinado' => $previous_signed ? 1 : 0,
                )
            );

            return $qr_url;
        }

        $acceptance = WCAI_Legal_Acceptances::record_for_participant(
            $participant['id'],
            0,
            'signature',
            $guardian_name,
            $guardian_cpf,
            $signature_post_id
        );

        if ( is_wp_error( $acceptance ) ) {
            WCAI_Participants_DB::update(
                $participant['id'],
                array(
                    'ticket_hash'   => $previous_hash,
                    'termo_assinado' => $previous_signed ? 1 : 0,
                )
            );

            if ( '' === $previous_hash ) {
                self::delete_local_qr( $hash, absint( $participant['id'] ) );
            }

            return $acceptance;
        }

        WCAI_Audit_Log::log(
            'participant_signature_recorded',
            'participant',
            $participant['id'],
            array(
                'reservation_id' => absint( $participant['reservation_id'] ),
                'acceptance_id'  => absint( $acceptance ),
            )
        );

        return array(
            'hash'   => $hash,
            'qr_url' => $qr_url,
            'nome'   => $participant['nome_completo'],
        );
    }

    public static function consume_ticket_session() {
        if ( empty( $_COOKIE['wcai_ticket_session'] ) ) {
            return false;
        }

        $parts = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE['wcai_ticket_session'] ) ) );
        if ( 5 !== count( $parts ) ) {
            return false;
        }

        $order_id = absint( $parts[0] );
        $participant_id = absint( $parts[1] );
        $signature_post_id = absint( $parts[2] );
        $expires = absint( $parts[3] );
        $payload = $order_id . '|' . $participant_id . '|' . $signature_post_id . '|' . $expires;
        $expected = self::hmac( 'ticket-cookie', $payload );

        setcookie(
            'wcai_ticket_session',
            '',
            array(
                'expires' => time() - HOUR_IN_SECONDS,
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );

        if (
            ! $order_id ||
            ! $participant_id ||
            ! $signature_post_id ||
            $expires < time() ||
            ! hash_equals( $expected, $parts[4] ) ||
            ! class_exists( 'WCAI_Participants_DB' )
        ) {
            return false;
        }

        $participant = WCAI_Participants_DB::get_by_id( $participant_id );
        if (
            ! $participant ||
            absint( $participant['order_id'] ) !== $order_id ||
            absint( $participant['reservation_id'] ) < 1
        ) {
            return false;
        }

        $reservation = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_by_id( absint( $participant['reservation_id'] ) ) : false;
        if ( ! $reservation || 'confirmed' !== $reservation->status ) {
            return false;
        }

        if (
            ! class_exists( 'WCAI_Legal_Acceptances' ) ||
            'accepted' !== WCAI_Legal_Acceptances::legal_status(
                $participant_id,
                absint( $participant['reservation_id'] )
            )
        ) {
            return false;
        }

        $hash = ! empty( $participant['ticket_hash'] ) ? (string) $participant['ticket_hash'] : '';
        if ( '' === $hash ) {
            return false;
        }

        $qr_url = self::generate_local_qr( $hash, $participant_id );
        if ( is_wp_error( $qr_url ) ) {
            return false;
        }

        return array(
            'hash'    => $hash,
            'qr_url'  => $qr_url,
            'nome'    => $participant['nome_completo'],
            'order_id' => $order_id,
            'participant_id' => $participant_id,
            'cpf'     => '',
        );
    }
}

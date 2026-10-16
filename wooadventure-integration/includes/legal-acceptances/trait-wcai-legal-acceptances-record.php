<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Legal_Acceptances_Record {

    /**
     * Registra o aceite usando a identidade canônica do Participant.
     * O documento e o contexto da reserva são determinados no servidor.
     */
    public static function record_for_participant( $participant_id, $document_id = 0, $acceptance_method = 'signature', $guardian_name = '', $guardian_cpf = '', $signature_post_id = 0 ) {
        $participant_id = absint( $participant_id );
        if ( ! $participant_id ) {
            return new WP_Error( 'wcai_acceptance_invalid_participant', 'Participante inválido.' );
        }

        if ( ! class_exists( 'WCAI_Participants_DB' ) || ! class_exists( 'WCAI_Reservations' ) || ! class_exists( 'WCAI_Legal_Documents' ) ) {
            return new WP_Error( 'wcai_acceptance_dependencies_missing', 'Dependências jurídicas indisponíveis.' );
        }

        $participant = WCAI_Participants_DB::get_by_id( $participant_id );
        if ( ! $participant ) {
            return new WP_Error( 'wcai_acceptance_participant_missing', 'Participante não encontrado.' );
        }

        $reservation_id = absint( $participant['reservation_id'] );
        if ( ! $reservation_id ) {
            return new WP_Error( 'wcai_acceptance_reservation_missing', 'O participante ainda não está vinculado a uma reserva.' );
        }

        $reservation = WCAI_Reservations::get_by_id( $reservation_id );
        if ( ! $reservation ) {
            return new WP_Error( 'wcai_acceptance_reservation_invalid', 'Reserva do participante não encontrada.' );
        }

        if ( 'confirmed' !== $reservation->status ) {
            return new WP_Error( 'wcai_acceptance_reservation_not_confirmed', 'A reserva do participante não está confirmada.' );
        }

        $document_id = absint( $document_id );
        if ( ! $document_id ) {
            $active = WCAI_Legal_Documents::get_active_snapshot();
            if ( empty( $active ) ) {
                return new WP_Error( 'wcai_acceptance_document_missing', 'Não existe documento jurídico vigente.' );
            }
        } else {
            $active = WCAI_Legal_Documents::get_snapshot( $document_id );
            if ( empty( $active ) ) {
                return new WP_Error( 'wcai_acceptance_document_invalid', 'Documento jurídico inválido ou não publicado.' );
            }
        }

        $existing = self::find_exact(
            $participant_id,
            $reservation_id,
            $active['document_id'],
            $active['document_version'],
            $active['document_hash']
        );

        if ( $existing ) {
            return absint( $existing['id'] );
        }

        $guardian = self::validate_guardian( $participant, $guardian_name, $guardian_cpf );
        if ( is_wp_error( $guardian ) ) {
            return $guardian;
        }

        return self::insert_acceptance(
            array(
                'participant_id'      => $participant_id,
                'reservation_id'      => $reservation_id,
                'order_id'            => absint( $participant['order_id'] ),
                'departure_id'        => absint( $reservation->departure_id ),
                'document_id'         => absint( $active['document_id'] ),
                'document_version'   => $active['document_version'],
                'document_hash'      => $active['document_hash'],
                'document_title'     => $active['document_title'],
                'document_text'      => $active['document_text'],
                'document_date'      => $active['document_date'],
                'guardian_name'      => $guardian_name,
                'guardian_cpf'       => $guardian_cpf,
                'acceptance_method'  => $acceptance_method,
                'signature_post_id'  => $signature_post_id,
            )
        );
    }

    /**
     * Menor de 18 anos exige responsável. Sem data de nascimento válida, trata como menor.
     *
     * @param array $participant Linha de wcai_participantes (data_nascimento em Y-m-d).
     */
    public static function guardian_required( $participant ) {
        $raw   = isset( $participant['data_nascimento'] ) ? (string) $participant['data_nascimento'] : '';
        $birth = DateTime::createFromFormat( '!Y-m-d', $raw );

        // '0000-00-00' é aceito pelo parser (vira ano -1): exige ida e volta idêntica e ano plausível.
        if ( ! $birth || $birth->format( 'Y-m-d' ) !== $raw || (int) $birth->format( 'Y' ) < 1900 ) {
            return true;
        }

        return ! WCAI_Utils::is_min_age( $birth->format( 'd/m/Y' ), 18 );
    }

    /**
     * Menor exige nome e CPF válido do responsável, diferente do CPF do participante.
     *
     * @return true|WP_Error
     */
    public static function validate_guardian( $participant, $guardian_name, $guardian_cpf ) {
        if ( ! self::guardian_required( $participant ) ) {
            return true;
        }

        $cpf = preg_replace( '/\D/', '', (string) $guardian_cpf );

        if (
            '' === trim( (string) $guardian_name ) ||
            ! WCAI_Utils::is_valid_cpf( $cpf ) ||
            $cpf === preg_replace( '/\D/', '', (string) $participant['cpf'] )
        ) {
            return new WP_Error( 'wcai_acceptance_guardian_required', 'Participante menor de 18 anos: informe nome e CPF válido do responsável.' );
        }

        return true;
    }

    /**
     * Compatibilidade com chamadas legadas. Para novos fluxos, prefira
     * record_for_participant(), que deriva todos os vínculos do Participant.
     */
    public static function record( $data ) {
        $participant_id = isset( $data['participant_id'] ) ? absint( $data['participant_id'] ) : 0;
        $document_id    = isset( $data['document_id'] ) ? absint( $data['document_id'] ) : 0;

        if ( ! $participant_id || ! $document_id ) {
            return new WP_Error( 'wcai_acceptance_invalid', 'Dados de aceite incompletos.' );
        }

        return self::record_for_participant(
            $participant_id,
            $document_id,
            isset( $data['acceptance_method'] ) ? sanitize_key( $data['acceptance_method'] ) : 'signature',
            isset( $data['guardian_name'] ) ? sanitize_text_field( $data['guardian_name'] ) : '',
            isset( $data['guardian_cpf'] ) ? $data['guardian_cpf'] : '',
            isset( $data['signature_post_id'] ) ? absint( $data['signature_post_id'] ) : 0
        );
    }

    private static function find_exact( $participant_id, $reservation_id, $document_id, $version, $hash ) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE participant_id = %d AND reservation_id = %d AND document_id = %d AND document_version = %s AND document_hash = %s ORDER BY id DESC LIMIT 1',
                absint( $participant_id ),
                absint( $reservation_id ),
                absint( $document_id ),
                $version,
                $hash
            ),
            ARRAY_A
        );
    }

    // Mesma derivação de WCAI_Assinatura::hmac (classe independente: este arquivo carrega sem ela). Um contexto por uso.
    private static function hmac( $context, $data ) {
        return hash_hmac( 'sha256', (string) $data, hash_hmac( 'sha256', 'wcai|' . $context, wp_salt( 'auth' ) ) );
    }

    private static function insert_acceptance( $data ) {
        global $wpdb;

        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        $row = array(
            'participant_id'     => absint( $data['participant_id'] ),
            'reservation_id'     => absint( $data['reservation_id'] ),
            'order_id'           => absint( $data['order_id'] ),
            'departure_id'       => absint( $data['departure_id'] ),
            'document_id'        => absint( $data['document_id'] ),
            'document_version'   => sanitize_text_field( $data['document_version'] ),
            'document_hash'      => strtolower( sanitize_text_field( $data['document_hash'] ) ),
            'document_title'     => sanitize_text_field( $data['document_title'] ),
            'document_text'      => (string) $data['document_text'],
            'document_date'      => preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $data['document_date'] ) ? $data['document_date'] : null,
            'guardian_name'      => isset( $data['guardian_name'] ) && $data['guardian_name'] !== '' ? sanitize_text_field( $data['guardian_name'] ) : null,
            'guardian_cpf_hash'  => ! empty( $data['guardian_cpf'] )
                ? self::hmac( 'guardian-cpf', absint( $data['participant_id'] ) . '|' . preg_replace( '/\D/', '', $data['guardian_cpf'] ) )
                : null,
            'acceptance_method'  => sanitize_key( $data['acceptance_method'] ),
            'signature_post_id'  => absint( $data['signature_post_id'] ),
            'ip_hash'            => $ip ? self::hmac( 'ip', $ip ) : null,
            'user_agent_hash'    => $agent ? self::hmac( 'device', $agent ) : null,
            'accepted_at'        => current_time( 'mysql', true ),
        );

        $suppress = $wpdb->suppress_errors( true );
        $ok       = $wpdb->insert( self::table(), $row );
        $wpdb->suppress_errors( $suppress );

        if ( false === $ok ) {
            // Duplo clique ou corrida: a chave UNIQUE barrou; devolve o aceite que já existe.
            $existing = $wpdb->get_row(
                $wpdb->prepare(
                    'SELECT id FROM ' . self::table() . ' WHERE participant_id = %d AND reservation_id = %d AND document_version = %s LIMIT 1',
                    $row['participant_id'],
                    $row['reservation_id'],
                    $row['document_version']
                ),
                ARRAY_A
            );

            if ( $existing ) {
                return absint( $existing['id'] );
            }

            return new WP_Error( 'wcai_acceptance_failed', 'Não foi possível registrar o aceite.' );
        }

        WCAI_Audit_Log::log(
            'legal_acceptance_recorded',
            'legal_acceptance',
            $wpdb->insert_id,
            array(
                'participant_id' => $row['participant_id'],
                'reservation_id' => $row['reservation_id'],
                'document_id'    => $row['document_id'],
                'version'        => $row['document_version'],
                'method'         => $row['acceptance_method'],
            )
        );

        return absint( $wpdb->insert_id );
    }
}

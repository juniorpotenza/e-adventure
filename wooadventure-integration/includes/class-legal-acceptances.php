<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Legal_Acceptances {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'wcai_legal_acceptances';
    }

    public static function create_table() {
        global $wpdb;

        $table   = self::table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            participant_id bigint(20) unsigned NOT NULL,
            reservation_id bigint(20) unsigned DEFAULT NULL,
            order_id bigint(20) unsigned NOT NULL,
            departure_id bigint(20) unsigned DEFAULT NULL,
            document_id bigint(20) unsigned NOT NULL,
            document_version varchar(100) NOT NULL,
            document_hash char(64) NOT NULL,
            guardian_name varchar(255) DEFAULT NULL,
            guardian_cpf_hash char(64) DEFAULT NULL,
            acceptance_method varchar(30) NOT NULL,
            signature_post_id bigint(20) unsigned DEFAULT NULL,
            ip_hash char(64) DEFAULT NULL,
            user_agent_hash char(64) DEFAULT NULL,
            accepted_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY participant_lookup (participant_id, reservation_id),
            KEY participant_document (participant_id, document_id),
            KEY reservation_id (reservation_id),
            KEY order_id (order_id),
            KEY departure_id (departure_id),
            KEY accepted_at (accepted_at)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    /**
     * Migra aceites legados para o vínculo canônico Participant → Reservation → Departure.
     */
    public static function backfill_reservation_links() {
        global $wpdb;

        if ( ! class_exists( 'WCAI_Participants_DB' ) || ! class_exists( 'WCAI_Reservations' ) ) {
            return;
        }

        $acceptances = self::table();
        $participants = WCAI_Participants_DB::get_table_name();
        $reservations = WCAI_Reservations::get_table_name();

        $wpdb->query(
            "UPDATE $acceptances a
             INNER JOIN $participants p
                ON p.id = a.participant_id
             LEFT JOIN $reservations r
                ON r.id = p.reservation_id
             SET a.reservation_id = NULLIF(p.reservation_id, 0),
                 a.departure_id = COALESCE(r.departure_id, a.departure_id)
             WHERE (a.reservation_id IS NULL OR a.reservation_id = 0)
                AND p.reservation_id IS NOT NULL
                AND p.reservation_id <> 0"
        );
    }

    public static function get_by_id( $acceptance_id ) {
        global $wpdb;
        return $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . self::table() . ' WHERE id = %d',
                absint( $acceptance_id )
            ),
            ARRAY_A
        );
    }

    public static function get_by_participant( $participant_id, $reservation_id = 0 ) {
        global $wpdb;

        $sql   = 'SELECT * FROM ' . self::table() . ' WHERE participant_id = %d';
        $args  = array( absint( $participant_id ) );

        if ( $reservation_id ) {
            $sql   .= ' AND reservation_id = %d';
            $args[] = absint( $reservation_id );
        }

        $sql .= ' ORDER BY accepted_at DESC, id DESC';

        return $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A );
    }

    public static function get_latest( $participant_id, $reservation_id = 0, $document_id = 0 ) {
        global $wpdb;

        $sql  = 'SELECT * FROM ' . self::table() . ' WHERE participant_id = %d';
        $args = array( absint( $participant_id ) );

        if ( $reservation_id ) {
            $sql   .= ' AND reservation_id = %d';
            $args[] = absint( $reservation_id );
        }

        if ( $document_id ) {
            $sql   .= ' AND document_id = %d';
            $args[] = absint( $document_id );
        }

        $sql .= ' ORDER BY accepted_at DESC, id DESC LIMIT 1';

        return $wpdb->get_row( $wpdb->prepare( $sql, $args ), ARRAY_A );
    }

    /**
     * Estado jurídico do participante. Não substitui o status operacional.
     */
    public static function legal_status( $participant_id, $reservation_id = 0, $document_id = 0 ) {
        if ( ! class_exists( 'WCAI_Legal_Documents' ) ) {
            return 'pending';
        }

        $current = $document_id
            ? WCAI_Legal_Documents::get_snapshot( $document_id )
            : WCAI_Legal_Documents::get_active_snapshot();

        if ( empty( $current ) ) {
            return 'pending';
        }

        $acceptance = self::find_exact(
            absint( $participant_id ),
            absint( $reservation_id ),
            absint( $current['document_id'] ),
            $current['document_version'],
            $current['document_hash']
        );

        if ( $acceptance ) {
            return 'accepted';
        }

        $historical = self::get_latest(
            $participant_id,
            $reservation_id,
            absint( $current['document_id'] )
        );

        return $historical ? 'version_outdated' : 'pending';
    }

    /**
     * Mantido por compatibilidade com código legado.
     * O novo código deve usar legal_status().
     */
    public static function participant_status( $participant_id, $order_id = 0, $departure_id = 0 ) {
        global $wpdb;

        $sql  = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE participant_id = %d';
        $args = array( absint( $participant_id ) );

        if ( $order_id ) {
            $sql .= ' AND order_id = %d';
            $args[] = absint( $order_id );
        }

        if ( $departure_id ) {
            $sql .= ' AND departure_id = %d';
            $args[] = absint( $departure_id );
        }

        return $wpdb->get_var( $wpdb->prepare( $sql, $args ) ) ? 'regular' : 'pending';
    }

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

        return self::insert_acceptance(
            array(
                'participant_id'      => $participant_id,
                'reservation_id'      => $reservation_id,
                'order_id'            => absint( $participant['order_id'] ),
                'departure_id'        => absint( $reservation->departure_id ),
                'document_id'         => absint( $active['document_id'] ),
                'document_version'   => $active['document_version'],
                'document_hash'      => $active['document_hash'],
                'guardian_name'      => $guardian_name,
                'guardian_cpf'       => $guardian_cpf,
                'acceptance_method'  => $acceptance_method,
                'signature_post_id'  => $signature_post_id,
            )
        );
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
            'guardian_name'      => isset( $data['guardian_name'] ) && $data['guardian_name'] !== '' ? sanitize_text_field( $data['guardian_name'] ) : null,
            'guardian_cpf_hash'  => ! empty( $data['guardian_cpf'] )
                ? hash_hmac( 'sha256', preg_replace( '/\D/', '', $data['guardian_cpf'] ), wp_salt( 'auth' ) )
                : null,
            'acceptance_method'  => sanitize_key( $data['acceptance_method'] ),
            'signature_post_id'  => absint( $data['signature_post_id'] ),
            'ip_hash'            => $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : null,
            'user_agent_hash'    => $agent ? hash_hmac( 'sha256', $agent, wp_salt( 'auth' ) ) : null,
            'accepted_at'        => current_time( 'mysql', true ),
        );

        $ok = $wpdb->insert( self::table(), $row );

        if ( false === $ok ) {
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

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Assinatura_Ajax {

    private function verify_ajax_request() {
        if ( ! check_ajax_referer( self::AJAX_NONCE, 'security', false ) ) {
            wp_send_json_error( 'Requisição inválida.', 403 );
        }
    }

    public function ajax_check_order() {
        $this->verify_ajax_request();

        $id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

        if ( ! $id ) {
            wp_send_json_error( 'Pedido inválido.', 400 );
        }

        if ( $this->signature_rate_limited( 'check', $id ) ) {
            wp_send_json_error( 'Muitas tentativas. Aguarde alguns minutos e tente novamente.', 429 );
        }

        if ( wc_get_order( $id ) ) {
            wp_send_json_success();
        }

        wp_send_json_error();
    }

    public function ajax_check_pax_deep() {
        $this->verify_ajax_request();

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $cpf_input = preg_replace( '/\D/', '', isset( $_POST['cpf'] ) ? wp_unslash( $_POST['cpf'] ) : '' );
        $order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';

        if ( ! $order_id || ! $cpf_input ) {
            wp_send_json_error( self::LOOKUP_ERROR, 403 );
        }

        if ( $this->signature_rate_limited( 'autocheck', $order_id, $cpf_input ) ) {
            wp_send_json_error( 'Muitas tentativas. Aguarde alguns minutos e tente novamente.', 429 );
        }

        $order = wc_get_order( $order_id );
        // Mesma resposta para pedido inexistente, chave errada e CPF não vinculado (sem oráculo).
        $participant = ( $order && $this->order_key_matches( $order, $order_key ) )
            ? $this->get_participant_for_order( $order, $cpf_input )
            : false;

        if ( ! $participant ) {
            wp_send_json_error( self::LOOKUP_ERROR, 403 );
        }

        $this->set_session_cookie( $order_id, $participant['id'] );
        wp_send_json_success(
            array(
                'participant_id' => absint( $participant['id'] ),
                'nome'           => $participant['nome_completo'],
                'menor'          => class_exists( 'WCAI_Legal_Acceptances' ) && WCAI_Legal_Acceptances::guardian_required( $participant ),
            )
        );
    }

    public function ajax_save_signature() {
        $this->verify_ajax_request();

        $pedido_id  = isset( $_POST['pedido'] ) ? absint( $_POST['pedido'] ) : 0;
        $cpf        = isset( $_POST['cpf'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['cpf'] ) ) : '';
        $img_base64 = isset( $_POST['assinatura'] ) ? wp_unslash( $_POST['assinatura'] ) : '';

        if ( $this->signature_rate_limited( 'save', $pedido_id, $cpf ) ) {
            wp_send_json_error( 'Muitas tentativas de assinatura. Aguarde alguns minutos e tente novamente.', 429 );
        }

        $order = wc_get_order( $pedido_id );
        $participant = $order && $cpf ? $this->get_participant_for_order( $order, $cpf ) : false;

        // A sessão só nasce após order_key + CPF em ajax_check_pax_deep; mesma resposta para qualquer falha.
        if ( ! $participant || ! $this->valid_session_for_participant( $pedido_id, $participant['id'] ) ) {
            wp_send_json_error( self::SESSION_ERROR, 403 );
        }

        if (
            class_exists( 'WCAI_Legal_Acceptances' ) &&
            'accepted' === WCAI_Legal_Acceptances::legal_status( $participant['id'], $participant['reservation_id'] )
        ) {
            wp_send_json_error( 'Este participante já possui aceite da versão jurídica vigente.' );
        }

        // Campos novos do formulário de assinatura; obrigatórios só para menor de 18 anos.
        $guardian_name = isset( $_POST['responsavel_nome'] ) ? sanitize_text_field( wp_unslash( $_POST['responsavel_nome'] ) ) : '';
        $guardian_cpf  = isset( $_POST['responsavel_cpf'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['responsavel_cpf'] ) ) : '';

        if ( class_exists( 'WCAI_Legal_Acceptances' ) ) {
            $guardian = WCAI_Legal_Acceptances::validate_guardian( $participant, $guardian_name, $guardian_cpf );
            if ( is_wp_error( $guardian ) ) {
                wp_send_json_error( $guardian->get_error_message(), 400 );
            }
        }

        if ( empty( $img_base64 ) ) {
            wp_send_json_error( 'Assinatura vazia.' );
        }

        $parts   = explode( ';base64,', $img_base64, 2 );
        $decoded = base64_decode( isset( $parts[1] ) ? $parts[1] : $img_base64, true );

        if (
            false === $decoded ||
            strlen( $decoded ) > 2 * MB_IN_BYTES ||
            ! function_exists( 'getimagesizefromstring' )
        ) {
            wp_send_json_error( 'Assinatura inválida.' );
        }

        $image = getimagesizefromstring( $decoded );

        if ( ! $image || IMAGETYPE_PNG !== $image[2] ) {
            wp_send_json_error( 'A assinatura deve estar no formato PNG.' );
        }

        $post_id = wp_insert_post(
            array(
                'post_type'   => 'wcai_assinatura',
                'post_title'  => 'Participante #' . absint( $participant['id'] ) . ' - Pedido #' . absint( $pedido_id ),
                'post_status' => 'publish',
            ),
            true
        );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( 'Erro ao registrar a assinatura.' );
        }

        $stored = self::store_signature_png( $decoded, $participant['id'], $post_id );

        if ( ! $stored ) {
            wp_delete_post( $post_id, true );
            wp_send_json_error( 'Não foi possível armazenar a assinatura.' );
        }

        update_post_meta( $post_id, '_wcai_participant_id', absint( $participant['id'] ) );
        update_post_meta( $post_id, '_wcai_reservation_id', absint( $participant['reservation_id'] ) );
        update_post_meta( $post_id, '_wcai_pedido_id', absint( $participant['order_id'] ) );
        update_post_meta( $post_id, '_wcai_assinatura_file', $stored['name'] );

        $ip = self::client_ip();
        $ip = 'unknown' === $ip ? '' : $ip;
        $agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        update_post_meta(
            $post_id,
            '_wcai_ip_hash',
            $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : ''
        );
        update_post_meta(
            $post_id,
            '_wcai_device_hash',
            $agent ? hash_hmac( 'sha256', $agent, wp_salt( 'auth' ) ) : ''
        );
        update_post_meta( $post_id, '_wcai_data_hora', current_time( 'mysql' ) );

        $ticket_data = $this->generate_and_save_ticket( $participant, $post_id, $guardian_name, $guardian_cpf );

        if ( is_wp_error( $ticket_data ) ) {
            if ( file_exists( $stored['file'] ) ) {
                wp_delete_file( $stored['file'] );
            }
            wp_delete_post( $post_id, true );
            wp_send_json_error( $ticket_data->get_error_message(), 500 );
        }

        $this->set_ticket_session_cookie( $pedido_id, absint( $participant['id'] ), $post_id );

        setcookie(
            'wcai_pax_session',
            '',
            array(
                'expires' => time() - HOUR_IN_SECONDS,
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );

        wp_send_json_success(
            array(
                'ticket_url' => $ticket_data['qr_url'],
                'ticket_hash' => $ticket_data['hash'],
                'participant_id' => absint( $participant['id'] ),
            )
        );
    }

    /**
     * Localiza primeiro a identidade operacional canônica.
     * Pedido + CPF servem apenas como mecanismo de entrada do formulário legado.
     */
    private function get_participant_for_order( $order, $cpf ) {
        if ( ! $order || ! class_exists( 'WCAI_Participants_DB' ) ) {
            return false;
        }

        $cpf = preg_replace( '/\D/', '', $cpf );
        if ( ! $cpf ) {
            return false;
        }

        foreach ( WCAI_Participants_DB::get_by_order( $order->get_id() ) as $participant ) {
            $participant_cpf = preg_replace( '/\D/', '', $participant['cpf'] );

            if ( $participant_cpf !== $cpf ) {
                continue;
            }

            $reservation_id = absint( $participant['reservation_id'] );
            if ( ! $reservation_id || ! class_exists( 'WCAI_Reservations' ) ) {
                continue;
            }

            $reservation = WCAI_Reservations::get_by_id( $reservation_id );
            if ( ! $reservation || 'confirmed' !== $reservation->status ) {
                continue;
            }

            return $participant;
        }

        return false;
    }

    // Prova de posse do pedido: order_key (a chave do link do pedido), além do CPF.
    private function order_key_matches( $order, $order_key ) {
        $expected = (string) $order->get_order_key();

        return '' !== $expected && '' !== $order_key && hash_equals( $expected, $order_key );
    }
}

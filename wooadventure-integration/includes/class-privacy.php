<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LGPD: exportador e apagador de dados pessoais (Ferramentas > Exportar/Apagar dados pessoais).
 * Dados são localizados pelos pedidos do e-mail (billing_email) e pela conta WP com esse e-mail.
 */
class WCAI_Privacy {

    const CONSENT_TEXT = 'Declaro que tenho autorização das pessoas incluídas nesta reserva para informar seus dados pessoais (nome, CPF e data de nascimento) e que esses dados serão tratados para a execução do passeio, o controle de segurança e o envio aos sistemas de parceiros da operação (API de integração), conforme a Política de Privacidade.';

    const ERASED_NAME = 'Anonimizado';
    const ERASED_BIRTH = '1900-01-01';

    // Metas do pedido com dados pessoais do titular e dos acompanhantes (gravados pelo checkout e pela Minha Conta).
    const ORDER_PII_METAS = array( '_additional_participants', 'billing_cpf', '_billing_cpf', 'billing_birthdate', '_billing_birthdate' );

    public function __construct() {
        add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
        add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
    }

    /**
     * Texto de consentimento para dados de terceiros e envio à API (exibido pelo checkout/passeio).
     */
    public static function consent_text() {
        return (string) apply_filters( 'wcai_third_party_consent_text', self::CONSENT_TEXT );
    }

    public function register_exporter( $exporters ) {
        $exporters['wooadventure-integration'] = array(
            'exporter_friendly_name' => 'WooAdventure: participantes, aceites e assinaturas',
            'callback'               => array( $this, 'export' ),
        );
        return $exporters;
    }

    public function register_eraser( $erasers ) {
        $erasers['wooadventure-integration'] = array(
            'eraser_friendly_name' => 'WooAdventure: participantes, aceites e assinaturas',
            'callback'             => array( $this, 'erase' ),
        );
        return $erasers;
    }

    // shortcut: uma página só (done = true); paginar se um e-mail passar de centenas de pedidos.
    // Escolha LGPD: o e-mail do comprador não dá direito aos dados dos acompanhantes. Titular = dono do
    // CPF de cobrança do pedido (sem CPF de cobrança, o participante de menor id); acompanhante sai só com nome.
    public function export( $email, $page = 1 ) {
        $items = array();

        foreach ( $this->orders_for_email( $email ) as $order ) {
            $rows   = WCAI_Participants_DB::get_by_order( $order->get_id() );
            $holder = $this->holder_of( $order, $rows );

            foreach ( $rows as $participant ) {
                if ( $holder && absint( $participant['id'] ) === absint( $holder['id'] ) ) {
                    $items = array_merge( $items, $this->export_holder( $participant ) );
                    continue;
                }

                $items[] = array(
                    'group_id'    => 'wcai-acompanhantes',
                    'group_label' => 'Acompanhantes (somente nome)',
                    'item_id'     => 'wcai-acompanhante-' . absint( $participant['id'] ),
                    'data'        => array(
                        array( 'name' => 'Pedido', 'value' => absint( $participant['order_id'] ) ),
                        array( 'name' => 'Nome', 'value' => $participant['nome_completo'] ),
                    ),
                );
            }
        }

        return array( 'data' => $items, 'done' => true );
    }

    private function export_holder( $participant ) {
        $pid   = absint( $participant['id'] );
        $items = array(
            array(
                'group_id'    => 'wcai-participantes',
                'group_label' => 'Participantes',
                'item_id'     => 'wcai-participante-' . $pid,
                'data'        => array(
                    array( 'name' => 'Pedido', 'value' => absint( $participant['order_id'] ) ),
                    array( 'name' => 'Nome', 'value' => $participant['nome_completo'] ),
                    array( 'name' => 'CPF (mascarado)', 'value' => WCAI_Utils::mask_cpf( $participant['cpf'] ) ),
                    array( 'name' => 'Data de nascimento', 'value' => $participant['data_nascimento'] ),
                    array( 'name' => 'Termo assinado', 'value' => ! empty( $participant['termo_assinado'] ) ? 'Sim' : 'Não' ),
                    array( 'name' => 'Cadastrado em', 'value' => $participant['created_at'] ),
                ),
            ),
        );

        foreach ( WCAI_Legal_Acceptances::get_by_participant( $pid ) as $acceptance ) {
            $items[] = array(
                'group_id'    => 'wcai-aceites',
                'group_label' => 'Aceites jurídicos',
                'item_id'     => 'wcai-aceite-' . absint( $acceptance['id'] ),
                'data'        => array(
                    array( 'name' => 'Participante', 'value' => $pid ),
                    array( 'name' => 'Versão do documento', 'value' => $acceptance['document_version'] ),
                    array( 'name' => 'Data do documento', 'value' => (string) $acceptance['document_date'] ),
                    array( 'name' => 'Método', 'value' => $acceptance['acceptance_method'] ),
                    array( 'name' => 'Responsável legal', 'value' => (string) $acceptance['guardian_name'] ),
                    array( 'name' => 'Aceito em (UTC)', 'value' => $acceptance['accepted_at'] ),
                ),
            );
        }

        foreach ( $this->signature_ids( $pid ) as $post_id ) {
            $items[] = array(
                'group_id'    => 'wcai-assinaturas',
                'group_label' => 'Assinaturas',
                'item_id'     => 'wcai-assinatura-' . $post_id,
                'data'        => array(
                    array( 'name' => 'Participante', 'value' => $pid ),
                    array( 'name' => 'Assinado em', 'value' => (string) get_post_meta( $post_id, '_wcai_data_hora', true ) ),
                    array( 'name' => 'Imagem da assinatura', 'value' => 'Armazenada em área privada; disponível mediante solicitação ao responsável.' ),
                ),
            );
        }

        return $items;
    }

    /**
     * Anonimiza o participante titular e seus aceites, apaga assinaturas (CPT + arquivo) e QR do ingresso,
     * limpa os metas de dados pessoais dos pedidos do comprador e grava log sem PII.
     * Acompanhantes não são apagados por este e-mail (ver export). Aceites mantêm versão, hash e texto
     * do documento (guarda legal, LGPD art. 16 I).
     */
    public function erase( $email, $page = 1 ) {
        $removed  = false;
        $retained = 0;

        foreach ( $this->orders_for_email( $email ) as $order ) {
            $rows   = WCAI_Participants_DB::get_by_order( $order->get_id() );
            $holder = $this->holder_of( $order, $rows );

            if ( $holder ) {
                $retained += $this->erase_holder( $holder );
            }

            // Depois de holder_of: ele usa o billing_cpf que será apagado aqui.
            foreach ( self::ORDER_PII_METAS as $key ) {
                $order->delete_meta_data( $key );
            }
            $order->save();

            WCAI_Audit_Log::log( 'privacy_erased', 'order', $order->get_id(), array( 'metas' => count( self::ORDER_PII_METAS ) ) );
            $removed = true;
        }

        return array(
            'items_removed'  => $removed,
            'items_retained' => $retained > 0,
            'messages'       => $retained > 0 ? array( 'Aceites jurídicos mantidos sem dados pessoais, por obrigação legal.' ) : array(),
            'done'           => true,
        );
    }

    // Retorna o número de aceites mantidos (anonimizados).
    private function erase_holder( $participant ) {
        global $wpdb;

        $pid = absint( $participant['id'] );

        $signatures = $this->signature_ids( $pid );
        foreach ( $signatures as $post_id ) {
            $this->delete_signature_file( $post_id );
            wp_delete_post( $post_id, true );
        }

        $hash = isset( $participant['ticket_hash'] ) ? (string) $participant['ticket_hash'] : '';
        if ( '' !== $hash && class_exists( 'WCAI_Assinatura' ) ) {
            WCAI_Assinatura::delete_local_qr( $hash, $pid );
        }

        $acceptances = absint(
            $wpdb->query(
                $wpdb->prepare(
                    'UPDATE ' . WCAI_Legal_Acceptances::table() . ' SET guardian_name = NULL, guardian_cpf_hash = NULL, ip_hash = NULL, user_agent_hash = NULL, signature_post_id = NULL WHERE participant_id = %d',
                    $pid
                )
            )
        );

        WCAI_Participants_DB::update(
            $pid,
            array(
                'nome_completo'   => self::ERASED_NAME,
                'cpf'             => '',
                'data_nascimento' => self::ERASED_BIRTH,
                'ticket_hash'     => null,
            )
        );

        WCAI_Audit_Log::log(
            'privacy_erased',
            'participant',
            $pid,
            array(
                'signatures'  => count( $signatures ),
                'acceptances' => $acceptances,
                'ticket_qr'   => '' !== $hash,
            )
        );

        return $acceptances;
    }

    // Pedidos do comprador: por billing_email e pela conta WP com esse e-mail. Retorna objetos de pedido por id.
    private function orders_for_email( $email ) {
        $email = sanitize_email( $email );
        if ( ! $email || ! class_exists( 'WCAI_Participants_DB' ) || ! class_exists( 'WCAI_Legal_Acceptances' ) ) {
            return array();
        }

        $ids  = (array) wc_get_orders( array( 'billing_email' => $email, 'limit' => -1, 'return' => 'ids' ) );
        $user = get_user_by( 'email', $email );
        if ( $user ) {
            $ids = array_merge( $ids, (array) wc_get_orders( array( 'customer_id' => absint( $user->ID ), 'limit' => -1, 'return' => 'ids' ) ) );
        }

        $orders = array();
        foreach ( array_unique( array_map( 'absint', $ids ) ) as $id ) {
            $order = wc_get_order( $id );
            if ( $order ) {
                $orders[ $id ] = $order;
            }
        }

        return $orders;
    }

    // Titular: participante com o CPF de cobrança do pedido; sem CPF de cobrança, o de menor id.
    private function holder_of( $order, $rows ) {
        $cpf    = WCAI_Data_Resolver::get_billing_cpf( $order );
        $holder = null;

        foreach ( $rows as $row ) {
            if ( '' !== $cpf ) {
                if ( preg_replace( '/\D/', '', (string) $row['cpf'] ) === $cpf ) {
                    return $row;
                }
            } elseif ( null === $holder || absint( $row['id'] ) < absint( $holder['id'] ) ) {
                $holder = $row;
            }
        }

        return $holder;
    }

    private function signature_ids( $participant_id ) {
        return get_posts(
            array(
                'post_type'      => 'wcai_assinatura',
                'post_status'    => 'any',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_key'       => '_wcai_participant_id',
                'meta_value'     => absint( $participant_id ),
            )
        );
    }

    private function delete_signature_file( $post_id ) {
        $upload = wp_upload_dir();
        if ( ! empty( $upload['error'] ) || empty( $upload['basedir'] ) ) {
            return;
        }

        $name = basename( (string) get_post_meta( $post_id, '_wcai_assinatura_file', true ) );
        $file = trailingslashit( $upload['basedir'] ) . 'wcai-private/' . $name;

        if ( '' !== $name && is_file( $file ) ) {
            wp_delete_file( $file );
        }

        // Registros antigos: PNG público em _wcai_assinatura_url, dentro do baseurl de uploads.
        $url = (string) get_post_meta( $post_id, '_wcai_assinatura_url', true );
        if ( $url && ! empty( $upload['baseurl'] ) && 0 === strpos( $url, $upload['baseurl'] ) ) {
            $legacy = $upload['basedir'] . substr( $url, strlen( $upload['baseurl'] ) );

            if ( false === strpos( $legacy, '..' ) && is_file( $legacy ) ) {
                wp_delete_file( $legacy );
            }
        }
    }
}

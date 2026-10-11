<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * LGPD: exportador e apagador de dados pessoais (Ferramentas > Exportar/Apagar dados pessoais).
 * Participantes são localizados pelos pedidos do e-mail (billing_email) e pela conta WP com esse e-mail.
 */
class WCAI_Privacy {

    const CONSENT_TEXT = 'Declaro que tenho autorização das pessoas incluídas nesta reserva para informar seus dados pessoais (nome, CPF e data de nascimento) e que esses dados serão tratados para a execução do passeio, o controle de segurança e o envio aos sistemas de parceiros da operação (API de integração), conforme a Política de Privacidade.';

    const ERASED_NAME = 'Anonimizado';
    const ERASED_BIRTH = '1900-01-01';

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
    public function export( $email, $page = 1 ) {
        $items = array();

        foreach ( $this->participants_for_email( $email ) as $participant ) {
            $pid = absint( $participant['id'] );

            $items[] = array(
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
        }

        return array( 'data' => $items, 'done' => true );
    }

    /**
     * Anonimiza participante e aceites, apaga assinaturas (CPT + arquivo) e grava log sem PII.
     * Os aceites mantêm versão, hash e texto do documento (guarda legal, LGPD art. 16 I).
     */
    public function erase( $email, $page = 1 ) {
        global $wpdb;

        $removed = 0;
        $retained = 0;

        foreach ( $this->participants_for_email( $email ) as $participant ) {
            $pid = absint( $participant['id'] );

            $signatures = $this->signature_ids( $pid );
            foreach ( $signatures as $post_id ) {
                $this->delete_signature_file( $post_id );
                wp_delete_post( $post_id, true );
            }

            $acceptances = $wpdb->query(
                $wpdb->prepare(
                    'UPDATE ' . WCAI_Legal_Acceptances::table() . ' SET guardian_name = NULL, guardian_cpf_hash = NULL, ip_hash = NULL, user_agent_hash = NULL, signature_post_id = NULL WHERE participant_id = %d',
                    $pid
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
                    'acceptances' => absint( $acceptances ),
                )
            );

            $removed++;
            $retained += absint( $acceptances );
        }

        return array(
            'items_removed'  => $removed > 0,
            'items_retained' => $retained > 0,
            'messages'       => $retained > 0 ? array( 'Aceites jurídicos mantidos sem dados pessoais, por obrigação legal.' ) : array(),
            'done'           => true,
        );
    }

    private function participants_for_email( $email ) {
        global $wpdb;

        $email = sanitize_email( $email );
        if ( ! $email || ! class_exists( 'WCAI_Participants_DB' ) || ! class_exists( 'WCAI_Legal_Acceptances' ) ) {
            return array();
        }

        $order_ids = wc_get_orders( array( 'billing_email' => $email, 'limit' => -1, 'return' => 'ids' ) );
        $user      = get_user_by( 'email', $email );
        $table     = WCAI_Participants_DB::get_table_name();
        $where     = array();
        $args      = array();

        if ( $order_ids ) {
            $where[] = 'order_id IN (' . implode( ',', array_fill( 0, count( $order_ids ), '%d' ) ) . ')';
            $args    = array_merge( $args, array_map( 'absint', $order_ids ) );
        }

        if ( $user ) {
            $where[] = 'customer_id = %d';
            $args[]  = absint( $user->ID );
        }

        if ( ! $where ) {
            return array();
        }

        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM $table WHERE " . implode( ' OR ', $where ), $args ),
            ARRAY_A
        );
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

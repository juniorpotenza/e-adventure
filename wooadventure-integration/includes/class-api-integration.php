<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_API_Integration {
    // URL Base Original
    private $api_base_url = 'https://www.roca.floripa.br/api/';

    public function __construct() {
        add_action( 'woocommerce_order_status_completed', array( $this, 'process_api_sync' ), 10, 1 );
    }

    public static function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_evento_event_tokens';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            event_date date NOT NULL,
            token varchar(255) NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_date (event_date)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public function process_api_sync( $order_id ) {
        // Verifica configurações
        $carta_oferta = WCAI_Settings::get_carta_oferta();
        if ( empty( $carta_oferta ) ) {
            error_log( "[WCAI] ERRO: Carta Oferta não configurada." );
            return;
        }

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        error_log( "[WCAI] Iniciando sync Pedido #{$order_id}" );

        // Lógica original: Data do passeio define o evento
        $tour_date = $order->get_meta( 'tour_date' ); // Certifique-se que seu plugin salva isso com esse nome
        
        // Se não tiver tour_date, tenta pegar a data do pedido como fallback (opcional)
        if ( ! $tour_date ) {
            // $tour_date = $order->get_date_created()->date('Y-m-d'); 
            error_log( "[WCAI] Pedido #{$order_id} sem 'tour_date'. Abortando." );
            return; 
        }

        $event_date_db = date( 'Y-m-d', strtotime( $tour_date ) );
        
        // 1. Obter ou Criar Token do Evento (Lógica Original Mantida)
        $token = $this->get_event_token( $event_date_db );
        
        if ( ! $token ) {
            error_log( "[WCAI] Criando novo evento para data {$event_date_db}..." );
            $token = $this->create_remote_event( $order, $event_date_db );
            
            if ( $token ) {
                $this->save_event_token( $event_date_db, $token );
                error_log( "[WCAI] Evento criado. Token: {$token}" );
            } else {
                error_log( "[WCAI] Falha ao criar evento remoto." );
                return;
            }
        } else {
            error_log( "[WCAI] Token existente recuperado: {$token}" );
        }

        // 2. Enviar Participantes (Aqui entra a alteração para ler do BD)
        $participants_payload = $this->prepare_participants( $order );
        
        if ( ! empty( $participants_payload['participantes'] ) ) {
            error_log( "[WCAI] Enviando " . count($participants_payload['participantes']) . " participantes..." );
            $this->send_participants( $token, $participants_payload );
        } else {
            error_log( "[WCAI] Nenhum participante para enviar." );
        }
    }

    private function get_event_token( $date ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_evento_event_tokens';
        return $wpdb->get_var( $wpdb->prepare( "SELECT token FROM $table WHERE event_date = %s", $date ) );
    }

    private function save_event_token( $date, $token ) {
        global $wpdb;
        $table = $wpdb->prefix . 'wc_evento_event_tokens';
        $wpdb->replace( $table, array( 'event_date' => $date, 'token' => $token ), array( '%s', '%s' ) );
    }

    private function create_remote_event( $order, $date_db ) {
        $tour_date = $order->get_meta('tour_date');
        $time = ($tour_date) ? date('H:i:s', strtotime($tour_date)) : '08:00:00';
        
        $carta_oferta = WCAI_Settings::get_carta_oferta();

        $body = array(
            'dados' => array(
                'cartaOferta' => $carta_oferta,
                'nomeEvento' => 'Visitação e Turismo de Aventura',
                'dataInicio' => $date_db,
                'horaInicio' => $time,
                'dataFinal' => $date_db,
                'localEvento' => 'Cachoeira dos Ciganos/PR',
                'descricao' => 'Visitação e Turismo de Aventura'
            )
        );

        $response = $this->request( 'POST', 'seguroAventura/evento', $body );
        return isset( $response['dados']['token'] ) ? $response['dados']['token'] : false;
    }

    // --- AQUI ESTÁ A MUDANÇA CRÍTICA ---
    private function prepare_participants( $order ) {
        $list = array();
        
        // 1. DADOS DO TITULAR (Billing)
        $titular_dob = $order->get_meta('billing_birthdate') ?: $order->get_meta('_billing_birthdate');
        $titular_dob_db = $this->format_date_db( $titular_dob );

        $titular_cpf_raw = $order->get_meta('billing_cpf') ?: $order->get_meta('_billing_cpf');
        $titular_cpf = preg_replace('/[^0-9]/', '', $titular_cpf_raw);
        
        // Adiciona Titular como 001
        $list[] = array(
            'dados' => array(
                'numeroInscricao' => '001',
                'nome' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'cpf' => $titular_cpf,
                'dataNascimento' => $titular_dob_db,
                'email' => $order->get_billing_email(),
                'telefone' => preg_replace('/[^0-9]/', '', $order->get_billing_phone()),
                'estrangeiro' => 'N', 'nacionalidade' => 'Brasil'
            )
        );

        // 2. BUSCA ADICIONAIS NO BANCO DE DADOS (Nova Lógica)
        $db_participants = array();
        if ( class_exists('WCAI_Participants_DB') ) {
            $db_participants = WCAI_Participants_DB::get_by_order( $order->get_id() );
        }

        // Se o banco estiver vazio, tenta fallback no meta antigo
        if ( empty($db_participants) ) {
            $meta_p = $order->get_meta('_additional_participants');
            if(is_array($meta_p)) $db_participants = $meta_p;
        }

        if ( ! empty( $db_participants ) ) {
            $count = 2; // Começa do 002
            
            foreach ( $db_participants as $p ) {
                $nome = $p['nome_completo'];
                $cpf_add = preg_replace('/[^0-9]/', '', $p['cpf']);
                $nasc = $this->format_date_db( $p['data_nascimento'] );

                // PREVENÇÃO DE DUPLICIDADE:
                // Se o CPF deste participante for igual ao do Titular, PULA.
                // (Porque o titular já foi adicionado no passo 1)
                if ( !empty($titular_cpf) && $cpf_add === $titular_cpf ) {
                    continue;
                }

                if ( $nome && $cpf_add ) {
                    $list[] = array(
                        'dados' => array(
                            'numeroInscricao' => sprintf('%03d', $count),
                            'nome' => $nome,
                            'cpf' => $cpf_add,
                            'dataNascimento' => $nasc,
                            'email' => $order->get_billing_email(), // Usa email do titular
                            'estrangeiro' => 'N', 'nacionalidade' => 'Brasil'
                        )
                    );
                    $count++;
                }
            }
        }

        return array( 'participantes' => $list );
    }

    private function send_participants( $token, $data ) {
        foreach ( $data['participantes'] as $p ) {
            $nome = $p['dados']['nome'];
            // Atenção: Endpoint Original -> PUT seguroAventuraParticipante/evento/{token}
            $response = $this->request( 'PUT', 'seguroAventuraParticipante/evento/' . $token, array('participantes' => array($p)) );
            
            if ( isset( $response['sucesso'] ) && $response['sucesso'] ) {
                error_log( "[WCAI] Enviado: {$nome}" );
            } else {
                error_log( "[WCAI] Erro API {$nome}: " . print_r( $response, true ) );
            }
            // Pequena pausa para não floodar a API
            usleep(200000); 
        }
    }

    private function request( $method, $endpoint, $body ) {
        $carta_oferta = WCAI_Settings::get_carta_oferta(); // Token usado no Header

        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => $carta_oferta // Lógica específica da sua API antiga
            ),
            'body' => json_encode( $body )
        );
        
        $response = wp_remote_request( $this->api_base_url . $endpoint, $args );
        
        if ( is_wp_error( $response ) ) {
            error_log( "[WCAI] Erro Conexão: " . $response->get_error_message() );
            return false;
        }
        
        $body_response = wp_remote_retrieve_body( $response );
        return json_decode( $body_response, true );
    }

    // Helper simples para garantir Y-m-d
    private function format_date_db($date) {
        if(empty($date)) return '';
        if(strpos($date, '/') !== false) {
            $parts = explode('/', $date);
            if(count($parts) == 3) return $parts[2].'-'.$parts[1].'-'.$parts[0];
        }
        return date('Y-m-d', strtotime($date));
    }
}

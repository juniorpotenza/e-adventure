<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_API_Integration {

    private $api_base_url = 'https://www.roca.floripa.br/api/';

    public function __construct() {
        $status = WCAI_Settings::get_trigger_status();
        $status = $status ? sanitize_key( $status ) : 'completed';
        add_action( 'woocommerce_order_status_' . $status, array( $this, 'process_api_sync' ), 10, 1 );
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

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }

    public function process_api_sync( $order_id ) {
        $carta_oferta = WCAI_Settings::get_carta_oferta();

        if ( empty( $carta_oferta ) ) {
            error_log( '[WCAI] Sincronização não executada: credencial da API não configurada.' );
            return;
        }

        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        error_log( '[WCAI] Iniciando sincronização do pedido.' );

        $has_booking_item = false;

        foreach ( $order->get_items() as $item_id => $item ) {
            if ( ! $this->is_target_item( $item ) ) {
                continue;
            }

            $has_booking_item = true;
            $departure_id = WCAI_Data_Resolver::get_departure_id_for_order_item( $item );
            $event_datetime = WCAI_Data_Resolver::get_event_datetime_for_order_item( $order, $item );
            $event_date = $event_datetime ? wp_date( 'Y-m-d', WCAI_Data_Resolver::parse_timestamp( $event_datetime ) ) : '';
            $event_time = $event_datetime ? wp_date( 'H:i:s', WCAI_Data_Resolver::parse_timestamp( $event_datetime ) ) : '08:00:00';

            if ( ! $event_date ) {
                error_log( '[WCAI] Item do pedido sem data válida para o evento.' );
                continue;
            }

            $token = $this->get_event_token( $event_date );

            if ( ! $token ) {
                $token = $this->create_remote_event( $order, $item, $departure_id, $event_date, $event_time );

                if ( ! $token ) {
                    error_log( '[WCAI] Não foi possível criar/obter o evento remoto.' );
                    continue;
                }

                $this->save_event_token( $event_date, $token );
            }

            $reservation = $departure_id && class_exists( 'WCAI_Reservations' )
                ? WCAI_Reservations::get_by_order_item( $item_id )
                : false;

            $participants = $reservation
                ? $this->prepare_participants_from_reservation( $order, $reservation )
                : $this->prepare_legacy_participants( $order );

            if ( empty( $participants ) ) {
                error_log( '[WCAI] Nenhum participante disponível para envio.' );
                continue;
            }

            $this->send_participants( $token, array( 'participantes' => $participants ) );
        }

        if ( ! $has_booking_item ) {
            error_log( '[WCAI] Pedido sem itens WooAdventure.' );
        }
    }

    private function is_target_item( $item ) {
        $target_ids = WCAI_Settings::get_product_ids();

        return in_array( absint( $item->get_product_id() ), $target_ids, true )
            || in_array( absint( $item->get_variation_id() ), $target_ids, true );
    }

    private function get_event_token( $date ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wc_evento_event_tokens';

        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT token FROM $table WHERE event_date = %s",
                $date
            )
        );
    }

    private function save_event_token( $date, $token ) {
        global $wpdb;

        $table = $wpdb->prefix . 'wc_evento_event_tokens';

        $wpdb->replace(
            $table,
            array(
                'event_date' => $date,
                'token' => $token,
            ),
            array( '%s', '%s' )
        );
    }

    private function create_remote_event( $order, $item, $departure_id, $date_db, $time ) {
        $product = $item->get_product();
        $event_name = 'Visitação e Turismo de Aventura';
        $location = 'Cachoeira dos Ciganos/PR';
        $description = $product ? $product->get_name() : 'Visitação e Turismo de Aventura';

        if ( $departure_id ) {
            $custom_name = get_post_meta( $departure_id, '_wcai_event_name', true );
            $custom_location = get_post_meta( $departure_id, '_wcai_meeting_point', true );
            $custom_description = get_post_meta( $departure_id, '_wcai_event_description', true );

            if ( $custom_name ) {
                $event_name = sanitize_text_field( $custom_name );
            }

            if ( $custom_location ) {
                $location = sanitize_text_field( $custom_location );
            }

            if ( $custom_description ) {
                $description = sanitize_text_field( $custom_description );
            }
        }

        $body = array(
            'dados' => array(
                'cartaOferta' => WCAI_Settings::get_carta_oferta(),
                'nomeEvento' => $event_name,
                'dataInicio' => $date_db,
                'horaInicio' => $time,
                'dataFinal' => $date_db,
                'localEvento' => $location,
                'descricao' => $description,
            ),
        );

        $response = $this->request( 'POST', 'seguroAventura/evento', $body );

        return isset( $response['dados']['token'] ) ? sanitize_text_field( $response['dados']['token'] ) : false;
    }

    private function prepare_participants_from_reservation( $order, $reservation ) {
        $participants = array();

        if ( ! $reservation || ! class_exists( 'WCAI_Participants_DB' ) ) {
            return $participants;
        }

        $rows = WCAI_Participants_DB::get_by_reservation( absint( $reservation->id ) );

        foreach ( $rows as $index => $participant ) {
            $cpf = preg_replace( '/\D+/', '', $participant['cpf'] );
            $birthdate = $this->format_date_db( $participant['data_nascimento'] );
            $name = sanitize_text_field( $participant['nome_completo'] );

            if ( ! $name || ! $cpf || ! $birthdate ) {
                continue;
            }

            $participants[] = array(
                'dados' => array(
                    'numeroInscricao' => sprintf( '%03d', $index + 1 ),
                    'nome' => $name,
                    'cpf' => $cpf,
                    'dataNascimento' => $birthdate,
                    'email' => $order->get_billing_email(),
                    'telefone' => preg_replace( '/\D+/', '', $order->get_billing_phone() ),
                    'estrangeiro' => 'N',
                    'nacionalidade' => 'Brasil',
                ),
            );
        }

        return $participants;
    }

    private function prepare_legacy_participants( $order ) {
        $list = array();
        $titular_cpf = WCAI_Data_Resolver::get_billing_cpf( $order );
        $titular_birthdate = $this->format_date_db( WCAI_Data_Resolver::get_billing_birthdate( $order ) );
        $titular_name = WCAI_Data_Resolver::get_billing_name( $order );

        if ( $titular_name && $titular_cpf && $titular_birthdate ) {
            $list[] = array(
                'dados' => array(
                    'numeroInscricao' => '001',
                    'nome' => $titular_name,
                    'cpf' => $titular_cpf,
                    'dataNascimento' => $titular_birthdate,
                    'email' => $order->get_billing_email(),
                    'telefone' => preg_replace( '/\D+/', '', $order->get_billing_phone() ),
                    'estrangeiro' => 'N',
                    'nacionalidade' => 'Brasil',
                ),
            );
        }

        $legacy = $order->get_meta( '_additional_participants' );

        if ( is_array( $legacy ) ) {
            $counter = 2;

            foreach ( $legacy as $participant ) {
                $name = sanitize_text_field( isset( $participant['nome_completo'] ) ? $participant['nome_completo'] : '' );
                $cpf = preg_replace( '/\D+/', '', isset( $participant['cpf'] ) ? $participant['cpf'] : '' );
                $birthdate = $this->format_date_db( isset( $participant['data_nascimento'] ) ? $participant['data_nascimento'] : '' );

                if ( $name && $cpf && $birthdate && $cpf !== $titular_cpf ) {
                    $list[] = array(
                        'dados' => array(
                            'numeroInscricao' => sprintf( '%03d', $counter ),
                            'nome' => $name,
                            'cpf' => $cpf,
                            'dataNascimento' => $birthdate,
                            'email' => $order->get_billing_email(),
                            'estrangeiro' => 'N',
                            'nacionalidade' => 'Brasil',
                        ),
                    );

                    $counter++;
                }
            }
        }

        return $list;
    }

    private function send_participants( $token, $data ) {
        foreach ( $data['participantes'] as $participant ) {
            $response = $this->request(
                'PUT',
                'seguroAventuraParticipante/evento/' . rawurlencode( $token ),
                array( 'participantes' => array( $participant ) )
            );

            if ( isset( $response['sucesso'] ) && $response['sucesso'] ) {
                error_log( '[WCAI] Participante enviado com sucesso.' );
            } else {
                error_log( '[WCAI] Falha no envio de participante.' );
            }

            usleep( 200000 );
        }
    }

    private function request( $method, $endpoint, $body ) {
        $args = array(
            'method' => $method,
            'timeout' => 30,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => WCAI_Settings::get_carta_oferta(),
            ),
            'body' => wp_json_encode( $body ),
        );

        $response = wp_remote_request( $this->api_base_url . $endpoint, $args );

        if ( is_wp_error( $response ) ) {
            error_log( '[WCAI] Falha de conexão com a API externa.' );
            return false;
        }

        $status_code = (int) wp_remote_retrieve_response_code( $response );
        $body_response = wp_remote_retrieve_body( $response );
        $decoded = json_decode( $body_response, true );

        if ( $status_code < 200 || $status_code >= 300 ) {
            error_log( '[WCAI] API externa retornou HTTP ' . $status_code . '.' );
            return is_array( $decoded ) ? $decoded : false;
        }

        if ( ! is_array( $decoded ) ) {
            error_log( '[WCAI] API externa retornou resposta JSON inválida.' );
            return false;
        }

        return $decoded;
    }

    private function format_date_db( $date ) {
        $date = trim( (string) $date );

        if ( '' === $date ) {
            return '';
        }

        $formats = array( 'd/m/Y', 'Y-m-d' );

        foreach ( $formats as $format ) {
            $parsed = DateTimeImmutable::createFromFormat( $format, $date, wp_timezone() );

            if ( $parsed instanceof DateTimeImmutable ) {
                return $parsed->format( 'Y-m-d' );
            }
        }

        $timestamp = WCAI_Data_Resolver::parse_timestamp( $date );

        return $timestamp ? wp_date( 'Y-m-d', $timestamp ) : '';
    }
}

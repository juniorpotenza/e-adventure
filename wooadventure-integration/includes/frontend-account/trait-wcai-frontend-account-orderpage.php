<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Páginas do cliente (obrigado, "Ver pedido", lista de pedidos): chips de termo, explicação do QR,
// próximos passos e máscara de CPF nos campos adicionais do Woo.
trait WCAI_Frontend_Account_Order_Page {
    private $cpf_buffer_level = 0;
    private $cpf_holder = '';

    // HTML do bloco "extras" guardado para imprimir dentro da confirmação do pedido (tema de blocos).
    private static $deferred_extras = '';

    // Nomes todo minúsculos ou todo maiúsculos viram "Nome Sobrenome"; nomes em caixa mista nunca mudam.
    private function display_name( $name ) {
        $name = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $name ) ) );
        if ( '' === $name || ( mb_strtolower( $name, 'UTF-8' ) !== $name && mb_strtoupper( $name, 'UTF-8' ) !== $name ) ) return $name;
        $words = explode( ' ', mb_convert_case( $name, MB_CASE_TITLE, 'UTF-8' ) );
        foreach ( $words as $i => $word ) {
            if ( $i > 0 && in_array( mb_strtolower( $word, 'UTF-8' ), array( 'da', 'de', 'do', 'das', 'dos', 'e' ), true ) ) $words[ $i ] = mb_strtolower( $word, 'UTF-8' );
        }
        return implode( ' ', $words );
    }

    // Condições que faltam para liberar o QR: 'payment' (reserva não confirmada) e/ou 'term'.
    private function qr_missing_conditions( $signed, $payment_confirmed ) {
        $missing = array();
        if ( ! $payment_confirmed ) $missing[] = 'payment';
        if ( ! $signed ) $missing[] = 'term';
        return $missing;
    }

    // Uma vez acima da lista: a regra do ingresso (QR). Cada linha só leva a nota curta.
    private function qr_intro_text() {
        return __( 'O ingresso (QR) é liberado depois que o pagamento é confirmado e o termo de responsabilidade é assinado.', 'wooadventure-integration' );
    }

    private function qr_explanation( $signed, $payment_confirmed ) {
        $missing = $this->qr_missing_conditions( $signed, $payment_confirmed );
        if ( empty( $missing ) ) {
            return __( 'O ingresso (QR) está sendo gerado. Atualize a página em instantes.', 'wooadventure-integration' );
        }
        $labels = array( 'payment' => __( 'pagamento pendente', 'wooadventure-integration' ), 'term' => __( 'termo pendente', 'wooadventure-integration' ) );
        $names = array_map( function ( $key ) use ( $labels ) { return $labels[ $key ]; }, $missing );
        return $this->qr_intro_text() . ' ' . sprintf( __( 'Falta: %s.', 'wooadventure-integration' ), implode( ' e ', $names ) );
    }

    // Nota curta por linha (14px); a explicação completa fica no title.
    private function qr_short_note( $signed, $payment_confirmed, $incomplete ) {
        if ( $incomplete ) return __( 'Complete os dados para liberar o QR', 'wooadventure-integration' );
        if ( ! $payment_confirmed ) return $signed ? __( 'QR após o pagamento', 'wooadventure-integration' ) : __( 'QR após pagar e assinar', 'wooadventure-integration' );
        return $signed ? __( 'QR em geração', 'wooadventure-integration' ) : __( 'QR após assinar', 'wooadventure-integration' );
    }

    // Lista de pedidos: "Nome A (36), Nome B (41) +N".
    private function participants_summary_line( $participants ) {
        $parts = array();
        foreach ( array_slice( $participants, 0, 2 ) as $p ) {
            $name = $this->display_name( $p['nome_completo'] );
            $age = $this->participant_age( $p['data_nascimento'] );
            $parts[] = null === $age ? $name : $name . ' (' . $age . ')';
        }
        $line = implode( ', ', $parts );
        $extra = count( $participants ) - 2;
        return $extra > 0 ? $line . ' +' . $extra : $line;
    }

    // --- Próximos passos (página de pedido recebido) ---
    private function plain_order_total( $order ) {
        $text = html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' );
        return trim( str_replace( "\xC2\xA0", ' ', $text ) );
    }

    // Saídas do pedido (sem repetir): data/hora e ponto de encontro. Mesma leitura que render_departure_itemmeta usa no admin.
    private function order_departures( $order ) {
        $departures = array();
        $seen = array();
        foreach ( $order->get_items() as $item ) {
            $departure_id = WCAI_Data_Resolver::get_departure_id_for_order_item( $item );
            if ( ! $departure_id || isset( $seen[ $departure_id ] ) ) continue;
            $seen[ $departure_id ] = true;
            $departures[] = array(
                'timestamp'     => WCAI_Data_Resolver::parse_timestamp( WCAI_Data_Resolver::get_departure_start( $departure_id ) ),
                'meeting_point' => trim( (string) get_post_meta( $departure_id, '_wcai_meeting_point', true ) ),
            );
        }
        return $departures;
    }

    private function next_steps_lines( $order, $with_email_note = true ) {
        $lines = array();
        if ( 'cod' === $order->get_payment_method() ) {
            $lines[] = sprintf( __( 'Pagamento na entrega: leve o valor de %s no dia do passeio.', 'wooadventure-integration' ), $this->plain_order_total( $order ) );
        }

        foreach ( $this->order_departures( $order ) as $departure ) {
            if ( $departure['timestamp'] ) {
                $lines[] = sprintf( __( 'Data e horário de saída: %s.', 'wooadventure-integration' ), wp_date( 'd/m/Y H:i', $departure['timestamp'] ) );
            }
            if ( '' !== $departure['meeting_point'] ) {
                $lines[] = sprintf( __( 'Ponto de encontro: %s.', 'wooadventure-integration' ), $departure['meeting_point'] );
            }
        }

        $policy = trim( (string) get_option( 'wcai_cancellation_policy', '' ) );
        if ( '' !== $policy ) {
            $lines[] = sprintf( __( 'Política de cancelamento: %s', 'wooadventure-integration' ), $policy );
        }
        if ( $with_email_note ) {
            $lines[] = __( 'Você receberá este resumo por e-mail.', 'wooadventure-integration' );
        }
        return $lines;
    }

    // Tema de blocos: o conteúdo do template renderiza antes do wp_head e o Woo dispara os hooks do pedido já nessa fase,
    // então o HTML fica guardado (não sai antes do <html>) e entra pelos filtros dos blocos de confirmação.
    private function uses_confirmation_blocks() {
        return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme()
            && function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' )
            && ! did_action( 'wp_head' );
    }

    // Rede de segurança: se nenhum bloco de confirmação consumiu o HTML (template sem esses blocos), imprime no rodapé.
    public function flush_deferred_extras() {
        echo self::$deferred_extras;
        self::$deferred_extras = '';
    }

    // Imprime uma única vez: o primeiro bloco filtrado (totais; senão informações adicionais) esvazia o HTML guardado.
    public function append_extras_to_confirmation_block( $content ) {
        $html = self::$deferred_extras;
        self::$deferred_extras = '';
        return $content . $html;
    }

    // Tradução errada do pt_BR do Woo ("Order number:" virou "Pedidor #:").
    public function fix_order_number_label( $translated, $text = '', $domain = '' ) {
        if ( 'woocommerce' !== $domain ) return $translated;
        if ( 'Pedidor #:' === $translated ) return 'Nº do pedido:';
        return 'Pedidor #' === $translated ? 'Nº do pedido' : $translated;
    }

    // O aria-label do link da lista precisa começar pelo texto visível ("Visualizar pedido N").
    public function fix_orders_action_labels( $actions, $order ) {
        if ( is_array( $actions ) && isset( $actions['view']['name'] ) && is_object( $order ) ) {
            $actions['view']['aria-label'] = sprintf( '%s pedido %s', $actions['view']['name'], $order->get_order_number() );
        }
        return $actions;
    }

    // --- CPF do titular mascarado nos "campos adicionais" do Woo (páginas do cliente) ---
    // $loose: aceita também 11 dígitos puros (só usar em HTML que contém apenas campos adicionais).
    public function mask_cpf_in_html( $html, $loose = false ) {
        $pattern = $loose ? '/(?<!\d)\d{3}\.?\d{3}\.?\d{3}-?\d{2}(?!\d)/' : '/(?<!\d)\d{3}\.\d{3}\.\d{3}-\d{2}(?!\d)/';
        return preg_replace_callback( $pattern, function ( $m ) { return WCAI_Utils::mask_cpf( $m[0] ); }, (string) $html );
    }

    // Nascimento do titular: só o ano aparece ("**/**/1990").
    public function mask_birthdate_in_html( $html ) {
        return preg_replace( '#(?<!\d)\d{2}/\d{2}/(\d{4})(?!\d)#', '**/**/$1', (string) $html );
    }

    // Tema de blocos: bloco de campos adicionais da confirmação do pedido (filtro core render_block).
    public function mask_cpf_in_additional_fields_block( $content ) {
        return $this->mask_birthdate_in_html( $this->mask_cpf_in_html( $content, true ) );
    }

    // Template clássico: o Woo 11 não tem filtro de valor para campos adicionais, então o HTML do detalhe do cliente
    // passa por um buffer aberto após a tabela do pedido e fechado em ..._after_customer_details.
    // O buffer tem callback que sempre mascara: se o Woo não disparar o hook de fechamento (ex.: detalhes do cliente
    // ocultos para admin ou convidado), o flush do fim da requisição também sai mascarado.
    public function begin_cpf_mask_buffer( $order ) {
        if ( $this->cpf_buffer_level || ! is_object( $order ) ) return;
        $this->cpf_holder = $this->safe_cpf_pad( $order->get_meta( 'billing_cpf' ) ?: $order->get_meta( '_billing_cpf' ) );
        ob_start( array( $this, 'mask_cpf_buffer' ) );
        $this->cpf_buffer_level = ob_get_level();
    }

    // Callback do buffer: roda no flush (hook de fechamento ou fim da requisição).
    public function mask_cpf_buffer( $html ) {
        if ( '' !== $this->cpf_holder ) {
            $html = str_replace( $this->cpf_holder, WCAI_Utils::mask_cpf( $this->cpf_holder ), (string) $html );
        }
        return $this->mask_birthdate_in_html( $this->mask_cpf_in_html( $html ) );
    }

    // Fecha uma única vez; se outro plugin deixou um buffer aberto por cima, não mexe (o callback mascara no fim).
    public function end_cpf_mask_buffer( $order = null ) {
        if ( ! $this->cpf_buffer_level ) return;
        $level = $this->cpf_buffer_level;
        $this->cpf_buffer_level = 0;
        if ( ob_get_level() === $level ) ob_end_flush();
    }

    // Dono do pedido, gestor da loja ou quem tem a chave do pedido na URL (convidado na página de pedido recebido).
    // Todo renderizador de dados de participantes (nomes, QR, link do termo com a chave) passa por aqui.
    private function can_view_order_participants( $order ) {
        if ( ! is_object( $order ) ) return false;
        $user_id = (int) get_current_user_id();
        if ( $user_id && (int) $order->get_customer_id() === $user_id ) return true;
        if ( current_user_can( 'manage_woocommerce' ) ) return true;
        $key = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- chave do pedido, não ação.
        return '' !== $key && hash_equals( (string) $order->get_order_key(), $key );
    }
}

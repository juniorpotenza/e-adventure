<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Páginas do cliente (obrigado, "Ver pedido", lista de pedidos): chips de termo, explicação do QR,
// próximos passos e máscara de CPF nos campos adicionais do Woo.
trait WCAI_Frontend_Account_Order_Page {
    private $cpf_buffer_level = 0;
    private $cpf_holder = '';

    private function terms_summary( $order, $participants ) {
        $signed = 0;
        foreach ( $participants as $p ) {
            if ( $this->participant_is_signed( $order, $p ) ) $signed++;
        }
        $total = count( $participants );
        return array( 'total' => $total, 'signed' => $signed, 'pending' => $total - $signed );
    }

    // Texto + cor (nunca só ícone): o estado do termo é sempre legível.
    private function term_chip_html( $signed ) {
        $label = $signed ? __( 'Termo assinado', 'wooadventure-integration' ) : __( 'Termo pendente', 'wooadventure-integration' );
        $style = $signed ? 'color:#1e7e34; background:#e6f4ea; border:1px solid #1e7e34;' : 'color:#7a4a00; background:#fff3cd; border:1px solid #b36b00;';
        return '<span class="wcai-chip ' . ( $signed ? 'wcai-chip-signed' : 'wcai-chip-pending' ) . '" style="' . $style . ' display:inline-block; font-size:12px; font-weight:600; padding:2px 8px; border-radius:12px; white-space:nowrap;">' . esc_html( $label ) . '</span>';
    }

    // Condições que faltam para liberar o QR: 'payment' (reserva não confirmada) e/ou 'term'.
    private function qr_missing_conditions( $signed, $payment_confirmed ) {
        $missing = array();
        if ( ! $payment_confirmed ) $missing[] = 'payment';
        if ( ! $signed ) $missing[] = 'term';
        return $missing;
    }

    private function qr_explanation( $signed, $payment_confirmed ) {
        $missing = $this->qr_missing_conditions( $signed, $payment_confirmed );
        if ( empty( $missing ) ) {
            return __( 'O QR code está sendo gerado. Atualize a página em instantes.', 'wooadventure-integration' );
        }
        $labels = array( 'payment' => __( 'pagamento pendente', 'wooadventure-integration' ), 'term' => __( 'termo pendente', 'wooadventure-integration' ) );
        $names = array_map( function ( $key ) use ( $labels ) { return $labels[ $key ]; }, $missing );
        return __( 'O QR code do ingresso é liberado depois que o pagamento é confirmado e o termo é assinado.', 'wooadventure-integration' )
            . ' ' . sprintf( __( 'Falta: %s.', 'wooadventure-integration' ), implode( ' e ', $names ) );
    }

    // Botão principal "Assinar termos": só com reserva confirmada, pagamento na entrega ou pedido em processamento.
    private function terms_cta_visible( $order, $participants, $pending ) {
        if ( $pending < 1 ) return false;
        if ( 'cod' === $order->get_payment_method() || 'processing' === $order->get_status() ) return true;
        foreach ( $participants as $p ) {
            if ( ! empty( $p['reservation_id'] ) && $this->participant_reservation_confirmed( $p ) ) return true;
        }
        return false;
    }

    // Lista de pedidos: "Nome A (36), Nome B (41) +N".
    private function participants_summary_line( $participants ) {
        $parts = array();
        foreach ( array_slice( $participants, 0, 2 ) as $p ) {
            $name = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $p['nome_completo'] ) ) );
            $age = $this->participant_age( $p['data_nascimento'] );
            $parts[] = null === $age ? $name : $name . ' (' . $age . ')';
        }
        $line = implode( ', ', $parts );
        $extra = count( $participants ) - 2;
        return $extra > 0 ? $line . ' +' . $extra : $line;
    }

    private function terms_chip_summary_html( $summary ) {
        $tip = sprintf( __( 'Termos assinados: %1$d de %2$d. Cada participante assina o seu termo; o QR code do ingresso só é liberado depois disso e da confirmação do pagamento.', 'wooadventure-integration' ), $summary['signed'], $summary['total'] );
        $done = $summary['pending'] < 1;
        $style = $done ? 'color:#1e7e34; background:#e6f4ea; border:1px solid #1e7e34;' : 'color:#7a4a00; background:#fff3cd; border:1px solid #b36b00;';
        return '<span class="wcai-chip" title="' . esc_attr( $tip ) . '" style="' . $style . ' display:inline-block; font-size:12px; font-weight:600; padding:2px 8px; border-radius:12px; white-space:nowrap;">'
            . esc_html( sprintf( 'Termos %d/%d', $summary['signed'], $summary['total'] ) )
            . '<span class="screen-reader-text" style="position:absolute; left:-9999px;"> ' . esc_html( $tip ) . '</span></span>';
    }

    // --- Próximos passos (página de pedido recebido) ---
    private function plain_order_total( $order ) {
        $text = html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' );
        return trim( str_replace( "\xC2\xA0", ' ', $text ) );
    }

    private function next_steps_lines( $order ) {
        $lines = array();
        if ( 'cod' === $order->get_payment_method() ) {
            $lines[] = sprintf( __( 'Pagamento na entrega: leve o valor de %s no dia do passeio.', 'wooadventure-integration' ), $this->plain_order_total( $order ) );
        }

        $seen = array();
        foreach ( $order->get_items() as $item ) {
            // Mesma leitura da saída que render_departure_itemmeta usa no admin.
            $departure_id = WCAI_Data_Resolver::get_departure_id_for_order_item( $item );
            if ( ! $departure_id || isset( $seen[ $departure_id ] ) ) continue;
            $seen[ $departure_id ] = true;

            $timestamp = WCAI_Data_Resolver::parse_timestamp( WCAI_Data_Resolver::get_departure_start( $departure_id ) );
            if ( $timestamp ) {
                $lines[] = sprintf( __( 'Data e horário de saída: %s.', 'wooadventure-integration' ), wp_date( 'd/m/Y H:i', $timestamp ) );
            }
            $meeting_point = trim( (string) get_post_meta( $departure_id, '_wcai_meeting_point', true ) );
            if ( '' !== $meeting_point ) {
                $lines[] = sprintf( __( 'Ponto de encontro: %s.', 'wooadventure-integration' ), $meeting_point );
            }
        }

        $lines[] = __( 'Você receberá este resumo por e-mail.', 'wooadventure-integration' );
        return $lines;
    }

    private function render_next_steps( $order ) {
        echo '<section class="wcai-next-steps" aria-labelledby="wcai-next-steps-title" style="margin:20px 0;">';
        echo '<h3 id="wcai-next-steps-title" style="font-size:16px; font-weight:600;">' . esc_html__( 'Próximos passos', 'wooadventure-integration' ) . '</h3><ul>';
        foreach ( $this->next_steps_lines( $order ) as $line ) {
            echo '<li>' . esc_html( $line ) . '</li>';
        }
        echo '</ul></section>';
    }

    // --- CPF do titular mascarado nos "campos adicionais" do Woo (páginas do cliente) ---
    // $loose: aceita também 11 dígitos puros (só usar em HTML que contém apenas campos adicionais).
    public function mask_cpf_in_html( $html, $loose = false ) {
        $pattern = $loose ? '/(?<!\d)\d{3}\.?\d{3}\.?\d{3}-?\d{2}(?!\d)/' : '/(?<!\d)\d{3}\.\d{3}\.\d{3}-\d{2}(?!\d)/';
        return preg_replace_callback( $pattern, function ( $m ) { return WCAI_Utils::mask_cpf( $m[0] ); }, (string) $html );
    }

    // Tema de blocos: bloco de campos adicionais da confirmação do pedido (filtro core render_block).
    public function mask_cpf_in_additional_fields_block( $content ) {
        return $this->mask_cpf_in_html( $content, true );
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
        return $this->mask_cpf_in_html( $html );
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

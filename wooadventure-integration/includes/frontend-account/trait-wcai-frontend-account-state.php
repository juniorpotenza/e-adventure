<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Estado do termo de responsabilidade de um pedido: UMA regra para lista, "Ver pedido", obrigado, painel e modal.
trait WCAI_Frontend_Account_State {
    // Registro sem nome utilizável, ou sem CPF válido e sem nascimento (pedidos antigos): não conta como termo pendente.
    private function participant_is_incomplete( $order, $p ) {
        if ( $this->participant_is_signed( $order, $p ) ) return false;
        if ( ! $this->is_valid_participant_name( $p['nome_completo'] ) ) return true;
        return ! WCAI_Utils::is_valid_cpf( $this->safe_cpf_pad( $p['cpf'] ) ) && null === $this->participant_age( $p['data_nascimento'] );
    }

    private function terms_summary( $order, $participants ) {
        $signed = 0;
        $total = 0;
        $incomplete = 0;
        foreach ( $participants as $p ) {
            if ( $this->participant_is_incomplete( $order, $p ) ) { $incomplete++; continue; }
            $total++;
            if ( $this->participant_is_signed( $order, $p ) ) $signed++;
        }
        return array( 'total' => $total, 'signed' => $signed, 'pending' => $total - $signed, 'incomplete' => $incomplete );
    }

    // Resumo + 'state': closed (pedido encerrado) | none (nenhum participante com dados) | pending | incomplete | signed.
    private function order_terms_state( $order, $participants ) {
        $summary = $this->terms_summary( $order, $participants );
        if ( $this->is_order_locked( $order ) ) $state = 'closed';
        elseif ( $summary['total'] < 1 ) $state = 'none';
        elseif ( $summary['pending'] > 0 ) $state = 'pending';
        elseif ( $summary['incomplete'] > 0 ) $state = 'incomplete';
        else $state = 'signed';
        $summary['state'] = $state;
        return $summary;
    }

    private function chip_html( $class, $label, $tone, $tip = '' ) {
        $tones = array(
            'ok'      => 'color:#1e7e34; background:#e6f4ea; border:1px solid #1e7e34;',
            'warn'    => 'color:#7a4a00; background:#fff3cd; border:1px solid #b36b00;',
            'neutral' => 'color:#50575e; background:#f0f0f1; border:1px solid #8c8f94;',
        );
        $title = '' === $tip ? '' : ' title="' . esc_attr( $tip ) . '"';
        $sr = '' === $tip ? '' : '<span class="wcai-sr"> ' . esc_html( $tip ) . '</span>';
        return '<span class="wcai-chip ' . esc_attr( $class ) . '"' . $title . ' style="' . $tones[ $tone ] . ' display:inline-block; font-size:13px; font-weight:600; padding:2px 8px; border-radius:12px; white-space:nowrap;">' . esc_html( $label ) . $sr . '</span>';
    }

    private function incomplete_chip_html() {
        return $this->chip_html( 'wcai-chip-incomplete', __( 'Dados incompletos', 'wooadventure-integration' ), 'neutral' );
    }

    // Chip neutro de pedido encerrado ("Concluído", "Cancelado"...): nunca "Termo pendente".
    private function closed_chip_html( $order = null ) {
        $label = is_object( $order ) ? wc_get_order_status_name( $order->get_status() ) : __( 'Encerrado', 'wooadventure-integration' );
        return $this->chip_html( 'wcai-chip-closed', $label, 'neutral' );
    }

    // Texto + cor (nunca só ícone): o estado do termo é sempre legível.
    private function term_chip_html( $signed ) {
        $label = $signed ? __( 'Termo assinado', 'wooadventure-integration' ) : __( 'Termo pendente', 'wooadventure-integration' );
        return $this->chip_html( $signed ? 'wcai-chip-signed' : 'wcai-chip-pending', $label, $signed ? 'ok' : 'warn' );
    }

    // Chip da lista de pedidos, um por estado de order_terms_state().
    private function terms_chip_summary_html( $summary, $order = null ) {
        $state = isset( $summary['state'] ) ? $summary['state'] : ( $summary['pending'] < 1 ? 'signed' : 'pending' );
        if ( 'closed' === $state ) return $this->closed_chip_html( $order );
        if ( 'none' === $state ) return $this->chip_html( 'wcai-chip-none', __( 'Sem participantes', 'wooadventure-integration' ), 'neutral' );
        if ( 'incomplete' === $state ) return $this->incomplete_chip_html();
        $tip = sprintf( __( 'Termos de responsabilidade assinados: %1$d de %2$d. Cada participante assina o seu; o ingresso (QR) só é liberado depois disso e da confirmação do pagamento.', 'wooadventure-integration' ), $summary['signed'], $summary['total'] );
        if ( 'signed' === $state ) return $this->chip_html( 'wcai-chip-signed', __( 'Termos assinados', 'wooadventure-integration' ), 'ok', $tip );
        return $this->chip_html( 'wcai-chip-pending', sprintf( __( 'Termo pendente (%d)', 'wooadventure-integration' ), (int) $summary['pending'] ), 'warn', $tip );
    }

    // Link do termo: sempre em nova aba e com o aviso para leitor de tela (lista, Ver pedido, obrigado, painel, modal).
    private function terms_link_html( $url, $label, $class, $aria = '' ) {
        $new_tab = __( '(abre em nova aba)', 'wooadventure-integration' );
        $aria_attr = '' === $aria ? '' : ' aria-label="' . esc_attr( $aria . ' ' . $new_tab ) . '"';
        return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $url ) . '" target="_blank" rel="noopener"' . $aria_attr . '>'
            . esc_html( $label ) . '<span class="wcai-sr"> ' . esc_html( $new_tab ) . '</span></a>';
    }

    // Botão principal "Assinar termos": só com reserva confirmada, pagamento na entrega ou pedido em processamento.
    private function terms_cta_visible( $order, $participants, $pending ) {
        if ( $pending < 1 || $this->is_order_locked( $order ) ) return false;
        if ( 'cod' === $order->get_payment_method() || 'processing' === $order->get_status() ) return true;
        foreach ( $participants as $p ) {
            if ( ! empty( $p['reservation_id'] ) && $this->participant_reservation_confirmed( $p ) ) return true;
        }
        return false;
    }
}

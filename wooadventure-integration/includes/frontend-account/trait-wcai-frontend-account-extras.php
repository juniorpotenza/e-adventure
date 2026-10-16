<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Bloco "extras" das páginas de pedido recebido e "Ver pedido": lista de participantes + próximos passos.
trait WCAI_Frontend_Account_Extras {
    // Ícones dos títulos (SVG inline, decorativo): herdam a cor do texto.
    private function title_icon_svg( $name ) {
        $paths = array(
            'people' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
            'steps'  => '<path d="M4 22V4"/><path d="M4 4h13l-2 4 2 4H4"/>',
        );
        return '<svg aria-hidden="true" focusable="false" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $paths[ $name ] . '</svg>';
    }

    // Mesmo bloco na página de pedido recebido e em "Ver pedido"; pedido encerrado não tem mais próximos passos.
    private function render_next_steps( $order ) {
        if ( $this->is_order_locked( $order ) ) return;
        $received = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' );
        echo '<section class="wcai-next-steps" aria-labelledby="wcai-next-steps-title">';
        echo '<h3 id="wcai-next-steps-title" class="wcai-extra-title">' . $this->title_icon_svg( 'steps' ) . '<span>' . esc_html__( 'Próximos passos', 'wooadventure-integration' ) . '</span></h3><ul>';
        foreach ( $this->next_steps_lines( $order, $received ) as $line ) {
            echo '<li>' . esc_html( $line ) . '</li>';
        }
        $phone = trim( (string) get_option( 'wcai_contact_phone', '' ) );
        $tel = preg_replace( '/[^0-9+]/', '', $phone );
        if ( '' !== $tel ) {
            echo '<li>' . esc_html__( 'Dúvidas? Ligue:', 'wooadventure-integration' ) . ' <a href="' . esc_url( 'tel:' . $tel ) . '">' . esc_html( $phone ) . '</a></li>';
        }
        echo '</ul></section>';
    }

    // Chip da linha: pedido encerrado nunca mostra "Termo pendente".
    private function participant_chip_html( $order, $r ) {
        if ( $r['incomplete'] ) return $this->incomplete_chip_html();
        if ( $r['signed'] ) return $this->term_chip_html( true );
        return $this->is_order_locked( $order ) ? $this->closed_chip_html( $order ) : $this->term_chip_html( false );
    }

    private function extras_rows( $order, $participants ) {
        $rows = array();
        foreach ( $participants as $p ) {
            $hash = isset( $p['ticket_hash'] ) ? $p['ticket_hash'] : '';
            $signed = $this->participant_is_signed( $order, $p );
            $confirmed = $this->participant_reservation_confirmed( $p );
            $incomplete = $this->participant_is_incomplete( $order, $p );
            $rows[] = array(
                'p'          => $p,
                'name'       => $this->display_name( $p['nome_completo'] ),
                'signed'     => $signed,
                'incomplete' => $incomplete,
                'hash'       => $hash,
                'show_qr'    => $signed && ! empty( $hash ) && $confirmed,
                'qr_note'    => $this->qr_short_note( $signed, $confirmed, $incomplete ),
                'qr_tip'     => $incomplete ? __( 'Complete os dados do participante para liberar o ingresso (QR).', 'wooadventure-integration' ) : $this->qr_explanation( $signed, $confirmed ),
                'cpf'        => $incomplete ? __( 'não informado', 'wooadventure-integration' ) : WCAI_Utils::mask_cpf( $this->safe_cpf_pad( $p['cpf'] ) ),
            );
        }
        return $rows;
    }

    // Lista de participantes + "Assinar termos" + próximos passos: um único renderizador para obrigado e "Ver pedido".
    // $wrap: no tema de blocos a seção entra na confirmação como alignwide, na mesma coluna dos blocos do Woo.
    private function order_extras_html( $order, $participants, $wrap ) {
        $terms_url = $this->get_terms_url( $order );
        $summary = $this->terms_summary( $order, $participants );
        $closed = $this->is_order_locked( $order );
        $rows = $this->extras_rows( $order, $participants );
        // Coluna "Ações" só existe quando alguma linha tem ação (evita coluna vazia).
        $has_actions = (bool) array_filter( array_column( $rows, 'show_qr' ) );
        // A regra do ingresso (QR) aparece uma vez; só quando alguma linha ainda não tem QR.
        $show_intro = ! $closed && count( array_filter( array_column( $rows, 'show_qr' ) ) ) < count( $rows );

        ob_start();
        ?>
        <section class="wcai-order-extra<?php echo $wrap ? ' alignwide' : ''; ?>">
        <section class="wcai-order-details-participants">
            <h3 class="wcai-extra-title"><?php echo $this->title_icon_svg( 'people' ); ?><span><?php echo esc_html__( 'Lista de Participantes', 'wooadventure-integration' ); ?></span></h3>

            <?php if ( $terms_url && $this->terms_cta_visible( $order, $participants, $summary['pending'] ) ) : ?>
                <p><?php echo $this->terms_link_html( $terms_url, sprintf( $summary['pending'] > 1 ? 'Assinar termos (%d pendentes)' : 'Assinar termos (%d pendente)', $summary['pending'] ), 'wcai-sign-all' ); ?></p>
            <?php endif; ?>
            <?php if ( $show_intro ) : ?><p class="wcai-qr-intro"><?php echo esc_html( $this->qr_intro_text() ); ?></p><?php endif; ?>

            <table class="wcai-pt">
                <thead>
                    <tr>
                        <th scope="col">Nome / Idade</th>
                        <th scope="col"><?php echo esc_html__( 'Termo de responsabilidade', 'wooadventure-integration' ); ?></th>
                        <th scope="col" class="wcai-pt-center"><?php echo esc_html__( 'Ingresso (QR)', 'wooadventure-integration' ); ?></th>
                        <?php if ( $has_actions ) : ?><th scope="col">Ações</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rows as $r ) : $p = $r['p']; $qr_url = false; ?>
                    <tr>
                        <td data-label="Participante">
                            <strong><?php echo esc_html( $r['name'] ); ?></strong>
                            <?php echo $this->get_age_badge_html( $p['data_nascimento'] ); ?>
                            <small>CPF: <?php echo esc_html( $r['cpf'] ); ?></small>
                        </td>
                        <td data-label="Termo de responsabilidade">
                            <?php echo $this->participant_chip_html( $order, $r ); ?>
                            <?php if ( ! $r['signed'] && ! $r['incomplete'] && $terms_url && ! $closed ) : ?>
                                <div class="wcai-pt-action"><?php echo $this->terms_link_html( $terms_url, __( 'Assinar termo', 'wooadventure-integration' ), 'wcai-sign-one', sprintf( __( 'Assinar termo de %s', 'wooadventure-integration' ), $r['name'] ) ); ?></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Ingresso (QR)" class="wcai-pt-center">
                            <?php if ( $r['show_qr'] ) : ?>
                                <?php $qr_url = class_exists( 'WCAI_Assinatura' ) ? WCAI_Assinatura::generate_local_qr( $r['hash'], absint( $p['id'] ) ) : false; ?>
                                <?php if ( $qr_url && ! is_wp_error( $qr_url ) ) : ?>
                                    <img src="<?php echo esc_url( $qr_url ); ?>" style="width:80px; height:80px; border:1px solid #ccc; padding:2px;" alt="QR Code de <?php echo esc_attr( $r['name'] ); ?>">
                                <?php else : ?>
                                    <span style="color:#d63638;">QR indisponível</span>
                                <?php endif; ?>
                                <div style="font-size:14px; color:#1e7e34; font-weight:bold; margin-top:2px;">QR disponível</div>
                            <?php else : ?>
                                <small class="wcai-qr-note" title="<?php echo esc_attr( $r['qr_tip'] ); ?>"><?php echo esc_html( $r['qr_note'] ); ?></small>
                            <?php endif; ?>
                        </td>
                        <?php if ( $has_actions ) : ?>
                        <td data-label="Ações">
                            <?php if ( $r['show_qr'] ) : ?>
                                <?php $link_whats = 'https://wa.me/?text=' . urlencode( 'Olá ' . $r['name'] . ', aqui está seu ingresso para o passeio! Acesse o QR Code: ' . ( $qr_url && ! is_wp_error( $qr_url ) ? $qr_url : '' ) ); ?>
                                <a href="<?php echo esc_url( $link_whats ); ?>" target="_blank" rel="noopener" class="wcai-whatsapp">Enviar por WhatsApp</a>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php $this->render_next_steps( $order ); ?>
        </section>
        </section>
        <?php
        return ob_get_clean();
    }
}

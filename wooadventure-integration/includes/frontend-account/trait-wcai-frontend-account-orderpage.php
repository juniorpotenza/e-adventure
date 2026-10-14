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

    private function incomplete_chip_html() {
        return '<span class="wcai-chip wcai-chip-incomplete" style="color:#50575e; background:#f0f0f1; border:1px solid #8c8f94; display:inline-block; font-size:12px; font-weight:600; padding:2px 8px; border-radius:12px; white-space:nowrap;">' . esc_html__( 'Dados incompletos', 'wooadventure-integration' ) . '</span>';
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
        if ( $pending < 1 || $this->is_order_locked( $order ) ) return false;
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
            $name = $this->display_name( $p['nome_completo'] );
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

    private function next_steps_lines( $order, $with_email_note = true ) {
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

        $policy = trim( (string) get_option( 'wcai_cancellation_policy', '' ) );
        if ( '' !== $policy ) {
            $lines[] = sprintf( __( 'Política de cancelamento: %s', 'wooadventure-integration' ), $policy );
        }
        if ( $with_email_note ) {
            $lines[] = __( 'Você receberá este resumo por e-mail.', 'wooadventure-integration' );
        }
        return $lines;
    }

    // Mesmo bloco na página de pedido recebido e em "Ver pedido"; pedido encerrado não tem mais próximos passos.
    private function render_next_steps( $order ) {
        if ( $this->is_order_locked( $order ) ) return;
        $received = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' );
        echo '<section class="wcai-next-steps" aria-labelledby="wcai-next-steps-title" style="margin:20px 0;">';
        echo '<h3 id="wcai-next-steps-title" style="font-size:16px; font-weight:600;">' . esc_html__( 'Próximos passos', 'wooadventure-integration' ) . '</h3><ul>';
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

    // Lista de participantes + "Assinar termos" + próximos passos: um único renderizador para obrigado e "Ver pedido".
    // $wrap: dentro da confirmação do tema de blocos a seção não está num container do tema; alinha ao conteúdo da página.
    private function order_extras_html( $order, $participants, $wrap ) {
        $terms_url = $this->get_terms_url( $order );
        $summary = $this->terms_summary( $order, $participants );
        $closed = $this->is_order_locked( $order );
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
                'qr_note'    => $incomplete ? __( 'Complete os dados do participante para liberar o ingresso.', 'wooadventure-integration' ) : $this->qr_explanation( $signed, $confirmed ),
                'cpf'        => $incomplete ? __( 'não informado', 'wooadventure-integration' ) : WCAI_Utils::mask_cpf( $this->safe_cpf_pad( $p['cpf'] ) ),
            );
        }
        // Coluna "Ações" só existe quando alguma linha tem ação (evita coluna vazia).
        $has_actions = (bool) array_filter( array_column( $rows, 'show_qr' ) );
        $wrap_style = $wrap ? 'max-width:var(--wp--style--global--content-size,720px);margin:2rem auto;padding:0 1.5rem' : '';

        ob_start();
        ?>
        <section class="wcai-order-extra"<?php echo $wrap_style ? ' style="' . esc_attr( $wrap_style ) . '"' : ''; ?>>
        <section class="wcai-order-details-participants">
            <h2 class="woocommerce-column__title">👥 Lista de Participantes</h2>

            <?php if ( $terms_url && $this->terms_cta_visible( $order, $participants, $summary['pending'] ) ) : ?>
                <p><a class="wcai-sign-all" href="<?php echo esc_url( $terms_url ); ?>"><?php echo esc_html( sprintf( $summary['pending'] > 1 ? 'Assinar termos (%d pendentes)' : 'Assinar termos (%d pendente)', $summary['pending'] ) ); ?></a></p>
            <?php endif; ?>

            <table class="wcai-pt">
                <thead>
                    <tr>
                        <th scope="col">Nome / Idade</th>
                        <th scope="col"><?php echo esc_html__( 'Termo', 'wooadventure-integration' ); ?></th>
                        <th scope="col" class="wcai-pt-center"><?php echo esc_html__( 'Ingresso (QR Code)', 'wooadventure-integration' ); ?></th>
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
                        <td data-label="Termo">
                            <?php echo $r['incomplete'] ? $this->incomplete_chip_html() : $this->term_chip_html( $r['signed'] ); ?>
                            <?php if ( ! $r['signed'] && ! $r['incomplete'] && $terms_url && ! $closed ) : ?>
                                <div class="wcai-pt-action"><a class="wcai-sign-one" href="<?php echo esc_url( $terms_url ); ?>" rel="noopener" aria-label="<?php echo esc_attr( sprintf( __( 'Assinar termo de %s', 'wooadventure-integration' ), $r['name'] ) ); ?>"><?php echo esc_html__( 'Assinar termo', 'wooadventure-integration' ); ?></a></div>
                            <?php endif; ?>
                        </td>
                        <td data-label="Ingresso (QR Code)" class="wcai-pt-center">
                            <?php if ( $r['show_qr'] ) : ?>
                                <?php $qr_url = class_exists( 'WCAI_Assinatura' ) ? WCAI_Assinatura::generate_local_qr( $r['hash'], absint( $p['id'] ) ) : false; ?>
                                <?php if ( $qr_url && ! is_wp_error( $qr_url ) ) : ?>
                                    <img src="<?php echo esc_url( $qr_url ); ?>" style="width:80px; height:80px; border:1px solid #ccc; padding:2px;" alt="QR Code de <?php echo esc_attr( $r['name'] ); ?>">
                                <?php else : ?>
                                    <span style="color:#d63638;">QR indisponível</span>
                                <?php endif; ?>
                                <div style="font-size:12px; color:#1e7e34; font-weight:bold; margin-top:2px;">Ativo</div>
                            <?php else : ?>
                                <small class="wcai-qr-note"><?php echo esc_html( $r['qr_note'] ); ?></small>
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

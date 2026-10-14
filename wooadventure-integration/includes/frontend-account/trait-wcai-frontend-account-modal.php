<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Frontend_Account_Modal {
    // --- 3. FRONTEND (TABELAS) ---
    public function add_column($columns) {
        $new = array();
        foreach ($columns as $key => $col) {
            $new[$key] = $col;
            if ('order-total' === $key) $new['participantes'] = 'Participantes';
        }
        return $new;
    }

    public function append_status_column($order) {
        $participants = $this->can_view_order_participants( $order ) ? $this->get_participants_safe( $order ) : array();
        if ( empty( $participants ) ) { echo esc_html( wc_get_order_status_name( $order->get_status() ) ); return; }

        $summary = $this->terms_summary( $order, $participants );
        $total = $summary['total'];
        $signed = $summary['signed'];

        echo esc_html( wc_get_order_status_name( $order->get_status() ) );

        if ($total > 0 && $signed < $total) {
            $tip = __( 'Assine o termo de cada participante para liberar o QR code do ingresso.', 'wooadventure-integration' );
            echo '<div style="font-size: 12px; color: #b32d2e; margin-top: 3px; font-weight:600;" title="' . esc_attr( $tip ) . '">';
            echo esc_html__( 'Termo pendente', 'wooadventure-integration' ) . ' (' . (int) $summary['pending'] . ')';
            echo '<span class="screen-reader-text" style="position:absolute; left:-9999px;"> ' . esc_html( $tip ) . '</span>';
            echo '</div>';
        }
    }

    public function column_content($order) {
        $participants = $this->can_view_order_participants( $order ) ? $this->get_participants_safe( $order ) : array();
        if ( empty( $participants ) ) { echo '<span style="color:#999;">-</span>'; return; }

        $order_id = $order->get_id();
        $is_locked = $this->is_order_locked( $order );
        $modal_id = 'wcai-modal-' . $order_id;

        $summary = $this->terms_summary( $order, $participants );
        $total = $summary['total'];
        $signed = $summary['signed'];

        // Botão Inteligente: abre o modal de dados; a assinatura do termo é o link separado "Assinar termos".
        $btn_style = '';
        $btn_text = $is_locked ? 'Participantes' : 'Editar participantes';

        if ($total > 0 && !$is_locked) {
            if ($signed == $total) {
                $btn_text = '✅ Participantes';
                $btn_style = 'background-color: #1e7e34; color: #fff; border-color: #1e7e34;';
            } else {
                $btn_text = "⚠️ Editar participantes ($signed/$total)";
                $btn_style = 'background-color: #ffba00; color: #1d2327; border-color: #ffba00;';
            }
        }

        echo '<div class="wcai-orders-participants" style="margin-bottom:6px; font-size:13px;">' . esc_html( $this->participants_summary_line( $participants ) ) . ' ' . $this->terms_chip_summary_html( $summary ) . '</div>';
        echo '<button class="button wcai-open-modal" data-target="' . $modal_id . '" style="width:100%; ' . $btn_style . '">' . $btn_text . '</button>';
        $terms_url = $this->get_terms_url( $order );
        if ( $terms_url && $this->terms_cta_visible( $order, $participants, $summary['pending'] ) ) {
            echo '<a class="wcai-sign-all wcai-sign-all--list" href="' . esc_url( $terms_url ) . '">' . esc_html__( 'Assinar termos', 'wooadventure-integration' ) . '</a>';
        }

        $this->render_modal_html($order_id, $participants, $is_locked, $modal_id);
    }

    // Aviso do topo do modal: termos que faltam (com link) ou "tudo certo".
    private function modal_notice_html( $order, $participants, $summary ) {
        if ( $summary['total'] > 0 && $summary['pending'] < 1 ) {
            return array( 'ok', '✅ <strong>Tudo certo!</strong> Todos os ' . (int) $summary['total'] . ' participantes assinaram.' );
        }
        if ( $summary['total'] < 1 ) {
            return array( 'warn', '⚠️ <strong>Dados incompletos.</strong> Informe nome, CPF e data de nascimento dos participantes.' );
        }
        $missing = (int) $summary['pending'];
        $text = '⚠️ <strong>Atenção:</strong> ' . ( 1 === $missing ? 'Falta 1 termo.' : 'Faltam ' . $missing . ' termos.' ) . ' Cada participante assina o seu termo.';
        $terms_url = $this->get_terms_url( $order );
        if ( $terms_url && $this->terms_cta_visible( $order, $participants, $summary['pending'] ) ) {
            $first = '';
            foreach ( $participants as $p ) {
                if ( ! $this->participant_is_signed( $order, $p ) && ! $this->participant_is_incomplete( $order, $p ) ) {
                    $parts = explode( ' ', $this->display_name( $p['nome_completo'] ) );
                    $first = $parts[0];
                    break;
                }
            }
            $label = sprintf( __( 'Assinar termo de %s', 'wooadventure-integration' ), $first );
            $text .= '<br><a class="wcai-modal-sign" href="' . esc_url( $terms_url ) . '" target="_blank" rel="noopener">👉 ' . esc_html( $label ) . '</a>';
        }
        return array( 'warn', $text );
    }

    private function render_modal_html($order_id, $participants, $is_locked, $modal_id) {
        $readonly = $is_locked ? 'disabled' : '';
        $order = wc_get_order($order_id);

        $summary = $this->terms_summary( $order, $participants );
        list( $notice_class, $notice_html ) = $this->modal_notice_html( $order, $participants, $summary );

        ?>
        <div id="<?php echo esc_attr($modal_id); ?>" class="wcai-modal-overlay" style="display:none;" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="<?php echo esc_attr($modal_id); ?>-title">
            <div class="wcai-modal-content">
                <div class="wcai-modal-header">
                    <h3 id="<?php echo esc_attr($modal_id); ?>-title">Participantes - Pedido #<?php echo $order_id; ?></h3>
                    <button type="button" class="wcai-close-modal" aria-label="<?php echo esc_attr__( 'Fechar', 'wooadventure-integration' ); ?>">&times;</button>
                </div>

                <form class="wcai-participants-form">
                    <?php wp_nonce_field('wcai_update_nonce', 'security'); ?>
                    <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">

                    <div class="wcai-scroll-area">
                        <div class="wcai-modal-notice wcai-modal-notice--<?php echo esc_attr( $notice_class ); ?>">
                            <?php echo $notice_html; ?>
                        </div>

                        <?php foreach ($participants as $idx => $p) :
                            $is_signed = $this->participant_is_signed( $order, $p );
                            $has_id = isset($p['id']) && is_numeric($p['id']);
                            $field_key = $has_id ? $p['id'] : 'new_' . $idx;
                            $base = 'participantes[' . $field_key . ']';
                            $uid = $modal_id . '-' . $idx;
                            $badge = $this->get_age_badge_html($p['data_nascimento']);
                            $id_input = $has_id ? '<input type="hidden" name="participantes['.$p['id'].'][id]" value="'.$p['id'].'">' : '<input type="hidden" name="' . esc_attr( $base ) . '[vref]" value="' . esc_attr( $p['id'] ) . '">';
                            $icon = $is_signed
                                ? '<span title="Assinado" style="margin-right:5px; font-size:16px;">✅</span>'
                                : '<span title="Pendente" style="margin-right:5px; font-size:16px; opacity:0.5;">⚠️</span>';
                            $name_locked = $has_id && $this->identity_locked($p);

                            // CPF nunca vai completo no HTML: valor mascarado + data-masked (o servidor trata "*" como "não alterado").
                            $cpf_raw = $this->safe_cpf_pad($p['cpf']);
                            $cpf_valid = WCAI_Utils::is_valid_cpf( $cpf_raw );
                            $cpf_value = $cpf_valid ? WCAI_Utils::mask_cpf( $cpf_raw ) : '';

                            // Nascimento completo só aparece para quem pode editar; senão só a idade (+ flag "não alterado").
                            $show_date = ! $is_locked && ! $name_locked;
                            $val_nasc = $this->force_date_to_display($p['data_nascimento']);
                            $age_text = $this->participant_age_label( $p['data_nascimento'] );
                        ?>
                            <fieldset class="wcai-pcard">
                                <legend>Participante <?php echo ($idx + 1); ?> <?php echo $icon; ?></legend>
                                <?php echo $id_input; ?>
                                <div class="wcai-pfield wcai-pfield--name">
                                    <label for="<?php echo esc_attr( $uid ); ?>-nome">Nome completo <?php echo $badge; ?></label>
                                    <input type="text" id="<?php echo esc_attr( $uid ); ?>-nome" name="<?php echo esc_attr( $base ); ?>[nome_completo]" value="<?php echo esc_attr($p['nome_completo']); ?>" <?php echo $readonly; ?><?php echo $name_locked ? ' readonly' : ''; ?> required>
                                    <?php if ( $name_locked ) : ?>
                                        <small>🔒 <?php echo esc_html__( 'Nome bloqueado após o aceite do termo ou check-in. Para alterar, fale com o suporte.', 'wooadventure-integration' ); ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="wcai-pfield">
                                    <label for="<?php echo esc_attr( $uid ); ?>-cpf">CPF</label>
                                    <input type="text" id="<?php echo esc_attr( $uid ); ?>-cpf" class="wcai-mask-cpf" name="<?php echo esc_attr( $base ); ?>[cpf]" value="<?php echo esc_attr($cpf_value); ?>"<?php echo $cpf_valid ? ' data-masked="1"' : ''; ?> inputmode="numeric" autocomplete="off" <?php echo $readonly; ?> required>
                                </div>
                                <div class="wcai-pfield">
                                    <label for="<?php echo esc_attr( $uid ); ?>-nasc">Data de nascimento</label>
                                    <?php if ( $show_date ) : ?>
                                        <input type="text" id="<?php echo esc_attr( $uid ); ?>-nasc" class="wcai-mask-date" name="<?php echo esc_attr( $base ); ?>[data_nascimento]" value="<?php echo esc_attr($val_nasc); ?>" <?php echo $readonly; ?> required placeholder="dd/mm/aaaa" inputmode="numeric">
                                    <?php else : ?>
                                        <input type="text" id="<?php echo esc_attr( $uid ); ?>-nasc" value="<?php echo esc_attr( $age_text ? $age_text : '—' ); ?>" readonly>
                                        <?php if ( ! $is_locked ) : ?><input type="hidden" name="<?php echo esc_attr( $base ); ?>[nasc_unchanged]" value="1"><?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </fieldset>
                        <?php endforeach; ?>

                        <div class="wcai-modal-legend">
                            <p>ℹ️ <strong>Legenda:</strong> ✅ Assinado | ⚠️ Pendente. <br>Cada participante deve assinar individualmente.</p>
                        </div>

                    </div>
                    <div class="wcai-modal-actions">
                        <span class="wcai-msg" role="status" aria-live="polite"></span>
                        <?php if(!$is_locked): ?><button type="submit" class="button button-primary wcai-save">Salvar dados</button><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
}

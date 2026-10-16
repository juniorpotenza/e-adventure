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

    // Coluna Status: só o nome do status; o estado do termo fica no chip da coluna Participantes (uma regra só).
    public function append_status_column($order) {
        echo esc_html( wc_get_order_status_name( $order->get_status() ) );
    }

    public function column_content($order) {
        $participants = $this->can_view_order_participants( $order ) ? $this->get_participants_safe( $order ) : array();
        if ( empty( $participants ) ) { echo '<span style="color:#999;">-</span>'; return; }

        $order_id = $order->get_id();
        $is_locked = $this->is_order_locked( $order );
        $modal_id = 'wcai-modal-' . $order_id;
        $state = $this->order_terms_state( $order, $participants );

        // Um botão principal ("Assinar termos") e secundários em contorno; o modal edita os dados, o termo abre em nova aba.
        echo '<div class="wcai-orders-participants">' . esc_html( $this->participants_summary_line( $participants ) ) . ' ' . $this->terms_chip_summary_html( $state, $order ) . '</div>';
        $terms_url = $this->get_terms_url( $order );
        if ( $terms_url && $this->terms_cta_visible( $order, $participants, $state['pending'] ) ) {
            echo $this->terms_link_html( $terms_url, __( 'Assinar termos', 'wooadventure-integration' ), 'wcai-sign-all wcai-sign-all--list' );
        }
        echo '<button type="button" class="button wcai-open-modal" data-target="' . esc_attr( $modal_id ) . '">' . esc_html( $is_locked ? __( 'Participantes', 'wooadventure-integration' ) : __( 'Editar participantes', 'wooadventure-integration' ) ) . '</button>';

        $this->render_modal_html($order_id, $participants, $is_locked, $modal_id);
    }

    // Aviso do topo do modal: pedido encerrado, termos que faltam (com link) ou "tudo certo".
    private function modal_notice_html( $order, $participants, $summary ) {
        if ( $this->is_order_locked( $order ) ) {
            return array( 'info', '<strong>' . esc_html__( 'Pedido encerrado.', 'wooadventure-integration' ) . '</strong> ' . esc_html__( 'Os dados ficam só para consulta.', 'wooadventure-integration' ) );
        }
        if ( $summary['total'] > 0 && $summary['pending'] < 1 ) {
            return array( 'ok', '✅ <strong>Tudo certo!</strong> Todos os ' . (int) $summary['total'] . ' participantes assinaram o termo de responsabilidade.' );
        }
        if ( $summary['total'] < 1 ) {
            return array( 'warn', '⚠️ <strong>Dados incompletos.</strong> Informe nome, CPF e data de nascimento dos participantes.' );
        }
        $missing = (int) $summary['pending'];
        $text = '⚠️ <strong>Atenção:</strong> ' . ( 1 === $missing ? 'Falta 1 termo.' : 'Faltam ' . $missing . ' termos.' ) . ' Cada participante assina o seu termo de responsabilidade.';
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
            $text .= '<br>' . $this->terms_link_html( $terms_url, sprintf( __( 'Assinar termo de %s', 'wooadventure-integration' ), $first ), 'wcai-modal-sign' );
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
                            // Nascimento nunca vai completo no HTML: só o ano, com data-masked e a flag nasc_unchanged (o servidor mantém a data guardada).
                            $nasc_masked = $show_date && '' !== $val_nasc;
                            $age_text = $this->participant_age_label( $p['data_nascimento'] );
                        ?>
                            <fieldset class="wcai-pcard">
                                <legend>Participante <?php echo ($idx + 1); ?> <?php echo $icon; ?></legend>
                                <?php echo $id_input; ?>
                                <div class="wcai-pfield wcai-pfield--name">
                                    <label for="<?php echo esc_attr( $uid ); ?>-nome">Nome completo <?php echo $badge; ?></label>
                                    <input type="text" id="<?php echo esc_attr( $uid ); ?>-nome" name="<?php echo esc_attr( $base ); ?>[nome_completo]" value="<?php echo esc_attr( $this->display_name( $p['nome_completo'] ) ); ?>" <?php echo $readonly; ?><?php echo $name_locked ? ' readonly' : ''; ?> required>
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
                                        <input type="text" id="<?php echo esc_attr( $uid ); ?>-nasc" class="wcai-mask-date" name="<?php echo esc_attr( $base ); ?>[data_nascimento]" value="<?php echo esc_attr( $nasc_masked ? $this->mask_birthdate_in_html( $val_nasc ) : $val_nasc ); ?>"<?php echo $nasc_masked ? ' data-masked="1" maxlength="10"' : ''; ?> autocomplete="off" <?php echo $readonly; ?> required placeholder="dd/mm/aaaa" inputmode="numeric">
                                        <?php if ( $nasc_masked ) : ?><input type="hidden" name="<?php echo esc_attr( $base ); ?>[nasc_unchanged]" value="1"><?php endif; ?>
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

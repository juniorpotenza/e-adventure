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
        $participants = $this->get_participants_safe( $order );
        if ( empty( $participants ) ) { echo esc_html( wc_get_order_status_name( $order->get_status() ) ); return; }

        $total = count($participants);
        $signed = 0;
        foreach($participants as $p) {
            $cpf_clean = $this->safe_cpf_pad($p['cpf']); // PAD FIX
            $meta_signed = $order->get_meta('_waiver_signed_' . $cpf_clean);
            $db_signed = isset($p['termo_assinado']) && $p['termo_assinado'] == 1;
            if ($meta_signed || $db_signed) $signed++;
        }

        echo esc_html( wc_get_order_status_name( $order->get_status() ) );

        if ($total > 0 && $signed < $total) {
            echo '<div style="font-size: 12px; color: #d63638; margin-top: 3px; font-weight:600;">';
            echo '⚠️ ' . esc_html__( 'Termo pendente', 'wooadventure-integration' );
            echo '</div>';
        }
    }

    public function column_content($order) {
        $participants = $this->get_participants_safe( $order );
        if ( empty( $participants ) ) { echo '<span style="color:#999;">-</span>'; return; }

        $order_id = $order->get_id();
        $is_locked = $this->is_order_locked( $order );
        $modal_id = 'wcai-modal-' . $order_id;
        
        $total = count($participants);
        $signed = 0;
        foreach($participants as $p) {
            $cpf_clean = $this->safe_cpf_pad($p['cpf']); // PAD FIX
            $meta_signed = $order->get_meta('_waiver_signed_' . $cpf_clean);
            $db_signed = isset($p['termo_assinado']) && $p['termo_assinado'] == 1;
            if ($meta_signed || $db_signed) $signed++;
        }

        // Botão Inteligente
        $btn_style = '';
        $btn_text = $is_locked ? 'Ver Dados' : 'Ver/Editar';
        
        if ($total > 0 && !$is_locked) {
            if ($signed == $total) {
                $btn_text = '✅ Ver Dados';
                $btn_style = 'background-color: #1e7e34; color: #fff; border-color: #1e7e34;';
            } else {
                $btn_text = "⚠️ Assinar ($signed/$total)";
                $btn_style = 'background-color: #ffba00; color: #1d2327; border-color: #ffba00;';
            }
        }

        echo '<button class="button wcai-open-modal" data-target="' . $modal_id . '" style="width:100%; white-space:nowrap; ' . $btn_style . '">' . $btn_text . '</button>';
        
        $this->render_modal_html($order_id, $participants, $is_locked, $modal_id);
    }

    private function render_modal_html($order_id, $participants, $is_locked, $modal_id) {
        $readonly = $is_locked ? 'disabled' : '';
        $order = wc_get_order($order_id);

        $total = count($participants);
        $signed = 0;
        $participants_with_status = []; 

        foreach($participants as $p) {
            // CORREÇÃO CRÍTICA: Garante que o CPF tenha 11 dígitos (com zeros) antes de checar assinatura
            $cpf_clean = $this->safe_cpf_pad($p['cpf']); 
            
            $meta_signed = $order->get_meta('_waiver_signed_' . $cpf_clean);
            $db_signed = isset($p['termo_assinado']) && $p['termo_assinado'] == 1;
            
            $is_signed = ($meta_signed || $db_signed);
            if ($is_signed) $signed++;
            
            $participants_with_status[] = array_merge($p, ['is_signed' => $is_signed]);
        }

        if ($total > 0 && $signed == $total) {
            $msg_text = "✅ <strong>Tudo certo!</strong> Todos os $total participantes assinaram.";
            $msg_bg = '#d4edda'; $msg_color = '#155724'; $msg_border = '#c3e6cb';
        } else {
            $missing = $total - $signed;
            $msg_bg = '#fff3cd'; $msg_color = '#856404'; $msg_border = '#ffeeba';
            $termo_link = $this->get_terms_url( $order );
            $msg_text = "⚠️ <strong>Atenção:</strong> Faltam assinaturas ($missing/$total). Peça para cada participante acessar o site.";
            if ( $termo_link ) {
                $msg_text .= "<br><a href='" . esc_url( $termo_link ) . "' target='_blank' rel='noopener' style='color:{$msg_color}; text-decoration:underline; font-weight:bold; margin-top:5px; display:inline-block;'>👉 " . esc_html__( 'Clique aqui para assinar o termo', 'wooadventure-integration' ) . '</a>';
            }
        }

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
                        <div style="margin-bottom: 15px; padding: 15px; background: <?php echo $msg_bg; ?>; color: <?php echo $msg_color; ?>; border: 1px solid <?php echo $msg_border; ?>; border-radius: 5px; font-size: 14px; line-height: 1.5;">
                            <?php echo $msg_text; ?>
                        </div>

                        <table class="wcai-frontend-table">
                            <thead><tr><th>#</th><th>Nome / Status</th><th>CPF</th><th>Nasc.</th></tr></thead>
                            <tbody>
                            <?php foreach ($participants_with_status as $idx => $p) : 
                                $val_nasc = $this->force_date_to_display($p['data_nascimento']);
                                $badge = $this->get_age_badge_html($p['data_nascimento']);
                                $field_key = (isset($p['id']) && is_numeric($p['id'])) ? $p['id'] : 'new_' . $idx;
                                $id_input = (isset($p['id']) && is_numeric($p['id'])) ? '<input type="hidden" name="participantes['.$p['id'].'][id]" value="'.$p['id'].'">' : '';
                                
                                $icon = $p['is_signed'] 
                                    ? '<span title="Assinado" style="margin-right:5px; font-size:16px;">✅</span>' 
                                    : '<span title="Pendente" style="margin-right:5px; font-size:16px; opacity:0.5;">⚠️</span>';
                                
                                // CORREÇÃO: Garante zero à esquerda para exibição no campo
                                $cpf_raw = $this->safe_cpf_pad($p['cpf']);
                                $cpf_value = class_exists('WCAI_Utils') ? WCAI_Utils::format_cpf($cpf_raw) : $cpf_raw;
                            ?>
                                <tr>
                                    <td><?php echo ($idx + 1); ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <?php echo $badge; echo $icon; echo $id_input; ?>
                                            <input type="text" name="participantes[<?php echo $field_key; ?>][nome_completo]" value="<?php echo esc_attr($p['nome_completo']); ?>" <?php echo $readonly; ?> required style="flex-grow: 1;" aria-label="<?php echo esc_attr( sprintf( __( 'Nome completo do participante %d', 'wooadventure-integration' ), $idx + 1 ) ); ?>">
                                        </div>
                                    </td>
                                    <td><input type="text" class="wcai-mask-cpf" name="participantes[<?php echo $field_key; ?>][cpf]" value="<?php echo esc_attr($cpf_value); ?>" <?php echo $readonly; ?> required aria-label="<?php echo esc_attr( sprintf( __( 'CPF do participante %d', 'wooadventure-integration' ), $idx + 1 ) ); ?>"></td>
                                    <td><input type="text" class="wcai-mask-date" name="participantes[<?php echo $field_key; ?>][data_nascimento]" value="<?php echo esc_attr($val_nasc); ?>" <?php echo $readonly; ?> required placeholder="dd/mm/aaaa" aria-label="<?php echo esc_attr( sprintf( __( 'Data de nascimento do participante %d', 'wooadventure-integration' ), $idx + 1 ) ); ?>"></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div style="margin-top:10px; font-size:12px; color:#595959;">
                            <p>ℹ️ <strong>Legenda:</strong> ✅ Assinado | ⚠️ Pendente. <br>Cada participante deve assinar individualmente.</p>
                        </div>

                    </div>
                    <div class="wcai-modal-actions">
                        <span class="wcai-msg" role="status" aria-live="polite"></span>
                        <?php if(!$is_locked): ?><button type="submit" class="button button-primary">Salvar Dados</button><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }
}

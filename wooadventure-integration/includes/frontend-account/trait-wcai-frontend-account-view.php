<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Frontend_Account_View {
    // =========================================================================
    // 0. HELPER DE CPF (CORREÇÃO DO ZERO À ESQUERDA)
    // =========================================================================
    private function safe_cpf_pad( $cpf ) {
        // Remove tudo que não é número
        $clean = preg_replace('/[^0-9]/', '', $cpf);
        if ( empty($clean) ) return '';
        // Se tiver menos de 11 dígitos, completa com zeros à esquerda
        return str_pad($clean, 11, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    // RENDERIZAÇÃO DA LISTA (SITE E E-MAIL)
    // =========================================================================
    public function render_participants_on_thankyou( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $this->render_participants_on_order_view( $order );
        }
    }

    private function participant_age_label( $date_string ) {
        $age = $this->participant_age( $date_string );
        if ( null === $age ) return '';
        return 1 === $age ? '1 ano' : $age . ' anos';
    }

    private function participant_is_signed( $order, $p ) {
        $cpf = $this->safe_cpf_pad( $p['cpf'] );
        return ! empty( $p['ticket_hash'] ) || ( $cpf && $order->get_meta( '_waiver_signed_' . $cpf ) ) || ( isset( $p['termo_assinado'] ) && 1 == $p['termo_assinado'] );
    }

    // QR só com reserva confirmada; participante sem reserva (legado) não depende dela.
    private function participant_reservation_confirmed( $p ) {
        $reservation_id = isset( $p['reservation_id'] ) ? absint( $p['reservation_id'] ) : 0;
        if ( ! $reservation_id || ! class_exists( 'WCAI_Reservations' ) ) return true;
        $reservation = WCAI_Reservations::get_by_id( $reservation_id );
        return $reservation && 'confirmed' === $reservation->status;
    }

    // E-mail: texto puro vira linhas de texto; HTML vira tabela compacta (sem QR nem ações de edição).
    public function render_participants_in_email( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
        if ( ! is_object( $order ) ) return;
        $participants = $this->get_participants_safe( $order );
        if ( empty( $participants ) ) return;

        $rows = array();
        foreach ( $participants as $p ) {
            $rows[] = array(
                'name'   => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $p['nome_completo'] ) ) ),
                'age'    => $this->participant_age_label( $p['data_nascimento'] ),
                'cpf'    => WCAI_Utils::mask_cpf( $this->safe_cpf_pad( $p['cpf'] ) ),
                'signed' => $this->participant_is_signed( $order, $p ),
            );
        }

        if ( $plain_text ) {
            echo "\n" . __( 'Lista de Participantes', 'wooadventure-integration' ) . "\n";
            foreach ( $rows as $i => $r ) {
                $parts = array_filter( array( $r['name'], $r['age'], 'CPF ' . $r['cpf'] ) );
                echo sprintf( 'Visitante %d: %s', $i + 1, implode( ' — ', $parts ) ) . "\n";
            }
            echo "\n";
            return;
        }

        $cell = 'text-align:left; padding:8px; border:1px solid #e5e5e5;';
        echo '<h2 style="margin:20px 0 10px;">' . esc_html__( 'Lista de Participantes', 'wooadventure-integration' ) . '</h2>';
        echo '<table cellspacing="0" cellpadding="6" style="width:100%; border-collapse:collapse; margin-bottom:20px; border:1px solid #e5e5e5;"><thead><tr>';
        foreach ( array( 'Nome', 'Idade', 'CPF', 'Termo' ) as $h ) {
            echo '<th style="' . esc_attr( $cell ) . '">' . esc_html( $h ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ( $rows as $r ) {
            echo '<tr><td style="' . esc_attr( $cell ) . '">' . esc_html( $r['name'] ) . '</td>'
                . '<td style="' . esc_attr( $cell ) . '">' . esc_html( $r['age'] ) . '</td>'
                . '<td style="' . esc_attr( $cell ) . '">' . esc_html( $r['cpf'] ) . '</td>'
                . '<td style="' . esc_attr( $cell ) . '">' . esc_html( $r['signed'] ? 'Assinado' : 'Pendente' ) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    // Página do pedido/obrigado: hooks diferentes podem disparar no mesmo pedido; imprime uma vez por requisição.
    public function render_participants_on_order_view( $order ) {
        static $rendered = array();
        if ( ! is_object( $order ) || isset( $rendered[ $order->get_id() ] ) ) return;
        $participants = $this->get_participants_safe( $order );
        if ( empty( $participants ) ) return;
        $rendered[ $order->get_id() ] = true;

        $terms_url = $this->get_terms_url( $order );
        ?>
        <section class="wcai-order-details-participants" style="margin-bottom: 40px; margin-top: 30px; font-family: inherit;">
            <h2 class="woocommerce-column__title" style="font-size: 18px; font-weight: 600; color: #333; margin-bottom: 10px;">👥 Lista de Participantes</h2>
            
            <table class="woocommerce-table shop_table" style="width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #e5e5e5;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:10px; border-bottom:2px solid #eee; background-color: #f8f8f8;">Nome / Idade</th>
                        <th style="text-align:center; padding:10px; border-bottom:2px solid #eee; background-color: #f8f8f8;"><?php echo esc_html__( 'Ingresso (QR Code)', 'wooadventure-integration' ); ?></th>
                        <th style="text-align:left; padding:10px; border-bottom:2px solid #eee; background-color: #f8f8f8;">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ( $participants as $p ) : 
                        // Garante CPF completo
                        $cpf_full = $this->safe_cpf_pad($p['cpf']);
                        $cpf_clean = preg_replace('/[^0-9]/', '', $cpf_full);
                        
                        // Verifica se tem ticket/termo assinado
                        $has_ticket = false;
                        $ticket_hash = isset($p['ticket_hash']) ? $p['ticket_hash'] : '';
                        
                        // Fallback para meta antigo
                        if ( empty($ticket_hash) ) {
                            $meta_signed = $order->get_meta('_waiver_signed_' . $cpf_clean);
                            if ( $meta_signed ) {
                                $has_ticket = true;
                            }
                        } else {
                            $has_ticket = true;
                        }

                        $badge = $this->get_age_badge_html( $p['data_nascimento'] );
                        $show_qr = $has_ticket && ! empty( $ticket_hash ) && $this->participant_reservation_confirmed( $p );

                        // APLICA MÁSCARA LGPD VISUAL (***)
                        $cpf_display = ( class_exists('WCAI_Utils') && method_exists('WCAI_Utils', 'mask_cpf') )
                            ? WCAI_Utils::mask_cpf($cpf_full) 
                            : '***';
                    ?>
                    <tr>
                        <td style="padding:12px 10px; border-bottom:1px solid #eee; vertical-align: middle;">
                            <div style="display:flex; flex-direction:column; gap:4px;">
                                <strong><?php echo esc_html( $p['nome_completo'] ); ?></strong>
                                <?php echo $badge; ?>
                                <small style="color:#777;">CPF: <?php echo esc_html( $cpf_display ); ?></small>
                            </div>
                        </td>
                        <td style="padding:12px 10px; border-bottom:1px solid #eee; vertical-align: middle; text-align:center;">
                            <?php if ( $show_qr ): ?>
                                <?php $ticket_qr_url = class_exists('WCAI_Assinatura') ? WCAI_Assinatura::generate_local_qr($ticket_hash, absint($p['id'])) : false; ?>
                                <?php if ( $ticket_qr_url && ! is_wp_error($ticket_qr_url) ) : ?>
                                    <img src="<?php echo esc_url($ticket_qr_url); ?>" style="width:80px; height:80px; border:1px solid #ccc; padding:2px;" alt="QR Code">
                                <?php else : ?>
                                    <span style="color:#d63638;">QR indisponível</span>
                                <?php endif; ?>
                                <div style="font-size:12px; color:#1e7e34; font-weight:bold; margin-top:2px;">✅ Ativo</div>
                            <?php elseif ( $has_ticket ): ?>
                                <span style="color:#1e7e34; font-weight:bold;">✅ Assinado</span><br><small style="color:#595959;"><?php echo esc_html( ! empty( $ticket_hash ) ? 'QR disponível após a confirmação da reserva.' : '(QR gerando...)' ); ?></small>
                            <?php else: ?>
                                <span style="color:#ccc; font-size:30px; line-height:1;" aria-hidden="true">⬜</span>
                                <div style="font-size:12px; color:#595959;">Pendente</div>
                            <?php endif; ?>
                        </td>
                        <td style="padding:12px 10px; border-bottom:1px solid #eee; vertical-align: middle;">
                            <?php if ( $show_qr ): ?>
                                <?php
                                    $ticket_qr_url = class_exists('WCAI_Assinatura') ? WCAI_Assinatura::generate_local_qr($ticket_hash, absint($p['id'])) : false;
                                    $msg_whats = "Olá " . $p['nome_completo'] . ", aqui está seu ingresso para o passeio! Acesse o QR Code: " . ( $ticket_qr_url && ! is_wp_error($ticket_qr_url) ? $ticket_qr_url : '' );
                                    $link_whats = "https://wa.me/?text=" . urlencode($msg_whats);
                                ?>
                                <a href="<?php echo esc_url($link_whats); ?>" target="_blank" class="button" style="font-size:12px; padding:5px 10px; background-color:#25D366; color:white; border:none; text-decoration:none; display:inline-block;">📲 Enviar Whats</a>
                            <?php elseif ( $terms_url ): ?>
                                <a href="<?php echo esc_url( $terms_url ); ?>" target="_blank" rel="noopener" style="color:#d63638; text-decoration:underline; font-weight:bold;">👉 <?php echo esc_html__( 'Assinar termo', 'wooadventure-integration' ); ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <?php 
            $has_pending = false;
            foreach($participants as $p) {
                // Pad CPF antes de checar pendencia
                $c = $this->safe_cpf_pad($p['cpf']); 
                $hash = isset($p['ticket_hash']) ? $p['ticket_hash'] : '';
                $meta = $order->get_meta('_waiver_signed_'.$c);
                
                if ( empty($hash) && empty($meta) && ( !isset($p['termo_assinado']) || $p['termo_assinado'] != 1 ) ) {
                    $has_pending = true; 
                    break;
                }
            }
            if( $has_pending ): ?>
                <div style="margin-top: 15px; background: #fff3cd; color: #856404; padding: 12px; border-radius: 4px; font-size: 14px; border: 1px solid #ffeeba;">
                    ⚠️ <?php echo esc_html__( 'Existem participantes pendentes.', 'wooadventure-integration' ); ?>
                    <?php if ( $terms_url ) : ?>
                        <br>
                        <a href="<?php echo esc_url( $terms_url ); ?>" target="_blank" rel="noopener" style="color:#856404; text-decoration:underline; font-weight:bold;">👉 <?php echo esc_html__( 'Clique aqui para assinar o termo de responsabilidade', 'wooadventure-integration' ); ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        </section>
        <?php
    }

    // A chave do pedido vai no link: ajax_check_pax_deep exige order_key (o JS da assinatura lê ?key=).
    private function get_terms_url( $order ) {
        $url = class_exists( 'WCAI_Settings' ) ? (string) WCAI_Settings::get_terms_url() : '';

        return $url ? add_query_arg( 'key', $order->get_order_key(), $url ) : '';
    }

    // --- 1. ASSETS ---
    public function enqueue_scripts() {
        if ( is_account_page() ) {
            wp_enqueue_script( 'jquery-mask', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js', array( 'jquery' ), '1.14.16', true );
        }
    }

    public function enqueue_admin_scripts( $hook ) {
        $screen = get_current_screen();
        if ( $screen && ( 'shop_order' === $screen->post_type || 'woocommerce_page_wc-orders' === $screen->id ) ) {
            wp_enqueue_script( 'jquery-mask', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js', array( 'jquery' ), '1.14.16', true );
            wp_add_inline_script( 'jquery-mask', "
                jQuery(document).ready(function($){ 
                    function initWCAIMasks() {
                        $('.wcai-mask-cpf').mask('000.000.000-00'); 
                        $('.wcai-mask-date').mask('00/00/0000'); 
                    }
                    initWCAIMasks();
                    $( document ).on( 'woocommerce_post_init', function() { initWCAIMasks(); } );
                });
            ");
        }
    }

    // --- 2. HELPERS DE DATA E BADGE ---
    private function force_date_to_display( $date ) {
        if ( empty($date) || $date == '0000-00-00' || $date == '1970-01-01' ) return '';
        if ( strpos($date, '/') !== false ) return $date;
        $date_clean = explode(' ', $date)[0];
        if ( preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_clean) ) {
            $parts = explode('-', $date_clean);
            return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
        }
        $ts = strtotime($date);
        return ( $ts && $ts > 0 ) ? date('d/m/Y', $ts) : '';
    }

    private function force_date_to_db( $date ) {
        if ( empty($date) ) return null;
        if ( preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ) return $date;
        if ( strpos($date, '/') !== false ) {
            $parts = explode('/', $date);
            if ( count($parts) === 3 ) return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
        return null;
    }

    private function participant_age( $date_string ) {
        if ( empty($date_string) || $date_string == '0000-00-00' ) return null;
        try {
            $calc_date = $this->force_date_to_db($date_string);
            if(!$calc_date) return null;
            return (new DateTime())->diff(new DateTime( $calc_date ))->y;
        } catch (Exception $e) { return null; }
    }

    private function get_age_badge_html( $date_string ) {
        $age = $this->participant_age( $date_string );
        if ( null === $age ) return '';

        $icon_adult = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
        $icon_child = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="3"></circle><path d="M9 12h6"></path><path d="M12 21v-9"></path><path d="M8 21h8"></path><path d="M6 12v3a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-3"></path></svg>';

        if ( $age >= 18 ) { $color = '#2271b1'; $bg = '#eaf4fc'; $icon = $icon_adult; } 
        else { $color = '#d63638'; $bg = '#fbeaea'; $icon = $icon_child; }

        $style = "display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; color: {$color}; background: {$bg}; padding: 3px 8px; border-radius: 12px; border: 1px solid {$color}; margin-right: 8px; white-space: nowrap; flex-shrink: 0;";
        return sprintf('<span style="%s">%s %s</span>', $style, $icon, esc_html( $this->participant_age_label( $date_string ) ));
    }
}

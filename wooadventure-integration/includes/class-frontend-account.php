<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Frontend_Account {
    public function __construct() {
        // 1. Tabela Minha Conta (Coluna Personalizada)
        add_filter('woocommerce_my_account_my_orders_columns', array($this, 'add_column'));
        add_action('woocommerce_my_account_my_orders_column_participantes', array($this, 'column_content'));
        
        // 2. Coluna Status Híbrida (Aviso extra)
        add_action('woocommerce_my_account_my_orders_column_order-status', array($this, 'append_status_column'));

        // 3. Visualização do Pedido (Site e E-mail)
        add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_participants_on_order_view' ), 10, 1 );
        add_action( 'woocommerce_email_after_order_table', array( $this, 'render_participants_on_order_view' ), 10, 1 );

        // 4. Scripts e Modais
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts')); 
        add_action('wp_footer', array($this, 'modal_assets')); 
        add_action('wp_ajax_wcai_update_participants', array($this, 'ajax_update'));
        
        // 5. Backend (Admin)
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts')); 
        add_action('add_meta_boxes', array($this, 'register_admin_metabox'));
        add_action('woocommerce_process_shop_order_meta', array($this, 'admin_save'), 10, 2);
    }

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
    public function render_participants_on_order_view( $order ) {
        if ( ! $order ) return;
        $participants = $this->get_participants_safe( $order );
        if ( empty( $participants ) ) return;

        ?>
        <section class="wcai-order-details-participants" style="margin-bottom: 40px; margin-top: 30px; font-family: inherit;">
            <h2 class="woocommerce-column__title" style="font-size: 18px; font-weight: 600; color: #333; margin-bottom: 10px;">👥 Lista de Participantes</h2>
            
            <table class="woocommerce-table shop_table" style="width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #e5e5e5;">
                <thead>
                    <tr>
                        <th style="text-align:left; padding:10px; border-bottom:2px solid #eee; background-color: #f8f8f8;">Nome / Idade</th>
                        <th style="text-align:center; padding:10px; border-bottom:2px solid #eee; background-color: #f8f8f8;">Ticket (QR Code)</th>
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
                            <?php if ( $has_ticket && !empty($ticket_hash) ): ?>
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=80x80&data=<?php echo esc_attr($ticket_hash); ?>" style="width:80px; height:80px; border:1px solid #ccc; padding:2px;">
                                <div style="font-size:10px; color:#46b450; font-weight:bold; margin-top:2px;">✅ Ativo</div>
                            <?php elseif ( $has_ticket ): ?>
                                <span style="color:#46b450; font-weight:bold;">✅ Assinado</span><br><small style="color:#999;">(QR gerando...)</small>
                            <?php else: ?>
                                <span style="color:#ccc; font-size:30px; line-height:1;">⬜</span>
                                <div style="font-size:10px; color:#999;">Pendente</div>
                            <?php endif; ?>
                        </td>
                        <td style="padding:12px 10px; border-bottom:1px solid #eee; vertical-align: middle;">
                            <?php if ( $has_ticket && !empty($ticket_hash) ): ?>
                                <?php 
                                    $msg_whats = "Olá " . $p['nome_completo'] . ", aqui está seu ingresso para o passeio! Acesse o QR Code: https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . $ticket_hash;
                                    $link_whats = "https://wa.me/?text=" . urlencode($msg_whats);
                                ?>
                                <a href="<?php echo esc_url($link_whats); ?>" target="_blank" class="button" style="font-size:12px; padding:5px 10px; background-color:#25D366; color:white; border:none; text-decoration:none; display:inline-block;">📲 Enviar Whats</a>
                            <?php else: ?>
                                <a href="https://www.cachoeiradosciganos.com.br/termo" target="_blank" style="color:#d63638; text-decoration:underline; font-weight:bold;">👉 Assinar Termo</a>
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
                    ⚠️ Existem participantes pendentes. <br>
                    <a href="https://www.cachoeiradosciganos.com.br/termo" target="_blank" style="color:#856404; text-decoration:underline; font-weight:bold;">👉 Clique aqui para assinar o termo de responsabilidade</a>
                </div>
            <?php endif; ?>

        </section>
        <?php
    }

    // --- 1. ASSETS ---
    public function enqueue_scripts() {
        if ( is_account_page() ) {
            wp_enqueue_script( 'jquery-mask', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js', array( 'jquery' ), '1.14.16', true );
        }
    }

    public function enqueue_admin_scripts( $hook ) {
        $screen = get_current_screen();
        if ( $screen && 'shop_order' === $screen->post_type ) {
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

    private function get_age_badge_html( $date_string ) {
        if ( empty($date_string) || $date_string == '0000-00-00' ) return '';
        try {
            $calc_date = $this->force_date_to_db($date_string);
            if(!$calc_date) return '';
            $dob = new DateTime( $calc_date );
            $now = new DateTime();
            $age = $now->diff($dob)->y;
        } catch (Exception $e) { return ''; }

        $icon_adult = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
        $icon_child = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="3"></circle><path d="M9 12h6"></path><path d="M12 21v-9"></path><path d="M8 21h8"></path><path d="M6 12v3a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-3"></path></svg>';

        if ( $age >= 18 ) { $color = '#2271b1'; $bg = '#eaf4fc'; $icon = $icon_adult; } 
        else { $color = '#d63638'; $bg = '#fbeaea'; $icon = $icon_child; }

        $style = "display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 600; color: {$color}; background: {$bg}; padding: 3px 8px; border-radius: 12px; border: 1px solid {$color}; margin-right: 8px; white-space: nowrap; flex-shrink: 0;";
        return sprintf('<span style="%s">%s %s anos</span>', $style, $icon, $age);
    }

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
            echo '<div style="font-size: 11px; color: #d63638; margin-top: 3px; font-weight:600;">';
            echo '⚠️ Termo Pendente';
            echo '</div>';
        }
    }

    public function column_content($order) {
        $participants = $this->get_participants_safe( $order );
        if ( empty( $participants ) ) { echo '<span style="color:#999;">-</span>'; return; }

        $order_id = $order->get_id();
        $is_locked = in_array( $order->get_status(), array('completed', 'cancelled', 'refunded', 'failed') );
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
                $btn_style = 'background-color: #46b450; color: #fff; border-color: #46b450;'; 
            } else {
                $btn_text = "⚠️ Assinar ($signed/$total)";
                $btn_style = 'background-color: #ffba00; color: #fff; border-color: #ffba00;'; 
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
            $termo_link = 'https://www.cachoeiradosciganos.com.br/termo';
            $msg_text = "⚠️ <strong>Atenção:</strong> Faltam assinaturas ($missing/$total). Peça para cada participante acessar o site.<br>";
            $msg_text .= "<a href='$termo_link' target='_blank' style='color:{$msg_color}; text-decoration:underline; font-weight:bold; margin-top:5px; display:inline-block;'>👉 Clique aqui para assinar o termo</a>";
            $msg_bg = '#fff3cd'; $msg_color = '#856404'; $msg_border = '#ffeeba';
        }

        ?>
        <div id="<?php echo esc_attr($modal_id); ?>" class="wcai-modal-overlay" style="display:none;">
            <div class="wcai-modal-content">
                <div class="wcai-modal-header">
                    <h3>Participantes - Pedido #<?php echo $order_id; ?></h3>
                    <span class="wcai-close-modal">&times;</span>
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
                                            <input type="text" name="participantes[<?php echo $field_key; ?>][nome_completo]" value="<?php echo esc_attr($p['nome_completo']); ?>" <?php echo $readonly; ?> required style="flex-grow: 1;">
                                        </div>
                                    </td>
                                    <td><input type="text" class="wcai-mask-cpf" name="participantes[<?php echo $field_key; ?>][cpf]" value="<?php echo esc_attr($cpf_value); ?>" <?php echo $readonly; ?> required></td>
                                    <td><input type="text" class="wcai-mask-date" name="participantes[<?php echo $field_key; ?>][data_nascimento]" value="<?php echo esc_attr($val_nasc); ?>" <?php echo $readonly; ?> required placeholder="dd/mm/aaaa"></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div style="margin-top:10px; font-size:12px; color:#666;">
                            <p>ℹ️ <strong>Legenda:</strong> ✅ Assinado | ⚠️ Pendente. <br>Cada participante deve assinar individualmente.</p>
                        </div>

                    </div>
                    <div class="wcai-modal-actions">
                        <span class="wcai-msg"></span>
                        <?php if(!$is_locked): ?><button type="submit" class="button button-primary">Salvar Dados</button><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
        <?php
    }

    public function ajax_update() {
        check_ajax_referer('wcai_update_nonce', 'security');
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        if (!$order || $order->get_customer_id() !== get_current_user_id()) wp_send_json_error('Erro permissão');
        
        $this->process_save($order, $_POST['participantes'], 'Frontend');
        wp_send_json_success(array('message' => 'Salvo com sucesso!'));
    }

    // =========================================================================
    // 4. ADMIN (LAYOUT OTIMIZADO - COLUNAS DIVIDIDAS)
    // =========================================================================
    public function register_admin_metabox() {
        add_meta_box('wcai_participants_box', 'Lista de Participantes (DB)', array($this, 'render_admin_content'), 'shop_order', 'normal', 'high');
    }

    public function render_admin_content($post) {
        $order = wc_get_order($post->ID);
        $participants = $this->get_participants_safe($order);
        
        $total = count($participants);
        $signed = 0;
        foreach($participants as $p) {
            $cpf_clean = $this->safe_cpf_pad($p['cpf']); // PAD FIX
            $meta_signed = $order->get_meta('_waiver_signed_' . $cpf_clean);
            $db_signed = isset($p['termo_assinado']) && $p['termo_assinado'] == 1;
            if ($meta_signed || $db_signed) $signed++;
        }
        $percent = ($total > 0) ? round(($signed / $total) * 100) : 0;
        $color_bar = ($signed == $total && $total > 0) ? '#46b450' : '#f0f0f1';
        $text_color = ($signed == $total && $total > 0) ? '#fff' : '#50575e';
        $border = ($signed == $total && $total > 0) ? 'none' : '1px solid #ddd';

        echo '<style>
            .wcai-table input { width: 100%; box-sizing: border-box; } 
            .wcai-table td { padding: 8px; vertical-align: middle; }
            .wcai-col-id { width: 30px; }
            .wcai-col-cpf { width: 140px; } 
            .wcai-col-nasc { width: 110px; }
            .wcai-col-in { width: 125px; } 
            .wcai-col-out { width: 125px; }
            .wcai-status-badge { 
                display: block; 
                padding: 3px 6px; 
                border-radius: 3px; 
                font-size: 11px; 
                font-weight: bold; 
                line-height: 1;
                text-align: center;
                white-space: nowrap; 
            }
        </style>';
        
        if($total > 0) {
            echo '<div style="margin-bottom: 10px; padding: 8px 12px; background: '.esc_attr($color_bar).'; color:'.esc_attr($text_color).'; border-radius: 4px; border: '.esc_attr($border).'; font-weight: 500;">';
            echo '📝 <strong>Status dos Termos:</strong> ' . $signed . '/' . $total . ' Assinados (' . $percent . '%)';
            echo '</div>';
        }

        echo '<table class="widefat wcai-table">';
        echo '<thead><tr>
                <th class="wcai-col-id">#</th>
                <th>Nome / Status</th>
                <th class="wcai-col-cpf">CPF</th>
                <th class="wcai-col-nasc">Nasc.</th>
                <th class="wcai-col-in">Check-in</th>
                <th class="wcai-col-out">Check-out</th>
              </tr></thead><tbody>';
        
        if ( empty($participants) ) {
            echo '<tr><td colspan="6">Nenhum participante encontrado.</td></tr>';
        } else {
            foreach($participants as $idx => $p) {
                $val_nasc = $this->force_date_to_display($p['data_nascimento']);
                $badge = $this->get_age_badge_html($p['data_nascimento']);
                $field_key = (isset($p['id']) && is_numeric($p['id'])) ? $p['id'] : 'new_' . $idx;
                $id_input = (isset($p['id']) && is_numeric($p['id'])) ? '<input type="hidden" name="wcai_admin_p['.$p['id'].'][id]" value="'.$p['id'].'">' : '';
                
                $cpf_clean = $this->safe_cpf_pad($p['cpf']); // PAD FIX
                $meta_signed = $order->get_meta('_waiver_signed_' . $cpf_clean);
                $db_signed = isset($p['termo_assinado']) && $p['termo_assinado'] == 1;
                
                $status_icon = ($meta_signed || $db_signed) 
                    ? '<span class="dashicons dashicons-yes-alt" style="color:green; font-size:18px; margin-right:5px;" title="Termo Assinado"></span>' 
                    : '<span class="dashicons dashicons-warning" style="color:#ccc; font-size:18px; margin-right:5px;" title="Termo Pendente"></span>';

                // --- LÓGICA DE CHECK-IN ---
                $html_in = '-';
                if( !empty($p['checkin_time']) ) {
                    $hora_ent = date('d/m/y H:i', strtotime($p['checkin_time']));
                    $html_in = '<span class="wcai-status-badge" style="background:#d4edda; color:#155724;">'.$hora_ent.'</span>';
                }

                // --- LÓGICA DE CHECK-OUT ---
                $html_out = '-';
                if( !empty($p['checkout_time']) ) {
                    $hora_sai = date('d/m/y H:i', strtotime($p['checkout_time']));
                    $html_out = '<span class="wcai-status-badge" style="background:#f8d7da; color:#721c24;">'.$hora_sai.'</span>';
                }

                echo '<tr>';
                echo '<td>' . ($idx + 1) . '</td>';
                echo '<td><div style="display: flex; align-items: center;">' . $badge . $status_icon . $id_input . '<input type="text" name="wcai_admin_p['.$field_key.'][nome_completo]" value="'.esc_attr($p['nome_completo']).'" style="flex-grow:1;"></div></td>';
                
                // CPF ADMIN (PAD FIX)
                $cpf_val_admin = class_exists('WCAI_Utils') ? WCAI_Utils::format_cpf($cpf_clean) : $cpf_clean;
                echo '<td><input type="text" class="wcai-mask-cpf" name="wcai_admin_p['.$field_key.'][cpf]" value="'.esc_attr($cpf_val_admin).'"></td>';
                echo '<td><input type="text" class="wcai-mask-date" name="wcai_admin_p['.$field_key.'][data_nascimento]" value="'.esc_attr($val_nasc).'" placeholder="dd/mm/aaaa"></td>';
                
                // COLUNA ENTRADA
                echo '<td>' . $html_in . '</td>';
                // COLUNA SAÍDA
                echo '<td>' . $html_out . '</td>';
                
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '<p class="description">Clique em "Atualizar" no pedido para salvar.</p>';
    }

    public function admin_save($post_id) {
        if (!isset($_POST['wcai_admin_p'])) return;
        $order = wc_get_order($post_id);
        $this->process_save($order, $_POST['wcai_admin_p'], 'Admin');
    }

    // --- 5. BUSCA E SALVAMENTO ---
    private function get_participants_safe($order) {
        $list = array();
        if (class_exists('WCAI_Participants_DB')) {
            $list = WCAI_Participants_DB::get_by_order($order->get_id());
        }
        if (!is_array($list)) $list = array();

        $b_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
        $b_cpf_raw  = $order->get_meta('billing_cpf') ?: $order->get_meta('_billing_cpf');
        $b_dob  = $order->get_meta('billing_birthdate') ?: $order->get_meta('_billing_birthdate');
        // Pad titular se necessário
        $b_cpf_clean = $this->safe_cpf_pad($b_cpf_raw);

        $titular_exists = false;
        foreach ($list as $p) {
            $p_cpf_clean = $this->safe_cpf_pad($p['cpf']); // PAD FIX
            if (!empty($b_cpf_clean) && $p_cpf_clean === $b_cpf_clean) {
                $titular_exists = true; break;
            }
        }

        if (!$titular_exists && !empty($b_name)) {
            $virtual = array('id' => 'virtual_titular', 'nome_completo' => $b_name, 'cpf' => $b_cpf_raw, 'data_nascimento' => $b_dob);
            array_unshift($list, $virtual);
        }
        
        if (count($list) <= 1) {
            $meta = $order->get_meta('_additional_participants');
            if (!empty($meta) && is_array($meta)) {
                foreach($meta as $k => $v) {
                    $list[] = array('id'=>'legacy_'.$k, 'nome_completo'=>$v['nome_completo'], 'cpf'=>$v['cpf'], 'data_nascimento'=>$v['data_nascimento']);
                }
            }
        }
        return $list;
    }

    private function process_save($order, $data, $source) {
        if (empty($data) || !class_exists('WCAI_Participants_DB')) return;

        $has_changes = false;
        
        foreach ($data as $row) {
            // PAD FIX NA HORA DE SALVAR
            $cpf_raw = preg_replace('/[^0-9]/', '', $row['cpf']);
            $cpf_padded = str_pad($cpf_raw, 11, '0', STR_PAD_LEFT);

            $item = array(
                'nome_completo' => sanitize_text_field($row['nome_completo']),
                'cpf' => $cpf_padded, // Salva sempre com 11 dígitos
                'data_nascimento' => sanitize_text_field($row['data_nascimento']) 
            );

            if (isset($row['id']) && is_numeric($row['id'])) {
                WCAI_Participants_DB::update($row['id'], $item);
                $has_changes = true;
            } else {
                $item['order_id'] = $order->get_id();
                $item['customer_id'] = $order->get_customer_id();
                WCAI_Participants_DB::add($item);
                $has_changes = true;
            }
        }

        if ($has_changes) {
            $order->add_order_note("Atualização de Participantes ($source) realizada.");
            $fresh_list = WCAI_Participants_DB::get_by_order($order->get_id());
            $meta_mirror = array();
            foreach($fresh_list as $row) {
                // PAD FIX para o META também
                $cpf_p = $this->safe_cpf_pad($row['cpf']);
                $meta_mirror[] = array(
                    'nome_completo' => $row['nome_completo'],
                    'cpf' => WCAI_Utils::format_cpf($cpf_p),
                    'data_nascimento' => $row['data_nascimento']
                );
            }
            $order->update_meta_data('_additional_participants', $meta_mirror);
            $order->save();
        }
    }
    
    // Assets modal
    public function modal_assets() { 
        ?>
        <style>
            .wcai-modal-overlay { position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; }
            .wcai-modal-content { background: #fff; width: 90%; max-width: 800px; border-radius: 10px; display: flex; flex-direction: column; max-height: 90vh; overflow: hidden; }
            .wcai-modal-header { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; }
            .wcai-close-modal { font-size: 40px; font-weight: bold; line-height: 25px; cursor: pointer; color: #555; padding: 0 10px; transition: color 0.2s; }
            .wcai-close-modal:hover { color: #000; }
            .wcai-participants-form { display: flex; flex-direction: column; flex: 1; min-height: 0; }
            .wcai-scroll-area { overflow-y: auto; padding: 20px; flex: 1; }
            .wcai-frontend-table { width: 100%; border-collapse: collapse; }
            .wcai-frontend-table td, .wcai-frontend-table th { padding: 12px 8px; border-bottom: 1px solid #eee; }
            .wcai-modal-actions { padding: 15px 20px; border-top: 1px solid #eee; text-align: right; }
            a.woocommerce-button.wcai-action-success { background-color: #46b450 !important; color: #fff !important; border-color: #46b450 !important; }
            a.woocommerce-button.wcai-action-pending { background-color: #ffba00 !important; color: #fff !important; border-color: #ffba00 !important; }
            @media (max-width: 600px) { .wcai-frontend-table thead { display: none; } .wcai-frontend-table td { display: block; width: 100%; border: 0; } }
        </style>
        <script>
        jQuery(document).ready(function($){
            const setupMasks = () => {
                $('.wcai-mask-cpf').mask('000.000.000-00');
                $('.wcai-mask-date').mask('00/00/0000');
            };
            setupMasks();
            $(document).on('click', '.wcai-open-modal', function(){ $('#'+$(this).data('target')).fadeIn().css('display','flex'); });
            $(document).on('click', '.wcai-close-modal', function(){ $(this).closest('.wcai-modal-overlay').fadeOut(); });
            $('.wcai-participants-form').on('submit', function(e){
                e.preventDefault();
                var form = $(this), btn = form.find('button'), msg = form.find('.wcai-msg');
                btn.prop('disabled', true).text('Processando...');
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', form.serialize() + '&action=wcai_update_participants', function(r){
                    if(r.success){ msg.css('color','green').text(r.data.message); setTimeout(()=>location.reload(), 1000); }
                    else { msg.css('color','red').text(r.data); btn.prop('disabled', false).text('Salvar'); }
                });
            });
        });
        </script>
        <?php
    }
}

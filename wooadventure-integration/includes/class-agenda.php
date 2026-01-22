<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Agenda {

    public function __construct() {
        // 1. Menus e Scripts
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        
        // 2. AJAX Painel
        add_action( 'wp_ajax_wcai_get_calendar_events', array( $this, 'ajax_get_events' ) );
        add_action( 'wp_ajax_wcai_get_day_details', array( $this, 'ajax_get_day_details' ) );
        add_action( 'wp_ajax_wcai_clear_cache', array( $this, 'ajax_clear_cache' ) );
        
        // 3. Shortcode de Sucesso (MODIFICADO: GERA O TICKET + E-MAIL)
        add_shortcode( 'wcai_confirma_assinatura', array( $this, 'render_success_tracker' ) );

        // 4. Integração iCal (AGRUPADA)
        add_action( 'init', array( $this, 'handle_ical_feed' ), 1 ); 
        
        // Manutenção
        add_action( 'woocommerce_process_shop_order_meta', array( $this, 'clear_calendar_cache_internal' ) );
        add_action( 'woocommerce_order_status_changed', array( $this, 'clear_calendar_cache_internal' ) );
        add_action( 'admin_init', array( $this, 'db_auto_repair_column' ) );
        add_action( 'admin_post_wcai_reset_key', array($this, 'admin_reset_key') );
    }

    // =========================================================================
    // PARTE A: ICAL FEED (ORIGINAL AGRUPADO - MANTIDO)
    // =========================================================================

    public function handle_ical_feed() {
        if(isset($_GET['wcai_action']) && $_GET['wcai_action'] == 'ical') {
            
            $stored_key = trim(get_option('wcai_ical_secret_key'));
            $request_key = isset($_GET['key']) ? trim($_GET['key']) : '';
            
            if(empty($stored_key) || $request_key !== $stored_key) {
                wp_die('Acesso Negado (Chave Inválida)', '403', 403);
            }

            @set_time_limit(0);
            while(ob_get_level()) ob_end_clean();

            if (isset($_GET['debug'])) {
                header('Content-Type: text/html; charset=utf-8');
                echo "<h1>Relatório iCal (Agrupado)</h1><pre>";
            } else {
                header('Content-Type: text/calendar; charset=utf-8');
                header('Content-Disposition: attachment; filename="agenda_agrupada.ics"');
            }

            global $wpdb;
            $eol = "\r\n";
            
            if(!isset($_GET['debug'])) {
                echo "BEGIN:VCALENDAR" . $eol;
                echo "VERSION:2.0" . $eol;
                echo "PRODID:-//WooAdventure//Grouped//PT" . $eol;
                echo "CALSCALE:GREGORIAN" . $eol;
                echo "METHOD:PUBLISH" . $eol;
                echo "X-WR-CALNAME:Agenda Agrupada" . $eol;
            }
            
            $tz_local = new DateTimeZone('America/Sao_Paulo');
            $tz_utc   = new DateTimeZone('UTC');

            $sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND post_status IN ('wc-processing','wc-completed','wc-on-hold') ORDER BY ID DESC LIMIT 300";
            $order_ids = $wpdb->get_col($sql);
            
            if (isset($_GET['debug'])) echo "IDs Encontrados: " . count($order_ids) . "\n-----------------------------\n";
            
            $start_req = date('Y-m-d', strtotime('-2 months'));
            $end_req   = date('Y-m-d', strtotime('+18 months'));

            $groups = [];

            foreach($order_ids as $oid) {
                $meta = $this->get_date_via_sql_direct($oid);
                
                if(!$meta) continue;
                if($meta['date'] < $start_req || $meta['date'] > $end_req) continue;

                $pax_names = $this->get_pax_via_sql_direct($oid);
                $qtd_pax = count($pax_names);
                if($qtd_pax == 0) $qtd_pax = 1;

                $slot_key = $meta['full'];

                if(!isset($groups[$slot_key])) {
                    $groups[$slot_key] = [
                        'pax_total' => 0,
                        'orders_count' => 0,
                        'details' => [],
                        'dt_start_local' => $slot_key
                    ];
                }

                $groups[$slot_key]['pax_total'] += $qtd_pax;
                $groups[$slot_key]['orders_count']++;
                $groups[$slot_key]['details'][] = "Pedido #$oid ($qtd_pax): " . implode(", ", $pax_names);
            }

            $count_events = 0;

            foreach($groups as $slot_key => $data) {
                try {
                    $dt_obj = new DateTime($data['dt_start_local'], $tz_local);
                    $dt_obj->setTimezone($tz_utc);
                    $dtstart = $dt_obj->format('Ymd\THis\Z');

                    $dt_end_obj = clone $dt_obj;
                    $dt_end_obj->modify('+3 hours');
                    $dtend = $dt_end_obj->format('Ymd\THis\Z');
                    $dtstamp = gmdate('Ymd\THis\Z');
                } catch (Exception $e) { continue; }

                $title = $data['pax_total'] . " pax (" . $data['orders_count'] . " peds)";
                $desc = "Resumo:\\nTotal Pax: " . $data['pax_total'] . "\\nPedidos: " . $data['orders_count'] . "\\n--- DETALHES ---\\n" . implode("\\n", $data['details']);
                $uid = "slot_" . md5($slot_key) . "@" . $_SERVER['HTTP_HOST'];

                if(isset($_GET['debug'])) {
                    echo "📅 GRUPO $slot_key: $title\n";
                } else {
                    echo "BEGIN:VEVENT" . $eol;
                    echo "UID:" . $uid . $eol;
                    echo "DTSTAMP:" . $dtstamp . $eol;
                    echo "DTSTART:" . $dtstart . $eol;
                    echo "DTEND:" . $dtend . $eol;
                    echo "SUMMARY:" . $title . $eol;
                    echo "DESCRIPTION:" . $desc . $eol;
                    echo "END:VEVENT" . $eol;
                }
                $count_events++;
            }
            
            if (isset($_GET['debug'])) {
                echo "\n-----------------------------\nTotal de Grupos Gerados: $count_events.</pre>";
                exit;
            }

            if ($count_events == 0) {
                 echo "BEGIN:VEVENT" . $eol . "UID:empty" . $eol . "DTSTAMP:".gmdate('Ymd\THis\Z'). $eol . "DTSTART:".gmdate('Ymd\THis\Z'). $eol . "SUMMARY:Sem agendamentos" . $eol . "END:VEVENT" . $eol;
            }

            echo "END:VCALENDAR";
            exit;
        }
    }

    private function get_date_via_sql_direct($order_id) {
        global $wpdb;
        $sql = "SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta as im JOIN {$wpdb->prefix}woocommerce_order_items as i ON im.order_item_id = i.order_item_id WHERE i.order_id = %d AND (im.meta_key = 'tour_date' OR im.meta_key = '_tour_date' OR im.meta_key = 'Data' OR im.meta_key = 'Data do Passeio') LIMIT 1";
        $raw_val = $wpdb->get_var($wpdb->prepare($sql, $order_id));
        if(!$raw_val) return false;
        $raw_val = trim($raw_val);

        if(preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $raw_val, $m)){ 
             $d = $m[0]; $t = '00:00';
             if(preg_match('/\s(\d{1,2}):(\d{1,2})/', $raw_val, $mt)) $t = sprintf('%02d:%02d', $mt[1], $mt[2]);
             return ['full' => $d.'T'.$t.':00', 'date' => $d];
        }
        if(preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})/', $raw_val, $m)){ 
             $d = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]); $t = '00:00';
             if(preg_match('/\s(\d{1,2}):(\d{1,2})/', $raw_val, $mt)) $t = sprintf('%02d:%02d', $mt[1], $mt[2]);
             return ['full' => $d.'T'.$t.':00', 'date' => $d];
        }
        if(preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $raw_val, $m)){ 
             $d = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]); $t = '00:00';
             if(preg_match('/\s(\d{1,2}):(\d{1,2})/', $raw_val, $mt)) $t = sprintf('%02d:%02d', $mt[1], $mt[2]);
             return ['full' => $d.'T'.$t.':00', 'date' => $d];
        }
        return false;
    }

    private function get_pax_via_sql_direct($order_id) {
        global $wpdb;
        $names = [];
        $sql = "SELECT meta_value FROM {$wpdb->prefix}woocommerce_order_itemmeta as im JOIN {$wpdb->prefix}woocommerce_order_items as i ON im.order_item_id = i.order_item_id WHERE i.order_id = %d AND (im.meta_key LIKE '%%nome%%' OR im.meta_key LIKE '%%participante%%') AND im.meta_value NOT LIKE '%%{%%'";
        $results = $wpdb->get_col($wpdb->prepare($sql, $order_id));
        if($results) {
            foreach($results as $n) {
                $clean = trim(preg_replace('/[^a-zA-Z0-9 ]/', '', strtr(utf8_decode($n), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'), 'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY')));
                if(strlen($clean) > 2) $names[] = $clean;
            }
        }
        return $names;
    }

    // =========================================================================
    // PARTE B: PAINEL ADMIN (MANTIDO)
    // =========================================================================

    public function admin_reset_key() {
        if(current_user_can('manage_woocommerce')) {
            update_option('wcai_ical_secret_key', wp_generate_password(24, false));
            wp_safe_redirect(admin_url('admin.php?page=wcai-agenda'));
            exit;
        }
    }

    public function add_menu_page() { 
        add_submenu_page('woocommerce', 'Agenda', 'Agenda Passeios', 'manage_woocommerce', 'wcai-agenda', array($this, 'render_page')); 
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'wcai-agenda' ) === false ) return;
        wp_enqueue_style( 'fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' );
        wp_enqueue_script( 'fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js', array(), '5.11.3', true );
        wp_enqueue_script( 'fullcalendar-locales', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js', array('fullcalendar-js'), '5.11.3', true );
        wp_add_inline_style( 'fullcalendar-css', ".wcai-calendar-wrapper { background:#fff; padding:20px; margin-top:20px; border-radius:5px; box-shadow:0 1px 3px rgba(0,0,0,0.1); } .wcai-sync-box { background:#fff; padding:15px; border:1px solid #ccd0d4; border-left:4px solid #007cba; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 1px 1px rgba(0,0,0,.04); } .wcai-sync-input { width:60%; padding:8px; background:#f0f0f1; border:1px solid #8c8f94; color:#50575e; } .wcai-modal { display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(2px); } .wcai-modal-content { background:#fff; margin:5% auto; width:95%; max-width:900px; padding:0; border-radius:8px; box-shadow:0 5px 15px rgba(0,0,0,0.3); max-height:85vh; overflow-y:auto; } .wcai-modal-header { padding:15px 20px; background:#f8f9fa; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; border-radius:8px 8px 0 0; } .wcai-modal-body { padding:20px; } .wcai-close { font-size:28px; cursor:pointer; color:#aaa; } .wcai-table { width:100%; border-collapse:collapse; margin-top:10px; } .wcai-table th { text-align:left; padding:10px; background:#f1f1f1; border-bottom:2px solid #ddd; font-size:13px; } .wcai-table td { padding:10px; border-bottom:1px solid #eee; } .wcai-status { padding:3px 8px; border-radius:12px; font-size:10px; font-weight:700; text-transform:uppercase; } .status-completed { background:#d4edda; color:#155724; } .status-processing { background:#cce5ff; color:#004085; } .fc-event { cursor:pointer; border:none; margin-bottom:2px!important; }");
    }

    public function render_page() {
        $key = get_option('wcai_ical_secret_key') ?: wp_generate_password(24, false); update_option('wcai_ical_secret_key', $key);
        $feed_url = site_url('/?wcai_action=ical&key=' . $key);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Agenda de Passeios</h1>
            <hr class="wp-header-end">
            <div class="wcai-sync-box">
                <div style="flex-grow:1; margin-right:15px;">
                    <strong>🔗 Sincronização Automática:</strong><br>
                    <input type="text" class="wcai-sync-input" value="<?php echo $feed_url; ?>" style="width:100%" readonly onclick="this.select()">
                </div>
                <div>
                    <a href="<?php echo admin_url('admin-post.php?action=wcai_reset_key'); ?>" class="button" onclick="return confirm('Isso invalida o link anterior. Tem certeza?');">🔄 Gerar Nova Chave</a>
                    <button type="button" id="wcai-btn-clear-cache" class="button button-secondary">🧹 Limpar Cache</button>
                </div>
            </div>
            <div class="wcai-calendar-wrapper"><div id="wcai-calendar"></div></div>
            <div id="wcaiDetailModal" class="wcai-modal"><div class="wcai-modal-content"><div class="wcai-modal-header"><h2 style="margin:0" id="modalTitle">Detalhes</h2><span class="wcai-close">&times;</span></div><div class="wcai-modal-body" id="modalBody"></div></div></div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendar = new FullCalendar.Calendar(document.getElementById('wcai-calendar'), {
                initialView: 'dayGridMonth', locale: 'pt-br', height: 'auto', displayEventTime: true,
                eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false, hour12: false },
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listWeek' },
                events: { url: '<?php echo admin_url('admin-ajax.php'); ?>', method: 'POST', extraParams: { action: 'wcai_get_calendar_events', nonce: '<?php echo wp_create_nonce('wcai_calendar_nonce'); ?>' } },
                eventClick: function(info) {
                    var m = document.getElementById("wcaiDetailModal"); m.style.display = "block";
                    document.getElementById("modalBody").innerHTML = '<div style="text-align:center;padding:20px">Carregando...</div>';
                    var rawStr = info.event.startStr; var parts = rawStr.split('T');
                    var title = parts[0].split('-').reverse().join('/'); if(parts[1] && !info.event.allDay) title += ' às ' + parts[1].substring(0, 5);
                    document.getElementById("modalTitle").innerText = title;
                    jQuery.post(ajaxurl, { action: 'wcai_get_day_details', iso_string: rawStr, is_allday: info.event.allDay ? 1 : 0, nonce: '<?php echo wp_create_nonce('wcai_calendar_nonce'); ?>' })
                    .done(function(r){ document.getElementById("modalBody").innerHTML = r.success ? r.data.html : '<p>Erro.</p>'; });
                }
            });
            calendar.render();
            jQuery('#wcai-btn-clear-cache').click(function(e){ e.preventDefault(); jQuery(this).text('Limpando...').prop('disabled', true); jQuery.post(ajaxurl, { action: 'wcai_clear_cache' }, function(){ location.reload(); }); });
            jQuery('.wcai-close').click(function(){ jQuery('#wcaiDetailModal').fadeOut(); });
            jQuery(window).click(function(e){ if(e.target.id=='wcaiDetailModal') jQuery('#wcaiDetailModal').fadeOut(); });
        });
        </script>
        <?php
    }

    public function ajax_get_events() {
        check_ajax_referer('wcai_calendar_nonce', 'nonce');
        $start = isset($_POST['start']) ? $_POST['start'] : date('Y-m-01');
        $end = isset($_POST['end']) ? $_POST['end'] : date('Y-m-t');
        $cached = get_transient('wcai_events_' . md5($start . $end));
        if($cached !== false) wp_send_json($cached);

        $orders = $this->fetch_orders_in_range($start, $end);
        $grouped = []; 
        foreach($orders as $o) {
            $meta = $this->get_tour_datetime_smart($o);
            if(!$meta || $meta['date'] < $start || $meta['date'] > $end) continue;
            $k = $meta['full'];
            if(!isset($grouped[$k])) $grouped[$k] = ['pax'=>0, 'peds'=>[]];
            $c = $this->count_pax_forensic($o); if($c == 0) $c = 1; 
            $grouped[$k]['pax'] += $c; $grouped[$k]['peds'][$o->get_id()] = true;
        }
        $evs = [];
        foreach($grouped as $iso => $d) {
            if($d['pax'] > 0) {
                $evs[] = [
                    'title' => $d['pax'] . " pax (" . count($d['peds']) . " peds)",
                    'start' => $iso, 'allDay' => (strpos($iso, 'T00:00:00') !== false),
                    'backgroundColor' => ($d['pax'] >= 10 ? '#155724' : '#3788d8'), 'borderColor' => ($d['pax'] >= 10 ? '#155724' : '#3788d8')
                ];
            }
        }
        set_transient('wcai_events_' . md5($start . $end), $evs, 12 * HOUR_IN_SECONDS);
        wp_send_json($evs);
    }

    public function ajax_get_day_details() {
        check_ajax_referer('wcai_calendar_nonce', 'nonce');
        $iso = isset($_POST['iso_string']) ? sanitize_text_field($_POST['iso_string']) : '';
        if(empty($iso)) wp_send_json_error();
        $parts = explode('T', $iso); $dt = $parts[0]; $tm = isset($parts[1]) ? substr($parts[1], 0, 5) : '00:00';
        $orders = $this->fetch_orders_in_range($dt, $dt);
        $found = [];
        foreach($orders as $o) {
            $meta = $this->get_tour_datetime_smart($o);
            if(!$meta || $meta['date'] !== $dt || ($meta['time'] !== $tm && $tm !== '00:00')) continue;
            $pax = $this->get_pax_details_forensic($o);
            foreach($pax as $p) {
                $clean = preg_replace('/[^0-9]/','',$p['cpf']);
                $meta_signed = $o->get_meta('_waiver_signed_'.$clean);
                $db_signed = false;
                if(class_exists('WCAI_Participants_DB')) {
                    global $wpdb; $tb = WCAI_Participants_DB::get_table_name();
                    $chk = $wpdb->get_var($wpdb->prepare("SELECT termo_assinado FROM $tb WHERE order_id=%d AND REPLACE(REPLACE(cpf,'.',''),'-','')=%s", $o->get_id(), $clean));
                    if($chk) $db_signed = true;
                }
                $icon = ($meta_signed || $db_signed) ? '<span title="Assinado">✅</span>' : '<span title="Pendente" style="opacity:0.3">⚠️</span>';
                $nasc = $p['nasc'];
                if(!empty($p['nasc']) && strpos($p['nasc'],'-')!==false) {
                    $d = DateTime::createFromFormat('Y-m-d', $p['nasc']);
                    if($d) { $age = (new DateTime())->diff($d)->y; $nasc = $d->format('d/m/Y') . " <span style='color:#888'>($age anos)</span>"; }
                }
                $found[] = [
                    'order_number' => $o->get_order_number(), 'order_id' => $o->get_id(), 'nome' => $p['nome'], 'cpf' => $p['cpf'], 'nasc_html' => $nasc,
                    'status_name' => wc_get_order_status_name($o->get_status()), 'status_slug' => $o->get_status(), 'termo_icon' => $icon
                ];
            }
        }
        if(empty($found)) wp_send_json_error();
        ob_start(); ?>
        <table class="wcai-table">
            <thead><tr><th style="width:30px">T.</th><th>Pedido</th><th>Participante</th><th>CPF</th><th>Nascimento</th><th>Status</th></tr></thead>
            <tbody><?php foreach($found as $r): ?><tr><td style="text-align:center;"><?php echo $r['termo_icon']; ?></td><td><a href="<?php echo get_edit_post_link($r['order_id']); ?>" target="_blank">#<?php echo $r['order_number']; ?></a></td><td><?php echo esc_html($r['nome']); ?></td><td><?php echo esc_html($r['cpf']); ?></td><td><?php echo $r['nasc_html']; ?></td><td><span class="wcai-status status-<?php echo $r['status_slug']; ?>"><?php echo esc_html($r['status_name']); ?></span></td></tr><?php endforeach; ?></tbody>
        </table>
        <div style="margin-top:15px;text-align:right"><button class="button button-primary" onclick="window.print()">🖨️ Imprimir</button></div>
        <?php wp_send_json_success(['html'=>ob_get_clean()]);
    }

    // =========================================================================
    // MÉTODOS DE RASTREAMENTO (MODIFICADO: Ticket + Check-in + E-mail)
    // =========================================================================
    public function render_success_tracker() {
        error_log('[WCAI] Shortcode [wcai_confirma_assinatura] ACIONADO.');

        if ( isset($_COOKIE['wcai_pax_session']) ) {
            $data = explode('|', $_COOKIE['wcai_pax_session']);
            if(count($data) == 2) {
                // Processa assinatura e gera ticket
                $ticket_info = $this->sign_waiver_internal(intval($data[0]), sanitize_text_field($data[1]));
                
                // Limpa o cookie da sessão
                setcookie('wcai_pax_session', '', time() - 3600, '/'); 
                
                // Se gerou ticket, mostra o QR Code e TENTA ENVIAR E-MAIL
                if ( $ticket_info && !empty($ticket_info['qr_url']) ) {
                    error_log('[WCAI] Ticket gerado. Preparando envio de e-mail...');
                    
                    // --- DISPARO DE E-MAIL ---
                    $this->send_ticket_email_via_shortcode($ticket_info);
                    // -------------------------

                    return '
                    <div style="text-align:center; padding:20px; background:#fff; border:1px solid #d4edda; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.05); margin-bottom:20px;">
                        <div style="color:#155724; font-size:18px; font-weight:bold; margin-bottom:15px;">✅ Assinatura Confirmada!</div>
                        <p>O seu ingresso foi enviado por e-mail. Também pode guardá-lo agora:</p>
                        
                        <div style="margin:20px auto; display:inline-block; border:1px solid #ccc; padding:10px; background:#fff;">
                            <img src="' . $ticket_info['qr_url'] . '" alt="QR Code Ticket" style="width:200px; height:200px;">
                        </div>
                        
                        <div style="font-size:12px; color:#777; margin-top:10px;">
                            Participante: <strong>' . esc_html($ticket_info['nome']) . '</strong><br>
                            Pedido: #' . esc_html($ticket_info['order_id']) . '
                        </div>
                        
                        <button onclick="window.print()" style="margin-top:15px; padding:10px 20px; background:#007cba; color:#fff; border:none; border-radius:4px; cursor:pointer;">🖨️ Imprimir / Salvar</button>
                    </div>';
                }

                return '<div style="padding:15px;background:#d4edda;color:#155724;border-radius:5px;text-align:center;">✅ Assinatura confirmada com sucesso!</div>';
            }
        } else {
            // Caso opcional: Cookie não existe (refresh de página)
            // error_log('[WCAI] ALERTA: Shortcode rodou, mas o cookie wcai_pax_session NÃO foi encontrado.');
        }
        return '';
    }

    private function sign_waiver_internal($order_id, $cpf_clean) {
        $order = wc_get_order($order_id); if(!$order) return false;
        
        $sig = date('Y-m-d H:i:s') . ' | IP: ' . $_SERVER['REMOTE_ADDR'];
        $order->update_meta_data('_waiver_signed_' . $cpf_clean, $sig);
        $order->save();
        
        if(class_exists('WCAI_Participants_DB')) {
            global $wpdb; 
            $table = WCAI_Participants_DB::get_table_name();
            
            // Busca participante (já incluindo hash se existir)
            $pax = $wpdb->get_row($wpdb->prepare("SELECT id, nome_completo, ticket_hash FROM $table WHERE order_id=%d AND REPLACE(REPLACE(cpf,'.',''),'-','')=%s", $order_id, $cpf_clean));
            
            // --- AUTO-MIGRAÇÃO (Se não existe, cria agora) ---
            if(!$pax) {
                // error_log('[WCAI] Participante não achado no BD. Tentando migrar CPF: ' . $cpf_clean);
                $b_cpf = preg_replace('/\D/', '', $order->get_meta('_billing_cpf') ?: $order->get_meta('billing_cpf'));
                $new_data = [];

                if($b_cpf === $cpf_clean) {
                    $new_data = [
                        'order_id' => $order_id,
                        'customer_id' => $order->get_customer_id(),
                        'nome_completo' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                        'cpf' => $b_cpf,
                        'data_nascimento' => $order->get_meta('billing_birthdate') ?: ''
                    ];
                } else {
                    $meta = $order->get_meta('_additional_participants');
                    if(is_array($meta)) {
                        foreach($meta as $m) {
                            if(preg_replace('/[^0-9]/','',$m['cpf']) === $cpf_clean) {
                                $new_data = [
                                    'order_id' => $order_id,
                                    'customer_id' => $order->get_customer_id(),
                                    'nome_completo' => $m['nome_completo'],
                                    'cpf' => $m['cpf'],
                                    'data_nascimento' => $m['data_nascimento']
                                ];
                                break;
                            }
                        }
                    }
                }

                if(!empty($new_data)) {
                    WCAI_Participants_DB::add($new_data);
                    $pax = $wpdb->get_row($wpdb->prepare("SELECT id, nome_completo, ticket_hash FROM $table WHERE order_id=%d AND REPLACE(REPLACE(cpf,'.',''),'-','')=%s", $order_id, $cpf_clean));
                }
            }

            if($pax) {
                // Gera o Hash se não existir
                $hash = $pax->ticket_hash;
                if(empty($hash)) {
                    $salt = wp_salt();
                    $hash = md5($pax->id . $order_id . $salt . time());
                    // Salva Ticket e Check
                    WCAI_Participants_DB::update($pax->id, array('ticket_hash' => $hash, 'termo_assinado' => 1));
                } else {
                    WCAI_Participants_DB::update($pax->id, array('termo_assinado' => 1));
                }
                
                $this->clear_calendar_cache_internal();
                
                return array(
                    'qr_url' => "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . $hash,
                    'nome' => $pax->nome_completo,
                    'order_id' => $order_id,
                    'cpf' => $cpf_clean
                );
            }
        }
        
        $this->clear_calendar_cache_internal();
        return false;
    }

    // --- NOVA FUNÇÃO DE DISPARO DE E-MAIL ---
    private function send_ticket_email_via_shortcode($ticket_info) {
        $order_id = $ticket_info['order_id'];
        $order = wc_get_order($order_id);
        
        if (!$order) {
            error_log('[WCAI] Erro Email: Pedido não carregou.');
            return;
        }

        // 1. TENTA LER O COOKIE DO E-MAIL CAPTURADO
        $to = '';
        if ( isset($_COOKIE['wcai_pax_email_temp']) && is_email($_COOKIE['wcai_pax_email_temp']) ) {
            $to = sanitize_email($_COOKIE['wcai_pax_email_temp']);
            error_log('[WCAI] Usando e-mail capturado do cookie: ' . $to);
            // Limpa o cookie do email para não ficar "sujo"
            setcookie('wcai_pax_email_temp', '', time() - 3600, '/');
        } else {
            // 2. FALLBACK: Usa o Billing Email
            $to = $order->get_billing_email();
            error_log('[WCAI] E-mail capturado não encontrado. Usando Billing: ' . $to);
        }

        if ( empty($to) ) {
            error_log('[WCAI] Erro Fatal: Nenhum destinatário para o ingresso.');
            return;
        }

        $nome_pax = $ticket_info['nome'];
        $qr_img = $ticket_info['qr_url'];
        $subject = "🎟️ Seu Ingresso - Pedido #$order_id";
        
        $admin_email = get_option('admin_email');
        $site_title = get_bloginfo('name');
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            "From: $site_title <$admin_email>"
        );

        $msg = "
        <html>
        <body style='font-family:Arial, sans-serif; color:#333; background-color:#f4f4f4; padding:20px;'>
            <div style='max-width:600px; margin:0 auto; background:#fff; border:1px solid #ddd; padding:20px; border-radius:10px;'>
                <h2 style='color:#007cba; text-align:center;'>Passeio Confirmado!</h2>
                <p>Olá,</p>
                <p>O termo de responsabilidade de <strong>$nome_pax</strong> foi assinado com sucesso.</p>
                <p>Abaixo está o ingresso digital para entrada no parque.</p>
                
                <div style='text-align:center; margin:30px 0; background:#f9f9f9; padding:20px; border-radius:10px; border:1px dashed #ccc;'>
                    <img src='$qr_img' alt='QR Code Ticket' style='width:200px; height:200px;'><br>
                    <strong style='font-size:24px; letter-spacing:2px; display:block; margin-top:15px; color:#333;'>#$order_id</strong>
                    <p style='font-size:14px; color:#666; margin-top:5px;'>Apresente este código na portaria.</p>
                </div>

                <p style='text-align:center; color:#555;'>Dica: <strong>Guarde esta imagem</strong> no seu telemóvel.</p>
                <hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>
                <p style='font-size:12px; color:#aaa; text-align:center;'>$site_title - Ecoturismo</p>
            </div>
        </body>
        </html>
        ";

        $sent = wp_mail($to, $subject, $msg, $headers);
        if($sent) error_log('[WCAI] Sucesso no envio do e-mail para: ' . $to);
        else error_log('[WCAI] Falha no wp_mail.');
    }

    // =========================================================================
    // AUXILIARES (MANTIDAS DO ORIGINAL - SEM ERROS DE FATAL ERROR)
    // =========================================================================
    private function fetch_orders_in_range($start, $end) {
        $st = class_exists('WCAI_Settings') ? WCAI_Settings::get_calendar_statuses() : ['wc-processing','wc-completed'];
        return wc_get_orders(['limit'=>-1, 'status'=>$st, 'date_created'=>'>='.strtotime('-15 years')]);
    }
    private function get_tour_datetime_smart($order) {
        $pk = class_exists('WCAI_Settings') ? WCAI_Settings::get_date_meta_key() : 'tour_date';
        $keys = array_unique([$pk, 'tour_date', '_tour_date', 'date', 'Data', 'Data do Passeio', 'booking_date']);
        $raw = ''; foreach($keys as $k){ if($v=$order->get_meta($k)){ $raw=$v; break; } }
        if(!$raw){ foreach($order->get_items() as $i){ foreach($keys as $k){ if($v=$i->get_meta($k)){ $raw=$v; break 2; } } } }
        if(!$raw) return false;
        $d=''; $t='00:00';
        if(preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})\s+(\d{1,2}):(\d{1,2})/', $raw, $m)){ $d=sprintf('%04d-%02d-%02d',$m[1],$m[2],$m[3]); $t=sprintf('%02d:%02d',$m[4],$m[5]); }
        elseif(preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}):(\d{1,2})/', $raw, $m)){ $d=sprintf('%04d-%02d-%02d',$m[3],$m[2],$m[1]); $t=sprintf('%02d:%02d',$m[4],$m[5]); }
        elseif(preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})/', $raw, $m)){ $d=sprintf('%04d-%02d-%02d',$m[3],$m[2],$m[1]); }
        else{ $ts=strtotime(str_replace('/','-',$raw)); if($ts){ $d=date('Y-m-d',$ts); if(strpos($raw,':')) $t=date('H:i',$ts); } }
        return empty($d) ? false : ['date'=>$d, 'time'=>$t, 'full'=>$d.'T'.$t.':00'];
    }
    private function get_pax_details_forensic($order) {
        $l=[]; if(class_exists('WCAI_Participants_DB')) foreach(WCAI_Participants_DB::get_by_order($order->get_id()) as $r) $l[]=['nome'=>$r['nome_completo'],'cpf'=>$r['cpf'],'nasc'=>$r['data_nascimento']];
        if(empty($l)) { $n=$order->get_customer_note(); if(!empty($n)) foreach(explode("\n",$n) as $lin){ $x=explode(',',$lin); if(count($x)>=3)$l[]=['nome'=>trim($x[0]),'cpf'=>trim($x[1]),'nasc'=>trim($x[2])]; } }
        if(empty($l)) { foreach($order->get_items() as $i) { foreach($i->get_meta_data() as $m) { $k=strtolower($m->key); $v=$m->value; if((strpos($k,'nome')!==false||strpos($k,'participante')!==false)&&strlen($v)>3&&!preg_match('/\d/',$v)) $l[]=['nome'=>$v,'cpf'=>'-','nasc'=>'-']; } } }
        $tit=$order->get_billing_first_name().' '.$order->get_billing_last_name(); if(!empty($tit) && empty($l)) $l[]=['nome'=>$tit,'cpf'=>$order->get_meta('_billing_cpf'),'nasc'=>''];
        return $l;
    }
    private function count_pax_forensic($order) { return count($this->get_pax_details_forensic($order)); }
    public function ajax_clear_cache() { global $wpdb; $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_wcai%'"); wp_send_json_success(); }
    public function db_auto_repair_column() {}
    public function clear_calendar_cache_internal() {} 
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Agenda_Admin {

    // =========================================================================
    // PARTE B: PAINEL ADMIN (MANTIDO)
    // =========================================================================

    public function admin_reset_key() {
        if ( ! current_user_can( WCAI_Capabilities::MANAGE_DEPARTURES ) ) {
            wp_die( 'Acesso negado.', 403 );
        }

        check_admin_referer( 'wcai_reset_ical_key' );
        update_option('wcai_ical_secret_key', wp_generate_password(24, false));
        wp_safe_redirect(admin_url('admin.php?page=wcai-agenda'));
        exit;
    }

    public function add_menu_page() { 
        add_submenu_page('woocommerce', 'Agenda', 'Agenda Passeios', WCAI_Capabilities::VIEW_MANIFEST, 'wcai-agenda', array($this, 'render_page')); 
    }

    public function add_sri_attributes( $tag, $handle ) {
        if ( ! isset( self::FULLCALENDAR_SRI[ $handle ] ) ) {
            return $tag;
        }

        return preg_replace( '/(<(?:script|link)\b)/', '$1 integrity="' . esc_attr( self::FULLCALENDAR_SRI[ $handle ] ) . '" crossorigin="anonymous"', $tag, 1 );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'wcai-agenda' ) === false ) return;
        wp_enqueue_style( 'fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' );
        wp_enqueue_script( 'fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js', array(), '5.11.3', true );
        wp_enqueue_script( 'fullcalendar-locales', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/locales-all.min.js', array('fullcalendar-js'), '5.11.3', true );
        wp_add_inline_style( 'fullcalendar-css', ".wcai-calendar-wrapper { background:#fff; padding:20px; margin-top:20px; border-radius:5px; box-shadow:0 1px 3px rgba(0,0,0,0.1); } .wcai-sync-box { background:#fff; padding:15px; border:1px solid #ccd0d4; border-left:4px solid #007cba; margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; box-shadow:0 1px 1px rgba(0,0,0,.04); } .wcai-sync-input { width:60%; padding:8px; background:#f0f0f1; border:1px solid #8c8f94; color:#50575e; } .wcai-modal { display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.6); backdrop-filter:blur(2px); } .wcai-modal-content { background:#fff; margin:5% auto; width:95%; max-width:900px; padding:0; border-radius:8px; box-shadow:0 5px 15px rgba(0,0,0,0.3); max-height:85vh; overflow-y:auto; } .wcai-modal-header { padding:15px 20px; background:#f8f9fa; border-bottom:1px solid #eee; display:flex; justify-content:space-between; align-items:center; border-radius:8px 8px 0 0; } .wcai-modal-body { padding:20px; } .wcai-close { min-width:44px; min-height:44px; padding:0; border:0; background:none; font-size:28px; line-height:1; cursor:pointer; color:#50575e; } .wcai-close:hover, .wcai-close:focus { color:#000; } .wcai-table { width:100%; border-collapse:collapse; margin-top:10px; } .wcai-table th { text-align:left; padding:10px; background:#f1f1f1; border-bottom:2px solid #ddd; font-size:13px; } .wcai-table td { padding:10px; border-bottom:1px solid #eee; } .wcai-status { padding:3px 8px; border-radius:12px; font-size:10px; font-weight:700; text-transform:uppercase; } .status-completed { background:#d4edda; color:#155724; } .status-processing { background:#cce5ff; color:#004085; } .fc-event { cursor:pointer; border:none; margin-bottom:2px!important; }");
    }

    public function render_page() {
        if ( ! current_user_can( WCAI_Capabilities::VIEW_MANIFEST ) ) {
            wp_die( 'Acesso negado.', 403 );
        }
        $key = get_option('wcai_ical_secret_key') ?: wp_generate_password(24, false); update_option('wcai_ical_secret_key', $key);
        $feed_url = site_url('/?wcai_action=ical&key=' . $key);
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Agenda de Passeios</h1>
            <hr class="wp-header-end">
            <div class="wcai-sync-box">
                <div style="flex-grow:1; margin-right:15px;">
                    <strong>🔗 Sincronização Automática:</strong><br>
                    <input type="text" class="wcai-sync-input" value="<?php echo esc_attr( $feed_url ); ?>" style="width:100%" readonly onclick="this.select()">
                </div>
                <div>
                    <a href="<?php echo esc_url( wp_nonce_url( admin_url('admin-post.php?action=wcai_reset_key'), 'wcai_reset_ical_key' ) ); ?>" class="button" onclick="return confirm('Isso invalida o link anterior. Tem certeza?');">🔄 Gerar Nova Chave</a>
                    <button type="button" id="wcai-btn-clear-cache" class="button button-secondary">🧹 Limpar Cache</button>
                </div>
            </div>
            <div class="wcai-calendar-wrapper"><div id="wcai-calendar"></div></div>
            <div id="wcaiDetailModal" class="wcai-modal" role="dialog" aria-modal="true" aria-labelledby="modalTitle" aria-hidden="true"><div class="wcai-modal-content"><div class="wcai-modal-header"><h2 style="margin:0" id="modalTitle"><?php echo esc_html__( 'Detalhes', 'wooadventure-integration' ); ?></h2><button type="button" class="wcai-close" aria-label="<?php echo esc_attr__( 'Fechar', 'wooadventure-integration' ); ?>">&times;</button></div><div class="wcai-modal-body" id="modalBody"></div></div></div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var wcaiText = { loading: <?php echo wp_json_encode( __( 'Carregando...', 'wooadventure-integration' ) ); ?>, error: <?php echo wp_json_encode( __( 'Não foi possível carregar os detalhes desta saída. Tente novamente.', 'wooadventure-integration' ) ); ?> };
            var modal = document.getElementById('wcaiDetailModal'), lastFocus = null;
            function openModal() {
                lastFocus = document.activeElement;
                modal.style.display = 'block';
                modal.setAttribute('aria-hidden', 'false');
                modal.querySelector('.wcai-close').focus();
            }
            function closeModal() {
                modal.style.display = 'none';
                modal.setAttribute('aria-hidden', 'true');
                if (lastFocus && lastFocus.focus) lastFocus.focus();
            }
            function showModalError(text) {
                var p = document.createElement('p'), body = document.getElementById('modalBody');
                p.setAttribute('role', 'alert');
                p.textContent = text;
                body.innerHTML = '';
                body.appendChild(p);
            }
            var calendar = new FullCalendar.Calendar(document.getElementById('wcai-calendar'), {
                initialView: 'dayGridMonth', locale: 'pt-br', height: 'auto', displayEventTime: true,
                eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: false, hour12: false },
                headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,listWeek' },
                events: { url: '<?php echo admin_url('admin-ajax.php'); ?>', method: 'POST', extraParams: { action: 'wcai_get_calendar_events', nonce: '<?php echo wp_create_nonce('wcai_calendar_nonce'); ?>' } },
                eventClick: function(info) {
                    openModal();
                    document.getElementById("modalBody").innerHTML = '<div style="text-align:center;padding:20px">' + wcaiText.loading + '</div>';
                    var rawStr = info.event.startStr; var parts = rawStr.split('T');
                    var title = parts[0].split('-').reverse().join('/'); if(parts[1] && !info.event.allDay) title += ' às ' + parts[1].substring(0, 5);
                    document.getElementById("modalTitle").innerText = title;
                    jQuery.post(ajaxurl, { action: 'wcai_get_day_details', departure_id: info.event.id, nonce: '<?php echo wp_create_nonce('wcai_calendar_nonce'); ?>' })
                    .done(function(r){
                        if (r.success) { document.getElementById("modalBody").innerHTML = r.data.html; return; }
                        showModalError((r.data && r.data.message) || wcaiText.error);
                    })
                    .fail(function(){ showModalError(wcaiText.error); });
                }
            });
            calendar.render();
            jQuery('#wcai-btn-clear-cache').click(function(e){ e.preventDefault(); jQuery(this).text('Limpando...').prop('disabled', true); jQuery.post(ajaxurl, { action: 'wcai_clear_cache', nonce: '<?php echo esc_js( wp_create_nonce( 'wcai_clear_calendar_cache' ) ); ?>' }, function(){ location.reload(); }); });
            jQuery('.wcai-close').click(closeModal);
            jQuery(window).click(function(e){ if(e.target.id=='wcaiDetailModal') closeModal(); });
            document.addEventListener('keydown', function(e){
                if (modal.style.display !== 'block') return;
                if (e.key === 'Escape') { closeModal(); return; }
                if (e.key !== 'Tab') return;
                var items = modal.querySelectorAll('a[href], button:not([disabled]), input, select, textarea');
                if (!items.length) return;
                var first = items[0], last = items[items.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            });
        });
        </script>
        <?php
    }

}

<?php

if ( ! defined( 'ABSPATH' ) ) exit;



class WCAI_Scanner {



    public function __construct() {

        add_shortcode( 'wcai_scanner_app', array( $this, 'render_scanner_app' ) );

        

        // AJAX Endpoints

        add_action( 'wp_ajax_wcai_process_qr_checkin', array( $this, 'ajax_process_checkin' ) );

        add_action( 'wp_ajax_wcai_manual_search', array( $this, 'ajax_manual_search' ) );

        add_action( 'wp_ajax_wcai_get_manifest', array( $this, 'ajax_get_manifest' ) );

        add_action( 'wp_ajax_wcai_get_calendar_data', array( $this, 'ajax_get_calendar_data' ) );

        add_action( 'wp_ajax_wcai_sync_data', array( $this, 'ajax_sync_data' ) );

        

        // PWA

        add_action( 'template_redirect', array( $this, 'handle_requests' ) );

        

        // Limpeza de Scripts (Iubenda Killer)

        add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_unwanted_scripts' ), 999 );

        add_action( 'wp_footer', array( $this, 'remove_footer_scripts' ), 1 );

    }



    public function dequeue_unwanted_scripts() {

        if ( isset($_GET['fullscreen']) && $_GET['fullscreen'] == '1' ) {

            $handles = ['iubenda-cs', 'iubenda-cookie-solution', 'cookie-notice', 'gdpr-cookie-compliance'];

            foreach($handles as $h) { wp_dequeue_script($h); wp_deregister_script($h); }

        }

    }

    

    public function remove_footer_scripts() {

        if ( isset($_GET['fullscreen']) && $_GET['fullscreen'] == '1' ) {

            remove_all_actions('wp_footer'); 

        }

    }



    public function handle_requests() {

        if ( isset($_GET['wcai_pwa']) ) {

            if($_GET['wcai_pwa'] == 'manifest') { $this->render_manifest_json(); exit; }

            if($_GET['wcai_pwa'] == 'sw') { $this->render_service_worker(); exit; }

        }

        if ( isset($_GET['fullscreen']) && $_GET['fullscreen'] == '1' && is_page() ) {

            global $post;

            if ( has_shortcode( $post->post_content, 'wcai_scanner_app' ) ) {

                $this->render_clean_layout();

                exit; 

            }

        }

    }



    private function render_manifest_json() {

        header('Content-Type: application/json');

        $icon = get_site_icon_url(192) ?: 'https://cdn-icons-png.flaticon.com/512/3126/3126504.png';

        $start = $this->get_scanner_url_automagic();

        $start = add_query_arg('fullscreen', '1', $start);

        

        echo json_encode([

            "name" => "Operação Ciganos",

            "short_name" => "Ciganos Op",

            "start_url" => $start,

            "display" => "standalone",

            "background_color" => "#ffffff",

            "theme_color" => "#007cba",

            "icons" => [[ "src" => $icon, "sizes" => "192x192", "type" => "image/png" ]]

        ]);

    }



    private function get_scanner_url_automagic() {

        global $wpdb;

        $id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content LIKE '%[wcai_scanner_app]%' AND post_status='publish' LIMIT 1");

        return $id ? get_permalink($id) : site_url();

    }



    private function render_service_worker() {

        header('Content-Type: application/javascript');

        header('Service-Worker-Allowed: /');

        echo "const CACHE_NAME='wcai-op-v37';const URLS=['https://unpkg.com/html5-qrcode'];self.addEventListener('install',e=>{self.skipWaiting();e.waitUntil(caches.open(CACHE_NAME).then(c=>c.addAll(URLS)))});self.addEventListener('fetch',e=>{if(e.request.method!=='POST')e.respondWith(caches.match(e.request).then(r=>r||fetch(e.request)))});";

    }



    private function render_clean_layout() {

        $man = site_url('/?wcai_pwa=manifest'); $sw = site_url('/?wcai_pwa=sw');

        ?>

        <!DOCTYPE html>

        <html lang="pt-BR">

        <head>

            <meta charset="UTF-8">

            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

            <title>Operação</title>

            <link rel="manifest" href="<?php echo $man; ?>">

            <meta name="theme-color" content="#007cba">

            <meta name="apple-mobile-web-app-capable" content="yes">

            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

            <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

            <?php wp_head(); ?>

            <style>

                body,html{margin:0;padding:0;background:#f4f7f6;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;height:100%;overscroll-behavior:none;user-select:none;}

                #wpadminbar{display:none!important}

                .wcai-clean-wrapper{max-width:600px;margin:0 auto;background:#fff;height:100vh;display:flex;flex-direction:column;position:relative;}

                

                #iubenda-cs-banner, .iubenda-cs-container, .iubenda-ibadge, #iubenda-iframe, .iubenda-cs-content, .iubenda-banner-content, iframe[title*="Iubenda"], .iubenda-cs-opt-group {

                    display: none !important; opacity: 0 !important; visibility: hidden !important; pointer-events: none !important; z-index: -99999 !important; width: 0 !important; height: 0 !important; position: absolute !important; left: -9999px !important;

                }

            </style>

        </head>

        <body><div class="wcai-clean-wrapper"><?php echo do_shortcode('[wcai_scanner_app]'); ?></div>

        <script>if('serviceWorker' in navigator)navigator.serviceWorker.register('<?php echo $sw; ?>');</script>

        <?php wp_footer(); ?></body></html>

        <?php

    }



    public function render_scanner_app() {

        if ( ! is_user_logged_in() ) {

            ob_start(); wp_login_form(['redirect' => add_query_arg('fullscreen','1',get_permalink())]); return '<div style="padding:40px;"><h3>Login Necessário</h3>'.ob_get_clean().'</div>';

        }

        if ( ! current_user_can( 'manage_woocommerce' ) ) return '<div style="padding:20px;color:red">Acesso Negado</div>';



        $ajax_url = admin_url('admin-ajax.php', 'https');

        $logout_url = wp_logout_url(add_query_arg('fullscreen','1',get_permalink()));



        ob_start();

        ?>

        <div class="wcai-app">

            

            <div class="wcai-top-bar">

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">

                    <div class="wcai-sync-status" id="sync-indicator" onclick="Store.forceSync()">☁️ Sincronizado</div>

                    <a href="<?php echo $logout_url; ?>" class="wcai-logout-btn">SAIR</a>

                </div>

                

                <div class="wcai-mode-switch" id="header-mode-switch">

                    <button class="mode-btn active" data-mode="in" onclick="setMode('in')">🟢 ENTRADA</button>

                    <button class="mode-btn" data-mode="out" onclick="setMode('out')">🔴 SAÍDA</button>

                </div>

            </div>



            <div class="wcai-content">

                

                <div id="tab-scanner" class="wcai-tab-content active">

                    <div class="wcai-cam-wrapper">

                        <div id="reader"></div>

                        <div class="wcai-scan-overlay-guide"></div>

                    </div>

                    <div class="wcai-instruction">Aponte para o ingresso</div>

                </div>



                <div id="tab-search" class="wcai-tab-content">

                    <div class="wcai-search-box">

                        <input type="text" id="manual-search-input" placeholder="Digite CPF ou Nome..." oninput="doLocalSearch()" />

                        <button onclick="doLocalSearch()">🔍</button>

                    </div>

                    <div id="manual-results" class="wcai-list-container">

                        <div style="text-align:center;color:#999;margin-top:20px;">Busca instantânea (Offline)</div>

                    </div>

                </div>



                <div id="tab-agenda" class="wcai-tab-content">

                    <div class="wcai-cal-controls">

                        <button onclick="changeMonth(-1)" class="mini-btn">◀</button>

                        <span id="cal-month-year">...</span>

                        <button onclick="changeMonth(1)" class="mini-btn">▶</button>

                    </div>

                    <div class="wcai-cal-actions">

                        <button onclick="goToToday()" class="action-btn-small">Hoje</button>

                        <button onclick="toggleCalView()" class="action-btn-small" id="view-toggle-btn">Lista</button>

                    </div>

                    <div class="wcai-cal-wrapper">

                        <div class="wcai-cal-grid" id="wcai-calendar"></div>

                    </div>

                    <div id="agenda-details" class="wcai-list-container"></div>

                </div>



                <div id="tab-list" class="wcai-tab-content">

                    <div class="wcai-list-filters">

                        <button class="filter-btn active" onclick="renderLocalList('arrival')">📅 A CHEGAR</button>

                        <button class="filter-btn warning" onclick="renderLocalList('onsite')">⚠️ NO PARQUE</button>

                    </div>

                    <div id="manifest-results" class="wcai-list-container"></div>

                </div>

            </div>



            <div class="wcai-bottom-nav">

                <button class="nav-item active" onclick="switchTab('scanner')"><span class="icon">📷</span> <small>Scanner</small></button>

                <button class="nav-item" onclick="switchTab('search')"><span class="icon">⌨️</span> <small>Manual</small></button>

                <button class="nav-item" onclick="switchTab('agenda')"><span class="icon">📅</span> <small>Agenda</small></button>

                <button class="nav-item" onclick="switchTab('list')"><span class="icon">📋</span> <small>Listas</small></button>

            </div>



            <div id="wcai-modal" class="wcai-modal" style="display:none">

                <div class="wcai-modal-card">

                    <div id="modal-icon"></div>

                    <h2 id="modal-title"></h2>

                    <div id="modal-body"></div>

                    <button id="modal-btn" onclick="closeModal()">OK</button>

                </div>

            </div>

        </div>



        <style>

            .wcai-app { height: 100dvh; display: flex; flex-direction: column; overflow: hidden; background:#f4f7f6; }

            

            .wcai-top-bar { 

                position: fixed; top: 0; left: 0; width: 100%; 

                background: #fff; padding: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); z-index: 1000;

                box-sizing: border-box;

            }

            .wcai-sync-status { text-align:center; font-size:11px; color:#888; text-transform:uppercase; font-weight:bold; cursor:pointer; }

            .wcai-logout-btn { font-size:10px; color:#dc3545; text-decoration:none; border:1px solid #dc3545; padding:2px 8px; border-radius:4px; font-weight:bold; }



            .wcai-content { 

                flex: 1; 

                overflow-y: auto; 

                -webkit-overflow-scrolling: touch; 

                padding-top: 110px; 

                padding-bottom: 70px; 

            }

            .wcai-tab-content { display: none; }

            .wcai-tab-content.active { display: block; }



            .wcai-bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; height: 60px; background: #fff; border-top: 1px solid #eee; display: flex; z-index: 1000; padding-bottom: env(safe-area-inset-bottom); box-shadow: 0 -2px 10px rgba(0,0,0,0.05); }

            .nav-item { flex: 1; border: none; background: transparent; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; color: #999; }

            .nav-item.active { color: #007cba; }

            .nav-item .icon { font-size: 18px; margin-bottom: 2px; }

            .nav-item small { font-size: 10px; }



            .wcai-mode-switch { display: flex; background: #e9ecef; border-radius: 8px; padding: 4px; }

            .mode-btn { flex: 1; border: none; background: transparent; padding: 10px; border-radius: 6px; font-weight: 800; font-size: 13px; cursor: pointer; color: #666; transition: 0.2s; }

            .mode-btn.active { background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1); color: #000; }

            .mode-btn[data-mode="in"].active { color: #28a745; }

            .mode-btn[data-mode="out"].active { color: #dc3545; }



            .wcai-cam-wrapper { 

                width: 100%; 

                background: #000; 

                position: relative;

                min-height: 250px;

                margin-bottom: 10px;

            }

            #reader { width: 100%; border: none; }

            

            .wcai-scan-overlay-guide {

                position: absolute;

                top: 50%; left: 50%;

                transform: translate(-50%, -50%);

                width: 250px; height: 250px;

                border: 2px solid rgba(255,255,255,0.7);

                border-radius: 20px;

                box-shadow: 0 0 0 9999px rgba(0,0,0,0.5); 

                z-index: 10; 

                pointer-events: none;

            }

            .wcai-scan-overlay-guide::after {

                content: ''; position: absolute; top: -5px; left: -5px; right: -5px; bottom: -5px;

                border: 3px solid #007cba; border-radius: 24px; opacity: 0.5;

                animation: pulse-border 2s infinite;

            }

            @keyframes pulse-border { 0% { opacity: 0.5; transform: scale(1); } 50% { opacity: 1; transform: scale(1.02); } 100% { opacity: 0.5; transform: scale(1); } }



            .wcai-instruction { text-align: center; padding: 10px; color: #666; font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }



            .wcai-list-container { padding: 10px; }

            .wcai-list-filters { display: flex; padding: 10px; gap: 10px; background: #fff; border-bottom: 1px solid #eee; }

            .filter-btn { flex: 1; padding: 10px; border: 1px solid #ddd; background: #fff; border-radius: 6px; font-size: 12px; font-weight: bold; cursor: pointer; }

            .filter-btn.active { background: #007cba; color: #fff; border-color: #007cba; }

            .filter-btn.warning.active { background: #d69e2e; color: #fff; border-color: #d69e2e; }



            .wcai-item-card { background: #fff; padding: 12px 15px; border-radius: 10px; margin-bottom: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.03); display: flex; justify-content: space-between; align-items: center; border-left: 4px solid #eee; }

            .wcai-item-card.status-0 { border-left-color: #007cba; }

            .wcai-item-card.status-1 { border-left-color: #28a745; background: #f0fff4; }

            .wcai-item-card.status-2 { border-left-color: #dc3545; opacity: 0.8; }

            

            .wcai-item-info { flex: 1; }

            .wcai-item-info strong { display: block; color: #333; font-size: 15px; margin-bottom: 4px; }

            .wcai-info-row { display: flex; gap: 10px; font-size: 13px; color: #666; align-items: center; }

            .wcai-info-row span.separator { color: #ccc; font-size: 10px; }

            .wcai-action-btn { background: #007cba; color: #fff; border: none; padding: 10px 15px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 12px; margin-left:10px; white-space:nowrap; }



            .wcai-search-box { padding: 15px; background: #fff; display: flex; gap: 10px; border-bottom: 1px solid #eee; }

            .wcai-search-box input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px; }

            .wcai-search-box button { background: #333; color: #fff; border: none; padding: 0 20px; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 20px; }



            .wcai-cal-controls { display: flex; justify-content: center; align-items: center; gap: 15px; padding: 15px 10px 5px 10px; background:#fff; }

            .wcai-cal-controls span { font-weight: 800; font-size: 16px; text-transform: uppercase; color: #333; }

            .mini-btn { border:none; background: #f0f2f5; width: 30px; height: 30px; border-radius: 50%; font-weight: bold; cursor: pointer; color: #555; }

            .wcai-cal-actions { display: flex; justify-content: center; gap: 10px; padding: 10px; background: #fff; border-bottom: 1px solid #eee; }

            .action-btn-small { border: 1px solid #ddd; background: #fff; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; cursor: pointer; color: #555; }

            .wcai-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 5px; padding: 10px; background: #fff; }

            .wcai-cal-grid.list-view { display: flex; flex-direction: column; gap: 0; }

            .wcai-cal-grid.list-view .wcai-cal-day { aspect-ratio: auto; padding: 15px; border-bottom: 1px solid #eee; flex-direction: row; justify-content: space-between; align-items: center; border-radius: 0; }

            .wcai-cal-grid.list-view .wcai-cal-day.empty { display: none; }

            .wcai-cal-day { aspect-ratio: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: 8px; font-size: 14px; position: relative; cursor: pointer; border: 1px solid transparent; }

            .wcai-cal-day.empty { cursor: default; }

            .wcai-cal-day.has-event { background: #eef2f7; font-weight: bold; color: #007cba; }

            .wcai-cal-day.selected { background: #007cba; color: #fff; }

            .wcai-cal-day .dot { width: 5px; height: 5px; background: #007cba; border-radius: 50%; margin-top: 3px; }

            .wcai-cal-day.selected .dot { background: #fff; }

            .wcai-cal-day.today { border: 1px solid #007cba; }



            .wcai-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 10000; display: flex; align-items: center; justify-content: center; }

            .wcai-modal-card { background: #fff; width: 85%; max-width: 350px; border-radius: 15px; padding: 30px; text-align: center; }

            #modal-icon { font-size: 60px; margin-bottom: 15px; }

            #modal-title { margin: 0 0 10px; font-size: 22px; text-transform: uppercase; }

            #modal-body { font-size: 15px; color: #555; margin-bottom: 20px; line-height: 1.5; }

            #modal-btn { width: 100%; padding: 15px; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; text-transform: uppercase; background: #007cba; color: #fff; }

        </style>



        <script>

        console.log('🚀 Script iniciando...');

        

        const AJAX_URL = '<?php echo $ajax_url; ?>'; 

        

        // IUBENDA KILLER

        const observer = new MutationObserver((mutations) => {

            mutations.forEach(() => {

                var bad = document.querySelectorAll('#iubenda-cs-banner, .iubenda-cs-container, .iubenda-ibadge, iframe[title*="Iubenda"]');

                bad.forEach(el => el.remove());

            });

        });

        observer.observe(document.body, { childList: true, subtree: true });



        const Store = {

            pax: [], queue: [],

            init: function() {

                console.log('📦 Store.init()');

                const sp = localStorage.getItem('wcai_pax'); const sq = localStorage.getItem('wcai_queue');

                if(sp) this.pax = JSON.parse(sp); if(sq) this.queue = JSON.parse(sq);

                this.sync(); setInterval(() => this.sync(), 30000);

            },

            find: function(term) {

                term = term.toLowerCase();

                return this.pax.filter(p => (p.hash===term) || (String(p.id)===term) || (p.nome.toLowerCase().includes(term)) || (p.cpf.includes(term)));

            },

            addCheckin: function(paxId, mode) {

                const now = new Date(); const hora = now.getHours().toString().padStart(2,'0')+':'+now.getMinutes().toString().padStart(2,'0');

                const p = this.pax.find(x => x.id == paxId);

                if(p) {

                    if(mode==='in') { p.status = 1; p.entry_time = hora; } 

                    else { p.status = 2; }

                    localStorage.setItem('wcai_pax', JSON.stringify(this.pax));

                }

                this.queue.push({ id: paxId, mode: mode, time: now.toISOString() });

                localStorage.setItem('wcai_queue', JSON.stringify(this.queue));

                this.updateUI(); this.sync();

                return { success: true, nome: p?p.nome:'?', msg: (mode=='in'?'Entrada':'Saída')+' Registrada' };

            },

            forceSync: function() {

                const ind = document.getElementById('sync-indicator');

                ind.innerHTML = '🔄 Forçando...';

                this.sync();

            },

            sync: function() {

                const ind = document.getElementById('sync-indicator');

                if(!navigator.onLine) { ind.innerHTML = '⚠️ OFFLINE'; ind.style.color='#d69e2e'; return; }

                

                jQuery.post(AJAX_URL, { action: 'wcai_sync_data', queue: this.queue }).done(function(res) {

                    if(res.success) {

                        Store.queue = []; localStorage.setItem('wcai_queue', '[]');

                        if(res.data.full_manifest) { Store.pax = res.data.full_manifest; localStorage.setItem('wcai_pax', JSON.stringify(Store.pax)); }

                        ind.innerHTML = '☁️ Sincronizado'; ind.style.color='#28a745';

                        Store.updateUI();

                    }

                });

            },

            updateUI: function() {

                if(document.getElementById('tab-list').classList.contains('active')) {

                    const type = document.querySelector('.filter-btn.warning').classList.contains('active') ? 'onsite' : 'arrival';

                    renderLocalList(type);

                }

            }

        };



        var currentMode = 'in'; 

        var scannerObj = null; 

        var isProcessing = false; 

        var calDate = new Date(); 

        var calView = 'grid'; 



        function setMode(mode) {

            console.log('🔧 setMode:', mode);

            currentMode = mode; 

            document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));

            document.querySelector(`.mode-btn[data-mode="${mode}"]`).classList.add('active');

        }



        function switchTab(tab) {

            console.log('🔄 switchTab:', tab);

            document.querySelectorAll('.wcai-tab-content').forEach(t => t.classList.remove('active'));

            document.getElementById('tab-'+tab).classList.add('active');

            document.querySelectorAll('.nav-item').forEach(b => b.classList.remove('active'));

            event.currentTarget.classList.add('active');



            const headerSwitch = document.getElementById('header-mode-switch');

            

            if(tab === 'scanner') {

                headerSwitch.style.display = 'flex';

                setTimeout(() => startScanner(), 100);

            } else {

                headerSwitch.style.display = 'none'; 

                if(scannerObj) { 

                    try { scannerObj.stop(); } catch(e) {}

                    scannerObj = null;

                }

                if(tab==='list') renderLocalList('arrival');

                if(tab==='agenda') loadCalendar();

            }

        }



        function renderLocalList(type) {

            console.log('📋 renderLocalList:', type);

            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));

            if(type=='onsite') document.querySelector('.filter-btn.warning').classList.add('active'); 

            else document.querySelector('.filter-btn').classList.add('active');

            

            const div = document.getElementById('manifest-results');

            const list = Store.pax.filter(p => {

                if(type === 'onsite') return (p.status == 1 || p.status == 2);

                return p.status == 0;

            });

            

            if(type === 'onsite') list.sort((a,b) => (b.entry_time||'').localeCompare(a.entry_time||''));

            else list.sort((a,b) => (a.tour_time||'').localeCompare(b.tour_time||''));



            if(list.length===0) { 

                div.innerHTML = `<div style="text-align:center;padding:30px;color:#999;font-style:italic">Não há participantes para esta lista.</div>`; 

                return; 

            }

            

            let html = `<div style="text-align:center;margin-bottom:10px;font-size:12px;color:#666">${list.length} pessoas (Local)</div>`;

            list.forEach(p => {

                let btnTxt = type=='arrival'?'CHECK-IN':'SA\u00CDDA'; 

                let clickMode = type=='arrival'?'in':'out';

                let timeStr = type=='arrival' ? (p.tour_time || 'Agendado') : (p.entry_time || 'Entrou');

                if(p.status == 2 && type == 'onsite') { btnTxt = 'JÁ SAIU'; timeStr += ' (Saiu)'; }



                html += `<div class="wcai-item-card status-${p.status}">

                    <div class="wcai-item-info"><strong>${p.nome}</strong><div class="wcai-info-row"><span>${timeStr}</span><span class="separator">•</span><span>Pedido #${p.pedido}</span></div></div>

                    <button class="wcai-action-btn" onclick="setMode('${clickMode}'); processLocalCheckin(${p.id})">${btnTxt}</button>

                </div>`;

            });

            div.innerHTML = html;

        }



        function processLocalCheckin(id) {

            console.log('✅ processLocalCheckin:', id);

            const p = Store.pax.find(x => x.id == id);

            if(currentMode==='in' && p.status==1) { showModal('error',{message:'Já entrou!'}); return; }

            if(currentMode==='out' && p.status==0) { showModal('error',{message:'Não deu entrada!'}); return; }

            if(currentMode==='out' && p.status==2) { showModal('error',{message:'Já saiu!'}); return; }

            const res = Store.addCheckin(id, currentMode);

            showModal('success', {nome: res.nome, msg: res.msg});

            if(navigator.vibrate) navigator.vibrate(200);

        }



        function doLocalSearch() {

            console.log('🔍 doLocalSearch');

            const term = document.getElementById('manual-search-input').value; 

            if(term.length<3) return;

            const results = Store.find(term);

            const div = document.getElementById('manual-results');

            if(results.length===0) { 

                div.innerHTML = '<div style="text-align:center;padding:20px;">Nada encontrado.</div>'; 

                return; 

            }

            let html = '';

            results.forEach(p => {

                html += `<div class="wcai-item-card"><div class="wcai-item-info"><strong>${p.nome}</strong><div class="wcai-info-row"><span>${p.data_agendada}</span></div></div><button class="wcai-action-btn" onclick="processLocalCheckin(${p.id})">CHECK</button></div>`;

            });

            div.innerHTML = html;

        }



        function onScan(hash) {

            console.log('📸 onScan:', hash);

            if(isProcessing) return; 

            isProcessing = true;

            const p = Store.pax.find(x => x.hash === hash);

            if(p) {

                processLocalCheckin(p.id);

            } else { 

                showModal('error', {message:'Ingresso não encontrado.'}); 

                if(navigator.vibrate) navigator.vibrate([100,50,100]); 

            }

            setTimeout(() => { isProcessing = false; }, 2000);

        }

        

        function startScanner() { 

            console.log('📷 startScanner chamado');

            

            if(!document.getElementById('reader')) {

                console.error('❌ Elemento #reader não existe');

                return;

            }

            

            if(typeof Html5Qrcode === 'undefined') {

                console.error('❌ Html5Qrcode não carregado');

                setTimeout(startScanner, 500);

                return;

            }

            

            if(scannerObj) {

                console.log('⚠️ Scanner já existe, pulando');

                return;

            }

            

            console.log('✅ Iniciando novo scanner...');

            scannerObj = new Html5Qrcode("reader");

            

            scannerObj.start(

                { facingMode: "environment" },

                { fps: 10, qrbox: { width: 250, height: 250 } },

                onScan

            ).then(() => {

                console.log('✅ Scanner iniciado com sucesso!');

            }).catch(err => {

                console.error('❌ Erro ao iniciar scanner:', err);

                alert('Erro ao acessar câmera: ' + err);

            });

        }



        function goToToday() { calDate = new Date(); loadCalendar(); }

        function toggleCalView() { 

            calView = (calView === 'grid') ? 'list' : 'grid'; 

            document.getElementById('view-toggle-btn').innerText = (calView === 'grid') ? 'Lista' : 'Grade'; 

            loadCalendar(); 

        }

        

        function loadCalendar() {

            var m = calDate.getMonth()+1; var y = calDate.getFullYear();

            const monthNames = ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"];

            document.getElementById('cal-month-year').innerText = monthNames[calDate.getMonth()]+' '+y;

            jQuery.post(AJAX_URL, {action:'wcai_get_calendar_data', month:m, year:y}, function(res){ 

                renderCalendarGrid(res.data||[]); 

            });

        }

        

        function changeMonth(d) { calDate.setMonth(calDate.getMonth()+d); loadCalendar(); }

        

        function renderCalendarGrid(events) {

            var grid = document.getElementById('wcai-calendar'); grid.innerHTML = '';

            grid.className = 'wcai-cal-grid ' + (calView === 'list' ? 'list-view' : '');

            var y=calDate.getFullYear(); var m=calDate.getMonth(); 

            var firstDay=new Date(y,m,1).getDay(); 

            var daysInMonth=new Date(y,m+1,0).getDate(); 

            var today = new Date();

            

            if(calView === 'grid') {

                const days=['D','S','T','Q','Q','S','S']; 

                days.forEach(d=>{ 

                    grid.innerHTML+=`<div style="text-align:center;font-size:10px;font-weight:bold;color:#888;">${d}</div>`; 

                });

                for(let i=0;i<firstDay;i++) grid.innerHTML+=`<div class="wcai-cal-day empty"></div>`;

            }

            

            for(let d=1;d<=daysInMonth;d++) {

                let dateStr=`${y}-${(m+1).toString().padStart(2,'0')}-${d.toString().padStart(2,'0')}`;

                let hasEvent=events[dateStr]>0;

                let isToday = (d===today.getDate() && m===today.getMonth() && y===today.getFullYear());

                let classes = 'wcai-cal-day' + (hasEvent ? ' has-event' : '') + (isToday ? ' today' : '');

                let content = '';

                

                if(calView === 'grid') { 

                    content = `<span>${d}</span>${hasEvent?'<div class="dot"></div>':''}`;

                } else { 

                    const weekDay = new Date(y,m,d).toLocaleDateString('pt-BR', {weekday: 'short'}); 

                    content = `<div style="display:flex;gap:10px"><b>${d}</b> <span style="text-transform:uppercase;color:#888">${weekDay}</span></div> ${hasEvent?'<span style="color:#007cba;font-size:12px">Ver reservas ▶</span>':''}`;

                }

                

                let el = document.createElement('div'); 

                el.className = classes; 

                el.innerHTML = content;

                el.onclick = function() { 

                    if(hasEvent) { 

                        document.querySelectorAll('.wcai-cal-day').forEach(x => x.classList.remove('selected')); 

                        el.classList.add('selected'); 

                        loadAgendaDetails(dateStr); 

                    } 

                };

                grid.appendChild(el);

            }

        }



        function loadAgendaDetails(d) {

            document.getElementById('agenda-details').innerHTML = 'Carregando...';

            jQuery.post(AJAX_URL, {action:'wcai_sync_data', date_query:d}, function(res){

                var list = res.data.specific_date||[];

                var html = '';

                if(list.length > 0) {

                    const uniqueOrders = new Set(list.map(p => p.pedido)).size;

                    html += `<div style="text-align:center;margin-bottom:15px;background:#eef2f7;padding:10px;border-radius:8px;"><div style="font-size:16px;font-weight:bold;color:#007cba">${list.length} PAX</div><div style="font-size:12px;color:#666">${uniqueOrders} Pedidos</div></div>`;

                    list.forEach(p => {

                       html += `<div class="wcai-item-card status-${p.status}"><div class="wcai-item-info"><strong>${p.nome}</strong><div class="wcai-info-row"><span>${p.tour_time}</span><span class="separator">•</span><span>Pedido #${p.pedido}</span></div></div></div>`;

                    });

                } else { 

                    html = '<div style="text-align:center;padding:20px;">Vazio</div>'; 

                }

                document.getElementById('agenda-details').innerHTML = html;

            });

        }



        function showModal(type, data) {

            console.log('🔔 showModal:', type, data);

            var m=document.getElementById('wcai-modal'); 

            var icon=document.getElementById('modal-icon'); 

            var title=document.getElementById('modal-title'); 

            var body=document.getElementById('modal-body'); 

            var btn=document.getElementById('modal-btn');

            m.style.display='flex';

            

            if(type=='loading') { 

                icon.innerHTML='⌛'; 

                title.innerText='...'; 

                body.innerText='...'; 

                btn.style.display='none'; 

            } else if(type=='success') { 

                icon.innerHTML='✅'; 

                title.innerText='OK'; 

                title.style.color='#28a745'; 

                body.innerHTML=data.msg; 

                btn.style.display='block'; 

            } else { 

                icon.innerHTML='🚫'; 

                title.innerText='Erro'; 

                title.style.color='#dc3545'; 

                body.innerText=data.message; 

                btn.style.display='block'; 

            }

        }

        

        function closeModal() { 

            console.log('❌ closeModal');

            document.getElementById('wcai-modal').style.display='none'; 

            isProcessing=false; 

        }

        

        // INIT QUANDO JQUERY ESTIVER PRONTO

        jQuery(document).ready(function() {

            console.log('✅ jQuery ready');

            Store.init();

            setTimeout(startScanner, 1000);

        });

        </script>

        <?php

        return ob_get_clean();

    }



    public function ajax_sync_data() {

        if(!current_user_can('manage_woocommerce')) wp_send_json_error(['message'=>'Forbidden']);

        global $wpdb; $table = WCAI_Participants_DB::get_table_name();

        $queue = isset($_POST['queue']) ? $_POST['queue'] : [];

        if(!empty($queue)) {

            foreach($queue as $item) {

                $status = ($item['mode'] == 'in') ? 1 : 2;

                $pax_id = intval($item['id']);

                if($status == 1) {

                    $wpdb->update($table, ['checkin_status'=>1, 'checkin_time'=>current_time('mysql')], ['id'=>$pax_id]);

                } else {

                    $wpdb->update($table, ['checkin_status'=>2, 'checkout_time'=>current_time('mysql')], ['id'=>$pax_id]);

                }

            }

        }

        $date_query = isset($_POST['date_query']) ? sanitize_text_field($_POST['date_query']) : '';

        if($date_query) {

            $pax_list = $this->get_pax_by_date($date_query);

            wp_send_json_success(['specific_date' => $pax_list]);

        } else {

            $today = date('Y-m-d');

            $tomorrow = date('Y-m-d', strtotime('+1 day'));

            $pax_today = $this->get_pax_by_date($today);

            $pax_tomorrow = $this->get_pax_by_date($tomorrow);

            wp_send_json_success(['full_manifest' => array_merge($pax_today, $pax_tomorrow)]);

        }

    }



    private function get_pax_by_date($date) {

        global $wpdb; $table = WCAI_Participants_DB::get_table_name();

        $sql_orders = "SELECT post_id, meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 

            WHERE pm.meta_key IN ('tour_date', 'Data', 'booking_date') AND (pm.meta_value LIKE %s OR pm.meta_value LIKE %s)

            AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')";

        $fmt1 = '%' . $date . '%'; $parts = explode('-', $date); $fmt2 = '%' . $parts[2].'/'.$parts[1].'/'.$parts[0] . '%';

        $results_meta = $wpdb->get_results($wpdb->prepare($sql_orders, $fmt1, $fmt2));

        if(empty($results_meta)) return [];

        $map = []; $ids = [];

        foreach($results_meta as $row) {

            $ids[] = $row->post_id;

            if(preg_match('/(\d{1,2}:\d{2})/', $row->meta_value, $m)) $map[$row->post_id] = $m[1];

            else $map[$row->post_id] = '';

        }

        $ids_str = implode(',', array_map('intval', $ids));

        $sql_pax = "SELECT id, ticket_hash as hash, nome_completo as nome, cpf, order_id as pedido, checkin_status as status, checkin_time, checkout_time FROM $table WHERE order_id IN ($ids_str)";

        $pax_list = $wpdb->get_results($sql_pax);

        foreach($pax_list as $p) {

            $p->tour_time = isset($map[$p->pedido]) ? $map[$p->pedido] : '';

            $p->data_agendada = date('d/m', strtotime($date)) . ' ' . $p->tour_time;

            $p->entry_time = $p->checkin_time ? date('H:i', strtotime($p->checkin_time)) : '';

        }

        return $pax_list;

    }



    public function ajax_get_calendar_data() {

        if(!current_user_can('manage_woocommerce')) wp_send_json_error();

        global $wpdb; $m = intval($_POST['month']); $y = intval($_POST['year']);

        $str_like = $y . '-' . str_pad($m, 2, '0', STR_PAD_LEFT); 

        $sql = "SELECT pm.meta_value FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID WHERE pm.meta_key IN ('tour_date', 'Data', 'booking_date') AND (pm.meta_value LIKE %s OR pm.meta_value LIKE %s) AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')"; 

        $like1 = $str_like . '%'; $like2 = '%' . str_pad($m, 2, '0', STR_PAD_LEFT) . '/' . $y;

        $dates = $wpdb->get_col($wpdb->prepare($sql, $like1, $like2));

        $counts = [];

        foreach($dates as $raw) {

            $iso = '';

            if(strpos($raw, '/') !== false) { $parts = explode('/', substr($raw, 0, 10)); if(count($parts)==3) $iso = $parts[2].'-'.$parts[1].'-'.$parts[0]; } 

            else { $iso = substr($raw, 0, 10); }

            if($iso) { if(!isset($counts[$iso])) $counts[$iso] = 0; $counts[$iso]++; }

        }

        wp_send_json_success($counts);

    }

    

    public function ajax_process_qr_checkin() {} 

    public function ajax_manual_search() {} 

    public function ajax_get_manifest() {}

}



new WCAI_Scanner();

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Scanner_JsCore {

    private function scanner_js_core($ajax_url, $ajax_nonce) {
        ?>
        <script>


        

        const AJAX_URL = '<?php echo esc_url( $ajax_url ); ?>';
        const AJAX_NONCE = '<?php echo esc_js( $ajax_nonce ); ?>';

        

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


                const sp = localStorage.getItem('wcai_pax');
                const sq = localStorage.getItem('wcai_queue');
                const cachedAt = parseInt(localStorage.getItem('wcai_pax_cached_at') || '0', 10);
                const queueUpdatedAt = parseInt(localStorage.getItem('wcai_queue_updated_at') || '0', 10);
                const cacheTtl = 12 * 60 * 60 * 1000;
                const queueTtl = 24 * 60 * 60 * 1000;

                if ( sp && cachedAt && (Date.now() - cachedAt) <= cacheTtl ) {
                    try {
                        this.pax = JSON.parse(sp);
                        if ( ! Array.isArray(this.pax) ) this.pax = [];
                        this.pax = this.pax.map(function(p) {
                            return {
                                id: Number(p.id) || 0,
                                hash: typeof p.hash === 'string' ? p.hash : '',
                                nome: typeof p.nome === 'string' ? p.nome : '',
                                pedido: p.pedido != null ? String(p.pedido) : '',
                                status: Number(p.status) || 0,
                                entry_time: typeof p.entry_time === 'string' ? p.entry_time : '',
                                tour_time: typeof p.tour_time === 'string' ? p.tour_time : '',
                                data_agendada: typeof p.data_agendada === 'string' ? p.data_agendada : ''
                            };
                        }).filter(function(p) { return p.id > 0; });
                    } catch (e) {
                        this.pax = [];
                    }
                } else {
                    localStorage.removeItem('wcai_pax');
                    localStorage.removeItem('wcai_pax_cached_at');
                }

                if ( sq && queueUpdatedAt && (Date.now() - queueUpdatedAt) <= queueTtl ) {
                    try {
                        this.queue = JSON.parse(sq);
                        if ( ! Array.isArray(this.queue) ) this.queue = [];
                    } catch (e) {
                        this.queue = [];
                    }
                } else {
                    this.queue = [];
                    localStorage.removeItem('wcai_queue');
                    localStorage.removeItem('wcai_queue_updated_at');
                }

                this.sync();
                setInterval(() => this.sync(), 30000);

            },

            clearLocalData: function() {
                localStorage.removeItem('wcai_pax');
                localStorage.removeItem('wcai_pax_cached_at');
                localStorage.removeItem('wcai_queue');
                localStorage.removeItem('wcai_queue_updated_at');
                this.pax = [];
                this.queue = [];
                return true;
            },

            find: function(term) {

                term = term.toLowerCase();

                return this.pax.filter(p => (p.hash===term) || (String(p.id)===term) || (p.nome && p.nome.toLowerCase().includes(term)));

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

                this.queue = this.queue.slice(-200);
                localStorage.setItem('wcai_queue', JSON.stringify(this.queue));
                localStorage.setItem('wcai_queue_updated_at', String(Date.now()));

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

                

                jQuery.post(AJAX_URL, { action: 'wcai_sync_data', queue: this.queue, nonce: AJAX_NONCE }).fail(function() { ind.innerHTML = '⚠️ Falha ao sincronizar'; ind.style.color='#dc3545'; }).done(function(res) {

                    if(res.success) {

                        Store.queue = []; localStorage.setItem('wcai_queue', '[]'); localStorage.setItem('wcai_queue_updated_at', String(Date.now()));

                        if(res.data.full_manifest) {
                            Store.pax = res.data.full_manifest.map(function(p) {
                                return {
                                    id: Number(p.id) || 0,
                                    hash: typeof p.hash === 'string' ? p.hash : '',
                                    nome: typeof p.nome === 'string' ? p.nome : '',
                                    pedido: p.pedido != null ? String(p.pedido) : '',
                                    status: Number(p.status) || 0,
                                    entry_time: typeof p.entry_time === 'string' ? p.entry_time : '',
                                    tour_time: typeof p.tour_time === 'string' ? p.tour_time : '',
                                    data_agendada: typeof p.data_agendada === 'string' ? p.data_agendada : ''
                                };
                            }).filter(function(p) { return p.id > 0; });
                            localStorage.setItem('wcai_pax', JSON.stringify(Store.pax));
                            localStorage.setItem('wcai_pax_cached_at', String(Date.now()));
                        }

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



        <?php
    }


}

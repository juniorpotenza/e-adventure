<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Scanner_JsList {

    private function scanner_js_list() {
        ?>
        function setMode(mode) {


            currentMode = mode; 

            document.querySelectorAll('.mode-btn').forEach(b => b.classList.remove('active'));

            document.querySelector(`.mode-btn[data-mode="${mode}"]`).classList.add('active');

        }



        function switchTab(tab) {


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



        function wcaiEscapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>"']/g, function(char) {
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'})[char];
            });
        }

        function renderLocalList(type) {


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



                html += '<div class="wcai-item-card status-' + wcaiEscapeHtml(p.status) + '">' +
                    '<div class="wcai-item-info"><strong>' + wcaiEscapeHtml(p.nome) + '</strong><div class="wcai-info-row"><span>' + wcaiEscapeHtml(timeStr) + '</span><span class="separator">•</span><span>Pedido #' + wcaiEscapeHtml(p.pedido) + '</span></div></div>' +
                    '<button class="wcai-action-btn" onclick="setMode(\'' + clickMode + '\'); processLocalCheckin(' + Number(p.id) + ')">' + wcaiEscapeHtml(btnTxt) + '</button>' +
                '</div>';

            });

            div.innerHTML = html;

        }



        function processLocalCheckin(id) {


            const p = Store.pax.find(x => x.id == id);

            if(currentMode==='in' && p.status==1) { showModal('error',{message:'Já entrou!'}); return; }

            if(currentMode==='out' && p.status==0) { showModal('error',{message:'Não deu entrada!'}); return; }

            if(currentMode==='out' && p.status==2) { showModal('error',{message:'Já saiu!'}); return; }

            const res = Store.addCheckin(id, currentMode);

            showModal('success', {nome: res.nome, msg: res.msg});

            if(navigator.vibrate) navigator.vibrate(200);

        }



        function doLocalSearch() {


            const term = document.getElementById('manual-search-input').value; 

            if(term.length<3) { document.getElementById('manual-results').innerHTML = '<div style="text-align:center;padding:20px;color:#999;">Digite ao menos 3 caracteres.</div>'; return; }

            const results = Store.find(term);

            const div = document.getElementById('manual-results');

            if(results.length===0) { 

                div.innerHTML = '<div style="text-align:center;padding:20px;">Nada encontrado.</div>'; 

                return; 

            }

            let html = '';

            results.forEach(p => {

                html += '<div class="wcai-item-card"><div class="wcai-item-info"><strong>' + wcaiEscapeHtml(p.nome) + '</strong><div class="wcai-info-row"><span>' + wcaiEscapeHtml(p.data_agendada) + '</span></div></div><button class="wcai-action-btn" onclick="processLocalCheckin(' + Number(p.id) + ')">CHECK</button></div>';

            });

            div.innerHTML = html;

        }



        function onScan(hash) {


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


                return;

            }

            


            scannerObj = new Html5Qrcode("reader");

            

            scannerObj.start(

                { facingMode: "environment" },

                { fps: 10, qrbox: { width: 250, height: 250 } },

                onScan

            ).then(() => { document.getElementById('cam-error').style.display = 'none';


            }).catch(err => {

                console.error('❌ Erro ao iniciar scanner:', err);

                scannerObj = null; var ce = document.getElementById('cam-error'); ce.innerText = 'Erro ao acessar câmera: ' + err; ce.style.display = 'block';

            });

        }



        <?php
    }


}

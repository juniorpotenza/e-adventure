<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Scanner_JsCalendar {

    private function scanner_js_calendar() {
        ?>
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

            jQuery.post(AJAX_URL, {action:'wcai_get_calendar_data', month:m, year:y, nonce: AJAX_NONCE}, function(res){

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

            jQuery.post(AJAX_URL, {action:'wcai_sync_data', date_query:d, nonce: AJAX_NONCE}, function(res){

                var list = res.data.specific_date||[];

                var html = '';

                if(list.length > 0) {

                    const uniqueOrders = new Set(list.map(p => p.pedido)).size;

                    html += `<div style="text-align:center;margin-bottom:15px;background:#eef2f7;padding:10px;border-radius:8px;"><div style="font-size:16px;font-weight:bold;color:#007cba">${list.length} PAX</div><div style="font-size:12px;color:#666">${uniqueOrders} Pedidos</div></div>`;

                    list.forEach(p => {

                       html += `<div class="wcai-item-card status-${Number(p.status) || 0}"><div class="wcai-item-info"><strong>${wcaiEscapeHtml(p.nome)}</strong><div class="wcai-info-row"><span>${wcaiEscapeHtml(p.tour_time)}</span><span class="separator">•</span><span>Pedido #${wcaiEscapeHtml(p.pedido)}</span></div></div></div>`;

                    });

                } else { 

                    html = '<div style="text-align:center;padding:20px;">Vazio</div>'; 

                }

                document.getElementById('agenda-details').innerHTML = html;

            });

        }



        function wcaiBeep(ok) {
            try {
                var Ctx = window.AudioContext || window.webkitAudioContext;
                if(!Ctx) return;
                window.wcaiAudio = window.wcaiAudio || new Ctx();
                var o = wcaiAudio.createOscillator(), g = wcaiAudio.createGain();
                o.frequency.value = ok ? 880 : 220;
                g.gain.value = 0.1;
                o.connect(g); g.connect(wcaiAudio.destination);
                o.start(); o.stop(wcaiAudio.currentTime + (ok ? 0.12 : 0.3));
            } catch(e) { /* sem áudio: o aviso visual basta */ }
        }

        function showModal(type, data) {


            var m=document.getElementById('wcai-modal'); 

            var icon=document.getElementById('modal-icon'); 

            var title=document.getElementById('modal-title'); 

            var body=document.getElementById('modal-body'); 

            var btn=document.getElementById('modal-btn');

            m.style.display='flex'; m.className='wcai-modal is-' + type;

            

            if(type=='loading') { 

                icon.innerHTML='⌛'; 

                title.innerText='...'; 

                body.innerText='...'; 

                btn.style.display='none'; 

            } else if(type=='success') { 

                icon.innerHTML='✅'; 

                title.innerText='OK'; 

                body.innerHTML=data.msg; 

                btn.style.display='block'; btn.focus(); wcaiBeep(type=='success'); 

            } else { 

                icon.innerHTML='🚫'; 

                title.innerText='Erro'; 

                body.innerText=data.message; 

                btn.style.display='block'; btn.focus(); wcaiBeep(type=='success'); 

            }

        }

        

        function closeModal() { 


            document.getElementById('wcai-modal').style.display='none'; 

            isProcessing=false; 

        }

        

        // INIT QUANDO JQUERY ESTIVER PRONTO

        jQuery(document).ready(function() {


            Store.init();

            setTimeout(startScanner, 1000);

        });

        </script>

        <?php
    }


}

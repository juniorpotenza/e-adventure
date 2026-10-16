<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Scanner_PageHtml {

    private function scanner_html($logout_url) {
        ?>

        <div class="wcai-app">

            

            <div class="wcai-top-bar">

                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">

                    <div class="wcai-sync-status" id="sync-indicator" onclick="Store.forceSync()">☁️ Sincronizado</div>

                    <a href="<?php echo esc_url( $logout_url ); ?>" class="wcai-logout-btn" onclick="return Store.clearLocalData()">SAIR</a>

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

                    <div id="cam-error" class="wcai-instruction" role="alert" style="display:none;color:#dc3545;text-transform:none"></div>

                </div>



                <div id="tab-search" class="wcai-tab-content">

                    <div class="wcai-search-box">

                        <input type="text" id="manual-search-input" placeholder="Digite o nome..." oninput="doLocalSearch()" />

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



            <div id="wcai-modal" class="wcai-modal" style="display:none" role="alertdialog" aria-modal="true" aria-labelledby="modal-title" aria-describedby="modal-body">

                <div class="wcai-modal-card">

                    <div id="modal-icon"></div>

                    <h2 id="modal-title"></h2>

                    <div id="modal-body"></div>

                    <button id="modal-btn" onclick="closeModal()">OK</button>

                </div>

            </div>

        </div>



        <?php
    }


}

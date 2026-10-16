<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Scanner_PageCss {

    private function scanner_css() {
        ?>
        <style>

            .wcai-app { height: 100dvh; display: flex; flex-direction: column; overflow: hidden; background:#f4f7f6; }

            

            .wcai-top-bar { 

                position: fixed; top: 0; left: 0; width: 100%; 

                background: #fff; padding: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); z-index: 1000;

                box-sizing: border-box;

            }

            .wcai-sync-status { display:flex; align-items:center; min-height:44px; text-align:center; font-size:12px; color:#888; text-transform:uppercase; font-weight:bold; cursor:pointer; }

            .wcai-logout-btn { display:inline-flex; align-items:center; min-height:44px; min-width:44px; box-sizing:border-box; font-size:12px; color:#dc3545; text-decoration:none; border:1px solid #dc3545; padding:0 12px; border-radius:4px; font-weight:bold; }



            .wcai-content { 

                flex: 1; 

                overflow-y: auto; 

                -webkit-overflow-scrolling: touch; 

                padding-top: 126px; 

                padding-bottom: 70px; 

            }

            .wcai-tab-content { display: none; }

            .wcai-tab-content.active { display: block; }



            .wcai-bottom-nav { position: fixed; bottom: 0; left: 0; width: 100%; height: 60px; background: #fff; border-top: 1px solid #eee; display: flex; z-index: 1000; padding-bottom: env(safe-area-inset-bottom); box-shadow: 0 -2px 10px rgba(0,0,0,0.05); }

            .nav-item { flex: 1; min-height: 44px; border: none; background: transparent; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; color: #999; }

            .nav-item.active { color: #007cba; }

            .nav-item .icon { font-size: 18px; margin-bottom: 2px; }

            .nav-item small { font-size: 12px; }



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

            .mini-btn { border:none; background: #f0f2f5; width: 44px; height: 44px; border-radius: 50%; font-weight: bold; cursor: pointer; color: #555; }

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

            .wcai-modal-card { background: #fff; color: #333; width: 85%; max-width: 350px; border-radius: 15px; padding: 30px; text-align: center; }

            #modal-icon { font-size: 60px; margin-bottom: 15px; }

            #modal-title { margin: 0 0 10px; font-size: 22px; text-transform: uppercase; }

            #modal-body { font-size: 15px; color: inherit; margin-bottom: 20px; line-height: 1.5; }

            #modal-btn { width: 100%; padding: 15px; border: none; border-radius: 8px; font-weight: bold; font-size: 16px; cursor: pointer; text-transform: uppercase; background: #007cba; color: #fff; }
            .wcai-modal.is-success .wcai-modal-card { background: #1e7e34; color: #fff; }
            .wcai-modal.is-error .wcai-modal-card { background: #b02a37; color: #fff; }
            .wcai-modal.is-success #modal-btn, .wcai-modal.is-error #modal-btn { background: #fff; color: #222; }

        </style>



        <?php
    }


}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// CSS da conta do cliente (rodada 4): menu, lista de pedidos em cartões, botões, títulos do bloco extras, painel.
trait WCAI_Frontend_Account_Css {
    // Impresso no <head> das páginas da conta e do pedido recebido (no tema de blocos a confirmação renderiza antes do footer).
    public function print_account_css() {
        $on_account = function_exists( 'is_account_page' ) && is_account_page();
        $on_received = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' );
        if ( ! $on_account && ! $on_received ) return;
        ?>
        <style id="wcai-account-css">
            :root{--wcai-ink:#1d2327;--wcai-muted:#50575e;--wcai-line:#dcdcde;--wcai-soft:#f2f2f2;--wcai-focus:#2271b1}
            .wcai-sr{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
            .wcai-open-modal,.woocommerce-orders-table .woocommerce-button.view{box-sizing:border-box;min-height:44px;padding:10px 16px;background:#fff;color:var(--wcai-ink);border:2px solid var(--wcai-ink);border-radius:6px;font-size:15px;font-weight:600;line-height:1.3;text-align:center;text-decoration:none;cursor:pointer}
            .wcai-open-modal:hover,.woocommerce-orders-table .woocommerce-button.view:hover{background:var(--wcai-soft);color:var(--wcai-ink)}
            .wcai-orders-participants{font-size:14px;line-height:1.5}
            .wcai-chip{font-size:13px}
            .woocommerce-MyAccount-navigation ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px}
            .woocommerce-MyAccount-navigation a{display:block;box-sizing:border-box;min-height:44px;padding:12px 16px;border-left:3px solid transparent;font-size:16px;line-height:1.4;text-decoration:none}
            .woocommerce-MyAccount-navigation a[aria-current="page"],.woocommerce-MyAccount-navigation .is-active a{background:var(--wcai-soft);border-left-color:var(--wcai-ink);font-weight:600}
            @media (max-width:600px){
             .wc-block-components-additional-fields-list{display:flex;flex-wrap:wrap;justify-content:space-between;gap:4px 8px}
             .wc-block-components-additional-fields-list dt{display:block;flex:1 1 55%;min-width:0;margin:0}
             .wc-block-components-additional-fields-list dd{display:block;flex:1 1 35%;min-width:0;margin:0;text-align:right;overflow-wrap:anywhere}
            }
            @media (max-width:767px){
             .woocommerce-MyAccount-navigation ul{flex-direction:row;overflow-x:auto;gap:8px;padding-bottom:4px}
             .woocommerce-MyAccount-navigation li{flex:0 0 auto}
             .woocommerce-MyAccount-navigation a{white-space:nowrap}
            }
            .woocommerce-MyAccount-navigation ul li.woocommerce-MyAccount-navigation-link{margin:0;padding:0}
            .woocommerce-MyAccount-navigation ul{margin:0 0 24px}
            @media (min-width:1024px){
             .woocommerce-MyAccount-content table.woocommerce-orders-table{display:block;border:0;width:100%}
             .woocommerce-MyAccount-content table.woocommerce-orders-table thead{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0,0,0,0)}
             .woocommerce-MyAccount-content table.woocommerce-orders-table tbody{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px}
             .woocommerce-MyAccount-content table.woocommerce-orders-table tbody tr{display:flex;flex-wrap:wrap;align-items:center;gap:8px;box-sizing:border-box;padding:16px;border:1px solid var(--wcai-line);border-radius:8px;background:#fff}
             .woocommerce-MyAccount-content table.woocommerce-orders-table tbody tr>th,.woocommerce-MyAccount-content table.woocommerce-orders-table tbody tr>td{display:block;padding:0;border:0;background:none;font-size:14px;line-height:1.4}
             .woocommerce-MyAccount-content table.woocommerce-orders-table tbody tr>.woocommerce-orders-table__cell-order-number{order:1;font-size:18px;font-weight:700}
             .woocommerce-MyAccount-content table.woocommerce-orders-table tbody tr>.woocommerce-orders-table__cell-order-status{order:2;margin-left:auto;font-weight:600}
             .woocommerce-MyAccount-content table.woocommerce-orders-table tbody tr>.woocommerce-orders-table__cell-order-date{order:3;color:var(--wcai-muted)}
             .woocommerce-MyAccount-content table.woocommerce-orders-table tbody tr>.woocommerce-orders-table__cell-order-total{order:4;margin-left:auto}
             .woocommerce-MyAccount-content table.woocommerce-orders-table tbody tr>.woocommerce-orders-table__cell-participantes,.woocommerce-MyAccount-content table.woocommerce-orders-table tbody tr>.woocommerce-orders-table__cell-order-actions{display:contents}
             .woocommerce-orders-table .wcai-orders-participants{order:5;flex:0 0 100%;margin:0}
             .woocommerce-orders-table .wcai-sign-all--list{order:6;flex:0 0 100%;margin:0}
             .woocommerce-orders-table .wcai-open-modal{order:7;flex:0 0 100%;width:auto}
             .woocommerce-MyAccount-content table.woocommerce-orders-table .woocommerce-orders-table__cell-order-actions .woocommerce-button{order:8;flex:0 0 100%;margin:0}
            }
            .wcai-order-extra{margin-block:2rem;padding:0}
            .wcai-extra-title{display:flex;align-items:center;gap:8px;margin:0 0 16px;font-size:20px;font-weight:600;line-height:1.3;color:var(--wcai-ink)}
            .wcai-extra-title svg{flex:0 0 auto}
            .wcai-qr-intro{margin:0 0 12px;font-size:14px;line-height:1.5;color:var(--wcai-muted)}
            .wcai-pt .wcai-qr-note{font-size:14px}
            .wcai-next-card{box-sizing:border-box;margin:0 0 24px;padding:16px 20px;border:1px solid var(--wcai-line);border-radius:8px;background:#fff}
            .wcai-next-card p,.wcai-next-card ul{margin:0 0 12px;font-size:16px;line-height:1.5}
            .wcai-next-card ul{padding-left:20px}
            .wcai-next-card__order{font-size:18px;font-weight:700}
            .woocommerce-MyAccount-content:has(.wcai-next-card){display:flex;flex-direction:column}
            .woocommerce-MyAccount-content:has(.wcai-next-card) .wcai-next-card{order:-1}
            .woocommerce-MyAccount-content:has(.wcai-next-card) .woocommerce-notices-wrapper{order:-2}
            @media (max-width:1023px){.woocommerce-orders-table .wcai-open-modal{display:block;width:100%}}
        </style>
        <?php
    }
}

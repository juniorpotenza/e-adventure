<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Product_Booking_Round4_Assets {

    // Quantidade fixa do passeio: sem cara de campo editável (bloco do carrinho e mini-carrinho).
    public static function cart_readonly_css() {
        return 'input[readonly].wc-block-components-quantity-selector__input{border:0;background:transparent;pointer-events:none;box-shadow:none}'
            . '.wc-block-components-quantity-selector:has(input[readonly]){border:0;box-shadow:none;background:transparent}'
            . '.wc-block-components-quantity-selector:has(input[readonly]) .wc-block-components-quantity-selector__button{display:none}';
    }

    public static function round4_css() {
        return '.wcai-product-booking,.wcai-sticky-cta{--wcai-accent:#111;--wcai-accent-contrast:#fff;--wcai-focus:#1a56db}'
            . '.wcai-price-unit{margin-left:.4em;font-size:16px;font-weight:400;color:#595959}'
            . 'body.wcai-tour .product_meta .posted_in,body.wcai-tour .wp-block-post-terms.taxonomy-product_cat{display:none}'
            . 'body.wcai-no-image .woocommerce-product-gallery,body.wcai-no-image .wp-block-column:has(.wp-block-woocommerce-product-image-gallery){display:none}'
            . 'body.wcai-no-image .wp-block-columns:has(.wp-block-woocommerce-product-image-gallery)>.wp-block-column{flex-basis:100%!important}'
            . '.wcai-quick-arrow{position:absolute;top:calc(50% - 25px);z-index:2;display:flex;align-items:center;justify-content:center;width:44px;height:44px;padding:0;border:1px solid #bbb;border-radius:50%;background:#fff;box-shadow:0 2px 8px rgba(0,0,0,.18);color:#111;font-size:24px;line-height:1;cursor:pointer}'
            . '.wcai-quick-arrow.is-prev{left:-4px}.wcai-quick-arrow.is-next{right:-4px}.wcai-quick-arrow[hidden]{display:none!important}'
            . '.wcai-group-note{margin:10px 0 0;font-size:13px;line-height:1.4;color:#595959}.wcai-group-note[hidden]{display:none!important}'
            . '.wcai-sticky-cta{display:none}'
            . '@media(max-width:700px){body.wcai-has-cta{padding-bottom:calc(76px + env(safe-area-inset-bottom,0px))}'
            . '.wcai-sticky-cta:not([hidden]){display:flex;position:fixed;left:0;right:0;bottom:0;z-index:60;box-sizing:border-box;align-items:center;justify-content:space-between;gap:12px;padding:10px 16px calc(10px + env(safe-area-inset-bottom,0px));background:#fff;border-top:1px solid #e5e5e5;box-shadow:0 -4px 16px rgba(0,0,0,.08)}'
            . '.wcai-sticky-cta p{min-width:0;margin:0;font-size:15px;font-weight:700;line-height:1.3}'
            . '.wcai-sticky-cta button{flex:0 0 auto;min-width:44px;min-height:44px;padding:0 24px;border:0;border-radius:10px;background:var(--wcai-accent,#111);color:var(--wcai-accent-contrast,#fff);font:inherit;font-weight:700;cursor:pointer}'
            . '.wcai-sticky-cta button:focus-visible{outline:3px solid var(--wcai-focus,#1a56db);outline-offset:2px}}';
    }

    public static function cta_js() {
        return <<<'JS'
(function () {
    'use strict';

    var bar = document.querySelector('[data-wcai-cta]');
    var card = document.querySelector('.wcai-product-booking');
    if (!bar || !card) return;

    var text = bar.querySelector('[data-wcai-cta-text]');
    var button = bar.querySelector('[data-wcai-cta-button]');
    var initial = text.textContent;
    var ready = false;

    document.body.classList.add('wcai-has-cta');

    // A barra só aparece quando o cartão de reserva está fora da tela.
    if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) {
            bar.hidden = entries[0].isIntersecting;
        }).observe(card);
    } else {
        bar.hidden = false;
    }

    // O cartão avisa o estado da reserva; sem saída escolhida a barra mostra o preço por participante.
    document.addEventListener('wcai:booking-state', function (event) {
        ready = !!event.detail.ready;
        text.textContent = ready ? event.detail.text : initial;
    });

    button.addEventListener('click', function () {
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var target = ready ? document.querySelector('.single_add_to_cart_button') : card.querySelector('.wcai-quick-date[tabindex="0"]');

        (ready && target ? target : card).scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: ready && target ? 'center' : 'start' });

        if (!target) {
            card.tabIndex = -1;
            target = card;
        }

        target.focus({ preventScroll: true });
    });
}());
JS;
    }
}

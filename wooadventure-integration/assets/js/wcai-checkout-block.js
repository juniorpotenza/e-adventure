(function () {
    'use strict';

    var NS = 'wcai-checkout';
    var ROOT = 'wcai-checkout-wizard';
    var state = { items: [] };
    var signature = '';
    var currentStep = 1;
    var validationIds = [];
    var syncTimer = null;
    var attempted = false;
    var flaggedNative = [];
    var consent = false;
    var NBSP = String.fromCharCode(160);
    var BR_UFS = ['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'];
    var MIN_AGE = 7; // espelha WCAI_Utils::is_min_age (includes/class-utils.php)
    var CPF_SELECTORS = [
        'input[name="billing_cpf"]',
        'input[name="wcai/billing_cpf"]',
        'input[name="wcai/billing-cpf"]',
        'input[name="wcai_billing_cpf"]',
        '#contact-wcai-billing-cpf',
        '#wcai-billing-cpf'
    ];
    var BIRTH_SELECTORS = [
        'input[name="billing_birthdate"]',
        'input[name="wcai/billing_birthdate"]',
        'input[name="wcai/billing-birthdate"]',
        'input[name="wcai_billing_birthdate"]',
        '#contact-wcai-billing-birthdate',
        '#wcai-billing-birthdate'
    ];

    var CPF_SEL = CPF_SELECTORS.join(',');
    var BIRTH_SEL = BIRTH_SELECTORS.join(',');

    // Dados do servidor (WCAI_Privacy::consent_text, wcai_terms_url) via wp_localize_script.
    function config() {
        return typeof window !== 'undefined' && window.wcaiCheckout ? window.wcaiCheckout : {};
    }

    function cart() {
        if (!window.wp || !wp.data) return null;
        var store = wp.data.select('wc/store/cart');
        return store && typeof store.getCartData === 'function' ? store.getCartData() : null;
    }

    function customerData() {
        if (!window.wp || !wp.data) return null;
        var store = wp.data.select('wc/store/cart');
        return store && typeof store.getCustomerData === 'function' ? store.getCustomerData() : null;
    }

    function checkoutDispatcher() {
        if (!window.wp || !wp.data) return null;
        var dispatcher = wp.data.dispatch('wc/store/checkout');
        return dispatcher && typeof dispatcher.setExtensionData === 'function' ? dispatcher : null;
    }

    // A action da store e setExtensionData(namespace, objeto, replace). Passar (NS, 'data', json)
    // gravava a string 'data' em extensions[NS] e a Store API recusava: "is not of type object,null".
    function pushExtensionData(checkout, json) {
        checkout.setExtensionData(NS, { data: String(json) });
    }

    function sync() {
        var checkout = checkoutDispatcher();
        if (!checkout) return;

        pushExtensionData(checkout, JSON.stringify(buildPayload(state.items, consent)));
    }

    // consent: boolean estrito; o servidor recusa o pedido se não for true.
    function buildPayload(items, accepted) {
        return {
            consent: accepted === true,
            items: items.map(function (item) {
                return {
                    key: item.key,
                    product_id: item.product_id,
                    variation_id: item.variation_id,
                    departure_id: item.departure_id,
                    adults: item.adults,
                    children: item.children,
                    additional_participants: item.additional_participants
                };
            })
        };
    }

    function scheduleSync() {
        if (syncTimer) clearTimeout(syncTimer);
        syncTimer = setTimeout(function () {
            syncTimer = null;
            sync();
        }, 30);
    }

    function setValidationErrors(errors) {
        var store = wp.data && wp.data.dispatch ? wp.data.dispatch('wc/store/validation') : null;
        if (!store) return;

        if (typeof store.clearValidationError === 'function') {
            validationIds.forEach(function (id) {
                store.clearValidationError(id);
            });
        }

        validationIds = errors ? Object.keys(errors) : [];

        if (validationIds.length && typeof store.setValidationErrors === 'function') {
            store.setValidationErrors(errors);
        }
    }

    function addError(errors, id, message) {
        errors[id] = { message: message, hidden: true };
    }

    function digits(value) {
        return String(value || '').replace(/\D/g, '');
    }

    function cpfMask(value) {
        var v = digits(value).slice(0, 11);

        if (v.length <= 3) return v;
        if (v.length <= 6) return v.slice(0, 3) + '.' + v.slice(3);
        if (v.length <= 9) return v.slice(0, 3) + '.' + v.slice(3, 6) + '.' + v.slice(6);
        return v.slice(0, 3) + '.' + v.slice(3, 6) + '.' + v.slice(6, 9) + '-' + v.slice(9);
    }

    function dateMask(value) {
        var v = digits(value).slice(0, 8);

        if (v.length <= 2) return v;
        if (v.length <= 4) return v.slice(0, 2) + '/' + v.slice(2);
        return v.slice(0, 2) + '/' + v.slice(2, 4) + '/' + v.slice(4);
    }

    // Valida CPF pelos dígitos verificadores (mesma regra de WCAI_Utils::is_valid_cpf).
    function isValidCpf(value) {
        var cpf = digits(value);

        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;

        for (var t = 9; t < 11; t++) {
            var sum = 0;

            for (var c = 0; c < t; c++) {
                sum += parseInt(cpf.charAt(c), 10) * (t + 1 - c);
            }

            if (parseInt(cpf.charAt(t), 10) !== ((10 * sum) % 11) % 10) return false;
        }

        return true;
    }

    // dd/mm/aaaa real (31/02 recusa), igual a WCAI_Utils::is_valid_date. Retorna Date ou null.
    function parseDate(value) {
        var m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(String(value || ''));

        if (!m) return null;

        var day = parseInt(m[1], 10);
        var month = parseInt(m[2], 10);
        var year = parseInt(m[3], 10);
        var date = new Date(year, month - 1, day);

        if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) return null;

        return date;
    }

    // Idade em anos completos (null se a data é inválida). Data futura dá idade negativa (o servidor ainda não recusa futuro).
    function ageOn(value, now) {
        var birth = parseDate(value);

        if (!birth) return null;

        now = now || new Date();

        var age = now.getFullYear() - birth.getFullYear();

        if (now.getMonth() < birth.getMonth() || (now.getMonth() === birth.getMonth() && now.getDate() < birth.getDate())) {
            age--;
        }

        return age;
    }

    function isMinAge(value, years, now) {
        var age = ageOn(value, now);

        return age !== null && age >= (years || MIN_AGE);
    }

    // Rascunho restaurado pelo Woo pode vir sem máscara ou duplicado ('1505199015051990'). Devolve '' se não aproveitável.
    function sanitizeDraft(kind, value) {
        var d = digits(value);

        if (!d) return '';
        if (kind === 'cpf') return cpfMask(d.slice(0, 11));

        var masked = dateMask(d.slice(0, 8));

        return parseDate(masked) ? masked : '';
    }

    function maskCpfForReview(value) {
        var d = digits(value);

        return d.length === 11 ? d.slice(0, 3) + '.***.***-' + d.slice(9) : '';
    }

    // Linhas do resumo da revisão: titular primeiro, depois os adicionais de cada item.
    function participantSummary(items, holder, now) {
        var rows = [{
            role: 'Titular',
            name: holder && holder.name ? holder.name : '',
            age: ageOn(holder && holder.birthdate, now),
            cpf: maskCpfForReview(holder && holder.cpf),
            category: 'Adulto'
        }];

        items.forEach(function (item) {
            item.additional_participants.forEach(function (person, index) {
                rows.push({
                    role: 'Participante ' + (index + 2),
                    name: person.name || '',
                    age: ageOn(person.birthdate, now),
                    cpf: maskCpfForReview(person.cpf),
                    category: person.type === 'child' ? 'Criança' : 'Adulto'
                });
            });
        });

        return rows;
    }

    // Erro de validação do Woo que bloqueia o pedido enquanto o aceite não é marcado.
    function consentErrors(accepted) {
        var errors = {};

        if (accepted !== true) {
            addError(errors, 'wcai-consent', 'Aceite o consentimento e os termos de participação para finalizar o pedido.');
        }

        return errors;
    }

    // Total do carrinho (store API: valor em unidade menor + separadores da moeda).
    function formatTotal(totals) {
        if (!totals || totals.total_price === undefined) return '';

        var minor = parseInt(totals.currency_minor_unit, 10);
        if (isNaN(minor)) minor = 2;

        var raw = String(Math.abs(parseInt(totals.total_price, 10) || 0));
        while (raw.length <= minor) raw = '0' + raw;

        var whole = raw.slice(0, raw.length - minor).replace(/\B(?=(\d{3})+(?!\d))/g, totals.currency_thousand_separator || '.');
        var fraction = minor ? (totals.currency_decimal_separator || ',') + raw.slice(raw.length - minor) : '';
        var prefix = totals.currency_prefix ? String(totals.currency_prefix).trim() + NBSP : '';
        var suffix = totals.currency_suffix ? NBSP + String(totals.currency_suffix).trim() : '';

        return prefix + whole + fraction + suffix;
    }

    // Endereço antigo: estado é UF brasileira, mas o país não é o Brasil.
    function suspectCountry(country, state) {
        return !!country && country !== 'BR' && BR_UFS.indexOf(String(state || '').toUpperCase()) !== -1;
    }

    function cpfMessage(value, who) {
        if (!String(value || '').trim()) return 'Informe o CPF ' + who + '.';
        return isValidCpf(value) ? '' : 'CPF ' + who + ' inválido.';
    }

    function birthMessage(value, who, now) {
        if (!String(value || '').trim()) return 'Informe a data de nascimento ' + who + '.';
        if (!parseDate(value)) return 'Data de nascimento ' + who + ' inválida. Use dd/mm/aaaa.';
        return isMinAge(value, MIN_AGE, now) ? '' : 'Data de nascimento ' + who + ': idade mínima de ' + MIN_AGE + ' anos.';
    }

    function inputField(label, value, placeholder, mask, onChange, attrs) {
        attrs = attrs || {};

        var wrap = document.createElement('div');
        wrap.className = 'wcai-wizard-field';

        var labelEl = document.createElement('label');
        labelEl.textContent = label;
        labelEl.htmlFor = attrs.id;
        wrap.appendChild(labelEl);

        var input = document.createElement('input');
        input.type = 'text';
        input.id = attrs.id;
        input.setAttribute('data-wcai-control', '');
        input.setAttribute('aria-required', 'true');
        if (attrs.inputmode) input.setAttribute('inputmode', attrs.inputmode);
        input.value = value || '';
        input.placeholder = placeholder || '';
        // Dados de outra pessoa: "off" evita preencher com os dados do comprador.
        input.autocomplete = 'off';

        input.addEventListener('input', function () {
            var valueNow = mask ? mask(input.value) : input.value;
            input.value = valueNow;
            onChange(valueNow);
            scheduleSync();
            updateLocalValidation();
        });

        wrap.appendChild(input);
        wrap.appendChild(fieldError(attrs.id));
        return wrap;
    }

    function fieldError(id) {
        var error = document.createElement('p');
        error.id = id + '-error';
        error.className = 'wcai-field-error';
        error.setAttribute('role', 'alert');
        error.hidden = true;
        return error;
    }

    function selectField(label, value, options, onChange, attrs) {
        attrs = attrs || {};

        var wrap = document.createElement('div');
        wrap.className = 'wcai-wizard-field';

        var labelEl = document.createElement('label');
        labelEl.textContent = label;
        labelEl.htmlFor = attrs.id;
        wrap.appendChild(labelEl);

        var select = document.createElement('select');
        select.id = attrs.id;
        select.setAttribute('data-wcai-control', '');
        select.setAttribute('aria-required', 'true');

        options.forEach(function (option) {
            var optionEl = document.createElement('option');
            optionEl.value = option.value;
            optionEl.textContent = option.label;
            optionEl.selected = String(option.value) === String(value);
            select.appendChild(optionEl);
        });

        select.addEventListener('change', function () {
            onChange(select.value);
            scheduleSync();
            updateLocalValidation();
        });

        wrap.appendChild(select);
        wrap.appendChild(fieldError(attrs.id));
        return wrap;
    }

    function itemState(item) {
        var ext = item.extensions && item.extensions[NS] ? item.extensions[NS] : {};
        var key = String(item.key || item.id || (ext.product_id + ':' + ext.variation_id));
        var old = state.items.filter(function (entry) {
            return entry.key === key;
        })[0];

        var quantity = parseInt(ext.quantity || item.quantity || 1, 10) || 1;
        var adults = Math.max(1, parseInt(ext.adults || quantity, 10) || quantity);
        var children = Math.max(0, parseInt(ext.children || 0, 10) || 0);
        var additionalCount = Math.max(0, quantity - 1);

        old = old || {
            key: key,
            name: item.name || 'Ingresso',
            product_id: ext.product_id,
            variation_id: ext.variation_id,
            departure_id: ext.departure_id || '',
            departure_label: ext.departure_label || '',
            quantity: quantity,
            adults: adults,
            children: children,
            additional_participants: []
        };

        old.name = item.name || old.name || 'Ingresso';
        old.product_id = ext.product_id || old.product_id;
        old.variation_id = ext.variation_id || old.variation_id;
        old.quantity = quantity;
        old.adults = adults;
        old.children = children;

        if (ext.departure_id) {
            old.departure_id = String(ext.departure_id);
        }

        if (ext.departure_label) {
            old.departure_label = ext.departure_label;
        }

        for (var i = 0; i < additionalCount; i++) {
            if (!old.additional_participants[i]) {
                old.additional_participants[i] = {};
            }

            if (!old.additional_participants[i].type) {
                old.additional_participants[i].type = i < Math.max(0, adults - 1) ? 'adult' : 'child';
            }
        }

        old.additional_participants = old.additional_participants.slice(0, additionalCount);

        return old;
    }

    function firstExisting(selectors) {
        for (var i = 0; i < selectors.length; i++) {
            var node = document.querySelector(selectors[i]);
            if (node) return node;
        }
        return null;
    }

    // Seletores por seção nativa: o atributo data-block-name some do DOM depois que o Woo monta o React;
    // as classes wc-block-checkout__* e os ids são o que existe no HTML final.
    var NATIVE_GROUPS = {
        contact: ['[data-block-name="woocommerce/checkout-contact-information-block"]', '.wc-block-checkout__contact-fields', '#contact-fields'],
        billing: ['[data-block-name="woocommerce/checkout-billing-address-block"]', '.wc-block-checkout__billing-fields', '#billing-fields'],
        shipping: [
            '[data-block-name="woocommerce/checkout-shipping-address-block"]',
            '[data-block-name="woocommerce/checkout-shipping-methods-block"]',
            '[data-block-name="woocommerce/checkout-shipping-method-block"]',
            '[data-block-name="woocommerce/checkout-pickup-options-block"]',
            '.wc-block-checkout__shipping-fields', '#shipping-fields',
            '.wc-block-checkout__shipping-option', '#shipping-option',
            '.wc-block-checkout__pickup-options', '#pickup-options'
        ],
        payment: ['[data-block-name="woocommerce/checkout-payment-block"]', '.wc-block-checkout__payment-method', '#payment-method'],
        note: ['[data-block-name="woocommerce/checkout-order-note-block"]', '.wc-block-checkout__add-note'],
        terms: ['[data-block-name="woocommerce/checkout-terms-block"]', '.wc-block-checkout__terms'],
        actions: ['[data-block-name="woocommerce/checkout-actions-block"]', '.wc-block-checkout__actions'],
        express: [
            '[data-block-name="woocommerce/checkout-express-payment-block"]',
            '.wc-block-components-express-payment',
            '.wc-block-components-express-payment-continue-rule'
        ]
    };

    // Seções nativas escondidas em cada etapa (só CSS: o DOM do React nunca é alterado).
    var HIDDEN_BY_STEP = {
        1: ['contact', 'billing', 'shipping', 'payment', 'note', 'terms', 'actions', 'express'],
        2: ['shipping', 'payment', 'note', 'terms', 'actions', 'express'],
        3: ['contact', 'billing', 'shipping', 'payment', 'note', 'terms', 'actions', 'express'],
        4: ['contact', 'billing']
    };

    // Função pura: CSS de visibilidade por etapa.
    function stageGateCss() {
        return [1, 2, 3, 4].map(function (step) {
            var selectors = [];

            HIDDEN_BY_STEP[step].forEach(function (group) {
                NATIVE_GROUPS[group].forEach(function (selector) {
                    selectors.push('body.wcai-checkout-step-' + step + ' ' + selector);
                });
            });

            return selectors.join(',') + '{display:none!important;visibility:hidden!important;pointer-events:none!important}';
        }).join('');
    }

    function nativeSections() {
        var sections = {};

        Object.keys(NATIVE_GROUPS).forEach(function (group) {
            sections[group] = firstExisting(NATIVE_GROUPS[group]);
        });

        return sections;
    }

    function setHidden(node, hidden) {
        if (!node) return;

        node.hidden = hidden;

        if (hidden) {
            node.setAttribute('aria-hidden', 'true');
        } else {
            node.removeAttribute('aria-hidden');
        }
    }

    function setCheckoutStageClass() {
        if (!document.body) return;

        for (var i = 1; i <= 4; i++) {
            document.body.classList.toggle('wcai-checkout-step-' + i, currentStep === i);
        }

        document.body.classList.toggle('wcai-checkout-before-payment', currentStep !== 4);
        document.body.classList.toggle('wcai-checkout-payment-step', currentStep === 4);
        document.body.classList.toggle('wcai-consent-missing', !consent);
    }

    function clearCheckoutStageClasses() {
        if (!document.body) return;

        for (var i = 1; i <= 4; i++) {
            document.body.classList.remove('wcai-checkout-step-' + i);
        }

        document.body.classList.remove('wcai-checkout-before-payment');
        document.body.classList.remove('wcai-checkout-payment-step');
        document.body.classList.remove('wcai-consent-missing');
    }

    function applyNativeStepVisibility() {
        setCheckoutStageClass();
    }

    function styleWizard() {
        if (document.getElementById('wcai-wizard-style')) return;

        var style = document.createElement('style');
        style.id = 'wcai-wizard-style';
        style.textContent =
            '#wcai-checkout-wizard{margin:0 0 14px;padding:0;border:0;background:transparent}' +
            '#wcai-checkout-wizard .wcai-wizard-progress{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:4px;margin:0 0 12px;padding:0;list-style:none}' +
            '#wcai-checkout-wizard .wcai-wizard-progress li{display:flex;margin:0;padding:0}' +
            '#wcai-checkout-wizard .wcai-wizard-progress button{display:flex;flex:1;align-items:center;justify-content:center;min-height:44px;padding:5px 6px;border:1px solid #e2e2e2;border-radius:8px;background:#fff;color:#767676;font:inherit;font-size:12px;line-height:1.2;text-align:center;box-sizing:border-box;cursor:default}' +
            '#wcai-checkout-wizard .wcai-wizard-progress button.is-active{border-color:#222;background:#222;color:#fff;font-weight:800}' +
            '#wcai-checkout-wizard .wcai-wizard-progress button.is-complete{color:#222;border-color:#cfcfcf;cursor:pointer}' +
            '#wcai-checkout-wizard .wcai-wizard-progress button.is-complete:before{content:"✓";margin-right:4px;font-weight:900}' +
            '#wcai-checkout-wizard .wcai-wizard-progress button:focus-visible,#wcai-checkout-wizard .wcai-wizard-actions button:focus-visible{outline:3px solid #1a73e8;outline-offset:2px}' +
            '#wcai-checkout-wizard h3:focus{outline:none}' +
            '#wcai-checkout-wizard .wcai-sr-only{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}' +
            '#wcai-checkout-wizard .wcai-error-summary{margin:0 0 12px;padding:10px 11px;border:1px solid #b3261e;border-radius:8px;background:#fdf1f0;color:#8c1d18;font-size:13px}' +
            '#wcai-checkout-wizard .wcai-error-summary ul{margin:6px 0 0 18px;padding:0}' +
            '#wcai-checkout-wizard .wcai-field-error{margin:4px 0 0;color:#b3261e;font-size:12px;font-weight:700}' +
            '#wcai-checkout-wizard [aria-invalid="true"]{border-color:#b3261e!important}' +
            '#wcai-checkout-wizard .wcai-wizard-panel{display:none;padding:14px;border:1px solid #e5e5e5;border-radius:12px;background:#fff;box-shadow:0 5px 18px rgba(0,0,0,.035)}' +
            '#wcai-checkout-wizard .wcai-wizard-panel.is-active{display:block}' +
            '#wcai-checkout-wizard h3{margin:0 0 7px;font-size:17px}' +
            '#wcai-checkout-wizard h4{margin:0 0 9px;font-size:14px}' +
            '#wcai-checkout-wizard h5{margin:0 0 9px;font-size:13px}' +
            '#wcai-checkout-wizard p{font-size:12px;line-height:1.5;color:#666}' +
            '#wcai-checkout-wizard .wcai-wizard-item{padding:12px;margin:0 0 10px;border:1px solid #e5e5e5;border-radius:10px;background:#fff}' +
            '#wcai-checkout-wizard .wcai-reservation-summary{display:grid;grid-template-columns:1fr;gap:7px;margin:10px 0}' +
            '#wcai-checkout-wizard .wcai-reservation-summary>div{display:flex;justify-content:space-between;gap:14px;padding:9px 10px;border:1px solid #ededed;border-radius:8px;background:#fafafa}' +
            '#wcai-checkout-wizard .wcai-reservation-summary span{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:#888}' +
            '#wcai-checkout-wizard .wcai-reservation-summary strong{font-size:12px;text-align:right}' +
            '#wcai-checkout-wizard .wcai-wizard-person{padding:11px;margin:9px 0;border:1px solid #eee;border-radius:9px;background:#fafafa}' +
            '#wcai-checkout-wizard .wcai-wizard-field{margin:0 0 9px}' +
            '#wcai-checkout-wizard .wcai-wizard-field label{display:block;font-weight:700;margin-bottom:4px;font-size:12px}' +
            '#wcai-checkout-wizard .wcai-wizard-field input,#wcai-checkout-wizard .wcai-wizard-field select{width:100%;min-height:44px;padding:8px 9px;box-sizing:border-box;border:1px solid #d9d9d9;border-radius:7px;background:#fff}' +
            '#wcai-checkout-wizard .wcai-wizard-actions{display:flex;justify-content:space-between;gap:9px;margin-top:14px;padding-top:11px;border-top:1px solid #eee}' +
            '#wcai-checkout-wizard .wcai-wizard-actions button{min-height:44px;padding:8px 14px;border:1px solid #222;border-radius:8px;cursor:pointer}' +
            '#wcai-checkout-wizard .wcai-wizard-actions button:not(.wcai-wizard-secondary){background:#222;color:#fff}' +
            '#wcai-checkout-wizard .wcai-wizard-actions .wcai-wizard-secondary{background:#fff;color:#222}' +
            '#wcai-checkout-wizard .wcai-wizard-actions .wcai-wizard-secondary:only-child{margin-right:auto}' +
            '#wcai-checkout-wizard .wcai-warning{padding:10px 11px;border:1px solid #e4d6a2;border-radius:8px;background:#fffaf0;color:#695b2e;font-size:12px}' +
            stageGateCss() +
            'body.wcai-checkout-before-payment .wc-block-components-checkout-place-order-button,body.wcai-checkout-before-payment #place_order,body.wcai-checkout-before-payment button[name="woocommerce_checkout_place_order"]{display:none!important}' +
            'body.wcai-checkout-step-4 .wc-block-checkout__actions .wc-block-components-checkout-place-order-button{display:block;width:100%;min-height:44px;font-weight:800;border-radius:8px}' +
            'body.wcai-checkout-step-4.wcai-consent-missing .wc-block-components-checkout-place-order-button{opacity:.45;cursor:not-allowed}' +
            'body[class*="wcai-checkout-step-"] input[aria-invalid="true"],body[class*="wcai-checkout-step-"] select[aria-invalid="true"]{border-color:#b3261e!important;box-shadow:0 0 0 1px #b3261e!important}' +
            'body[class*="wcai-checkout-step-"] .wc-block-components-text-input:has(input[aria-invalid="true"]) label{color:#b3261e!important}' +
            '#wcai-checkout-wizard .wcai-wizard-field select{appearance:none;-webkit-appearance:none;font:inherit;color:inherit;padding-right:34px;background:#fff url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'12\' height=\'8\' viewBox=\'0 0 12 8\'%3E%3Cpath d=\'M1 1l5 5 5-5\' fill=\'none\' stroke=\'%23222\' stroke-width=\'2\'/%3E%3C/svg%3E") no-repeat right 12px center}' +
            '#wcai-checkout-wizard .wcai-link{display:inline;padding:0;border:0;background:none;color:inherit;font:inherit;text-decoration:underline;cursor:pointer}' +
            '#wcai-checkout-wizard .wcai-review-people{margin:10px 0;padding:0;list-style:none}' +
            '#wcai-checkout-wizard .wcai-review-people li{display:flex;flex-wrap:wrap;gap:2px 10px;margin:0 0 6px;padding:8px 10px;border:1px solid #ededed;border-radius:8px;background:#fafafa;font-size:12px}' +
            '#wcai-checkout-wizard .wcai-review-people strong{min-width:96px}' +
            '#wcai-checkout-wizard .wcai-consent{margin:12px 0 0;padding:12px;border:1px solid #d9d9d9;border-radius:10px;background:#fff}' +
            '#wcai-checkout-wizard .wcai-consent p{margin:0 0 10px}' +
            '#wcai-checkout-wizard .wcai-consent-row{display:flex;align-items:flex-start;gap:10px;min-height:44px;font-size:13px;line-height:1.4;cursor:pointer}' +
            '#wcai-checkout-wizard .wcai-consent-row input{flex:none;width:24px;height:24px;margin:0}' +
            '#wcai-checkout-wizard-footer{margin:14px 0 0}' +
            '#wcai-checkout-wizard-footer .wcai-wizard-actions{display:flex;justify-content:space-between;align-items:center;gap:9px;margin:0;padding-top:11px;border-top:1px solid #eee}' +
            '#wcai-checkout-wizard-footer .wcai-wizard-actions button{min-height:44px;padding:8px 14px;border:1px solid #222;border-radius:8px;cursor:pointer}' +
            '#wcai-checkout-wizard-footer .wcai-wizard-actions button:not(.wcai-wizard-secondary){background:#222;color:#fff}' +
            '#wcai-checkout-wizard-footer .wcai-wizard-actions .wcai-wizard-secondary{background:#fff;color:#222}' +
            '#wcai-checkout-wizard-footer .wcai-wizard-actions button:focus-visible{outline:3px solid #1a73e8;outline-offset:2px}' +
            '#wcai-checkout-wizard-footer .wcai-order-total{font-weight:800;font-size:15px}' +
            '#wcai-checkout-wizard-footer .wcai-consent-hint{margin:8px 0 0;color:#b3261e;font-size:12px;font-weight:700}' +
            '@media(max-width:600px){' +
            '#wcai-checkout-wizard-footer{position:sticky;bottom:0;z-index:30;margin:14px -4px 0;padding:0 4px 8px;background:#fff;box-shadow:0 -6px 12px rgba(0,0,0,.06)}' +
            '#wcai-checkout-wizard .wcai-wizard-progress{grid-template-columns:repeat(2,minmax(0,1fr));gap:4px}' +
            '#wcai-checkout-wizard .wcai-wizard-progress button{padding:6px 8px;overflow-wrap:anywhere}' +
            'body[class*="wcai-checkout-step-"] .wc-block-components-radio-control__option{min-height:44px;padding-top:12px;padding-bottom:12px}' +
            'body[class*="wcai-checkout-step-"] .wc-block-components-checkbox label{min-height:44px;align-items:center}' +
            '}';

        document.head.appendChild(style);
    }

    // Função pura: devolve { idDoCampo: { message, hidden } } para os participantes adicionais.
    function collectAdditionalErrors(items, now) {
        var errors = {};

        items.forEach(function (item, itemIndex) {
            var additionalAdults = 0;
            var additionalChildren = 0;

            item.additional_participants.forEach(function (participant, index) {
                var number = index + 2;

                if (participant.type === 'child') {
                    additionalChildren++;
                } else if (participant.type === 'adult') {
                    additionalAdults++;
                } else {
                    addError(errors, 'wcai-type-' + itemIndex + '-' + index, 'Selecione adulto ou criança para o participante ' + number + '.');
                }

                if (!participant.name) {
                    addError(errors, 'wcai-name-' + itemIndex + '-' + index, 'Informe o nome completo do participante ' + number + '.');
                }

                var who = 'do participante ' + number;
                var cpfError = cpfMessage(participant.cpf, who);
                var birthError = birthMessage(participant.birthdate, who, now);

                if (cpfError) {
                    addError(errors, 'wcai-cpf-' + itemIndex + '-' + index, cpfError);
                }

                if (birthError) {
                    addError(errors, 'wcai-birth-' + itemIndex + '-' + index, birthError);
                }
            });

            if (
                additionalAdults !== Math.max(0, item.adults - 1) ||
                additionalChildren !== item.children
            ) {
                addError(
                    errors,
                    'wcai-category-count-' + itemIndex,
                    'A distribuição entre adultos e crianças não corresponde à reserva escolhida.'
                );
            }
        });

        return errors;
    }

    // Erros de participantes + aceite pendente: tudo no store de validação do Woo (bloqueia o pedido).
    function publishErrors(errors) {
        var all = {};
        var pending = consentErrors(consent);

        Object.keys(errors).forEach(function (id) { all[id] = errors[id]; });
        Object.keys(pending).forEach(function (id) { all[id] = pending[id]; });

        setValidationErrors(all);
    }

    function setConsent(value) {
        consent = value === true;
        setCheckoutStageClass();
        publishErrors(collectAdditionalErrors(state.items));

        var hint = document.querySelector('[data-wcai-consent-hint]');
        if (hint) hint.hidden = consent;

        scheduleSync();
    }

    function validateAdditional() {
        var errors = collectAdditionalErrors(state.items);

        publishErrors(errors);

        if (attempted) renderInlineErrors(errors);

        return Object.keys(errors).length === 0;
    }

    // Mostra, por campo, a mensagem (role=alert) e liga aria-invalid/aria-describedby.
    function renderInlineErrors(errors) {
        var root = document.getElementById(ROOT);
        if (!root) return;

        Array.prototype.slice.call(root.querySelectorAll('[data-wcai-control]')).forEach(function (control) {
            var error = errors[control.id];
            var node = document.getElementById(control.id + '-error');

            if (node) {
                var text = error ? error.message : '';
                if (node.textContent !== text) node.textContent = text;
                node.hidden = !error;
            }

            if (error) {
                control.setAttribute('aria-invalid', 'true');
                control.setAttribute('aria-describedby', control.id + '-error');
            } else {
                control.removeAttribute('aria-invalid');
                control.removeAttribute('aria-describedby');
            }
        });
    }

    // Resumo no topo da etapa. entries: [{ el, message }]. Cada campo vira um link que foca o campo.
    // Escolha: não inserimos <p role=alert> ao lado dos inputs do Woo (nós alheios dentro da árvore React).
    // Foca o 1º campo inválido (ou o resumo) quando focus !== false.
    function reportErrors(panel, entries, focus) {
        var summary = panel.querySelector('.wcai-error-summary');
        if (!summary) return;

        summary.textContent = '';
        summary.hidden = !entries.length;

        if (!entries.length) return;

        var intro = document.createElement('strong');
        intro.textContent = 'Corrija para continuar:';
        summary.appendChild(intro);

        var list = document.createElement('ul');

        entries.forEach(function (entry) {
            var li = document.createElement('li');

            if (entry.el) {
                var link = document.createElement('button');
                link.type = 'button';
                link.className = 'wcai-link';
                link.textContent = entry.message;
                link.addEventListener('click', function () {
                    entry.el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    entry.el.focus();
                });
                li.appendChild(link);
            } else {
                li.textContent = entry.message;
            }

            list.appendChild(li);
        });

        summary.appendChild(list);

        if (focus === false) return;

        var first = entries.filter(function (entry) {
            return entry.el;
        })[0];

        var target = first ? first.el : summary;
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.focus();
    }

    function errorSummary() {
        var node = document.createElement('div');
        node.className = 'wcai-error-summary';
        node.tabIndex = -1;
        node.hidden = true;
        return node;
    }

    function validateDeparture() {
        return state.items.every(function (item) {
            return !!item.departure_id;
        });
    }

    function customField(selectors) {
        for (var i = 0; i < selectors.length; i++) {
            var node = document.querySelector(selectors[i]);
            if (node) return node;
        }
        return null;
    }

    function readBillingIdentityFields() {
        var cpf = customField(CPF_SELECTORS);
        var birthdate = customField(BIRTH_SELECTORS);

        return {
            cpf: cpf ? cpf.value.trim() : '',
            birthdate: birthdate ? birthdate.value.trim() : '',
            cpfField: cpf,
            birthdateField: birthdate
        };
    }

    function missingRequiredFields(section) {
        if (!section) return [];

        var fields = Array.prototype.slice.call(section.querySelectorAll('input,select,textarea'));

        return fields.filter(function (field) {
            if (!field || field.disabled) return false;

            var style = window.getComputedStyle(field);
            if (style.display === 'none' || style.visibility === 'hidden') return false;

            return field.required || field.getAttribute('aria-required') === 'true';
        }).filter(function (field) {
            return !String(field.value || '').trim();
        });
    }

    // Campos nativos do WooCommerce: o bloco é React, então só marcamos aria-invalid e listamos no resumo.
    function validateBillingStep(panel, focus) {
        var identity = readBillingIdentityFields();
        var sections = nativeSections();
        var entries = [];

        function add(el, message) {
            if (el && message && !entries.some(function (entry) { return entry.el === el; })) {
                entries.push({ el: el, message: message });
            }
        }

        flaggedNative.forEach(function (el) {
            el.removeAttribute('aria-invalid');
        });

        if (identity.cpfField) add(identity.cpfField, cpfMessage(identity.cpf, 'do titular'));
        if (identity.birthdateField) add(identity.birthdateField, birthMessage(identity.birthdate, 'do titular'));

        missingRequiredFields(sections.contact).concat(missingRequiredFields(sections.billing)).forEach(function (field) {
            var label = field.labels && field.labels[0] ? field.labels[0].textContent.trim() : '';
            add(field, label ? 'Preencha o campo ' + label + '.' : 'Preencha todos os campos obrigatórios.');
        });

        flaggedNative = entries.map(function (entry) {
            entry.el.setAttribute('aria-invalid', 'true');
            return entry.el;
        });

        reportErrors(panel, entries, focus);

        return entries.length === 0;
    }

    function updateLocalValidation() {
        if (currentStep === 3) {
            validateAdditional();
        }
    }

    function button(text, onClick, secondary) {
        var buttonEl = document.createElement('button');
        buttonEl.type = 'button';
        buttonEl.textContent = text;

        if (secondary) {
            buttonEl.className = 'wcai-wizard-secondary';
        }

        buttonEl.addEventListener('click', onClick);
        return buttonEl;
    }

    function actions(nextText, nextHandler, previousHandler) {
        var wrap = document.createElement('div');
        wrap.className = 'wcai-wizard-actions';

        if (previousHandler) {
            wrap.appendChild(button('Voltar', previousHandler, true));
        } else {
            var spacer = document.createElement('span');
            spacer.setAttribute('aria-hidden', 'true');
            wrap.appendChild(spacer);
        }

        if (nextHandler) {
            wrap.appendChild(button(nextText || 'Continuar', nextHandler, false));
        }

        return wrap;
    }

    // Botões Voltar/Continuar vão para a barra abaixo dos campos nativos; sem a barra, ficam no painel.
    function mountActions(panel, step, nodes) {
        var footer = document.getElementById('wcai-checkout-wizard-footer');
        var host = panel;

        if (footer) {
            host = footer.querySelector('[data-wcai-actions="' + step + '"]');

            if (!host) {
                host = document.createElement('div');
                host.setAttribute('data-wcai-actions', step);
                footer.appendChild(host);
            }

            host.textContent = '';
        }

        nodes.forEach(function (node) {
            host.appendChild(node);
        });
    }

    function setNativeValue(input, value) {
        Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set.call(input, value);
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // Rascunho restaurado pelo Woo: limpa lixo ('1505199015051990') só em campo que o usuário não tocou nem está editando.
    function restoreNativeDrafts() {
        [[CPF_SELECTORS, 'cpf'], [BIRTH_SELECTORS, 'date']].forEach(function (pair) {
            var field = firstExisting(pair[0]);

            if (!field || !field.value || field === document.activeElement || field.getAttribute('data-wcai-touched')) return;

            var clean = sanitizeDraft(pair[1], field.value);

            if (clean !== field.value) setNativeValue(field, clean);
        });
    }

    function updateTotal() {
        var data = cart();
        var text = data ? formatTotal(data.totals) : '';

        Array.prototype.slice.call(document.querySelectorAll('[data-wcai-total]')).forEach(function (node) {
            var label = text ? 'Pedido de ' + text : '';
            if (node.textContent !== label) node.textContent = label;
        });
    }

    function refreshCountryHint() {
        var node = document.querySelector('[data-wcai-country-hint]');
        if (!node) return;

        var customer = customerData();
        var address = customer && customer.billingAddress ? customer.billingAddress : {};

        node.hidden = !suspectCountry(address.country, address.state);
    }

    function holderInfo() {
        var identity = readBillingIdentityFields();
        var customer = customerData();
        var address = customer && customer.billingAddress ? customer.billingAddress : {};

        return {
            name: [address.first_name, address.last_name].filter(Boolean).join(' '),
            cpf: identity.cpf,
            birthdate: identity.birthdate
        };
    }

    function setStep(step, moveFocus) {
        var next = Math.max(1, Math.min(4, step));

        if (next !== currentStep) attempted = false;

        currentStep = next;

        var root = document.getElementById(ROOT);
        if (!root) return;

        Array.prototype.slice.call(root.querySelectorAll('[data-wcai-step]')).forEach(function (panel) {
            var active = parseInt(panel.getAttribute('data-wcai-step'), 10) === currentStep;
            panel.classList.toggle('is-active', active);
        });

        Array.prototype.slice.call(root.querySelectorAll('[data-step-label]')).forEach(function (label) {
            var labelStep = parseInt(label.getAttribute('data-step-label'), 10);
            label.classList.toggle('is-active', labelStep === currentStep);
            label.classList.toggle('is-complete', labelStep < currentStep);
            label.disabled = labelStep > currentStep;

            if (labelStep === currentStep) {
                label.setAttribute('aria-current', 'step');
            } else {
                label.removeAttribute('aria-current');
            }
        });

        if (currentStep === 4) {
            buildStepFour(root.querySelector('[data-wcai-step="4"]'));
        }

        if (currentStep === 2) {
            restoreNativeDrafts();
        }

        Array.prototype.slice.call(document.querySelectorAll('[data-wcai-actions]')).forEach(function (host) {
            host.hidden = parseInt(host.getAttribute('data-wcai-actions'), 10) !== currentStep;
        });

        applyNativeStepVisibility();

        if (moveFocus) {
            var heading = root.querySelector('[data-wcai-step="' + currentStep + '"] h3');
            var live = root.querySelector('[data-wcai-live]');

            if (heading) {
                heading.setAttribute('tabindex', '-1');
                heading.focus({ preventScroll: true });
            }

            if (live && heading) {
                live.textContent = 'Etapa ' + currentStep + ' de 4: ' + heading.textContent;
            }
        }

        if (currentStep === 3) {
            validateAdditional();
        }

        root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function buildStepOne(panel) {
        panel.innerHTML = '';

        var title = document.createElement('h3');
        title.textContent = '1. Confira sua reserva';
        panel.appendChild(title);
        panel.appendChild(errorSummary());

        state.items.forEach(function (item) {
            var box = document.createElement('div');
            box.className = 'wcai-wizard-item';

            var name = document.createElement('strong');
            name.textContent = item.name || 'Ingresso';
            box.appendChild(name);

            var summary = document.createElement('div');
            summary.className = 'wcai-reservation-summary';

            var departureBox = document.createElement('div');
            departureBox.innerHTML = '<span>Saída</span>';
            var departureValue = document.createElement('strong');
            departureValue.textContent = item.departure_label || 'Data e horário não selecionados';
            departureBox.appendChild(departureValue);
            summary.appendChild(departureBox);

            var peopleBox = document.createElement('div');
            peopleBox.innerHTML = '<span>Participantes</span>';
            var peopleValue = document.createElement('strong');
            peopleValue.textContent =
                item.adults + ' adulto' +
                (item.adults === 1 ? '' : 's') +
                (item.children ? ' + ' + item.children + ' criança' + (item.children === 1 ? '' : 's') : '');
            peopleBox.appendChild(peopleValue);
            summary.appendChild(peopleBox);

            box.appendChild(summary);

            if (!item.departure_id) {
                var warning = document.createElement('div');
                warning.className = 'wcai-warning';
                warning.textContent = 'A saída não foi definida. Volte à página do passeio e escolha a data e o horário antes de continuar.';
                box.appendChild(warning);
            }

            panel.appendChild(box);
        });

        mountActions(panel, 1, [
            actions('Continuar para seus dados', function () {
                if (!validateDeparture()) {
                    reportErrors(panel, [{ el: null, message: 'Escolha a data e o horário da saída na página do passeio.' }]);
                    return;
                }

                setStep(2, true);
            })
        ]);
    }

    function buildStepTwo(panel) {
        panel.innerHTML = '';

        var title = document.createElement('h3');
        title.textContent = '2. Seus dados';
        panel.appendChild(title);
        panel.appendChild(errorSummary());

        var text = document.createElement('p');
        text.textContent = 'Preencha os dados do titular abaixo. Os campos de identificação do passeio fazem parte desta etapa e não precisam ser repetidos depois.';
        panel.appendChild(text);

        var countryHint = document.createElement('div');
        countryHint.className = 'wcai-warning';
        countryHint.setAttribute('role', 'status');
        countryHint.setAttribute('data-wcai-country-hint', '');
        countryHint.hidden = true;
        countryHint.textContent = 'Confira o país: o endereço tem um estado do Brasil, mas o país informado é outro.';
        panel.appendChild(countryHint);

        mountActions(panel, 2, [
            actions('Continuar para participantes', function () {
                attempted = true;
                if (!validateBillingStep(panel)) return;
                setStep(3, true);
            }, function () {
                setStep(1, true);
            })
        ]);
    }

    function buildStepThree(panel) {
        panel.innerHTML = '';

        var title = document.createElement('h3');
        title.textContent = '3. Participantes';
        panel.appendChild(title);
        panel.appendChild(errorSummary());

        var text = document.createElement('p');
        text.textContent = 'O titular é o primeiro adulto. Preencha somente os dados dos demais participantes.';
        panel.appendChild(text);

        var hasAdditional = false;

        state.items.forEach(function (item, itemIndex) {
            if (!item.additional_participants.length) return;

            hasAdditional = true;

            var itemBox = document.createElement('div');
            itemBox.className = 'wcai-wizard-item';

            var heading = document.createElement('h4');
            heading.textContent = item.name + ' — ' + item.additional_participants.length + ' participante(s)';
            itemBox.appendChild(heading);

            item.additional_participants.forEach(function (person, index) {
                var personBox = document.createElement('div');
                personBox.className = 'wcai-wizard-person';

                var personTitle = document.createElement('h5');
                personTitle.textContent = 'Participante ' + (index + 2);
                personBox.appendChild(personTitle);

                personBox.appendChild(
                    selectField('Categoria', person.type || 'adult', [
                        { value: 'adult', label: 'Adulto' },
                        { value: 'child', label: 'Criança' }
                    ], function (value) {
                        person.type = value;
                    }, { id: 'wcai-type-' + itemIndex + '-' + index })
                );

                personBox.appendChild(
                    inputField('Nome completo', person.name, 'Nome completo', null, function (value) {
                        person.name = value;
                    }, { id: 'wcai-name-' + itemIndex + '-' + index })
                );

                personBox.appendChild(
                    inputField('CPF', person.cpf, '000.000.000-00', cpfMask, function (value) {
                        person.cpf = value;
                    }, { id: 'wcai-cpf-' + itemIndex + '-' + index, inputmode: 'numeric' })
                );

                personBox.appendChild(
                    inputField('Data de nascimento', person.birthdate, 'dd/mm/aaaa', dateMask, function (value) {
                        person.birthdate = value;
                    }, { id: 'wcai-birth-' + itemIndex + '-' + index, inputmode: 'numeric' })
                );

                itemBox.appendChild(personBox);
            });

            panel.appendChild(itemBox);
        });

        if (!hasAdditional) {
            var none = document.createElement('p');
            none.textContent = 'Esta reserva possui apenas o titular. Não há dados adicionais para preencher.';
            panel.appendChild(none);
        }

        mountActions(panel, 3, [
            actions('Revisar e pagar', function () {
                attempted = true;

                if (!validateAdditional()) {
                    var errors = collectAdditionalErrors(state.items);

                    reportErrors(panel, Object.keys(errors).map(function (id) {
                        return { el: document.getElementById(id), message: errors[id].message };
                    }));
                    return;
                }

                reportErrors(panel, []);
                setStep(4, true);
            }, function () {
                setStep(2, true);
            })
        ]);
    }

    function buildStepFour(panel) {
        panel.innerHTML = '';

        var title = document.createElement('h3');
        title.textContent = '4. Revisão e pagamento';
        panel.appendChild(title);

        state.items.forEach(function (item) {
            var box = document.createElement('div');
            box.className = 'wcai-wizard-item';

            var heading = document.createElement('strong');
            heading.textContent = item.name + ' — ' + item.quantity + ' participante(s)';
            box.appendChild(heading);

            var summary = document.createElement('div');
            summary.className = 'wcai-reservation-summary';

            var date = document.createElement('div');
            date.innerHTML = '<span>Saída</span>';
            var dateValue = document.createElement('strong');
            dateValue.textContent = item.departure_label || 'não selecionada';
            date.appendChild(dateValue);
            summary.appendChild(date);

            var participants = document.createElement('div');
            participants.innerHTML = '<span>Participantes</span>';
            var participantsValue = document.createElement('strong');
            participantsValue.textContent =
                item.adults + ' adulto' +
                (item.adults === 1 ? '' : 's') +
                (item.children ? ' + ' + item.children + ' criança' + (item.children === 1 ? '' : 's') : '');
            participants.appendChild(participantsValue);
            summary.appendChild(participants);

            box.appendChild(summary);
            panel.appendChild(box);
        });

        var peopleTitle = document.createElement('h4');
        peopleTitle.textContent = 'Participantes';
        panel.appendChild(peopleTitle);
        panel.appendChild(reviewPeople());
        panel.appendChild(consentBlock());

        var back = actions(null, null, function () {
            setStep(3, true);
        });
        var total = document.createElement('span');
        total.className = 'wcai-order-total';
        total.setAttribute('data-wcai-total', '');
        back.appendChild(total);

        var hint = document.createElement('p');
        hint.className = 'wcai-consent-hint';
        hint.setAttribute('role', 'status');
        hint.setAttribute('data-wcai-consent-hint', '');
        hint.hidden = consent;
        hint.textContent = 'Marque o aceite para finalizar o pedido.';

        mountActions(panel, 4, [back, hint]);
        updateTotal();
    }

    function reviewPeople() {
        var list = document.createElement('ul');
        list.className = 'wcai-review-people';

        participantSummary(state.items, holderInfo()).forEach(function (row) {
            var li = document.createElement('li');
            var role = document.createElement('strong');
            role.textContent = row.role;
            li.appendChild(role);

            [row.name || 'Nome não informado', row.age === null ? '' : row.age + ' anos', row.cpf ? 'CPF ' + row.cpf : ''].forEach(function (part) {
                if (!part) return;
                var span = document.createElement('span');
                span.textContent = part;
                li.appendChild(span);
            });

            list.appendChild(li);
        });

        return list;
    }

    function consentBlock() {
        var cfg = config();
        var box = document.createElement('div');
        box.className = 'wcai-consent';

        if (cfg.consentText) {
            var text = document.createElement('p');
            text.textContent = cfg.consentText;
            box.appendChild(text);
        }

        var row = document.createElement('label');
        row.className = 'wcai-consent-row';

        var input = document.createElement('input');
        input.type = 'checkbox';
        input.id = 'wcai-consent';
        input.checked = consent;
        input.setAttribute('aria-required', 'true');
        input.addEventListener('change', function () {
            setConsent(input.checked);
        });
        row.appendChild(input);

        var label = document.createElement('span');
        label.appendChild(document.createTextNode('Li e aceito os '));

        if (/^https?:\/\//i.test(cfg.termsUrl || '')) {
            var link = document.createElement('a');
            link.href = cfg.termsUrl;
            link.target = '_blank';
            link.rel = 'noopener noreferrer';
            link.textContent = 'termos de participação';
            label.appendChild(link);
        } else {
            label.appendChild(document.createTextNode('termos de participação'));
        }

        row.appendChild(label);
        box.appendChild(row);

        return box;
    }

    function render() {
        var root = document.getElementById(ROOT);
        var data = cart();

        if (!root || !data || !Array.isArray(data.items)) return;

        var targets = data.items.filter(function (item) {
            return item.extensions &&
                item.extensions[NS] &&
                item.extensions[NS].enabled;
        });

        state.items = targets.map(itemState);

        if (!targets.length) {
            root.style.display = 'none';
            clearCheckoutStageClasses();
            setValidationErrors({});
            return;
        }

        root.style.display = '';

        styleWizard();
        buildStepOne(root.querySelector('[data-wcai-step="1"]'));
        buildStepTwo(root.querySelector('[data-wcai-step="2"]'));
        buildStepThree(root.querySelector('[data-wcai-step="3"]'));
        buildStepFour(root.querySelector('[data-wcai-step="4"]'));
        scheduleSync();
        setStep(Math.min(currentStep, 4));
    }

    function sig(data) {
        if (!data || !Array.isArray(data.items)) return '';

        return data.items.map(function (item) {
            var ext = item.extensions && item.extensions[NS];

            if (!ext) {
                return 'x:' + (item.key || item.id);
            }

            return [
                item.key || item.id,
                ext.product_id,
                ext.variation_id,
                ext.quantity,
                ext.departure_id,
                ext.adults,
                ext.children,
                JSON.stringify(ext.departures || [])
            ].join('|');
        }).join('||');
    }

    function boot() {
        if (!document.getElementById(ROOT) || !window.wp || !wp.data) return;

        function update() {
            var data = cart();
            var next = sig(data);

            if (next !== signature || domLost()) {
                signature = next;
                render();
            }

            updateTotal();
            refreshCountryHint();
            restoreNativeDrafts();
        }

        // O React do Woo pode recriar nós estáticos: se o painel 1 ou a barra ficaram vazios, monta de novo.
        function domLost() {
            var panelOne = document.querySelector('#' + ROOT + ' [data-wcai-step="1"]');
            var footer = document.getElementById('wcai-checkout-wizard-footer');

            return state.items.length > 0 && ((panelOne && !panelOne.firstChild) || (footer && !footer.firstChild));
        }

        update();
        wp.data.subscribe(update, 'wc/store/cart');

        document.addEventListener('click', function (event) {
            var target = event.target;
            if (!target || !target.closest) return;

            var label = target.closest('[data-step-label]');

            if (!label) return;

            var targetStep = parseInt(label.getAttribute('data-step-label'), 10);

            if (targetStep < currentStep) {
                setStep(targetStep, true);
            }
        });

        // Woo rejeita inputmode/placeholder em checkout fields adicionais: aplica aqui, ao focar o campo nativo do titular.
        document.addEventListener('focusin', function (event) {
            var target = event.target;
            if (!target || !target.matches) return;
            var hint = target.matches(CPF_SELECTORS.join(',')) ? '000.000.000-00' : (target.matches(BIRTH_SELECTORS.join(',')) ? 'dd/mm/aaaa' : '');
            if (!hint) return;
            target.setAttribute('inputmode', 'numeric');
            target.setAttribute('placeholder', hint);
        });

        // Máscara de CPF e de data no campo nativo do titular (bloco React: usa o setter nativo para o estado atualizar).
        document.addEventListener('input', function (event) {
            var target = event.target;
            if (!target || !target.matches) return;

            var mask = target.matches(CPF_SEL) ? cpfMask : (target.matches(BIRTH_SEL) ? dateMask : null);
            if (!mask) return;

            // Evento sintético (nosso) não conta como edição do usuário.
            if (event.isTrusted) target.setAttribute('data-wcai-touched', '1');

            var masked = mask(target.value);

            if (masked !== target.value) {
                setNativeValue(target, masked);
                return;
            }

            var root = document.getElementById(ROOT);
            var panel = root && root.querySelector('[data-wcai-step="2"]');

            if (attempted && currentStep === 2 && panel) validateBillingStep(panel, false);
        });

        // Sem aceite, o clique em "Finalizar pedido" não chega ao Woo (o teclado também gera clique).
        document.addEventListener('click', function (event) {
            var target = event.target;

            if (consent || !target || !target.closest || !document.body.classList.contains('wcai-checkout-step-4')) return;
            if (!target.closest('.wc-block-components-checkout-place-order-button')) return;

            event.preventDefault();
            event.stopPropagation();

            var box = document.getElementById('wcai-consent');

            if (box) {
                box.scrollIntoView({ behavior: 'smooth', block: 'center' });
                box.focus();
            }
        }, true);
    }

    // Ambiente de teste (Node): expõe só as funções puras, sem tocar no DOM.
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = {
            isValidCpf: isValidCpf,
            parseDate: parseDate,
            isMinAge: isMinAge,
            cpfMessage: cpfMessage,
            birthMessage: birthMessage,
            collectAdditionalErrors: collectAdditionalErrors,
            pushExtensionData: pushExtensionData,
            cpfMask: cpfMask,
            dateMask: dateMask,
            ageOn: ageOn,
            sanitizeDraft: sanitizeDraft,
            maskCpfForReview: maskCpfForReview,
            participantSummary: participantSummary,
            consentErrors: consentErrors,
            buildPayload: buildPayload,
            formatTotal: formatTotal,
            suspectCountry: suspectCountry,
            stageGateCss: stageGateCss
        };
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());

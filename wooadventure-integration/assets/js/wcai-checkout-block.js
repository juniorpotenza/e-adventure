(function () {
    'use strict';

    var NS = 'wcai-checkout';
    var ROOT = 'wcai-checkout-wizard';
    var state = { items: [] };
    var signature = '';
    var currentStep = 1;
    var validationIds = [];

    function cart() {
        if (!window.wp || !wp.data) return null;
        var store = wp.data.select('wc/store/cart');
        return store && typeof store.getCartData === 'function' ? store.getCartData() : null;
    }

    function sync() {
        if (!wp.data) return;

        var checkout = wp.data.dispatch('wc/store/checkout');
        var payload = {
            items: state.items.map(function (item) {
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
        var data = JSON.stringify(payload);

        if (checkout && typeof checkout.setExtensionData === 'function') {
            checkout.setExtensionData(NS, { data: data });
        } else if (checkout && typeof checkout.__internalSetExtensionData === 'function') {
            checkout.__internalSetExtensionData(NS, { data: data });
        }
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

    function inputField(label, value, placeholder, mask, onChange) {
        var wrap = document.createElement('div');
        wrap.className = 'wcai-wizard-field';

        var labelEl = document.createElement('label');
        labelEl.textContent = label;
        wrap.appendChild(labelEl);

        var input = document.createElement('input');
        input.type = 'text';
        input.value = value || '';
        input.placeholder = placeholder || '';
        input.autocomplete = 'off';

        input.addEventListener('input', function () {
            var valueNow = mask ? mask(input.value) : input.value;
            input.value = valueNow;
            onChange(valueNow);
            sync();
            updateLocalValidation();
        });

        wrap.appendChild(input);

        return wrap;
    }

    function selectField(label, value, options, onChange) {
        var wrap = document.createElement('div');
        wrap.className = 'wcai-wizard-field';

        var labelEl = document.createElement('label');
        labelEl.textContent = label;
        wrap.appendChild(labelEl);

        var select = document.createElement('select');
        options.forEach(function (option) {
            var optionEl = document.createElement('option');
            optionEl.value = option.value;
            optionEl.textContent = option.label;
            if (String(option.value) === String(value)) {
                optionEl.selected = true;
            }
            select.appendChild(optionEl);
        });

        select.addEventListener('change', function () {
            onChange(select.value);
            sync();
            updateLocalValidation();
        });

        wrap.appendChild(select);
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
        old.quantity = quantity;
        old.adults = adults;
        old.children = children;

        if (!old.departure_id && ext.departure_id) {
            old.departure_id = String(ext.departure_id);
        }

        if (!old.departure_label && ext.departure_label) {
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

    function findSectionByTerms(terms) {
        var headings = Array.prototype.slice.call(document.querySelectorAll('h1,h2,h3,h4,h5,legend'));
        var heading = headings.filter(function (element) {
            var text = (element.textContent || '').trim().toLowerCase();
            return terms.some(function (term) {
                return text.indexOf(term.toLowerCase()) !== -1;
            });
        })[0];

        if (!heading) return null;

        var node = heading;
        var guard = 0;

        while (node && node.parentElement && guard < 8) {
            if (
                node.matches &&
                (
                    node.matches('[class*="checkout-step"]')
                    || node.matches('fieldset')
                    || node.matches('section')
                )
            ) {
                return node;
            }

            node = node.parentElement;
            guard++;
        }

        return heading.parentElement;
    }

    function firstExisting(selectors) {
        for (var i = 0; i < selectors.length; i++) {
            var node = document.querySelector(selectors[i]);
            if (node) return node;
        }
        return null;
    }

    function nativeSections() {
        return {
            contact: firstExisting([
                '[data-block-name="woocommerce/checkout-contact-information-block"]',
                '.wc-block-checkout__contact-fields'
            ]),
            billing: firstExisting([
                '[data-block-name="woocommerce/checkout-billing-address-block"]',
                '.wc-block-checkout__billing-fields'
            ]),
            shippingAddress: firstExisting([
                '[data-block-name="woocommerce/checkout-shipping-address-block"]'
            ]),
            shippingMethods: firstExisting([
                '[data-block-name="woocommerce/checkout-shipping-methods-block"]'
            ]),
            shippingMethod: firstExisting([
                '[data-block-name="woocommerce/checkout-shipping-method-block"]'
            ]),
            pickup: firstExisting([
                '[data-block-name="woocommerce/checkout-pickup-options-block"]'
            ]),
            payment: firstExisting([
                '[data-block-name="woocommerce/checkout-payment-block"]',
                '.wc-block-components-checkout-payment-methods',
                '.wc-block-checkout__payment-method',
                '.wc-block-checkout__payment-methods'
            ]),
            terms: firstExisting([
                '[data-block-name="woocommerce/checkout-terms-block"]'
            ]),
            actions: firstExisting([
                '[data-block-name="woocommerce/checkout-actions-block"]'
            ]),
            orderNote: firstExisting([
                '[data-block-name="woocommerce/checkout-order-note-block"]'
            ]),
            express: firstExisting([
                '[data-block-name="woocommerce/checkout-express-payment-block"]'
            ])
        };
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

    function placeOrderButtons() {
        return Array.prototype.slice.call(
            document.querySelectorAll(
                '.wc-block-components-checkout-place-order-button,' +
                '#place_order,' +
                'button[name="woocommerce_checkout_place_order"]'
            )
        );
    }

    function isPlaceOrderElement(target) {
        return !!(
            target &&
            target.closest &&
            target.closest(
                '.wc-block-components-checkout-place-order-button,' +
                '#place_order,' +
                'button[name="woocommerce_checkout_place_order"]'
            )
        );
    }

    function setCheckoutStageClass() {
        if (!document.body) return;

        document.body.classList.toggle('wcai-checkout-before-payment', currentStep !== 5);
        document.body.classList.toggle('wcai-checkout-payment-step', currentStep === 5);
    }

    function ensureNativeStepControls( sections ) {
        if ( sections.contact ) {
            var contactHeader = document.querySelector('.wcai-native-stage-header[data-wcai-native-stage="2"]');

            if (!contactHeader) {
                contactHeader = document.createElement('div');
                contactHeader.className = 'wcai-native-stage-header';
                contactHeader.setAttribute('data-wcai-native-stage', '2');
                contactHeader.innerHTML =
                    '<span class="wcai-native-stage-number">2</span>' +
                    '<div><strong>Seus dados</strong><small>Informe os dados do titular. Esses dados serão usados na reserva.</small></div>';
            }

            if ( contactHeader.nextElementSibling !== sections.contact ) {
                sections.contact.insertAdjacentElement('beforebegin', contactHeader);
            }

            var next = document.querySelector('.wcai-native-next');

            if (!next) {
                next = document.createElement('div');
                next.className = 'wcai-native-next wcai-native-stage-actions';
                next.innerHTML =
                    '<button type="button" class="button wcai-native-back-to-reservation">Voltar para reserva</button>' +
                    '<button type="button" class="button wcai-native-next-button">Continuar para participantes</button>';

                next.querySelector('.wcai-native-back-to-reservation').addEventListener('click', function () {
                    setStep(1);
                });

                next.querySelector('.wcai-native-next-button').addEventListener('click', function () {
                    if (validateBillingStep()) {
                        setStep(3);
                    }
                });
            }

            if ( next.previousElementSibling !== sections.billing ) {
                sections.billing.insertAdjacentElement('afterend', next);
            }
        }

        if ( sections.payment ) {
            var paymentHeader = document.querySelector('.wcai-native-stage-header[data-wcai-native-stage="5"]');

            if (!paymentHeader) {
                paymentHeader = document.createElement('div');
                paymentHeader.className = 'wcai-native-stage-header wcai-payment-stage-header';
                paymentHeader.setAttribute('data-wcai-native-stage', '5');
                paymentHeader.innerHTML =
                    '<span class="wcai-native-stage-number">5</span>' +
                    '<div><strong>Pagamento</strong><small>Escolha a forma de pagamento e finalize sua reserva.</small></div>';
            }

            if ( paymentHeader.nextElementSibling !== sections.payment ) {
                sections.payment.insertAdjacentElement('beforebegin', paymentHeader);
            }

            var back = document.querySelector('.wcai-native-back');

            if (!back) {
                back = document.createElement('div');
                back.className = 'wcai-native-back';
                back.innerHTML = '<button type="button" class="wcai-native-back-button">Voltar para revisão</button>';
                back.querySelector('button').addEventListener('click', function () {
                    setStep(4);
                });
            }

            if ( back.nextElementSibling !== sections.payment ) {
                sections.payment.insertAdjacentElement('beforebegin', back);
            }
        }
    }
    function applyNativeStepVisibility() {
        var sections = nativeSections();

        // O WooAdventure usa os blocos nativos apenas como conteúdo das etapas
        // "Seus dados" e "Pagamento". Os demais blocos permanecem fora do fluxo.
        ensureNativeStepControls( sections );
        setCheckoutStageClass();

        setHidden(sections.express, true);
        setHidden(sections.shippingAddress, true);
        setHidden(sections.shippingMethods, true);
        setHidden(sections.shippingMethod, true);
        setHidden(sections.pickup, true);

        setHidden(sections.contact, currentStep !== 2);
        setHidden(sections.billing, currentStep !== 2);
        setHidden(sections.orderNote, currentStep !== 4);
        setHidden(sections.terms, currentStep !== 5);
        setHidden(sections.payment, currentStep !== 5);
        setHidden(sections.actions, currentStep !== 5);

        var nativeNext = document.querySelector('.wcai-native-next');
        var nativeBack = document.querySelector('.wcai-native-back');
        var stage2Header = document.querySelector('.wcai-native-stage-header[data-wcai-native-stage="2"]');
        var stage5Header = document.querySelector('.wcai-native-stage-header[data-wcai-native-stage="5"]');

        setHidden(stage2Header, currentStep !== 2);
        setHidden(nativeNext, currentStep !== 2);
        setHidden(nativeBack, currentStep !== 5);
        setHidden(stage5Header, currentStep !== 5);

        placeOrderButtons().forEach(function (button) {
            if ( currentStep !== 5 ) {
                button.hidden = true;
                button.setAttribute('aria-hidden', 'true');
                button.setAttribute('tabindex', '-1');
                button.style.setProperty('display', 'none', 'important');
            } else {
                button.hidden = false;
                button.removeAttribute('aria-hidden');
                button.removeAttribute('tabindex');
                button.style.removeProperty('display');
            }
        });
    }

    function styleWizard() {
        if (document.getElementById('wcai-wizard-style')) return;

        var style = document.createElement('style');
        style.id = 'wcai-wizard-style';
        style.textContent =
            '#wcai-checkout-wizard{margin:0 0 14px;padding:0;border:0;background:transparent}' +
            '#wcai-checkout-wizard .wcai-wizard-progress{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:4px;margin:0 0 12px}' +
            '#wcai-checkout-wizard .wcai-wizard-progress span{display:flex;align-items:center;justify-content:center;min-height:32px;padding:5px 6px;border:1px solid #e2e2e2;border-radius:8px;background:#fff;color:#777;font-size:10px;text-align:center;cursor:default;box-sizing:border-box}' +
            '#wcai-checkout-wizard .wcai-wizard-progress span.is-active{border-color:#222;background:#222;color:#fff;font-weight:800}' +
            '#wcai-checkout-wizard .wcai-wizard-progress span.is-complete{color:#222;border-color:#cfcfcf;cursor:pointer}' +
            '#wcai-checkout-wizard .wcai-wizard-progress span.is-complete:before{content:"✓";margin-right:4px;font-weight:900}' +
            '#wcai-checkout-wizard .wcai-wizard-panel{display:none;padding:14px;border:1px solid #e5e5e5;border-radius:12px;background:#fff;box-shadow:0 5px 18px rgba(0,0,0,.035)}' +
            '#wcai-checkout-wizard .wcai-wizard-panel.is-active{display:block}' +
            '#wcai-checkout-wizard h3{margin:0 0 7px;font-size:17px}' +
            '#wcai-checkout-wizard h4{margin:0 0 9px;font-size:14px}' +
            '#wcai-checkout-wizard h5{margin:0 0 9px;font-size:13px}' +
            '#wcai-checkout-wizard p{font-size:12px;line-height:1.5;color:#666}' +
            '#wcai-checkout-wizard .wcai-wizard-item{padding:12px;margin:0 0 10px;border:1px solid #e5e5e5;border-radius:10px;background:#fff}' +
            '#wcai-checkout-wizard .wcai-wizard-person{padding:11px;margin:9px 0;border:1px solid #eee;border-radius:9px;background:#fafafa}' +
            '#wcai-checkout-wizard .wcai-wizard-field{margin:0 0 9px}' +
            '#wcai-checkout-wizard .wcai-wizard-field label{display:block;font-weight:700;margin-bottom:4px;font-size:11px}' +
            '#wcai-checkout-wizard .wcai-wizard-field input,#wcai-checkout-wizard .wcai-wizard-field select{width:100%;padding:8px 9px;box-sizing:border-box;border:1px solid #d9d9d9;border-radius:7px;background:#fff}' +
            '#wcai-checkout-wizard .wcai-wizard-actions{display:flex;justify-content:space-between;gap:9px;margin-top:14px;padding-top:11px;border-top:1px solid #eee}' +
            '#wcai-checkout-wizard .wcai-wizard-actions button{min-height:38px;padding:8px 14px;border:1px solid #222;border-radius:8px;cursor:pointer}' +
            '#wcai-checkout-wizard .wcai-wizard-actions button:not(.wcai-wizard-secondary){background:#222;color:#fff}' +
            '#wcai-checkout-wizard .wcai-wizard-actions .wcai-wizard-secondary{background:#fff;color:#222}' +
            '#wcai-checkout-wizard .wcai-wizard-error{color:#b32d2e;font-size:12px;margin-top:5px}' +
            '#wcai-checkout-wizard .wcai-reservation-summary{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:10px 0}' +
            '#wcai-checkout-wizard .wcai-reservation-summary>div{padding:9px 10px;border:1px solid #ededed;border-radius:8px;background:#fafafa}' +
            '#wcai-checkout-wizard .wcai-reservation-summary span{display:block;font-size:8px;text-transform:uppercase;letter-spacing:.06em;color:#888}' +
            '#wcai-checkout-wizard .wcai-reservation-summary strong{display:block;margin-top:3px;font-size:12px}' +
            '#wcai-checkout-wizard .wcai-warning{padding:10px 11px;border:1px solid #e4d6a2;border-radius:8px;background:#fffaf0;color:#695b2e;font-size:12px}' +
            '.wcai-native-stage-header{display:flex;align-items:flex-start;gap:10px;margin:0 0 10px;padding:12px 13px;border:1px solid #e5e5e5;border-radius:11px;background:#fff;box-shadow:0 4px 14px rgba(0,0,0,.025)}' +
            '.wcai-native-stage-number{display:flex;align-items:center;justify-content:center;width:26px;height:26px;flex:0 0 26px;border-radius:50%;background:#222;color:#fff;font-size:11px;font-weight:800}' +
            '.wcai-native-stage-header strong{display:block;font-size:15px}' +
            '.wcai-native-stage-header small{display:block;margin-top:2px;font-size:10px;color:#777;line-height:1.4}' +
            '.wcai-native-stage-actions{display:flex;justify-content:space-between;gap:8px;align-items:center;margin:10px 0 14px;padding:10px 0;border-top:1px solid #eee}' +
            '.wcai-native-stage-actions button{min-height:38px;padding:8px 13px;border:0;border-radius:7px;cursor:pointer;background:transparent;color:#555;font-weight:600}' +
            '.wcai-native-stage-actions .wcai-native-next-button{background:#222;color:#fff}' +
            '.wcai-native-back{margin:9px 0;padding:0;text-align:left}' +
            '.wcai-native-back-button{padding:5px 0;border:0;background:transparent;color:#666;font-size:11px;cursor:pointer;text-decoration:underline}' +
            '.wcai-checkout-before-payment [data-block-name="woocommerce/checkout-actions-block"]{display:none!important}' +
            '.wcai-checkout-before-payment .wc-block-components-checkout-place-order-button,.wcai-checkout-before-payment #place_order,.wcai-checkout-before-payment button[name="woocommerce_checkout_place_order"]{display:none!important;visibility:hidden!important;pointer-events:none!important}' +
            '@media(max-width:700px){#wcai-checkout-wizard .wcai-wizard-progress{grid-template-columns:repeat(5,minmax(0,1fr));gap:3px}#wcai-checkout-wizard .wcai-wizard-progress span{min-height:29px;padding:4px 3px;font-size:8px}#wcai-checkout-wizard .wcai-reservation-summary{grid-template-columns:1fr}.wcai-native-stage-actions{display:flex;gap:6px}.wcai-native-stage-actions button{width:auto}.wcai-native-stage-actions .wcai-native-next-button{flex:1}.wcai-native-stage-actions .wcai-native-back-to-reservation{padding-left:0}}';

        document.head.appendChild(style);
    }

    function validateAdditional() {
        var errors = {};

        state.items.forEach(function (item, itemIndex) {
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

                if (!participant.cpf) {
                    addError(errors, 'wcai-cpf-' + itemIndex + '-' + index, 'Informe o CPF do participante ' + number + '.');
                }

                if (!participant.birthdate) {
                    addError(errors, 'wcai-birth-' + itemIndex + '-' + index, 'Informe a data de nascimento do participante ' + number + '.');
                }
            });

            if (additionalAdults !== Math.max(0, item.adults - 1) || additionalChildren !== item.children) {
                addError(errors, 'wcai-category-count-' + itemIndex, 'A distribuição entre adultos e crianças não corresponde à reserva escolhida.');
            }
        });

        setValidationErrors(errors);
        return Object.keys(errors).length === 0;
    }

    function validateDeparture() {
        return state.items.every(function (item) {
            return !!item.departure_id;
        });
    }

    function readBillingCustomFields() {
        var cpf = document.querySelector('input[name="billing_cpf"]');
        var birthdate = document.querySelector('input[name="billing_birthdate"]');

        return {
            cpf: cpf ? cpf.value.trim() : '',
            birthdate: birthdate ? birthdate.value.trim() : '',
        };
    }

    function firstMissingRequiredField(section) {
        if (!section) return null;

        var fields = Array.prototype.slice.call(
            section.querySelectorAll('input,select,textarea')
        );

        return fields.filter(function (field) {
            if (!field || field.disabled) return false;

            var style = window.getComputedStyle(field);
            if (style.display === 'none' || style.visibility === 'hidden') return false;

            return field.required || field.getAttribute('aria-required') === 'true';
        }).filter(function (field) {
            return !String(field.value || '').trim();
        })[0] || null;
    }

    function validateBillingStep() {
        var billing = readBillingCustomFields();
        var customCpf = document.querySelector('input[name="billing_cpf"]');
        var customBirthdate = document.querySelector('input[name="billing_birthdate"]');
        var sections = nativeSections();

        if (customCpf && !billing.cpf) {
            customCpf.scrollIntoView({ behavior: 'smooth', block: 'center' });
            customCpf.focus();
            return false;
        }

        if (customBirthdate && !billing.birthdate) {
            customBirthdate.scrollIntoView({ behavior: 'smooth', block: 'center' });
            customBirthdate.focus();
            return false;
        }

        var missing = firstMissingRequiredField(sections.billing) || firstMissingRequiredField(sections.contact);

        if (missing) {
            missing.scrollIntoView({ behavior: 'smooth', block: 'center' });
            missing.focus();
            return false;
        }

        return true;
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
        if (secondary) buttonEl.className = 'wcai-wizard-secondary';
        buttonEl.addEventListener('click', onClick);
        return buttonEl;
    }

    function actions(nextText, nextHandler, previousHandler) {
        var wrap = document.createElement('div');
        wrap.className = 'wcai-wizard-actions';

        if (previousHandler) {
            wrap.appendChild(button('Voltar', previousHandler, true));
        }

        if (nextHandler) {
            wrap.appendChild(button(nextText || 'Continuar', nextHandler, false));
        }

        return wrap;
    }

    function setStep(step) {
        currentStep = Math.max(1, Math.min(5, step));

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
            label.setAttribute('aria-current', labelStep === currentStep ? 'step' : 'false');
            label.setAttribute('tabindex', labelStep < currentStep ? '0' : '-1');
        });

        applyNativeStepVisibility();

        if (currentStep !== 5) {
            placeOrderButtons().forEach(function (button) {
                button.hidden = true;
                button.style.setProperty('display', 'none', 'important');
            });
        }

        if (currentStep === 3) {
            validateAdditional();
        }

        if (currentStep === 2) {
            setTimeout(function () {
                var header = document.querySelector('.wcai-native-stage-header[data-wcai-native-stage="2"]');
                if (header) {
                    header.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 120);
        } else if (currentStep === 5) {
            setTimeout(function () {
                var header = document.querySelector('.wcai-native-stage-header[data-wcai-native-stage="5"]');
                if (header) {
                    header.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 120);
        } else {
            root.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function buildStepOne(panel) {
        panel.innerHTML = '';

        var title = document.createElement('h3');
        title.textContent = '1. Reserva';
        panel.appendChild(title);

        state.items.forEach(function (item) {
            var box = document.createElement('div');
            box.className = 'wcai-wizard-item';

            var name = document.createElement('strong');
            name.textContent = item.name || 'Ingresso';
            box.appendChild(name);

            var summary = document.createElement('div');
            summary.className = 'wcai-reservation-summary';

            var departureBox = document.createElement('div');
            var departureLabel = document.createElement('span');
            departureLabel.textContent = 'Saída';
            departureBox.appendChild(departureLabel);
            var departureValue = document.createElement('strong');
            departureValue.textContent = item.departure_label || 'Data e horário não selecionados';
            departureBox.appendChild(departureValue);
            summary.appendChild(departureBox);

            var peopleBox = document.createElement('div');
            var peopleLabel = document.createElement('span');
            peopleLabel.textContent = 'Participantes';
            peopleBox.appendChild(peopleLabel);
            var peopleValue = document.createElement('strong');
            peopleValue.textContent = item.adults + ' adulto' + (item.adults === 1 ? '' : 's') + (item.children ? ' + ' + item.children + ' criança' + (item.children === 1 ? '' : 's') : '');
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

        panel.appendChild(
            actions('Continuar para seus dados', function () {
                if (!validateDeparture()) return;
                setStep(2);
            })
        );
    }

    function buildStepThree(panel) {
        panel.innerHTML = '';

        var title = document.createElement('h3');
        title.textContent = 'Dados dos demais participantes';
        panel.appendChild(title);

        var text = document.createElement('p');
        text.textContent = 'O titular é considerado o primeiro adulto. Confira a categoria de cada participante adicional para manter a reserva correta.';
        panel.appendChild(text);

        var hasAdditional = false;

        state.items.forEach(function (item, itemIndex) {
            if (!item.additional_participants.length) return;

            hasAdditional = true;

            var itemBox = document.createElement('div');
            itemBox.className = 'wcai-wizard-item';

            var heading = document.createElement('h4');
            heading.textContent = item.name + ' — ' + item.additional_participants.length + ' participante(s) adicional(is)';
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
                    })
                );

                personBox.appendChild(
                    inputField('Nome completo', person.name, 'Nome completo', null, function (value) {
                        person.name = value;
                    })
                );

                personBox.appendChild(
                    inputField('CPF', person.cpf, '000.000.000-00', cpfMask, function (value) {
                        person.cpf = value;
                    })
                );

                personBox.appendChild(
                    inputField('Data de nascimento', person.birthdate, 'dd/mm/aaaa', dateMask, function (value) {
                        person.birthdate = value;
                    })
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

        panel.appendChild(
            actions('Continuar', function () {
                if (!validateAdditional()) return;
                buildStepFour(document.querySelector('#' + ROOT + ' [data-wcai-step="4"]'));
                setStep(4);
            }, function () {
                setStep(2);
            })
        );
    }

    function buildStepFour(panel) {
        panel.innerHTML = '';

        var title = document.createElement('h3');
        title.textContent = 'Revise sua reserva';
        panel.appendChild(title);

        state.items.forEach(function (item) {
            var box = document.createElement('div');
            box.className = 'wcai-wizard-item';

            var h = document.createElement('strong');
            h.textContent = item.name + ' — ' + item.quantity + ' participante(s)';
            box.appendChild(h);

            var date = document.createElement('p');
            date.textContent = 'Saída: ' + (item.departure_label || 'não selecionada');
            box.appendChild(date);

            var participants = document.createElement('p');
            participants.textContent = item.adults + ' adulto' + (item.adults === 1 ? '' : 's') + (item.children ? ' + ' + item.children + ' criança' + (item.children === 1 ? '' : 's') : '') + ' · ' + item.quantity + ' participante' + (item.quantity === 1 ? '' : 's');
            box.appendChild(participants);

            panel.appendChild(box);
        });

        var note = document.createElement('p');
        note.textContent = 'No próximo passo o pagamento será exibido pelo WooCommerce. O titular já foi identificado pelos dados de faturamento.';
        panel.appendChild(note);

        panel.appendChild(
            actions('Ir para pagamento', function () {
                setStep(5);
            }, function () {
                setStep(3);
            })
        );
    }

    function render() {
        var root = document.getElementById(ROOT);
        var data = cart();

        if (!root || !data || !Array.isArray(data.items)) return;

        var targets = data.items.filter(function (item) {
            return item.extensions && item.extensions[NS] && item.extensions[NS].enabled;
        });

        state.items = targets.map(itemState);

        if (!targets.length) {
            root.style.display = 'none';
            setValidationErrors({});
            return;
        }

        root.style.display = '';

        styleWizard();
        buildStepOne(root.querySelector('[data-wcai-step="1"]'));
        buildStepThree(root.querySelector('[data-wcai-step="3"]'));
        buildStepFour(root.querySelector('[data-wcai-step="4"]'));
        sync();
        setStep(currentStep);
    }

    function sig(data) {
        if (!data || !Array.isArray(data.items)) return '';

        return data.items.map(function (item) {
            var ext = item.extensions && item.extensions[NS];
            if (!ext) return 'x:' + (item.key || item.id);

            return [
                item.key || item.id,
                ext.product_id,
                ext.variation_id,
                ext.quantity,
                ext.departure_id,
                JSON.stringify(ext.departures || [])
            ].join('|');
        }).join('||');
    }

    function boot() {
        if (!document.getElementById(ROOT) || !window.wp || !wp.data) return;

        function update() {
            var data = cart();
            var next = sig(data);

            if (next !== signature) {
                signature = next;
                render();
            }
        }

        update();
        wp.data.subscribe(update, 'wc/store/cart');

        var visibilityTimer = null;
        var observer = new MutationObserver(function () {
            if ( visibilityTimer ) {
                clearTimeout(visibilityTimer);
            }

            visibilityTimer = setTimeout(function () {
                applyNativeStepVisibility();
            }, 60);
        });

        var observeTarget = document.querySelector('.wc-block-checkout') || document.body;
        observer.observe(observeTarget, { childList: true, subtree: true });

        Array.prototype.slice.call(document.querySelectorAll('[data-step-label]')).forEach(function (label) {
            label.addEventListener('click', function () {
                var targetStep = parseInt(label.getAttribute('data-step-label'), 10);

                if (targetStep < currentStep) {
                    setStep(targetStep);
                }
            });
        });

        document.addEventListener('click', function (event) {
            if (currentStep !== 5 && isPlaceOrderElement(event.target)) {
                event.preventDefault();
                event.stopImmediatePropagation();
                return false;
            }
        }, true);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());

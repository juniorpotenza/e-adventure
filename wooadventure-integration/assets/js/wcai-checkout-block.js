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
                    additional_participants: item.additional_participants
                };
            })
        };
        var data = JSON.stringify(payload);

        if (checkout && typeof checkout.setExtensionData === 'function') {
            checkout.setExtensionData(NS, 'data', data);
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

    function itemState(item) {
        var ext = item.extensions && item.extensions[NS] ? item.extensions[NS] : {};
        var key = String(item.key || item.id || (ext.product_id + ':' + ext.variation_id));
        var old = state.items.filter(function (entry) {
            return entry.key === key;
        })[0];

        var quantity = parseInt(ext.quantity || item.quantity || 1, 10) || 1;
        var additionalCount = Math.max(0, quantity - 1);

        old = old || {
            key: key,
            name: item.name || 'Ingresso',
            product_id: ext.product_id,
            variation_id: ext.variation_id,
            departure_id: ext.departure_id || '',
            departure_label: ext.departure_label || '',
            quantity: quantity,
            additional_participants: []
        };

        old.name = item.name || old.name || 'Ingresso';
        old.quantity = quantity;

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
            ]) || findSectionByTerms(['informações de contato', 'contact information']),
            billing: firstExisting([
                '[data-block-name="woocommerce/checkout-billing-address-block"]',
                '.wc-block-checkout__billing-fields'
            ]) || findSectionByTerms(['endereço de cobrança', 'billing address']),
            shipping: firstExisting([
                '[data-block-name="woocommerce/checkout-shipping-address-block"]'
            ]),
            payment: firstExisting([
                '[data-block-name="woocommerce/checkout-payment-block"]',
                '.wc-block-checkout__payment-method',
                '.wc-block-checkout__payment-methods',
                '.wc-block-components-checkout-payment-methods',
                '[class*="checkout-payment"]'
            ]) || findSectionByTerms(['pagamento', 'payment']),
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
        var candidates = Array.prototype.slice.call(document.querySelectorAll('button,a'));
        return candidates.filter(function (element) {
            var text = (element.textContent || '').trim().toLowerCase();
            return text.indexOf('fazer pedido') !== -1 || text.indexOf('place order') !== -1;
        });
    }

    function ensureNativeStepControls( sections ) {
        if ( sections.billing && !sections.billing.nextElementSibling && sections.billing.nextElementSibling.classList.contains('wcai-native-next') ) {
            var next = document.createElement('div');
            next.className = 'wcai-native-next';
            next.innerHTML = '<button type="button" class="button wcai-native-next-button">Continuar para participantes</button>';
            next.querySelector('button').addEventListener('click', function () {
                if (validateBillingStep()) {
                    setStep(3);
                }
            });
            sections.billing.insertAdjacentElement('afterend', next);
        }

        if ( sections.payment && !sections.payment.nextElementSibling && sections.payment.nextElementSibling.classList.contains('wcai-native-back') ) {
            var back = document.createElement('div');
            back.className = 'wcai-native-back';
            back.innerHTML = '<button type="button" class="button wcai-native-back-button">Voltar para revisão</button>';
            back.querySelector('button').addEventListener('click', function () {
                setStep(4);
            });
            sections.payment.insertAdjacentElement('beforebegin', back);
        }
    }

    function applyNativeStepVisibility() {
        var sections = nativeSections();

        // A etapa visual do WooAdventure controla a ordem de apresentação.
        // O Checkout Block continua sendo o responsável pelos campos e pelo pagamento.
        ensureNativeStepControls( sections );
        setHidden(sections.express, true);
        setHidden(sections.shipping, true);
        setHidden(sections.contact, currentStep !== 2);
        setHidden(sections.billing, currentStep !== 2);
        setHidden(sections.orderNote, currentStep !== 4);
        setHidden(sections.terms, currentStep !== 5);
        setHidden(sections.payment, currentStep !== 5);
        setHidden(sections.actions, currentStep !== 5);

        var nativeNext = document.querySelector('.wcai-native-next');
        var nativeBack = document.querySelector('.wcai-native-back');
        setHidden(nativeNext, currentStep !== 2);
        setHidden(nativeBack, currentStep !== 5);

        placeOrderButtons().forEach(function (button) {
            button.hidden = currentStep !== 5;
        });
    }

    function styleWizard() {
        if (document.getElementById('wcai-wizard-style')) return;

        var style = document.createElement('style');
        style.id = 'wcai-wizard-style';
        style.textContent =
            '#wcai-checkout-wizard{margin:0 0 28px;padding:20px;border:1px solid #ddd;border-radius:8px;background:#fff}' +
            '#wcai-checkout-wizard .wcai-wizard-progress{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px}' +
            '#wcai-checkout-wizard .wcai-wizard-progress span{padding:8px 10px;border-radius:999px;background:#f1f1f1;font-size:13px}' +
            '#wcai-checkout-wizard .wcai-wizard-progress span.is-active{font-weight:700;background:#222;color:#fff}' +
            '#wcai-checkout-wizard .wcai-wizard-panel{display:none}' +
            '#wcai-checkout-wizard .wcai-wizard-panel.is-active{display:block}' +
            '#wcai-checkout-wizard + .wp-block-woocommerce-checkout-fields-block{margin-top:0}' +
            '#wcai-checkout-wizard .wcai-wizard-item{padding:16px;margin:0 0 16px;border:1px solid #e2e2e2;border-radius:6px}' +
            '#wcai-checkout-wizard .wcai-wizard-person{padding:14px;margin:12px 0;border:1px solid #eee;border-radius:6px;background:#fafafa}' +
            '#wcai-checkout-wizard .wcai-wizard-field{margin:0 0 12px}' +
            '#wcai-checkout-wizard .wcai-wizard-field label{display:block;font-weight:600;margin-bottom:6px}' +
            '#wcai-checkout-wizard .wcai-wizard-field input,#wcai-checkout-wizard select{width:100%;padding:10px;box-sizing:border-box}' +
            '#wcai-checkout-wizard .wcai-wizard-actions{display:flex;gap:10px;justify-content:flex-end;margin-top:20px}' +
            '#wcai-checkout-wizard .wcai-wizard-actions button{padding:10px 16px;cursor:pointer}' +
            '#wcai-checkout-wizard .wcai-wizard-error{color:#b32d2e;font-size:13px;margin-top:5px}' +
            '#wcai-checkout-wizard .wcai-wizard-summary-row{display:flex;justify-content:space-between;gap:16px;padding:8px 0;border-bottom:1px solid #eee}' +
            '.wcai-native-next,.wcai-native-back{margin:14px 0;padding:12px 0;border:1px solid #eee;border-radius:8px;background:#fafafa;text-align:right}' +
            '.wcai-native-next-button,.wcai-native-back-button{margin-right:12px;padding:9px 14px;cursor:pointer}' +
            '[aria-hidden="true"] .wcai-native-next-button,[aria-hidden="true"] .wcai-native-back-button{display:none}' +
            '@media(max-width:600px){#wcai-checkout-wizard .wcai-wizard-summary-row{display:block}.wcai-wizard-progress{font-size:12px}.wcai-native-next,.wcai-native-back{text-align:stretch}.wcai-native-next-button,.wcai-native-back-button{width:100%;margin:0}}';

        document.head.appendChild(style);
    }

    function validateAdditional() {
        var errors = {};

        state.items.forEach(function (item, itemIndex) {
            item.additional_participants.forEach(function (participant, index) {
                var number = index + 2;

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

    function validateBillingStep() {
        var billing = readBillingCustomFields();

        if (billing.cpf && billing.birthdate) {
            return true;
        }

        var sections = nativeSections();
        if (sections.billing) {
            sections.billing.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        return false;
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
            label.classList.toggle('is-active', parseInt(label.getAttribute('data-step-label'), 10) === currentStep);
        });

        applyNativeStepVisibility();

        if (currentStep === 3) {
            validateAdditional();
        }

        if (currentStep === 2) {
            setTimeout(function () {
                var sections = nativeSections();
                if (sections.contact) {
                    sections.contact.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 120);
        } else if (currentStep === 5) {
            setTimeout(function () {
                var sections = nativeSections();
                if (sections.payment) {
                    sections.payment.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }, 120);
        } else {
            root.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function buildStepOne(panel) {
        panel.innerHTML = '';

        var title = document.createElement('h3');
        title.textContent = '1. Confira sua reserva';
        panel.appendChild(title);

        state.items.forEach(function (item) {
            var box = document.createElement('div');
            box.className = 'wcai-wizard-item';

            var name = document.createElement('strong');
            name.textContent = item.name || 'Ingresso';
            box.appendChild(name);

            var row = document.createElement('div');
            row.className = 'wcai-wizard-summary-row';

            var label = document.createElement('span');
            label.textContent = 'Saída';
            row.appendChild(label);

            if (item.departure_label) {
                var value = document.createElement('strong');
                value.textContent = item.departure_label;
                row.appendChild(value);
            } else {
                var select = document.createElement('select');
                var blank = document.createElement('option');
                blank.value = '';
                blank.textContent = 'Selecione uma data e horário';
                select.appendChild(blank);

                var ext = item.extensions || {};
                var dataItem = cart().items.filter(function (cartItem) {
                    return String(cartItem.key || cartItem.id) === String(item.key);
                })[0];

                var departures = dataItem && dataItem.extensions && dataItem.extensions[NS] ? dataItem.extensions[NS].departures : [];

                (departures || []).forEach(function (departure) {
                    var option = document.createElement('option');
                    option.value = departure.id;
                    option.textContent = departure.label + ' — ' + departure.available + ' vaga' + (departure.available === 1 ? '' : 's');
                    select.appendChild(option);
                });

                select.addEventListener('change', function () {
                    item.departure_id = select.value;
                    var selected = departures.filter(function (entry) {
                        return String(entry.id) === String(select.value);
                    })[0];
                    item.departure_label = selected ? selected.label : '';
                    sync();
                    buildStepOne(panel);
                });

                row.appendChild(select);
            }

            box.appendChild(row);

            var qtyRow = document.createElement('div');
            qtyRow.className = 'wcai-wizard-summary-row';
            qtyRow.innerHTML = '<span>Participantes</span><strong>' + item.quantity + '</strong>';
            box.appendChild(qtyRow);

            panel.appendChild(box);
        });

        panel.appendChild(
            actions('Continuar', function () {
                if (!validateDeparture()) return;
                setStep(2);
            })
        );
    }

    function buildStepTwo(panel) {
        panel.innerHTML = '';

        var title = document.createElement('h3');
        title.textContent = '2. Seus dados';
        panel.appendChild(title);

        var text = document.createElement('p');
        text.textContent = 'O titular já é identificado pelos dados de faturamento. Preencha nome, contato, CPF e data de nascimento no bloco de faturamento abaixo.';
        panel.appendChild(text);

        panel.appendChild(
            actions('Continuar', function () {
                if (!validateBillingStep()) return;
                setStep(3);
            }, function () {
                setStep(1);
            })
        );
    }

    function buildStepThree(panel) {
        panel.innerHTML = '';

        var title = document.createElement('h3');
        title.textContent = '3. Participantes adicionais';
        panel.appendChild(title);

        var hasAdditional = false;

        state.items.forEach(function (item) {
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
            none.textContent = 'Como sua reserva possui apenas um participante, não há dados adicionais para preencher.';
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
        title.textContent = '4. Revise sua reserva';
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

            var additional = document.createElement('p');
            additional.textContent = 'Participantes adicionais: ' + item.additional_participants.length;
            box.appendChild(additional);

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

    function buildStepFive(panel) {
        panel.innerHTML = '';

        var title = document.createElement('h3');
        title.textContent = '5. Pagamento';
        panel.appendChild(title);

        var note = document.createElement('p');
        note.textContent = 'Confira a forma de pagamento abaixo e finalize seu pedido.';
        panel.appendChild(note);

        panel.appendChild(
            actions(null, null, function () {
                setStep(4);
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
        buildStepTwo(root.querySelector('[data-wcai-step="2"]'));
        buildStepThree(root.querySelector('[data-wcai-step="3"]'));
        buildStepFour(root.querySelector('[data-wcai-step="4"]'));
        buildStepFive(root.querySelector('[data-wcai-step="5"]'));
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

        var observer = new MutationObserver(function () {
            applyNativeStepVisibility();
        });

        observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['hidden'] });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());

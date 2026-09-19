(function () {
    'use strict';

    var NS = 'wcai-checkout';
    var ROOT = 'wcai-checkout-integration';
    var state = { items: [] };
    var signature = '';

    function cart() {
        if (!window.wp || !wp.data) return null;
        var store = wp.data.select('wc/store/cart');
        return store && typeof store.getCartData === 'function' ? store.getCartData() : null;
    }

    function sync() {
        if (!wp.data) return;
        var checkout = wp.data.dispatch('wc/store/checkout');
        var data = JSON.stringify(state);
        if (checkout && typeof checkout.__internalSetExtensionData === 'function') {
            checkout.__internalSetExtensionData(NS, { data: data });
        } else if (checkout && typeof checkout.setExtensionData === 'function') {
            checkout.setExtensionData(NS, 'data', data);
        }
    }

    function validation(errors) {
        var store = wp.data.dispatch('wc/store/validation');
        if (!store) return;
        if (typeof store.clearValidationErrors === 'function') store.clearValidationErrors();
        if (errors && Object.keys(errors).length && typeof store.setValidationErrors === 'function') store.setValidationErrors(errors);
    }

    function error(errors, id, message) {
        errors[id] = { message: message, hidden: true };
    }

    function validate() {
        var errors = {};
        state.items.forEach(function (item, n) {
            var p = item.participants;
            if (!item.departure_id) error(errors, 'wcai-departure-' + n, 'Selecione a data e o horário da atividade.');
            if (!p[0].cpf) error(errors, 'wcai-cpf-0-' + n, 'Informe o CPF do titular.');
            if (!p[0].birthdate) error(errors, 'wcai-birth-0-' + n, 'Informe a data de nascimento do titular.');
            for (var i = 1; i < item.quantity; i++) {
                if (!p[i].name) error(errors, 'wcai-name-' + i + '-' + n, 'Informe o nome do visitante ' + (i + 1) + '.');
                if (!p[i].cpf) error(errors, 'wcai-cpf-' + i + '-' + n, 'Informe o CPF do visitante ' + (i + 1) + '.');
                if (!p[i].birthdate) error(errors, 'wcai-birth-' + i + '-' + n, 'Informe a data de nascimento do visitante ' + (i + 1) + '.');
            }
        });
        validation(errors);
    }

    function digits(value) { return String(value || '').replace(/\D/g, ''); }

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
        wrap.className = 'wcai-field';
        var l = document.createElement('label');
        l.textContent = label;
        wrap.appendChild(l);
        var input = document.createElement('input');
        input.type = 'text';
        input.value = value || '';
        input.placeholder = placeholder || '';
        input.addEventListener('input', function () {
            var valueNow = mask ? mask(input.value) : input.value;
            input.value = valueNow;
            onChange(valueNow);
            sync();
            validate();
        });
        wrap.appendChild(input);
        return wrap;
    }

    function itemState(item) {
        var ext = item.extensions[NS];
        var key = String(item.key || item.id || (ext.product_id + ':' + ext.variation_id));
        var old = state.items.filter(function (x) { return x.key === key; })[0];
        var quantity = parseInt(ext.quantity || item.quantity || 1, 10) || 1;
        old = old || { key: key, product_id: ext.product_id, variation_id: ext.variation_id, departure_id: '', participants: [] };
        old.quantity = quantity;
        for (var i = 0; i < quantity; i++) if (!old.participants[i]) old.participants[i] = {};
        old.participants = old.participants.slice(0, quantity);
        return old;
    }

    function render() {
        var root = document.getElementById(ROOT);
        var data = cart();
        if (!root || !data || !Array.isArray(data.items)) return;

        var targets = data.items.filter(function (item) {
            return item.extensions && item.extensions[NS] && item.extensions[NS].enabled;
        });

        state.items = targets.map(itemState);
        root.innerHTML = '';

        if (!targets.length) {
            root.style.display = 'none';
            sync();
            validation({});
            return;
        }

        root.style.display = '';
        var h = document.createElement('h3');
        h.textContent = 'Dados da atividade';
        root.appendChild(h);
        var intro = document.createElement('p');
        intro.textContent = 'Selecione sua saída e informe os dados de todos os participantes.';
        root.appendChild(intro);

        targets.forEach(function (item, n) {
            var ext = item.extensions[NS];
            var s = state.items[n];
            var box = document.createElement('div');
            box.className = 'wcai-item-box';

            var title = document.createElement('h4');
            title.textContent = (item.name || 'Ingresso') + ' — ' + s.quantity + ' participante(s)';
            box.appendChild(title);

            var depWrap = document.createElement('div');
            depWrap.className = 'wcai-field';
            var depLabel = document.createElement('label');
            depLabel.textContent = 'Data e horário da atividade';
            depWrap.appendChild(depLabel);
            var select = document.createElement('select');
            var blank = document.createElement('option');
            blank.value = '';
            blank.textContent = 'Selecione a data e horário';
            select.appendChild(blank);

            (ext.departures || []).forEach(function (d) {
                var o = document.createElement('option');
                o.value = d.id;
                o.textContent = d.label + ' (' + d.available + ' vaga' + (d.available === 1 ? '' : 's') + ')';
                if (String(s.departure_id) === String(d.id)) o.selected = true;
                select.appendChild(o);
            });

            select.addEventListener('change', function () {
                s.departure_id = select.value;
                sync();
                validate();
            });
            depWrap.appendChild(select);
            box.appendChild(depWrap);

            if (!ext.departures || !ext.departures.length) {
                var unavailable = document.createElement('p');
                unavailable.className = 'wcai-error';
                unavailable.textContent = 'Não há saídas disponíveis para este ingresso no momento.';
                box.appendChild(unavailable);
            }

            for (var i = 0; i < s.quantity; i++) {
                let person = s.participants[i];
                var personBox = document.createElement('div');
                personBox.className = 'wcai-person';
                var ph = document.createElement('h5');
                ph.textContent = i === 0 ? 'Participante 1 — Titular' : 'Visitante ' + (i + 1);
                personBox.appendChild(ph);

                if (i > 0) {
                    personBox.appendChild(inputField('Nome completo', person.name, 'Nome completo', null, function (v) { person.name = v; }));
                }
                personBox.appendChild(inputField('CPF', person.cpf, '000.000.000-00', cpfMask, function (v) { person.cpf = v; }));
                personBox.appendChild(inputField('Data de nascimento', person.birthdate, 'dd/mm/aaaa', dateMask, function (v) { person.birthdate = v; }));
                box.appendChild(personBox);
            }

            root.appendChild(box);
        });

        sync();
        validate();
    }

    function sig(data) {
        if (!data || !Array.isArray(data.items)) return '';
        return data.items.map(function (item) {
            var e = item.extensions && item.extensions[NS];
            if (!e) return 'x:' + (item.key || item.id);
            return [item.key || item.id, e.product_id, e.variation_id, e.quantity, JSON.stringify(e.departures || [])].join('|');
        }).join('||');
    }

    function boot() {
        if (!document.getElementById(ROOT) || !wp.data) return;
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
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Product_Booking_Js_A {

    private function calendar_js_a() {
        return <<<'JS'
(function () {
    'use strict';

    // Espera após o último clique no stepper antes de consultar a disponibilidade.
    var REFRESH_DELAY = 250;

    function fmt(template) {
        var args = Array.prototype.slice.call(arguments, 1);
        var index = 0;
        return String(template || '').replace(/%[ds]/g, function () { return args[index++]; });
    }

    function initCalendar(root) {
        if (!root || root.dataset.ready === '1') return;
        root.dataset.ready = '1';

        var departures = [];
        try {
            departures = JSON.parse(root.getAttribute('data-departures') || '[]');
        } catch (e) {
            departures = [];
        }

        if (!departures.length) return;

        var grid = root.querySelector('[data-calendar-grid]');
        var monthLabel = root.querySelector('[data-calendar-month]');
        var prev = root.querySelector('[data-calendar-prev]');
        var next = root.querySelector('[data-calendar-next]');
        var quickDates = root.querySelector('[data-calendar-quick]');
        var quickWrap = root.querySelector('[data-calendar-quick-wrap]');
        var quickArrows = root.querySelectorAll('[data-calendar-scroll]');
        var groupNote = root.querySelector('[data-calendar-group-note]');
        var calendarFull = root.querySelector('[data-calendar-full]');
        var calendarToggle = root.querySelector('[data-calendar-toggle]');
        var times = root.querySelector('[data-calendar-times]');
        var selection = root.querySelector('[data-calendar-selection]');
        var selectedDateLabel = root.querySelector('[data-calendar-selected-date]');
        var selectedDeparture = root.querySelector('[data-calendar-selected-departure]');
        var input = root.querySelector('[data-wcai-departure-input]');
        var card = root.closest('.wcai-product-booking');
        var footer = card ? card.querySelector('[data-wcai-footer]') : null;
        var hintEl = card ? card.querySelector('[data-wcai-hint]') : null;
        var participantRoot = document.querySelector('[data-wcai-participants]');
        var adultInput = participantRoot ? participantRoot.querySelector('[data-participant-input="adults"]') : null;
        var childInput = participantRoot ? participantRoot.querySelector('[data-participant-input="children"]') : null;
        var nativeQuantityInput = document.querySelector('form.cart input.qty[name="quantity"]');
        var errorBox = root.querySelector('[data-calendar-error]');
        var statusBox = root.querySelector('[data-calendar-status]');
        var i18n = window.wcaiBookingI18n || {};
        var dayButtons = [];
        var ajaxUrl = root.getAttribute('data-ajax-url');
        var nonce = root.getAttribute('data-nonce');
        var productId = root.getAttribute('data-product-id');
        var price = parseFloat(root.getAttribute('data-price')) || 0;
        var currency = root.getAttribute('data-currency') || 'BRL';

        var months = {};
        departures.forEach(function (departure) {
            var monthKey = departure.date.slice(0, 7);
            if (!months[monthKey]) months[monthKey] = [];
            months[monthKey].push(departure);
        });

        var monthKeys = Object.keys(months).sort();
        var monthIndex = 0;
        var selectedDate = '';
        var selectedDepartureId = '';
        var requestSeq = 0;
        var controller = null;
        var refreshTimer = 0;

        function announce(text) {
            if (statusBox) statusBox.textContent = text;
        }

        function showError(text) {
            if (!errorBox) return;
            errorBox.textContent = text || '';
            errorBox.hidden = !text;
        }

        function money(value, keepTogether) {
            var text;
            try {
                text = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: currency }).format(value);
            } catch (e) {
                text = 'R$' + String.fromCharCode(160) + value.toFixed(2).replace('.', ',');
            }
            // Espaço comum por padrão; inquebrável só na linha do total, para "R$ 350,00" não quebrar ao meio.
            return keepTogether ? text : text.split(String.fromCharCode(160)).join(' ');
        }

        function participantsLabel(count) {
            return count + (1 === count ? ' participante' : ' participantes');
        }

        function adultCount() {
            return Math.max(1, adultInput ? parseInt(adultInput.value, 10) || 1 : 1);
        }

        function childCount() {
            return Math.max(0, childInput ? parseInt(childInput.value, 10) || 0 : 0);
        }

        function quantity() {
            return adultCount() + childCount();
        }

        function selectedItem() {
            return selectedDepartureId ? departures.filter(function (item) { return String(item.id) === String(selectedDepartureId); })[0] : null;
        }

        function setText(element, text) {
            if (!element) return;
            element.textContent = text;
            element.hidden = !text;
        }

        function describeDisabled(button, reason) {
            if (!button) return;
            if (button.disabled && reason && !reason.hidden && reason.id) button.setAttribute('aria-describedby', reason.id);
            else button.removeAttribute('aria-describedby');
        }

        function updateParticipantUI() {
            var adults = adultCount();
            var children = childCount();
            var total = adults + children;
            var childMax = participantRoot ? parseInt(participantRoot.getAttribute('data-child-max') || '99', 10) : 99;
            var childEnabled = participantRoot ? participantRoot.getAttribute('data-child-enabled') === '1' : false;

            if (childInput) {
                childInput.value = childEnabled ? children : 0;
            }

            if (nativeQuantityInput) nativeQuantityInput.value = total;

            if (participantRoot) {
                var current = selectedItem();
                var available = current ? Math.max(0, parseInt(current.available, 10) || 0) : null;
                var capacityReached = null !== available && total >= available;
                var childRow = participantRoot.querySelector('[data-participant-row="children"]');
                var childPlus = participantRoot.querySelector('[data-participant-row="children"] [data-participant-action="plus"]');
                var childMinus = participantRoot.querySelector('[data-participant-row="children"] [data-participant-action="minus"]');
                var adultPlus = participantRoot.querySelector('[data-participant-row="adults"] [data-participant-action="plus"]');
                var adultMinus = participantRoot.querySelector('[data-participant-row="adults"] [data-participant-action="minus"]');
                var adultValue = participantRoot.querySelector('[data-participant-value="adults"]');
                var childValue = participantRoot.querySelector('[data-participant-value="children"]');
                var closed = !!current && !current.booking_open;

                if (adultValue) adultValue.textContent = String(adults);
                if (childValue) childValue.textContent = String(childEnabled ? children : 0);
                if (childRow) childRow.hidden = !childEnabled;
                if (childPlus) childPlus.disabled = !childEnabled || children >= childMax || capacityReached;
                if (childMinus) childMinus.disabled = !childEnabled || children <= 0;
                if (adultPlus) adultPlus.disabled = null !== available && (capacityReached || closed);
                if (adultMinus) adultMinus.disabled = adults <= 1;

                setText(participantRoot.querySelector('[data-participant-total]'),
                    price > 0
                        ? participantsLabel(total) + ' × ' + money(price, true) + ' = ' + money(Math.round(price * total * 100) / 100, true)
                        : participantsLabel(total));
                setText(participantRoot.querySelector('[data-participant-remaining]'),
                    current ? fmt(i18n.remaining, Math.max(0, available - total)) : '');
                setText(participantRoot.querySelector('[data-participant-limit]'),
                    closed ? i18n.closed : (capacityReached ? fmt(i18n.limit, available) : ''));
                var childReason = childRow ? childRow.querySelector('[data-participant-reason]') : null;
                setText(childReason,
                    childEnabled && children >= childMax && !capacityReached ? fmt(i18n.childLimit, childMax) : '');

                // Botão desabilitado aponta para o motivo visível (limite de vagas ou máximo de crianças).
                var limitEl = participantRoot.querySelector('[data-participant-limit]');
                describeDisabled(adultPlus, limitEl);
                describeDisabled(childPlus, limitEl && !limitEl.hidden ? limitEl : childReason);
            }

            updateCartState();
        }

        function qualifies(departure) {
            return departure.booking_open && parseInt(departure.available, 10) >= quantity();
        }

        function dateDepartures(date) {
            return departures.filter(function (departure) {
                return departure.date === date;
            });
        }

        function dateState(date) {
            var items = dateDepartures(date);
            if (!items.length) return { state: 'empty', label: '' };

            var bookable = items.filter(qualifies);
            if (bookable.length) {
                var best = Math.max.apply(null, bookable.map(function (item) { return parseInt(item.available, 10); }));
                var forming = bookable.filter(function (item) { return item.group_status === 'forming' && parseInt(item.minimum, 10) > 1; })[0];
                var withMinimum = bookable.filter(function (item) { return parseInt(item.minimum, 10) > 1; })[0];
                // O número do chip é sempre de vagas livres; a segunda linha é o grupo mínimo.
                return {
                    state: best <= 3 ? 'few' : 'available',
                    label: best + ' vaga' + (best === 1 ? '' : 's'),
                    hint: forming ? 'faltam ' + forming.remaining_to_minimum + ' para confirmar' : (withMinimum ? 'precisa de ' + withMinimum.minimum : '')
                };
            }

            var openItems = items.filter(function (item) { return item.booking_open; });
            var hasSeats = openItems.some(function (item) { return parseInt(item.available, 10) > 0; });

            if (hasSeats) return { state: 'insufficient', label: 'menos vagas' };
            if (items.some(function (item) { return item.state === 'closed'; })) return { state: 'closed', label: 'encerrado' };
            return { state: 'full', label: 'Esgotado' };
        }

        function monthName(key) {
            var parts = key.split('-');
            var date = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, 1);
            return date.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
        }

        function updateDepartureSnapshot(date, items) {
            departures = departures.filter(function (item) {
                return item.date !== date;
            });

            (items || []).forEach(function (nextDeparture) {
                departures.push(nextDeparture);
            });

            departures.sort(function (a, b) {
                return (a.date + ' ' + a.time).localeCompare(b.date + ' ' + b.time);
            });
        }

        function setCalendarExpanded(expanded) {
            if (!calendarFull || !calendarToggle) return;

            calendarFull.hidden = !expanded;
            calendarToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            calendarToggle.textContent = expanded ? 'Ocultar calendário' : 'Ver todas as datas';

            if (expanded) {
                renderCalendar();
            }
        }

        function uniqueDepartureDates() {
            var dates = [];

            departures.forEach(function (departure) {
                if (dates.indexOf(departure.date) === -1) {
                    dates.push(departure.date);
                }
            });

            return dates.slice(0, 7);
        }

        function shortWeekday(dateString) {
            var parts = dateString.split('-');
            var date = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            return date.toLocaleDateString('pt-BR', { weekday: 'short' }).replace('.', '');
        }

        function shortMonth(dateString) {
            var parts = dateString.split('-');
            var date = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
            return date.toLocaleDateString('pt-BR', { month: 'short' }).replace('.', '');
        }

        function enabledChips() {
            return Array.prototype.slice.call(quickDates.querySelectorAll('.wcai-quick-date:not(:disabled)'));
        }

        // Roving tabindex: um chip na sequência de Tab (o selecionado ou o primeiro disponível).
        function applyChipTabStops() {
            var chips = enabledChips();
            var stop = chips.filter(function (chip) { return chip.getAttribute('aria-checked') === 'true'; })[0] || chips[0];

            chips.forEach(function (chip) { chip.tabIndex = chip === stop ? 0 : -1; });
        }

        // Setas reais (botões) só aparecem quando há mais datas fora da área visível.
        function updateQuickFade() {
            if (!quickWrap || !quickDates) return;

            var max = quickDates.scrollWidth - quickDates.clientWidth;
            var hasPrev = quickDates.scrollLeft > 2;
            var hasNext = max > 2 && quickDates.scrollLeft < max - 2;

            quickArrows.forEach(function (arrow) {
                var hide = arrow.getAttribute('data-calendar-scroll') === '-1' ? !hasPrev : !hasNext;
                // Seta que sumiu com o foco nela: devolve o foco ao chip ativo, sem perdê-lo.
                if (hide && !arrow.hidden && document.activeElement === arrow) {
                    var stop = quickDates.querySelector('.wcai-quick-date[tabindex="0"]');
                    if (stop) stop.focus({ preventScroll: true });
                }
                arrow.hidden = hide;
            });
        }

JS;
    }

}

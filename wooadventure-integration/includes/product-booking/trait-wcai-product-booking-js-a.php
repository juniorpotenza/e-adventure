<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Product_Booking_Js_A {

    private function calendar_js_a() {
        return <<<'JS'
(function () {
    'use strict';

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
        var calendarFull = root.querySelector('[data-calendar-full]');
        var calendarToggle = root.querySelector('[data-calendar-toggle]');
        var times = root.querySelector('[data-calendar-times]');
        var selection = root.querySelector('[data-calendar-selection]');
        var selectedDateLabel = root.querySelector('[data-calendar-selected-date]');
        var selectedDeparture = root.querySelector('[data-calendar-selected-departure]');
        var input = root.querySelector('[data-wcai-departure-input]');
        var participantRoot = document.querySelector('[data-wcai-participants]');
        var adultInput = participantRoot ? participantRoot.querySelector('[data-participant-input="adults"]') : null;
        var childInput = participantRoot ? participantRoot.querySelector('[data-participant-input="children"]') : null;
        var nativeQuantityInput = document.querySelector('form.cart input.qty[name="quantity"]');
        var errorBox = root.querySelector('[data-calendar-error]');
        var statusBox = root.querySelector('[data-calendar-status]');
        var i18n = window.wcaiBookingI18n || {};
        var cartHint = null;
        var dayButtons = [];
        var ajaxUrl = root.getAttribute('data-ajax-url');
        var nonce = root.getAttribute('data-nonce');
        var productId = root.getAttribute('data-product-id');

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
        var loading = false;

        function announce(text) {
            if (statusBox) statusBox.textContent = text;
        }

        function showError(text) {
            if (!errorBox) return;
            errorBox.textContent = text || '';
            errorBox.hidden = !text;
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

        function updateParticipantUI() {
            var adults = adultCount();
            var children = childCount();
            var total = adults + children;
            var childMax = participantRoot ? parseInt(participantRoot.getAttribute('data-child-max') || '99', 10) : 99;
            var childEnabled = participantRoot ? participantRoot.getAttribute('data-child-enabled') === '1' : false;

            if ( childInput ) {
                childInput.value = childEnabled ? children : 0;
            }

            if ( nativeQuantityInput ) nativeQuantityInput.value = total;

            if ( participantRoot ) {
                var adultValue = participantRoot.querySelector('[data-participant-value="adults"]');
                var childValue = participantRoot.querySelector('[data-participant-value="children"]');
                var totalLabel = participantRoot.querySelector('[data-participant-total]');
                var availability = participantRoot.querySelector('[data-participant-availability]');
                var childRow = participantRoot.querySelector('[data-participant-row="children"]');

                if ( adultValue ) adultValue.textContent = String(adults);
                if ( childValue ) childValue.textContent = String(childEnabled ? children : 0);
                if ( totalLabel ) totalLabel.textContent = total + ( 1 === total ? ' participante' : ' participantes' );
                if ( childRow ) childRow.hidden = !childEnabled;

                var current = selectedDepartureId ? departures.filter(function(item){ return String(item.id) === String(selectedDepartureId); })[0] : null;
                var available = current ? Math.max(0, parseInt(current.available, 10) || 0) : null;
                var capacityReached = null !== available && total >= available;
                var childPlus = participantRoot.querySelector('[data-participant-row="children"] [data-participant-action="plus"]');
                var childMinus = participantRoot.querySelector('[data-participant-row="children"] [data-participant-action="minus"]');
                var adultPlus = participantRoot.querySelector('[data-participant-row="adults"] [data-participant-action="plus"]');

                if ( childPlus ) {
                    childPlus.disabled = !childEnabled || children >= childMax || capacityReached;
                    childPlus.title = capacityReached ? 'Limite de vagas atingido' : ( children >= childMax ? 'Limite de crianças por reserva atingido' : 'Adicionar criança' );
                }
                if ( childMinus ) {
                    childMinus.disabled = !childEnabled || children <= 0;
                    childMinus.title = childMinus.disabled ? 'Nenhuma criança para remover' : 'Remover criança';
                }

                if ( adultPlus ) {
                    adultPlus.disabled = null !== available && ( capacityReached || !current.booking_open );
                    adultPlus.title = capacityReached ? 'Limite de vagas atingido' : ( current && !current.booking_open ? 'Vendas encerradas' : 'Adicionar adulto' );
                }

                var adultMinus = participantRoot.querySelector('[data-participant-row="adults"] [data-participant-action="minus"]');
                if ( adultMinus ) {
                    adultMinus.disabled = adults <= 1;
                    adultMinus.title = adultMinus.disabled ? 'A reserva precisa ter pelo menos 1 adulto' : 'Remover adulto';
                }

                if ( availability ) {
                    if ( current ) {
                        var remainingAfterReservation = Math.max(0, available - total);
                        availability.textContent = remainingAfterReservation > 0
                            ? remainingAfterReservation + ' vagas restantes após sua reserva'
                            : 'Limite de vagas atingido para esta saída';
                    } else {
                        availability.textContent = 'Escolha o horário';
                    }
                }
            }
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
                // O número do chip é sempre de vagas livres; grupo mínimo é dica secundária.
                var seats = best + ' vaga' + (best === 1 ? '' : 's');
                return {
                    state: best <= 3 ? 'few' : 'available',
                    label: seats,
                    hint: forming ? 'mín. ' + forming.minimum + ' · faltam ' + forming.remaining_to_minimum : ''
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

        function renderQuickDates() {
            if (!quickDates) return;

            quickDates.innerHTML = '';

            uniqueDepartureDates().forEach(function (date) {
                var state = dateState(date);
                var dateItems = dateDepartures(date);
                var dayNumber = parseInt(date.slice(8, 10), 10);
                var button = document.createElement('button');

                button.type = 'button';
                button.className = 'wcai-quick-date state-' + state.state;
                button.disabled = state.state === 'empty' || state.state === 'full' || state.state === 'closed' || state.state === 'insufficient';
                button.setAttribute('aria-label', dayNumber + ' de ' + shortMonth(date) + ', ' + (state.label || state.state) + (state.hint ? ', ' + state.hint : ''));

                button.setAttribute('aria-pressed', selectedDate === date ? 'true' : 'false');
                if (selectedDate === date) {
                    button.classList.add('is-selected');
                }

                var weekday = document.createElement('span');
                weekday.textContent = shortWeekday(date);
                button.appendChild(weekday);

                var day = document.createElement('strong');
                day.textContent = String(dayNumber);
                button.appendChild(day);

                var label = document.createElement('small');
                if (state.state === 'few') {
JS;
    }

}

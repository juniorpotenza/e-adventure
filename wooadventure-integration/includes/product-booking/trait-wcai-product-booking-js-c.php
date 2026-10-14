<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Product_Booking_Js_C {

    private function calendar_js_c() {
        return <<<'JS'
                addCell(empty, blank);
            }

            for (var day = 1; day <= daysInMonth; day++) {
                var date = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                var state = dateState(date);
                var cell = document.createElement('div');
                var dayEl;

                cell.className = 'wcai-calendar-cell';

                if (state.state !== 'empty') {
                    cell.setAttribute('role', 'gridcell');
                    dayEl = document.createElement('button');
                    dayEl.type = 'button';
                    dayEl.className = 'wcai-calendar-day state-' + state.state;
                    dayEl.tabIndex = -1;
                    dayEl.setAttribute('aria-label', day + ' ' + monthName(key) + ', ' + (state.label || state.state));
                    dayEl.setAttribute('aria-pressed', date === selectedDate ? 'true' : 'false');
                    dayEl.title = state.label || state.state;
                    if (date === selectedDate) dayEl.classList.add('is-selected');
                    dayEl.addEventListener('click', function (dateValue) {
                        return function () {
                            selectDate(dateValue, this);
                        };
                    }(date));

                    var number = document.createElement('strong');
                    number.textContent = day;
                    dayEl.appendChild(number);

                    // Estado também por símbolo e texto (title/aria-label), não só por cor.
                    var dot = document.createElement('i');
                    dot.className = 'wcai-calendar-dot';
                    dot.setAttribute('aria-hidden', 'true');
                    dot.textContent = glyphs[state.state] || '';
                    dayEl.appendChild(dot);
                    dayButtons.push(dayEl);
                } else {
                    cell.setAttribute('aria-hidden', 'true');
                    dayEl = document.createElement('span');
                    dayEl.className = 'wcai-calendar-day is-empty-date';
                    dayEl.textContent = day;
                    dayButtons.push(null);
                }

                cell.appendChild(dayEl);
                addCell(cell, (firstDay + day - 1) % 7);
            }

            prev.disabled = monthIndex <= 0;
            next.disabled = monthIndex >= monthKeys.length - 1;

            // Roving tabindex: um único dia na sequência de Tab (o selecionado ou o primeiro disponível).
            var tabStop = dayButtons.filter(function (item) { return item && item.classList.contains('is-selected'); })[0]
                || dayButtons.filter(Boolean)[0];
            if (tabStop) tabStop.tabIndex = 0;
        }

        function onGridKeydown(event) {
            var offsets = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
            var offset = offsets[event.key];
            var from = dayButtons.indexOf(document.activeElement);

            if (!offset || from < 0) return;

            event.preventDefault();

            for (var i = from + offset; i >= 0 && i < dayButtons.length; i += offset < 0 ? -1 : 1) {
                if (dayButtons[i]) {
                    dayButtons[from].tabIndex = -1;
                    dayButtons[i].tabIndex = 0;
                    dayButtons[i].focus();
                    return;
                }
            }
        }

        function onQuickKeydown(event) {
            var chips = enabledChips();
            var from = chips.indexOf(document.activeElement);
            var target;

            if (from < 0) return;

            if (event.key === 'ArrowLeft') target = Math.max(0, from - 1);
            else if (event.key === 'ArrowRight') target = Math.min(chips.length - 1, from + 1);
            else if (event.key === 'Home') target = 0;
            else if (event.key === 'End') target = chips.length - 1;
            else return;

            event.preventDefault();
            chips.forEach(function (chip) { chip.tabIndex = -1; });
            chips[target].tabIndex = 0;
            chips[target].focus();
        }

        function guardAddToCart(event) {
            if (selectedDepartureId) return;

            event.preventDefault();
            event.stopImmediatePropagation();
            announce(hintEl ? hintEl.textContent : '');

            var firstDate = root.querySelector('.wcai-quick-date:not(:disabled)');
            if (firstDate) firstDate.focus();
        }

        function hintText() {
            if (selectedDepartureId) {
                var total = quantity();
                return fmt(i18n.hintReady, participantsLabel(total) + (price > 0 ? ', ' + money(Math.round(price * total * 100) / 100) : ''));
            }

            return selectedDate ? i18n.hintTime : i18n.hintDate;
        }

        // Estado do botão de compra: dica contextual dentro do cartão, logo acima do botão.
        function updateCartState() {
            var missing = !selectedDepartureId;
            var buttons = document.querySelectorAll('button.single_add_to_cart_button, input.single_add_to_cart_button');

            if (footer && buttons.length && buttons[0].parentNode !== footer) {
                footer.appendChild(buttons[0]);
            }

            if (hintEl) {
                hintEl.textContent = hintText();
                hintEl.classList.toggle('is-ready', !missing);
            }

            buttons.forEach(function (button) {
                // aria-disabled (e não disabled) mantém o botão focável e ligado à dica; o clique é barrado em guardAddToCart.
                button.disabled = false;
                button.setAttribute('aria-disabled', missing ? 'true' : 'false');
                button.classList.toggle('disabled', missing);
                button.setAttribute('aria-describedby', 'wcai-cart-hint');

                if (!button.hasAttribute('data-wcai-guard')) {
                    button.setAttribute('data-wcai-guard', '1');
                    button.addEventListener('click', guardAddToCart, true);
                }
            });
        }

        function scheduleRefresh() {
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(function () {
                if (!selectedDate) return;

                var date = selectedDate;
                ajaxForDate(date, function (items) {
                    renderTimes(date, items, true);
                });
            }, REFRESH_DELAY);
        }

        function refreshForQuantity() {
            var previous = selectedDepartureId;

            if (previous) {
                var current = departures.filter(function (departure) {
                    return String(departure.id) === String(previous);
                })[0];

                if (!current || !qualifies(current)) {
                    selectedDepartureId = '';
                    selectedDate = '';
                    input.value = '';
                    selectedDeparture.hidden = true;
                    if ( participantRoot ) participantRoot.hidden = true;
                }
            }

            renderCalendar();
            if (selectedDate) renderTimes(selectedDate, undefined, true);
            updateParticipantUI();
            scheduleRefresh();
        }

        if ( participantRoot ) {
            participantRoot.querySelectorAll('[data-participant-action]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var row = button.closest('[data-participant-row]');
                    var type = row ? row.getAttribute('data-participant-row') : '';
                    var inputEl = 'adults' === type ? adultInput : childInput;
                    var current = inputEl ? parseInt(inputEl.value, 10) || 0 : 0;
                    var max = participantRoot ? parseInt(participantRoot.getAttribute('data-child-max') || '99', 10) : 99;

                    if ( 'minus' === button.getAttribute('data-participant-action') ) {
                        current = 'adults' === type ? Math.max(1, current - 1) : Math.max(0, current - 1);
                    } else {
                        var selected = selectedItem();
                        var available = selected ? Math.max(0, parseInt(selected.available, 10) || 0) : null;

                        if ( 'children' === type && current >= max ) return;
                        if ( null !== available && quantity() >= available ) return;

                        current += 1;
                    }

                    if ( inputEl ) inputEl.value = current;
                    refreshForQuantity();
                });
            });
        }

        if (calendarToggle) {
            calendarToggle.addEventListener('click', function () {
                setCalendarExpanded(calendarFull ? calendarFull.hidden : false);
            });
        }

        updateParticipantUI();
        renderQuickDates();
        setCalendarExpanded(false);

        grid.addEventListener('keydown', onGridKeydown);

        if (quickDates) {
            quickDates.addEventListener('keydown', onQuickKeydown);
            quickDates.addEventListener('scroll', updateQuickFade, { passive: true });
            window.addEventListener('resize', updateQuickFade);
        }

        prev.addEventListener('click', function () {
            if (monthIndex > 0) {
                monthIndex--;
                renderCalendar();
            }
        });

        next.addEventListener('click', function () {
            if (monthIndex < monthKeys.length - 1) {
                monthIndex++;
                renderCalendar();
            }
        });

        if ( nativeQuantityInput ) {
            nativeQuantityInput.setAttribute('readonly', 'readonly');
            nativeQuantityInput.setAttribute('aria-hidden', 'true');
            nativeQuantityInput.setAttribute('tabindex', '-1');
        }

        // "Antes de reservar" abre sozinho em telas largas e fica recolhido no celular.
        var trust = document.querySelector('[data-wcai-trust]');
        if (trust && window.matchMedia && window.matchMedia('(min-width: 768px)').matches) trust.open = true;

        updateParticipantUI();
        renderQuickDates();
        renderCalendar();
    }

    function boot() {
        document.querySelectorAll('[data-wcai-calendar]').forEach(initCalendar);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
}());
JS;
    }

}

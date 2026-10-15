<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Product_Booking_Js_B {

    private function calendar_js_b() {
        return <<<'JS'
        function renderQuickDates() {
            if (!quickDates) return;

            var focusedDate = quickDates.contains(document.activeElement) ? document.activeElement.getAttribute('data-date') : '';

            quickDates.innerHTML = '';

            uniqueDepartureDates().forEach(function (date) {
                var state = dateState(date);
                var dayNumber = parseInt(date.slice(8, 10), 10);
                var button = document.createElement('button');
                var isSelected = selectedDate === date;

                button.type = 'button';
                button.className = 'wcai-quick-date state-' + state.state + (isSelected ? ' is-selected' : '');
                button.disabled = state.state === 'empty' || state.state === 'full' || state.state === 'closed' || state.state === 'insufficient';
                button.setAttribute('role', 'radio');
                button.setAttribute('aria-checked', isSelected ? 'true' : 'false');
                button.setAttribute('data-date', date);
                button.setAttribute('aria-label', shortWeekday(date) + ' ' + dayNumber + ' de ' + shortMonth(date) + ', ' + (state.label || state.state) + (state.hint ? ', ' + state.hint : ''));

                var weekday = document.createElement('span');
                weekday.textContent = shortWeekday(date);
                button.appendChild(weekday);

                var day = document.createElement('strong');
                day.textContent = dayNumber + ' ' + shortMonth(date);
                button.appendChild(day);

                var label = document.createElement('small');
                label.textContent = state.label || 'Indisponível';
                button.appendChild(label);

                if (state.hint) {
                    var hint = document.createElement('small');
                    hint.className = 'wcai-quick-date-hint';
                    hint.textContent = state.hint;
                    button.appendChild(hint);
                }

                button.addEventListener('click', function () {
                    if (button.disabled) return;
                    selectDate(date, button);
                });

                quickDates.appendChild(button);
            });

            applyChipTabStops();
            updateQuickFade();

            if (focusedDate) {
                var again = quickDates.querySelector('.wcai-quick-date[data-date="' + focusedDate + '"]:not(:disabled)');
                if (again) again.focus();
            }
        }

        function ajaxForDate(date, done) {
            if (!ajaxUrl || !nonce) {
                done(dateDepartures(date));
                return;
            }

            // Contador de requisições: só a última resposta vale; as antigas são abortadas e ignoradas.
            var seq = ++requestSeq;
            if (controller) controller.abort();
            controller = typeof AbortController === 'function' ? new AbortController() : null;

            showError('');
            root.classList.add('is-loading');

            var body = new URLSearchParams();
            body.append('action', 'wcai_refresh_calendar_date');
            body.append('nonce', nonce);
            body.append('product_id', productId);
            body.append('date', date);
            body.append('quantity', quantity());

            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
                body: body.toString(),
                credentials: 'same-origin',
                signal: controller ? controller.signal : undefined
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('http ' + response.status);
                    return response.json();
                })
                .then(function (response) {
                    if (seq !== requestSeq) return;

                    if (response && response.success && response.data && Array.isArray(response.data.departures)) {
                        updateDepartureSnapshot(date, response.data.departures);
                        renderQuickDates();
                        renderCalendar();
                        done(response.data.departures);
                    } else {
                        throw new Error('invalid response');
                    }
                })
                .catch(function () {
                    if (seq !== requestSeq) return;

                    // Mostra o erro e mantém os últimos dados carregados; o servidor revalida a vaga ao adicionar ao carrinho.
                    showError(i18n.fetchError);
                    done(dateDepartures(date));
                })
                .finally(function () {
                    if (seq === requestSeq) root.classList.remove('is-loading');
                });
        }

        function capitalize(text) {
            return text ? text.charAt(0).toUpperCase() + text.slice(1) : '';
        }

        // Uma linha só: o aviso do grupo mínimo fica em updateGroupNote (nunca repetido aqui).
        function renderSelected(departure) {
            selectedDeparture.innerHTML = '';

            var title = document.createElement('strong');
            var check = document.createElement('span');
            check.setAttribute('aria-hidden', 'true');
            check.textContent = '✓ ';
            title.appendChild(check);
            title.appendChild(document.createTextNode(fmt(i18n.selectedTime, departure.time)));
            selectedDeparture.appendChild(title);
            selectedDeparture.hidden = false;
        }

        // Único aviso de grupo mínimo: o da saída escolhida ou, antes disso, o da primeira em formação do dia.
        function updateGroupNote() {
            if (!groupNote) return;

            var chosen = selectedItem();
            var forming = chosen && chosen.group_status === 'forming' && chosen.minimum > 1 ? chosen
                : (chosen ? null : dateDepartures(selectedDate).filter(function (item) { return item.group_status === 'forming' && item.minimum > 1; })[0]);

            setText(groupNote, forming
                ? (forming.cutoff ? fmt(i18n.groupRule, forming.minimum, forming.cutoff) : fmt(i18n.groupRuleAny, forming.minimum))
                : '');
        }

        function renderTimes(date, freshItems, silent) {
            var options = Array.isArray(freshItems) ? freshItems : dateDepartures(date);
            times.innerHTML = '';
            selectedDateLabel.textContent = date ? dateDepartures(date).map(function (item) { return item.date_label; })[0] || date : '';
            renderQuickDates();

            if (!options.length) {
                selection.hidden = false;
                times.innerHTML = '<div class="wcai-no-times"></div>';
                times.firstChild.textContent = i18n.noTimes;
                announce(i18n.noTimes);
                return;
            }

            selection.hidden = false;
            // Re-render após mudar a quantidade não pode esconder o seletor com saída já escolhida.
            if ( participantRoot ) participantRoot.hidden = !selectedDepartureId;

            options.forEach(function (departure) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'wcai-time-option state-' + departure.state;
                button.setAttribute('data-departure-id', departure.id);
                button.disabled = !qualifies(departure);

                var time = document.createElement('strong');
                time.textContent = departure.time;
                button.appendChild(time);

                var content = document.createElement('div');
                content.className = 'wcai-time-option-content';

                // Linha 1: situação do grupo; linha 2 (cinza): dados de apoio; linha 3: prazo da reserva.
                var status = document.createElement('span');
                status.className = 'wcai-time-status';
                status.textContent = departure.group_status === 'forming' && departure.minimum > 1
                    ? fmt(i18n.forming, departure.remaining_to_minimum)
                    : (departure.group_label || departure.state_label);
                content.appendChild(status);

                if (departure.minimum > 1) {
                    var progress = document.createElement('span');
                    progress.className = 'wcai-time-progress';
                    var progressBar = document.createElement('i');
                    progressBar.style.width = Math.min(100, Math.max(0, parseInt(departure.formation_percent, 10) || 0)) + '%';
                    progress.appendChild(progressBar);
                    content.appendChild(progress);
                }

                var meta = document.createElement('small');
                var parts = [];
                if (departure.minimum > 1) parts.push(departure.reserved + ' de ' + departure.minimum + ' reservados');
                if (departure.available > 0) parts.push(departure.available + (departure.available === 1 ? ' vaga restante' : ' vagas restantes'));
                if (departure.capacity) parts.push('Grupo até ' + departure.capacity);
                else parts.push('Lotado');
                if (departure.duration) parts.push(departure.duration + ' min');
                if (departure.meeting_point) parts.push(departure.meeting_point);
                if (departure.guide) parts.push('Guia: ' + capitalize(departure.guide));
                meta.textContent = parts.join(' · ');
                content.appendChild(meta);

                if (departure.cutoff && departure.booking_open) {
                    var cutoff = document.createElement('small');
                    cutoff.textContent = 'Reserva até ' + departure.cutoff;
                    content.appendChild(cutoff);
                }

                button.appendChild(content);

                var isSelectedTime = String(selectedDepartureId) === String(departure.id);
                button.setAttribute('aria-pressed', isSelectedTime ? 'true' : 'false');
                if (isSelectedTime) {
                    button.classList.add('is-selected');
                }

                button.addEventListener('click', function () {
                    if (button.disabled) return;

                    selectedDepartureId = String(departure.id);
                    input.value = departure.id;

                    root.querySelectorAll('.wcai-time-option').forEach(function (item) {
                        item.classList.remove('is-selected');
                        item.setAttribute('aria-pressed', 'false');
                    });
                    button.classList.add('is-selected');
                    button.setAttribute('aria-pressed', 'true');

                    renderSelected(departure);
                    updateGroupNote();
                    if ( participantRoot ) participantRoot.hidden = false;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    updateParticipantUI();
                    announce(fmt(i18n.selected, departure.date_label + ' às ' + departure.time));
                });

                times.appendChild(button);
            });

            updateGroupNote();

            if (!silent) {
                var count = options.filter(qualifies).length;
                announce(fmt(count === 1 ? i18n.timesOne : i18n.timesMany, count, selectedDateLabel.textContent));
            }
        }

        function selectDate(date, dateElement) {
            clearTimeout(refreshTimer);
            selectedDate = date;
            selectedDepartureId = '';
            input.value = '';
            selectedDeparture.hidden = true;
            updateGroupNote();
            if ( participantRoot ) participantRoot.hidden = true;

            root.querySelectorAll('button.wcai-calendar-day').forEach(function (item) {
                item.classList.remove('is-selected');
                item.setAttribute('aria-pressed', 'false');
            });

            if (dateElement && dateElement.classList.contains('wcai-calendar-day')) {
                dateElement.classList.add('is-selected');
                dateElement.setAttribute('aria-pressed', 'true');
            }

            renderQuickDates();
            updateParticipantUI();

            ajaxForDate(date, function (items) {
                renderTimes(date, items);
                setCalendarExpanded(false);
            });
        }

        function renderCalendar() {
            var key = monthKeys[monthIndex];
            if (!key) return;

            monthLabel.textContent = monthName(key);
            grid.innerHTML = '';
            dayButtons = [];

            var parts = key.split('-');
            var year = parseInt(parts[0], 10);
            var month = parseInt(parts[1], 10) - 1;
            var firstDay = new Date(year, month, 1).getDay();
            var daysInMonth = new Date(year, month + 1, 0).getDate();
            var glyphs = { available: '✓', few: '!', insufficient: '↓', full: '×', closed: '–' };
            var row = null;

            function addCell(cell, column) {
                if (column === 0) {
                    row = document.createElement('div');
                    row.className = 'wcai-calendar-row';
                    row.setAttribute('role', 'row');
                    grid.appendChild(row);
                }
                row.appendChild(cell);
            }

            for (var blank = 0; blank < firstDay; blank++) {
                var empty = document.createElement('div');
                empty.className = 'wcai-calendar-cell';
                empty.setAttribute('role', 'presentation');
JS;
    }

}

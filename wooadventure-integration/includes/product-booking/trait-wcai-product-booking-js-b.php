<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Product_Booking_Js_B {

    private function calendar_js_b() {
        return <<<'JS'
                    label.textContent = state.label || 'Últimas vagas';
                } else if (state.state === 'available') {
                    label.textContent = state.label || 'Disponível';
                } else {
                    label.textContent = state.label || 'Indisponível';
                }
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

                if (!dateItems.length) {
                    button.disabled = true;
                }
            });
        }

        function ajaxForDate(date, done) {
            if (loading || !ajaxUrl || !nonce) {
                done(dateDepartures(date));
                return;
            }

            loading = true;
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
                credentials: 'same-origin'
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('http ' + response.status);
                    return response.json();
                })
                .then(function (response) {
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
                    // Mostra o erro e mantém os últimos dados carregados; o servidor revalida a vaga ao adicionar ao carrinho.
                    showError(i18n.fetchError || 'Não foi possível atualizar a disponibilidade. Mostrando os últimos dados carregados.');
                    done(dateDepartures(date));
                })
                .finally(function () {
                    loading = false;
                    root.classList.remove('is-loading');
                });
        }

        function renderTimes(date, freshItems) {
            var options = Array.isArray(freshItems) ? freshItems : dateDepartures(date);
            times.innerHTML = '';
            selectedDateLabel.textContent = date ? dateDepartures(date).map(function (item) { return item.date_label; })[0] || date : '';
            renderQuickDates();

            if (!options.length) {
                selection.hidden = false;
                times.innerHTML = '<div class="wcai-no-times">Não há saída registrada para este dia.</div>';
                announce('Não há saída registrada para este dia.');
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

                var status = document.createElement('span');
                status.className = 'wcai-time-status';
                status.textContent = departure.group_label || departure.state_label;
                content.appendChild(status);

                var formation = document.createElement('div');
                formation.className = 'wcai-time-formation';

                var formationText = document.createElement('b');
                if (departure.minimum > 1) {
                    formationText.textContent = departure.group_status === 'forming'
                        ? (departure.reserved + ' de ' + departure.minimum + ' participantes · faltam ' + departure.remaining_to_minimum)
                        : (departure.reserved + ' reservados · mínimo de ' + departure.minimum + ' atingido');
                } else if (departure.reserved > 0) {
                    formationText.textContent = departure.reserved + ' participante' + (departure.reserved === 1 ? '' : 's') + ' reservado' + (departure.reserved === 1 ? '' : 's');
                } else {
                    formationText.textContent = 'Ainda sem reservas';
                }
                formation.appendChild(formationText);

                if (departure.minimum > 1) {
                    var progress = document.createElement('span');
                    progress.className = 'wcai-time-progress';
                    var progressBar = document.createElement('i');
                    progressBar.style.width = Math.min(100, Math.max(0, parseInt(departure.formation_percent, 10) || 0)) + '%';
                    progress.appendChild(progressBar);
                    formation.appendChild(progress);
                }

                content.appendChild(formation);

                var meta = document.createElement('small');
                var parts = [];
                if (departure.available > 0) parts.push(departure.available + (departure.available === 1 ? ' vaga restante' : ' vagas restantes'));
                if (departure.capacity) parts.push('Grupo até ' + departure.capacity);
                else parts.push('Lotado');
                if (departure.duration) parts.push(departure.duration + ' min');
                if (departure.meeting_point) parts.push(departure.meeting_point);
                if (departure.guide) parts.push('Guia: ' + departure.guide);
                if (departure.cutoff && departure.booking_open) parts.push('Reserva até ' + departure.cutoff);
                meta.textContent = parts.join(' • ');
                content.appendChild(meta);

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

                    selectedDeparture.innerHTML = '';
                    var selectedHeader = document.createElement('div');
                    var selectedTitle = document.createElement('strong');
                    selectedTitle.textContent = departure.date_label + ' às ' + departure.time;
                    selectedHeader.appendChild(selectedTitle);

                    var selectedStatus = document.createElement('span');
                    selectedStatus.textContent = departure.group_label || departure.state_label;
                    selectedHeader.appendChild(selectedStatus);
                    selectedDeparture.appendChild(selectedHeader);

                    if (departure.minimum > 1) {
                        var selectedFormation = document.createElement('p');
                        selectedFormation.textContent = departure.group_status === 'forming'
                            ? departure.reserved + ' de ' + departure.minimum + ' participantes reservados. Faltam ' + departure.remaining_to_minimum + ' para atingir o mínimo.'
                            : departure.reserved + ' participantes reservados. O mínimo de ' + departure.minimum + ' já foi atingido.';
                        selectedDeparture.appendChild(selectedFormation);
                    }

                    var selectedMeta = document.createElement('small');
                    var selectedParts = [];
                    if (departure.duration) selectedParts.push(departure.duration + ' min');
                    if (departure.capacity) selectedParts.push('Grupo até ' + departure.capacity);
                    if (departure.meeting_point) selectedParts.push(departure.meeting_point);
                    if (departure.available > 0) selectedParts.push(departure.available + ' vagas restantes');
                    selectedMeta.textContent = selectedParts.join(' • ');
                    selectedDeparture.appendChild(selectedMeta);

                    selectedDeparture.hidden = false;
                    if ( participantRoot ) participantRoot.hidden = false;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    updateParticipantUI();
                    enableAddToCart();
                    announce((i18n.selected || 'Selecionado: %s').replace('%s', selectedTitle.textContent));
                });

                times.appendChild(button);
            });

            announce((i18n.timesCount || '%d horário(s) disponível(is) em %s').replace('%d', options.filter(qualifies).length).replace('%s', selectedDateLabel.textContent));
        }

        function selectDate(date, dateElement) {
            selectedDate = date;
            selectedDepartureId = '';
            input.value = '';
            selectedDeparture.hidden = true;
            if ( participantRoot ) participantRoot.hidden = true;

            root.querySelectorAll('button.wcai-calendar-day, .wcai-quick-date').forEach(function (item) {
                item.classList.remove('is-selected');
                item.setAttribute('aria-pressed', 'false');
            });

            if (dateElement) {
                dateElement.classList.add('is-selected');
                dateElement.setAttribute('aria-pressed', 'true');
            }

            renderQuickDates();

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

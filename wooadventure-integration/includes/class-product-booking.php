<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Product_Booking {

    const FIELD = 'wcai_product_departure_id';

    public function __construct() {
        add_action( 'woocommerce_before_add_to_cart_quantity', array( $this, 'render_departure_selector' ), 20 );
        add_action( 'wp_ajax_wcai_refresh_calendar_date', array( $this, 'ajax_refresh_calendar_date' ) );
        add_action( 'wp_ajax_nopriv_wcai_refresh_calendar_date', array( $this, 'ajax_refresh_calendar_date' ) );
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 5 );
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 4 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'render_cart_item_data' ), 10, 2 );
        add_action( 'woocommerce_single_product_summary', array( $this, 'render_product_facts' ), 16 );
    }

    private function is_target_product( $product_id, $variation_id = 0 ) {
        $target_ids = WCAI_Settings::get_product_ids();

        return in_array( absint( $product_id ), $target_ids, true )
            || in_array( absint( $variation_id ), $target_ids, true );
    }

    private function get_departure_state( $departure_id, $quantity = 1 ) {
        $available = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_available_quantity( $departure_id ) : 0;
        $booking_open = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::is_booking_open( $departure_id ) : false;

        if ( ! $booking_open ) {
            $state = 'closed';
            $label = 'Vendas encerradas';
        } elseif ( $available < $quantity ) {
            $state = $available > 0 ? 'insufficient' : 'full';
            $label = $available > 0
                ? sprintf( '%d vaga(s) — não comporta a quantidade', $available )
                : 'Lotado';
        } elseif ( $available <= 3 ) {
            $state = 'few';
            $label = $available . ( 1 === $available ? ' vaga disponível' : ' vagas disponíveis' );
        } else {
            $state = 'available';
            $label = $available . ' vagas disponíveis';
        }

        return array(
            'available'    => absint( $available ),
            'booking_open' => (bool) $booking_open,
            'state'        => $state,
            'label'        => $label,
        );
    }

    private function departure_payload( $departure_id, $quantity = 1 ) {
        $starts_at = WCAI_Data_Resolver::get_departure_start( $departure_id );
        $timestamp = WCAI_Data_Resolver::parse_timestamp( $starts_at );

        if ( ! $timestamp ) {
            return null;
        }

        $status = get_post_meta( $departure_id, '_wcai_departure_status', true );
        if ( in_array( $status, array( 'cancelled', 'completed', 'draft' ), true ) ) {
            return null;
        }

        $state = $this->get_departure_state( $departure_id, $quantity );
        $capacity = absint( get_post_meta( $departure_id, '_wcai_capacity', true ) );
        $guide_id = absint( get_post_meta( $departure_id, '_wcai_guide_id', true ) );
        $guide = $guide_id ? get_userdata( $guide_id ) : false;

        return array(
            'id'             => absint( $departure_id ),
            'date'           => wp_date( 'Y-m-d', $timestamp ),
            'date_label'     => wp_date( 'D, d/m/Y', $timestamp ),
            'time'           => wp_date( 'H:i', $timestamp ),
            'available'      => $state['available'],
            'capacity'       => $capacity,
            'booking_open'   => $state['booking_open'],
            'state'          => $state['state'],
            'state_label'    => $state['label'],
            'duration'       => absint( get_post_meta( $departure_id, '_wcai_duration_minutes', true ) ),
            'meeting_point'  => sanitize_text_field( get_post_meta( $departure_id, '_wcai_meeting_point', true ) ),
            'guide'          => $guide ? sanitize_text_field( $guide->display_name ) : '',
            'cutoff'         => class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_booking_cutoff_label( $departure_id ) : '',
        );
    }

    public function render_product_facts() {
        global $product;

        if ( ! $product || ! $this->is_target_product( $product->get_id() ) ) {
            return;
        }

        $facts = array();

        foreach ( $product->get_attributes() as $attribute ) {
            if ( ! $attribute->get_visible() ) {
                continue;
            }

            $label = wc_attribute_label( $attribute->get_name() );
            $values = $attribute->is_taxonomy()
                ? wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) )
                : $attribute->get_options();

            $values = array_filter( array_map( 'sanitize_text_field', (array) $values ) );

            if ( $label && ! empty( $values ) ) {
                $facts[] = array(
                    'label' => $label,
                    'value' => implode( ', ', array_slice( $values, 0, 3 ) ),
                );
            }

            if ( count( $facts ) >= 4 ) {
                break;
            }
        }

        if ( empty( $facts ) ) {
            return;
        }

        echo '<section class="wcai-tour-facts" aria-label="Informações do passeio">';
        echo '<div class="wcai-tour-facts-heading"><strong>Sobre este passeio</strong><small>Informações principais</small></div>';
        echo '<div class="wcai-tour-facts-grid">';
        foreach ( $facts as $fact ) {
            echo '<div><span>' . esc_html( $fact['label'] ) . '</span><strong>' . esc_html( $fact['value'] ) . '</strong></div>';
        }
        echo '</div>';
        echo '</section>';

        $this->enqueue_styles();
    }

    public function render_departure_selector() {
        global $product;

        if ( ! $product || ! $this->is_target_product( $product->get_id() ) ) {
            return;
        }

        $departures = class_exists( 'WCAI_Reservations' )
            ? WCAI_Reservations::get_calendar_departures_for_product( $product->get_id(), 0 )
            : array();

        $calendar_departures = array();

        foreach ( $departures as $departure ) {
            $payload = $this->departure_payload( $departure->ID, 1 );

            if ( $payload ) {
                $calendar_departures[] = $payload;
            }
        }

        echo '<section class="wcai-product-booking" aria-labelledby="wcai-booking-title">';
        echo '<div class="wcai-booking-top">';
        echo '<div>';
        echo '<span class="wcai-booking-eyebrow">RESERVE SEU PASSEIO</span>';
        echo '<h2 id="wcai-booking-title">Escolha quando você quer ir</h2>';
        echo '<p>Veja as datas, horários e vagas disponíveis antes de reservar.</p>';
        echo '</div>';
        echo '<div class="wcai-booking-price">' . wp_kses_post( $product->get_price_html() ) . '<small>por participante</small></div>';
        echo '</div>';

        if ( empty( $calendar_departures ) ) {
            echo '<div class="wcai-booking-empty"><strong>Nenhuma saída disponível no momento.</strong><p>Assim que uma nova data for aberta, ela aparecerá aqui.</p></div>';
            echo '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="">';
            echo '</section>';
            $this->enqueue_styles();
            return;
        }

        $first = reset( $calendar_departures );
        $highlights = array();

        if ( ! empty( $first['duration'] ) ) {
            $highlights[] = array( 'label' => 'Duração', 'value' => $first['duration'] . ' min' );
        }

        if ( ! empty( $first['meeting_point'] ) ) {
            $highlights[] = array( 'label' => 'Encontro', 'value' => $first['meeting_point'] );
        }

        if ( ! empty( $first['capacity'] ) ) {
            $highlights[] = array( 'label' => 'Grupo', 'value' => $first['capacity'] . ' vagas por saída' );
        }

        if ( ! empty( $highlights ) ) {
            echo '<div class="wcai-booking-highlights">';
            foreach ( $highlights as $highlight ) {
                echo '<div><span>' . esc_html( $highlight['label'] ) . '</span><strong>' . esc_html( $highlight['value'] ) . '</strong></div>';
            }
            echo '</div>';
        }

        echo '<div class="wcai-booking-section">';
        echo '<div class="wcai-booking-section-title"><span>1</span><div><strong>Escolha a data</strong><small>As datas indicam a disponibilidade da saída.</small></div></div>';
        echo '<div class="wcai-calendar" data-wcai-calendar data-product-id="' . esc_attr( $product->get_id() ) . '" data-ajax-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'wcai_calendar_availability' ) ) . '" data-departures="' . esc_attr( wp_json_encode( $calendar_departures ) ) . '">';
        echo '<div class="wcai-calendar-toolbar">';
        echo '<button type="button" class="wcai-calendar-nav" data-calendar-prev aria-label="Mês anterior">&lsaquo;</button>';
        echo '<strong class="wcai-calendar-month" data-calendar-month></strong>';
        echo '<button type="button" class="wcai-calendar-nav" data-calendar-next aria-label="Próximo mês">&rsaquo;</button>';
        echo '</div>';
        echo '<div class="wcai-calendar-weekdays" aria-hidden="true">';
        foreach ( array( 'Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb' ) as $weekday ) {
            echo '<span>' . esc_html( $weekday ) . '</span>';
        }
        echo '</div>';
        echo '<div class="wcai-calendar-grid" data-calendar-grid role="grid" aria-label="Calendário de disponibilidade"></div>';
        echo '<div class="wcai-calendar-legend">';
        echo '<span><i class="is-available"></i> Disponível</span>';
        echo '<span><i class="is-few"></i> Últimas vagas</span>';
        echo '<span><i class="is-full"></i> Lotado</span>';
        echo '<span><i class="is-closed"></i> Encerrado</span>';
        echo '</div>';

        echo '<div class="wcai-calendar-selection" data-calendar-selection hidden>';
        echo '<div class="wcai-selection-heading"><strong>Horários disponíveis</strong><small data-calendar-selected-date></small></div>';
        echo '<div class="wcai-time-options" data-calendar-times></div>';
        echo '</div>';

        echo '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="" data-wcai-departure-input required>';
        echo '<div class="wcai-selected-departure" data-calendar-selected-departure hidden></div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="wcai-booking-next-step">';
        echo '<strong>2</strong><div><b>Quantidade de participantes</b><small>Depois de escolher o horário, informe a quantidade no campo abaixo.</small></div>';
        echo '</div>';

        echo '<p class="wcai-booking-note">A disponibilidade é atualizada novamente quando você escolhe o horário. O sistema também valida a vaga no carrinho e no checkout.</p>';
        echo '</section>';

        $this->enqueue_styles();
        $this->enqueue_calendar_script();
    }

    public function ajax_refresh_calendar_date() {
        check_ajax_referer( 'wcai_calendar_availability', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
        $date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
        $quantity = isset( $_POST['quantity'] ) ? max( 1, absint( wp_unslash( $_POST['quantity'] ) ) ) : 1;

        if ( ! $product_id || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! $this->is_target_product( $product_id ) ) {
            wp_send_json_error( array( 'message' => 'Parâmetros inválidos.' ), 400 );
        }

        if ( ! class_exists( 'WCAI_Reservations' ) ) {
            wp_send_json_error( array( 'message' => 'Disponibilidade indisponível.' ), 500 );
        }

        $departures = WCAI_Reservations::get_calendar_departures_for_product( $product_id, 0 );
        $result = array();

        foreach ( $departures as $departure ) {
            $payload = $this->departure_payload( $departure->ID, $quantity );

            if ( $payload && $payload['date'] === $date ) {
                $result[] = $payload;
            }
        }

        wp_send_json_success(
            array(
                'date'        => $date,
                'quantity'    => $quantity,
                'departures'  => $result,
            )
        );
    }

    private function enqueue_calendar_script() {
        if ( wp_script_is( 'wcai-product-booking-calendar', 'enqueued' ) ) {
            return;
        }

        wp_register_script( 'wcai-product-booking-calendar', false, array(), WCAI_VERSION, true );
        wp_enqueue_script( 'wcai-product-booking-calendar' );

        wp_add_inline_script(
            'wcai-product-booking-calendar',
            <<<'JS'
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
        var times = root.querySelector('[data-calendar-times]');
        var selection = root.querySelector('[data-calendar-selection]');
        var selectedDateLabel = root.querySelector('[data-calendar-selected-date]');
        var selectedDeparture = root.querySelector('[data-calendar-selected-departure]');
        var input = root.querySelector('[data-wcai-departure-input]');
        var quantityInput = document.querySelector('input.qty[name="quantity"], input.qty, input[name="quantity"]');
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

        function quantity() {
            var value = quantityInput ? parseInt(quantityInput.value, 10) : 1;
            return Math.max(1, value || 1);
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
                return best <= 3 ? { state: 'few', label: best + ' vaga' + (best === 1 ? '' : 's') } : { state: 'available', label: best + ' vagas' };
            }

            var openItems = items.filter(function (item) { return item.booking_open; });
            var hasSeats = openItems.some(function (item) { return parseInt(item.available, 10) > 0; });

            if (hasSeats) return { state: 'insufficient', label: 'menos vagas' };
            if (items.some(function (item) { return item.state === 'closed'; })) return { state: 'closed', label: 'encerrado' };
            return { state: 'full', label: 'lotado' };
        }

        function monthName(key) {
            var parts = key.split('-');
            var date = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, 1);
            return date.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
        }

        function updateDepartureSnapshot(items) {
            items.forEach(function (nextDeparture) {
                departures = departures.filter(function (item) {
                    return String(item.id) !== String(nextDeparture.id);
                });
                departures.push(nextDeparture);
            });

            departures.sort(function (a, b) {
                return (a.date + ' ' + a.time).localeCompare(b.date + ' ' + b.time);
            });
        }

        function ajaxForDate(date, done) {
            if (loading || !ajaxUrl || !nonce) {
                done(dateDepartures(date));
                return;
            }

            loading = true;
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
                .then(function (response) { return response.json(); })
                .then(function (response) {
                    if (response && response.success && response.data && Array.isArray(response.data.departures)) {
                        updateDepartureSnapshot(response.data.departures);
                        done(response.data.departures);
                    } else {
                        done(dateDepartures(date));
                    }
                })
                .catch(function () {
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

            if (!options.length) {
                selection.hidden = false;
                times.innerHTML = '<div class="wcai-no-times">Não há saída registrada para este dia.</div>';
                return;
            }

            selection.hidden = false;

            options.forEach(function (departure) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'wcai-time-option state-' + departure.state;
                button.setAttribute('data-departure-id', departure.id);
                button.disabled = !qualifies(departure);

                var time = document.createElement('strong');
                time.textContent = departure.time;
                button.appendChild(time);

                var detail = document.createElement('span');
                detail.textContent = departure.state_label;
                button.appendChild(detail);

                var meta = document.createElement('small');
                var parts = [];
                if (departure.duration) parts.push(departure.duration + ' min');
                if (departure.meeting_point) parts.push(departure.meeting_point);
                if (departure.guide) parts.push('Guia: ' + departure.guide);
                if (departure.cutoff && departure.booking_open) parts.push('Reserva até ' + departure.cutoff);
                meta.textContent = parts.join(' • ');
                button.appendChild(meta);

                if (String(selectedDepartureId) === String(departure.id)) {
                    button.classList.add('is-selected');
                }

                button.addEventListener('click', function () {
                    if (button.disabled) return;

                    selectedDepartureId = String(departure.id);
                    input.value = departure.id;

                    root.querySelectorAll('.wcai-time-option').forEach(function (item) {
                        item.classList.remove('is-selected');
                    });
                    button.classList.add('is-selected');

                    selectedDeparture.innerHTML = '<div><strong>' + departure.date_label + ' às ' + departure.time + '</strong><span>' + departure.state_label + '</span></div><small>Agora confirme a quantidade de participantes no campo abaixo.</small>';
                    selectedDeparture.hidden = false;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    enableAddToCart();
                });

                times.appendChild(button);
            });
        }

        function selectDate(date, dateElement) {
            selectedDate = date;
            selectedDepartureId = '';
            input.value = '';
            selectedDeparture.hidden = true;

            root.querySelectorAll('.wcai-calendar-day').forEach(function (item) {
                item.classList.remove('is-selected');
            });

            if (dateElement) dateElement.classList.add('is-selected');

            ajaxForDate(date, function (items) {
                renderTimes(date, items);
            });
        }

        function renderCalendar() {
            var key = monthKeys[monthIndex];
            if (!key) return;

            monthLabel.textContent = monthName(key);
            grid.innerHTML = '';

            var parts = key.split('-');
            var year = parseInt(parts[0], 10);
            var month = parseInt(parts[1], 10) - 1;
            var firstDay = new Date(year, month, 1).getDay();
            var daysInMonth = new Date(year, month + 1, 0).getDate();

            for (var blank = 0; blank < firstDay; blank++) {
                var empty = document.createElement('span');
                empty.className = 'wcai-calendar-day is-empty';
                grid.appendChild(empty);
            }

            for (var day = 1; day <= daysInMonth; day++) {
                var date = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
                var state = dateState(date);
                var items = dateDepartures(date);
                var dayEl;

                if (state.state !== 'empty') {
                    dayEl = document.createElement('button');
                    dayEl.type = 'button';
                    dayEl.className = 'wcai-calendar-day state-' + state.state;
                    dayEl.setAttribute('role', 'gridcell');
                    dayEl.setAttribute('aria-label', day + ' ' + monthName(key) + ', ' + (state.label || state.state));
                    dayEl.addEventListener('click', function (dateValue) {
                        return function () {
                            if (dateState(dateValue).state === 'closed' || dateState(dateValue).state === 'full' || dateState(dateValue).state === 'insufficient') {
                                selectDate(dateValue, this);
                                return;
                            }

                            selectDate(dateValue, this);
                        };
                    }(date));

                    var number = document.createElement('strong');
                    number.textContent = day;
                    dayEl.appendChild(number);

                    var label = document.createElement('small');
                    label.textContent = state.label;
                    dayEl.appendChild(label);
                } else {
                    dayEl = document.createElement('span');
                    dayEl.className = 'wcai-calendar-day is-empty-date';
                    dayEl.setAttribute('aria-hidden', 'true');
                    dayEl.textContent = day;
                }

                grid.appendChild(dayEl);
            }

            prev.disabled = monthIndex <= 0;
            next.disabled = monthIndex >= monthKeys.length - 1;

            if (selectedDate && selectedDate.slice(0, 7) === key) {
                var dayNumber = parseInt(selectedDate.slice(8, 10), 10);
                grid.querySelectorAll('.wcai-calendar-day').forEach(function (item) {
                    var strong = item.querySelector('strong');
                    if (strong && parseInt(strong.textContent, 10) === dayNumber) item.classList.add('is-selected');
                });
            }
        }

        function enableAddToCart() {
            var buttons = document.querySelectorAll('button.single_add_to_cart_button, input.single_add_to_cart_button');
            buttons.forEach(function (button) {
                button.disabled = !selectedDepartureId;
                button.classList.toggle('disabled', !selectedDepartureId);
            });
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
                }
            }

            renderCalendar();

            if (selectedDate) {
                ajaxForDate(selectedDate, function (items) {
                    renderTimes(selectedDate, items);
                });
            }

            enableAddToCart();
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

        if (quantityInput) {
            quantityInput.addEventListener('change', refreshForQuantity);
            quantityInput.addEventListener('input', refreshForQuantity);
        }

        enableAddToCart();
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
JS
        );
    }

    private function enqueue_styles() {
        if ( wp_style_is( 'wcai-product-booking-inline', 'enqueued' ) ) {
            return;
        }

        wp_register_style( 'wcai-product-booking-inline', false, array(), WCAI_VERSION );
        wp_enqueue_style( 'wcai-product-booking-inline' );
        wp_add_inline_style(
            'wcai-product-booking-inline',
            '.wcai-product-booking{margin:24px 0;padding:24px;border:1px solid #dedede;border-radius:16px;background:#fff;box-shadow:0 8px 30px rgba(0,0,0,.05);position:relative}.wcai-booking-top{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;margin-bottom:18px}.wcai-booking-eyebrow{display:block;font-size:11px;letter-spacing:.14em;font-weight:800;opacity:.58}.wcai-booking-top h2{margin:4px 0 6px;font-size:25px;line-height:1.2}.wcai-booking-top p{margin:0;color:#666}.wcai-tour-facts{margin:0 0 18px;padding:14px 16px;border:1px solid #ececec;border-radius:12px;background:#fff}.wcai-tour-facts-heading{display:flex;justify-content:space-between;gap:12px;align-items:baseline;margin-bottom:10px}.wcai-tour-facts-heading strong{font-size:15px}.wcai-tour-facts-heading small{font-size:11px;color:#777}.wcai-tour-facts-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.wcai-tour-facts-grid>div{min-width:0}.wcai-tour-facts-grid span{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.05em;color:#888}.wcai-tour-facts-grid strong{display:block;margin-top:2px;font-size:12px;line-height:1.35}.wcai-booking-price{text-align:right;font-weight:800;font-size:20px;white-space:nowrap}.wcai-booking-price small{display:block;margin-top:2px;font-size:11px;font-weight:500;color:#777}.wcai-booking-highlights{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:0 0 20px}.wcai-booking-highlights>div{padding:10px 12px;border:1px solid #ececec;border-radius:10px;background:#fafafa}.wcai-booking-highlights span{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:#777}.wcai-booking-highlights strong{display:block;margin-top:3px;font-size:13px;line-height:1.35}.wcai-booking-section{padding-top:2px}.wcai-booking-section-title{display:flex;align-items:flex-start;gap:10px;margin-bottom:14px}.wcai-booking-section-title>span,.wcai-booking-next-step>strong{display:flex;align-items:center;justify-content:center;width:27px;height:27px;border-radius:50%;background:#222;color:#fff;font-size:12px;flex:0 0 27px}.wcai-booking-section-title strong{display:block}.wcai-booking-section-title small{display:block;margin-top:2px;color:#777}.wcai-calendar{max-width:100%}.wcai-calendar-toolbar{display:grid;grid-template-columns:42px 1fr 42px;align-items:center;gap:8px;margin-bottom:12px}.wcai-calendar-month{text-align:center;text-transform:capitalize;font-size:18px}.wcai-calendar-nav{width:42px;height:38px;border:1px solid #ddd;border-radius:9px;background:#fff;font-size:24px;line-height:1;cursor:pointer}.wcai-calendar-nav:hover:not(:disabled){border-color:#999}.wcai-calendar-nav:disabled{opacity:.35;cursor:not-allowed}.wcai-calendar.is-loading{opacity:.72}.wcai-calendar-weekdays,.wcai-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:5px}.wcai-calendar-weekdays{margin-bottom:5px}.wcai-calendar-weekdays span{text-align:center;font-size:10px;font-weight:800;text-transform:uppercase;color:#888;padding:4px 0}.wcai-calendar-day{min-height:54px;border-radius:10px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:2px;padding:4px;box-sizing:border-box}.wcai-calendar-day.is-empty{visibility:hidden}.wcai-calendar-day.is-empty-date{color:#bbb;border:1px solid transparent}.wcai-calendar-day.state-available,.wcai-calendar-day.state-few,.wcai-calendar-day.state-insufficient,.wcai-calendar-day.state-full,.wcai-calendar-day.state-closed{border:1px solid #dedede;background:#fff;cursor:pointer}.wcai-calendar-day.state-available:hover,.wcai-calendar-day.state-few:hover{border-color:#666}.wcai-calendar-day.state-available{box-shadow:inset 0 -3px 0 #3f8f5b}.wcai-calendar-day.state-few{box-shadow:inset 0 -3px 0 #c58a21}.wcai-calendar-day.state-insufficient{box-shadow:inset 0 -3px 0 #888}.wcai-calendar-day.state-full{opacity:.58;box-shadow:inset 0 -3px 0 #b54b4b}.wcai-calendar-day.state-closed{opacity:.58;box-shadow:inset 0 -3px 0 #999}.wcai-calendar-day.is-selected{outline:2px solid #222;outline-offset:1px}.wcai-calendar-day strong{font-size:15px}.wcai-calendar-day small{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:9px;color:#777}.wcai-calendar-legend{display:flex;flex-wrap:wrap;gap:12px;margin:12px 0 18px;color:#666;font-size:11px}.wcai-calendar-legend span{display:flex;align-items:center;gap:5px}.wcai-calendar-legend i{width:8px;height:8px;border-radius:50%;display:inline-block;border:1px solid #999}.wcai-calendar-legend .is-available{background:#3f8f5b}.wcai-calendar-legend .is-few{background:#c58a21}.wcai-calendar-legend .is-full{background:#b54b4b}.wcai-calendar-legend .is-closed{background:#999}.wcai-calendar-selection{border-top:1px solid #eee;padding-top:16px;margin-top:2px}.wcai-selection-heading{display:flex;justify-content:space-between;gap:16px;align-items:baseline;margin-bottom:10px}.wcai-selection-heading strong{font-size:16px}.wcai-selection-heading small{color:#777}.wcai-time-options{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:10px}.wcai-time-option{display:grid;grid-template-columns:auto 1fr;column-gap:11px;row-gap:4px;text-align:left;padding:14px;border:1px solid #ddd;border-radius:11px;background:#fff;cursor:pointer;transition:.15s}.wcai-time-option:hover:not(:disabled){border-color:#777}.wcai-time-option:disabled{cursor:not-allowed;opacity:.55;background:#f8f8f8}.wcai-time-option strong{font-size:19px;grid-row:1/span 2}.wcai-time-option span{font-size:12px;font-weight:700}.wcai-time-option small{grid-column:2;font-size:10px;line-height:1.35;color:#777}.wcai-time-option.state-few{border-color:#c58a21}.wcai-time-option.state-full,.wcai-time-option.state-closed{border-color:#e2e2e2}.wcai-time-option.is-selected{border-color:#222;box-shadow:0 0 0 2px rgba(0,0,0,.07);background:#fafafa}.wcai-no-times{padding:14px;border:1px dashed #ccc;border-radius:10px;color:#666}.wcai-selected-departure{margin-top:10px;padding:12px 14px;border-radius:10px;background:#f5f5f5}.wcai-selected-departure>div{display:flex;justify-content:space-between;gap:12px;align-items:baseline}.wcai-selected-departure span{color:#666;font-size:12px}.wcai-selected-departure small{display:block;margin-top:4px;color:#777}.wcai-booking-next-step{display:flex;align-items:center;gap:10px;margin-top:18px;padding:12px 0 0;border-top:1px solid #eee}.wcai-booking-next-step b{display:block}.wcai-booking-next-step small{display:block;color:#777;margin-top:2px}.wcai-booking-note{margin:12px 0 0;color:#777;font-size:11px;line-height:1.45}.wcai-booking-empty{padding:20px;border:1px dashed #ccc;border-radius:10px;background:#fafafa}.wcai-booking-empty p{margin:6px 0 0;color:#777}@media(max-width:700px){.wcai-product-booking{padding:18px;margin:18px 0}.wcai-tour-facts-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.wcai-booking-top{display:block}.wcai-booking-price{text-align:left;margin-top:10px}.wcai-booking-top h2{font-size:22px}.wcai-booking-highlights{grid-template-columns:1fr}.wcai-calendar-day{min-height:48px}.wcai-time-options{grid-template-columns:1fr}.wcai-selection-heading{display:block}.wcai-selection-heading small{display:block;margin-top:3px}}'
        );
    }

    public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0 ) {
        if ( ! $this->is_target_product( $product_id, $variation_id ) ) {
            return $passed;
        }

        $departure_id = isset( $_POST[ self::FIELD ] ) ? absint( wp_unslash( $_POST[ self::FIELD ] ) ) : 0;

        if ( ! $departure_id ) {
            wc_add_notice( 'Selecione a data e o horário da atividade.', 'error' );
            return false;
        }

        if ( ! class_exists( 'WCAI_Reservations' ) || ! WCAI_Reservations::is_available_for_product( $departure_id, $product_id, $variation_id, $quantity ) ) {
            wc_add_notice( 'A saída selecionada não está disponível para a quantidade informada.', 'error' );
            return false;
        }

        return $passed;
    }

    public function add_cart_item_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
        if ( ! $this->is_target_product( $product_id, $variation_id ) ) {
            return $cart_item_data;
        }

        $departure_id = isset( $_POST[ self::FIELD ] ) ? absint( wp_unslash( $_POST[ self::FIELD ] ) ) : 0;

        if ( $departure_id ) {
            $cart_item_data['wcai_departure_id'] = $departure_id;
        }

        return $cart_item_data;
    }

    public function render_cart_item_data( $item_data, $cart_item ) {
        if ( empty( $cart_item['wcai_departure_id'] ) ) {
            return $item_data;
        }

        $departure_id = absint( $cart_item['wcai_departure_id'] );
        $start = WCAI_Data_Resolver::get_departure_start( $departure_id );
        $timestamp = WCAI_Data_Resolver::parse_timestamp( $start );
        $label = $timestamp ? wp_date( 'd/m/Y H:i', $timestamp ) : $start;

        if ( $label ) {
            $item_data[] = array(
                'key'   => 'Data da atividade',
                'value' => $label,
            );
        }

        return $item_data;
    }
}

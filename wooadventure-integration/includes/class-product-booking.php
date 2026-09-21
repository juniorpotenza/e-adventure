<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Product_Booking {

    const FIELD = 'wcai_product_departure_id';

    public function __construct() {
        add_action( 'woocommerce_before_add_to_cart_button', array( $this, 'render_departure_selector' ), 20 );
        add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 5 );
        add_filter( 'woocommerce_add_cart_item_data', array( $this, 'add_cart_item_data' ), 10, 4 );
        add_filter( 'woocommerce_get_item_data', array( $this, 'render_cart_item_data' ), 10, 2 );
    }

    private function is_target_product( $product_id, $variation_id = 0 ) {
        $target_ids = WCAI_Settings::get_product_ids();

        return in_array( absint( $product_id ), $target_ids, true )
            || in_array( absint( $variation_id ), $target_ids, true );
    }

    public function render_departure_selector() {
        global $product;

        if ( ! $product || ! $this->is_target_product( $product->get_id() ) ) {
            return;
        }

        $departures = class_exists( 'WCAI_Reservations' )
            ? WCAI_Reservations::get_open_departures_for_product( $product->get_id(), 0 )
            : array();

        $calendar_departures = array();

        foreach ( $departures as $departure ) {
            $available = WCAI_Reservations::get_available_quantity( $departure->ID );

            if ( $available < 1 ) {
                continue;
            }

            $starts_at = WCAI_Data_Resolver::get_departure_start( $departure->ID );
            $timestamp = WCAI_Data_Resolver::parse_timestamp( $starts_at );

            if ( ! $timestamp ) {
                continue;
            }

            $calendar_departures[] = array(
                'id' => absint( $departure->ID ),
                'date' => wp_date( 'Y-m-d', $timestamp ),
                'date_label' => wp_date( 'D, d/m/Y', $timestamp ),
                'time' => wp_date( 'H:i', $timestamp ),
                'available' => absint( $available ),
                'duration' => absint( get_post_meta( $departure->ID, '_wcai_duration_minutes', true ) ),
                'meeting_point' => sanitize_text_field( get_post_meta( $departure->ID, '_wcai_meeting_point', true ) ),
                'cutoff' => WCAI_Reservations::get_booking_cutoff_label( $departure->ID ),
            );
        }

        usort(
            $calendar_departures,
            static function( $a, $b ) {
                return strcmp( $a['date'] . ' ' . $a['time'], $b['date'] . ' ' . $b['time'] );
            }
        );

        echo '<section class="wcai-product-booking" aria-labelledby="wcai-booking-title">';
        echo '<div class="wcai-booking-header">';
        echo '<div><span class="wcai-booking-eyebrow">AGENDAMENTO</span><h2 id="wcai-booking-title">Escolha o dia do seu passeio</h2><p>Veja no calendário somente as datas com saída disponível.</p></div>';
        echo '<span class="wcai-booking-step">1. Data e horário</span>';
        echo '</div>';

        if ( empty( $calendar_departures ) ) {
            echo '<div class="wcai-booking-empty"><strong>Nenhuma saída disponível no momento.</strong><p>Assim que uma nova data for aberta, ela aparecerá aqui.</p></div>';
            echo '</section>';
            $this->enqueue_styles();
            return;
        }

        echo '<div class="wcai-calendar" data-wcai-calendar data-departures="' . esc_attr( wp_json_encode( $calendar_departures ) ) . '">';
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
        echo '<div class="wcai-calendar-legend"><span><i class="is-available"></i> Data disponível</span><span><i class="is-unavailable"></i> Sem saída</span></div>';

        echo '<div class="wcai-calendar-selection" data-calendar-selection hidden>';
        echo '<div class="wcai-selection-title">Escolha o horário</div>';
        echo '<p class="wcai-selection-date" data-calendar-selected-date></p>';
        echo '<div class="wcai-time-options" data-calendar-times></div>';
        echo '</div>';

        echo '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="" data-wcai-departure-input required>';
        echo '<div class="wcai-selected-departure" data-calendar-selected-departure hidden></div>';
        echo '<p class="wcai-booking-note">A disponibilidade é recalculada conforme a quantidade de participantes e validada novamente no carrinho e no checkout.</p>';
        echo '</div>';
        echo '</section>';

        $this->enqueue_styles();
        $this->enqueue_calendar_script();
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

        function quantity() {
            var value = quantityInput ? parseInt(quantityInput.value, 10) : 1;
            return Math.max(1, value || 1);
        }

        function availableForQuantity(departure) {
            return parseInt(departure.available, 10) >= quantity();
        }

        function availableOnDate(date) {
            return departures.filter(function (departure) {
                return departure.date === date && availableForQuantity(departure);
            });
        }

        function monthName(key) {
            var parts = key.split('-');
            var date = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, 1);
            return date.toLocaleDateString('pt-BR', { month: 'long', year: 'numeric' });
        }

        function renderTimes(date) {
            var options = availableOnDate(date);
            times.innerHTML = '';

            if (!options.length) {
                selection.hidden = true;
                return;
            }

            selectedDateLabel.textContent = options[0].date_label;
            selection.hidden = false;

            options.forEach(function (departure) {
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'wcai-time-option';
                button.setAttribute('data-departure-id', departure.id);

                var time = document.createElement('strong');
                time.textContent = departure.time;
                button.appendChild(time);

                var meta = document.createElement('span');
                meta.textContent = departure.available + (departure.available === 1 ? ' vaga disponível' : ' vagas disponíveis');
                button.appendChild(meta);

                if (departure.duration) {
                    var duration = document.createElement('small');
                    duration.textContent = departure.duration + ' min';
                    button.appendChild(duration);
                }

                if (String(selectedDepartureId) === String(departure.id)) {
                    button.classList.add('is-selected');
                }

                button.addEventListener('click', function () {
                    selectedDepartureId = String(departure.id);
                    input.value = departure.id;

                    root.querySelectorAll('.wcai-time-option').forEach(function (item) {
                        item.classList.remove('is-selected');
                    });
                    button.classList.add('is-selected');

                    selectedDeparture.innerHTML = '<strong>' + departure.date_label + ' às ' + departure.time + '</strong>';
                    selectedDeparture.hidden = false;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                });

                times.appendChild(button);
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
                var options = availableOnDate(date);
                var dayEl;

                if (options.length) {
                    dayEl = document.createElement('button');
                    dayEl.type = 'button';
                    dayEl.className = 'wcai-calendar-day is-available';
                    dayEl.setAttribute('role', 'gridcell');
                    dayEl.setAttribute('aria-label', options[0].date_label + ', disponível');
                    dayEl.addEventListener('click', function (dateValue) {
                        return function () {
                            selectedDate = dateValue;
                            root.querySelectorAll('.wcai-calendar-day.is-available').forEach(function (item) {
                                item.classList.remove('is-selected');
                            });
                            this.classList.add('is-selected');
                            renderTimes(dateValue);
                        };
                    }(date));
                } else {
                    dayEl = document.createElement('span');
                    dayEl.className = 'wcai-calendar-day is-unavailable';
                    dayEl.setAttribute('aria-hidden', 'true');
                }

                dayEl.textContent = day;
                grid.appendChild(dayEl);
            }

            prev.disabled = monthIndex <= 0;
            next.disabled = monthIndex >= monthKeys.length - 1;

            if (selectedDate && selectedDate.slice(0, 7) === key) {
                var all = grid.querySelectorAll('.wcai-calendar-day.is-available');
                var dayNumber = parseInt(selectedDate.slice(8, 10), 10);
                all.forEach(function (item) {
                    if (parseInt(item.textContent, 10) === dayNumber) item.classList.add('is-selected');
                });
                renderTimes(selectedDate);
            }
        }

        function refreshForQuantity() {
            if (selectedDepartureId) {
                var current = departures.filter(function (departure) {
                    return String(departure.id) === String(selectedDepartureId);
                })[0];

                if (!current || !availableForQuantity(current)) {
                    selectedDepartureId = '';
                    selectedDate = '';
                    input.value = '';
                    selectedDeparture.hidden = true;
                    selection.hidden = true;
                }
            }

            renderCalendar();
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
            '.wcai-product-booking{margin:28px 0;padding:24px;border:1px solid #ddd;border-radius:16px;background:#fff}.wcai-booking-header{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:22px}.wcai-booking-eyebrow{font-size:11px;letter-spacing:.12em;font-weight:700;opacity:.6}.wcai-booking-header h2{margin:4px 0 6px;font-size:24px}.wcai-booking-header p{margin:0;opacity:.75}.wcai-booking-step{font-size:12px;font-weight:600;padding:8px 11px;border-radius:999px;background:#f2f2f2;white-space:nowrap}.wcai-calendar{max-width:760px}.wcai-calendar-toolbar{display:grid;grid-template-columns:44px 1fr 44px;align-items:center;gap:8px;margin-bottom:14px}.wcai-calendar-month{text-align:center;text-transform:capitalize;font-size:18px}.wcai-calendar-nav{width:44px;height:40px;border:1px solid #ddd;border-radius:10px;background:#fff;font-size:25px;line-height:1;cursor:pointer}.wcai-calendar-nav:hover:not(:disabled){border-color:#999}.wcai-calendar-nav:disabled{opacity:.35;cursor:not-allowed}.wcai-calendar-weekdays,.wcai-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:6px}.wcai-calendar-weekdays{margin-bottom:6px}.wcai-calendar-weekdays span{text-align:center;font-size:11px;font-weight:700;opacity:.6;padding:5px 0}.wcai-calendar-day{min-height:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;box-sizing:border-box}.wcai-calendar-day.is-empty{visibility:hidden}.wcai-calendar-day.is-unavailable{opacity:.28;border:1px solid transparent}.wcai-calendar-day.is-available{border:1px solid #d7d7d7;background:#fff;cursor:pointer;font-weight:700}.wcai-calendar-day.is-available:hover{border-color:#777}.wcai-calendar-day.is-available.is-selected{border-color:#222;box-shadow:0 0 0 2px rgba(0,0,0,.08);background:#f5f5f5}.wcai-calendar-legend{display:flex;flex-wrap:wrap;gap:14px;margin:12px 0 18px;font-size:12px;opacity:.72}.wcai-calendar-legend span{display:flex;align-items:center;gap:6px}.wcai-calendar-legend i{width:9px;height:9px;border-radius:50%;display:inline-block;border:1px solid #999}.wcai-calendar-legend .is-available{background:#fff}.wcai-calendar-legend .is-unavailable{background:#ddd}.wcai-calendar-selection{border-top:1px solid #eee;padding-top:18px;margin-top:4px}.wcai-selection-title{font-size:16px;font-weight:700}.wcai-selection-date{margin:4px 0 12px;opacity:.72}.wcai-time-options{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:10px}.wcai-time-option{display:grid;grid-template-columns:auto 1fr;column-gap:10px;row-gap:2px;text-align:left;padding:13px 14px;border:1px solid #d7d7d7;border-radius:10px;background:#fff;cursor:pointer}.wcai-time-option strong{font-size:17px;grid-row:1 / span 2}.wcai-time-option span,.wcai-time-option small{font-size:11px;opacity:.72}.wcai-time-option.is-selected{border-color:#222;box-shadow:0 0 0 2px rgba(0,0,0,.08);background:#f5f5f5}.wcai-selected-departure{margin-top:12px;padding:12px 14px;border-radius:10px;background:#f6f6f6}.wcai-booking-note{margin:16px 0 0;font-size:12px;opacity:.7}.wcai-booking-empty{padding:20px;border:1px dashed #ccc;border-radius:10px}.wcai-booking-empty p{margin-bottom:0}@media(max-width:600px){.wcai-product-booking{padding:18px}.wcai-booking-header{display:block}.wcai-booking-header h2{font-size:21px}.wcai-booking-step{display:inline-block;margin-top:12px}.wcai-calendar-day{min-height:42px}.wcai-time-options{grid-template-columns:1fr}}'
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

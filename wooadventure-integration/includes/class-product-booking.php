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
        add_filter( 'woocommerce_cart_item_quantity', array( $this, 'render_cart_item_quantity' ), 10, 3 );
        add_filter( 'woocommerce_add_to_cart_quantity', array( $this, 'filter_add_to_cart_quantity' ), 10, 3 );
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_data' ), 10, 4 );
        add_action( 'woocommerce_single_product_summary', array( $this, 'render_product_facts' ), 16 );
        add_filter( 'woocommerce_product_data_tabs', array( $this, 'add_product_data_tab' ) );
        add_action( 'woocommerce_product_data_panels', array( $this, 'render_product_data_panel' ) );
        add_action( 'woocommerce_admin_process_product_object', array( $this, 'save_product_data' ) );
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
        $group = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_group_summary( $departure_id ) : array(
            'reserved'             => 0,
            'minimum'              => 0,
            'remaining_to_minimum' => 0,
            'formation_percent'    => 100,
            'status'               => 'open',
            'label'                => 'Reservas abertas',
            'detail'               => '',
        );
        $guide_id = absint( get_post_meta( $departure_id, '_wcai_guide_id', true ) );
        $guide = $guide_id ? get_userdata( $guide_id ) : false;

        return array(
            'id'                   => absint( $departure_id ),
            'date'                 => wp_date( 'Y-m-d', $timestamp ),
            'date_label'           => wp_date( 'D, d/m/Y', $timestamp ),
            'time'                 => wp_date( 'H:i', $timestamp ),
            'available'            => $state['available'],
            'capacity'             => $capacity,
            'booking_open'         => $state['booking_open'],
            'state'                => $state['state'],
            'state_label'          => $state['label'],
            'reserved'             => absint( $group['reserved'] ),
            'minimum'              => absint( $group['minimum'] ),
            'remaining_to_minimum' => absint( $group['remaining_to_minimum'] ),
            'formation_percent'    => min( 100, max( 0, absint( $group['formation_percent'] ) ) ),
            'group_status'         => sanitize_key( $group['status'] ),
            'group_label'          => sanitize_text_field( $group['label'] ),
            'group_detail'         => sanitize_text_field( $group['detail'] ),
            'duration'             => absint( get_post_meta( $departure_id, '_wcai_duration_minutes', true ) ),
            'meeting_point'        => sanitize_text_field( get_post_meta( $departure_id, '_wcai_meeting_point', true ) ),
            'guide'                => $guide ? sanitize_text_field( $guide->display_name ) : '',
            'cutoff'               => class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_booking_cutoff_label( $departure_id ) : '',
        );
    }

    public function add_product_data_tab( $tabs ) {
        $tabs['wcai_tour_data'] = array(
            'label'    => 'Passeio',
            'target'   => 'wcai_tour_data',
            'class'    => array(),
            'priority' => 75,
        );

        return $tabs;
    }

    public function render_product_data_panel() {
        global $post;

        if ( ! $post ) {
            return;
        }

        $age_min = absint( get_post_meta( $post->ID, '_wcai_tour_age_min', true ) );
        $age_max = absint( get_post_meta( $post->ID, '_wcai_tour_age_max', true ) );
        $children_allowed = 'yes' === get_post_meta( $post->ID, '_wcai_tour_children_allowed', true );
        $children_age_max = absint( get_post_meta( $post->ID, '_wcai_tour_children_age_max', true ) );
        $children_max = absint( get_post_meta( $post->ID, '_wcai_tour_children_max', true ) );

        echo '<div id="wcai_tour_data" class="panel woocommerce_options_panel">';
        echo '<div class="options_group">';
        echo '<p style="padding:0 12px 8px;"><strong>Informações do público</strong><br><span style="color:#646970;">Esses dados aparecem na página do passeio para o cliente entender rapidamente para quem a experiência é indicada.</span></p>';

        woocommerce_wp_text_input(
            array(
                'id'                => 'wcai_tour_age_min',
                'label'             => 'Idade mínima',
                'type'              => 'number',
                'desc_tip'          => true,
                'description'       => 'Deixe em branco quando não houver limite mínimo.',
                'custom_attributes' => array(
                    'min'  => '0',
                    'max'  => '120',
                    'step' => '1',
                ),
                'value'             => $age_min ?: '',
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'                => 'wcai_tour_age_max',
                'label'             => 'Idade máxima',
                'type'              => 'number',
                'desc_tip'          => true,
                'description'       => 'Deixe em branco quando não houver limite máximo.',
                'custom_attributes' => array(
                    'min'  => '0',
                    'max'  => '120',
                    'step' => '1',
                ),
                'value'             => $age_max ?: '',
            )
        );

        woocommerce_wp_checkbox(
            array(
                'id'          => 'wcai_tour_children_allowed',
                'label'       => 'Permite crianças',
                'description' => 'Ative quando crianças puderem participar desta experiência.',
                'desc_tip'    => true,
                'value'       => $children_allowed ? 'yes' : 'no',
                'cbvalue'     => 'yes',
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'                => 'wcai_tour_children_age_max',
                'label'             => 'Idade máxima para categoria criança',
                'type'              => 'number',
                'desc_tip'          => true,
                'description'       => 'Opcional. Use para deixar clara a faixa de idade considerada criança.',
                'custom_attributes' => array(
                    'min'  => '0',
                    'max'  => '120',
                    'step' => '1',
                ),
                'value'             => $children_age_max ?: '',
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'                => 'wcai_tour_children_max',
                'label'             => 'Máximo de crianças por reserva',
                'type'              => 'number',
                'desc_tip'          => true,
                'description'       => 'Informativo para o cliente; não altera automaticamente a quantidade de ingressos.',
                'custom_attributes' => array(
                    'min'  => '0',
                    'max'  => '100',
                    'step' => '1',
                ),
                'value'             => $children_max ?: '',
            )
        );

        echo '<p style="padding:0 12px 12px;color:#646970;">A capacidade e o mínimo para formação do grupo continuam sendo definidos na Programação/Saída, porque podem variar por data.</p>';
        echo '</div>';
        echo '</div>';
    }

    public function save_product_data( $product ) {
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        $age_min = isset( $_POST['wcai_tour_age_min'] ) ? min( 120, max( 0, absint( wp_unslash( $_POST['wcai_tour_age_min'] ) ) ) ) : 0;
        $age_max = isset( $_POST['wcai_tour_age_max'] ) ? min( 120, max( 0, absint( wp_unslash( $_POST['wcai_tour_age_max'] ) ) ) ) : 0;

        if ( $age_min && $age_max && $age_max < $age_min ) {
            $age_max = $age_min;
        }

        $children_allowed = ! empty( $_POST['wcai_tour_children_allowed'] ) ? 'yes' : 'no';
        $children_age_max = isset( $_POST['wcai_tour_children_age_max'] ) ? min( 120, max( 0, absint( wp_unslash( $_POST['wcai_tour_children_age_max'] ) ) ) ) : 0;
        $children_max = isset( $_POST['wcai_tour_children_max'] ) ? min( 100, max( 0, absint( wp_unslash( $_POST['wcai_tour_children_max'] ) ) ) ) : 0;

        $product->update_meta_data( '_wcai_tour_age_min', $age_min ?: '' );
        $product->update_meta_data( '_wcai_tour_age_max', $age_max ?: '' );
        $product->update_meta_data( '_wcai_tour_children_allowed', $children_allowed );
        $product->update_meta_data( '_wcai_tour_children_age_max', ( 'yes' === $children_allowed && $children_age_max ) ? $children_age_max : '' );
        $product->update_meta_data( '_wcai_tour_children_max', ( 'yes' === $children_allowed && $children_max ) ? $children_max : '' );
    }

    private function get_tour_profile( $product_id ) {
        return array(
            'age_min'          => absint( get_post_meta( $product_id, '_wcai_tour_age_min', true ) ),
            'age_max'          => absint( get_post_meta( $product_id, '_wcai_tour_age_max', true ) ),
            'children_allowed' => 'yes' === get_post_meta( $product_id, '_wcai_tour_children_allowed', true ),
            'children_age_max' => absint( get_post_meta( $product_id, '_wcai_tour_children_age_max', true ) ),
            'children_max'     => absint( get_post_meta( $product_id, '_wcai_tour_children_max', true ) ),
        );
    }

    private function get_age_label( $profile ) {
        $min = absint( isset( $profile['age_min'] ) ? $profile['age_min'] : 0 );
        $max = absint( isset( $profile['age_max'] ) ? $profile['age_max'] : 0 );

        if ( $min && $max ) {
            return sprintf( '%d a %d anos', $min, $max );
        }

        if ( $min ) {
            return sprintf( 'A partir de %d anos', $min );
        }

        if ( $max ) {
            return sprintf( 'Até %d anos', $max );
        }

        return '';
    }

    public function render_product_facts() {
        global $product;

        if ( ! $product || ! $this->is_target_product( $product->get_id() ) ) {
            return;
        }

        $facts = array();
        $profile = $this->get_tour_profile( $product->get_id() );
        $age_label = $this->get_age_label( $profile );

        if ( $age_label ) {
            $facts[] = array(
                'label' => 'Faixa etária',
                'value' => $age_label,
            );
        }

        if ( ! empty( $profile['children_allowed'] ) ) {
            $facts[] = array(
                'label' => 'Crianças',
                'value' => ! empty( $profile['children_age_max'] )
                    ? sprintf( 'Até %d anos', absint( $profile['children_age_max'] ) )
                    : 'Permitidas',
            );
        }

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

            if ( count( $facts ) >= 6 ) {
                break;
            }
        }

        if ( empty( $facts ) ) {
            return;
        }

        echo '<section class="wcai-tour-facts" aria-label="Informações do passeio">';
        echo '<div class="wcai-tour-facts-heading"><strong>Sobre este passeio</strong><small>Informações principais e regras do público</small></div>';
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

        $profile = $this->get_tour_profile( $product->get_id() );
        $child_enabled = ! empty( $profile['children_allowed'] );
        $child_max = ! empty( $profile['children_max'] ) ? absint( $profile['children_max'] ) : 99;

        echo '<div class="wcai-booking-context">';
        echo '<strong>Escolha sua experiência</strong><span>Selecione a data e o horário. Depois informe quantos adultos e crianças participarão. Cada participante ocupa 1 vaga.</span>';
        echo '</div>';

        echo '<div class="wcai-booking-section">';
        echo '<div class="wcai-booking-section-title"><span>1</span><div><strong>Escolha a data</strong><small>As datas indicam a disponibilidade da saída.</small></div></div>';
        echo '<div class="wcai-calendar" data-wcai-calendar data-product-id="' . esc_attr( $product->get_id() ) . '" data-child-enabled="' . esc_attr( $child_enabled ? '1' : '0' ) . '" data-child-max="' . esc_attr( $child_max ) . '" data-ajax-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'wcai_calendar_availability' ) ) . '" data-departures="' . esc_attr( wp_json_encode( $calendar_departures ) ) . '">';
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
        echo '<div class="wcai-selection-heading"><strong>2. Escolha o horário</strong><small data-calendar-selected-date></small></div>';
        echo '<div class="wcai-time-options" data-calendar-times></div>';
        echo '</div>';

        echo '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="" data-wcai-departure-input required>';
        echo '<div class="wcai-selected-departure" data-calendar-selected-departure hidden></div>';
        echo '</div>';
        echo '</div>';

        echo '<section class="wcai-participant-picker" data-wcai-participants data-child-enabled="' . esc_attr( $child_enabled ? '1' : '0' ) . '" data-child-max="' . esc_attr( $child_max ) . '">';
        echo '<div class="wcai-booking-section-title"><span>3</span><div><strong>Quem vai participar?</strong><small>Informe adultos e crianças. A quantidade total reserva as vagas da saída.</small></div></div>';
        echo '<div class="wcai-participant-rows">';
        echo '<div class="wcai-participant-row" data-participant-row="adults"><div><strong>Adultos</strong><small>Inclui o titular da reserva</small></div><div class="wcai-stepper"><button type="button" data-participant-action="minus" aria-label="Diminuir adultos">−</button><strong data-participant-value="adults">1</strong><button type="button" data-participant-action="plus" aria-label="Aumentar adultos">+</button></div></div>';
        echo '<div class="wcai-participant-row" data-participant-row="children"><div><strong>Crianças</strong><small>' . esc_html( ! empty( $profile['children_age_max'] ) ? 'Até ' . absint( $profile['children_age_max'] ) . ' anos' : 'Conforme as regras do passeio' ) . '</small></div><div class="wcai-stepper"><button type="button" data-participant-action="minus" aria-label="Diminuir crianças">−</button><strong data-participant-value="children">0</strong><button type="button" data-participant-action="plus" aria-label="Aumentar crianças">+</button></div></div>';
        echo '</div>';
        echo '<div class="wcai-participant-total"><div><strong data-participant-total>1 participante</strong><small>As vagas são calculadas por pessoa.</small></div><div class="wcai-participant-badge" data-participant-availability>Escolha o horário</div></div>';
        echo '<input type="hidden" name="wcai_adults" value="1" data-participant-input="adults">';
        echo '<input type="hidden" name="wcai_children" value="0" data-participant-input="children">';
        echo '</section>';

        echo '<p class="wcai-booking-note">O calendário fica apenas para escolher a data. O horário mostra formação do grupo, vagas restantes e detalhes da saída. O carrinho e o checkout exibem somente o resumo escolhido.</p>';
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
        var participantRoot = document.querySelector('[data-wcai-participants]');
        var adultInput = participantRoot ? participantRoot.querySelector('[data-participant-input="adults"]') : null;
        var childInput = participantRoot ? participantRoot.querySelector('[data-participant-input="children"]') : null;
        var totalInput = participantRoot ? participantRoot.querySelector('[data-wcai-booking-quantity]') : null;
        var nativeQuantityInput = document.querySelector('form.cart input.qty[name="quantity"]');
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

            if ( totalInput ) totalInput.value = total;
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
                if (forming) {
                    return { state: best <= 3 ? 'few' : 'available', label: forming.reserved + '/' + forming.minimum };
                }
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
                        updateDepartureSnapshot(date, response.data.departures);
                        renderCalendar();
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
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                    updateParticipantUI();
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
                        var selected = selectedDepartureId ? departures.filter(function(item){ return String(item.id) === String(selectedDepartureId); })[0] : null;
                        var available = selected ? Math.max(0, parseInt(selected.available, 10) || 0) : null;

                        if ( 'children' === type && current >= max ) return;
                        if ( null !== available && quantity() >= available ) return;

                        current += 1;
                    }

                    if ( inputEl ) inputEl.value = current;
                    updateParticipantUI();
                    refreshForQuantity();
                });
            });
        }

        updateParticipantUI();

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
        }

        enableAddToCart();
        updateParticipantUI();
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
            '.wcai-product-booking{margin:24px 0;padding:0;border:1px solid #e7e7e7;border-radius:18px;background:#fff;box-shadow:0 10px 32px rgba(0,0,0,.06);overflow:hidden}.single-product form.cart .wcai-product-booking{position:relative}.wcai-booking-top{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;padding:22px 24px 16px;border-bottom:1px solid #eee}.wcai-booking-eyebrow{display:block;font-size:10px;letter-spacing:.16em;font-weight:800;color:#777}.wcai-booking-top h2{margin:5px 0 5px;font-size:23px;line-height:1.2}.wcai-booking-top p{margin:0;color:#666;font-size:13px}.wcai-booking-price{text-align:right;font-weight:800;font-size:19px;white-space:nowrap}.wcai-booking-price small{display:block;margin-top:2px;font-size:11px;font-weight:500;color:#777}.wcai-tour-facts{margin:0 0 18px;padding:14px 16px;border:1px solid #ececec;border-radius:12px;background:#fff}.wcai-tour-facts-heading{display:flex;justify-content:space-between;gap:12px;align-items:baseline;margin-bottom:10px}.wcai-tour-facts-heading strong{font-size:15px}.wcai-tour-facts-heading small{font-size:11px;color:#777}.wcai-tour-facts-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px}.wcai-tour-facts-grid>div{min-width:0}.wcai-tour-facts-grid span{display:block;font-size:9px;text-transform:uppercase;letter-spacing:.05em;color:#888}.wcai-tour-facts-grid strong{display:block;margin-top:2px;font-size:12px;line-height:1.35}.wcai-booking-context{display:flex;gap:10px;align-items:flex-start;margin:18px 24px 0;padding:11px 13px;border:1px solid #eee;border-radius:10px;background:#fafafa}.wcai-booking-context strong{display:block;white-space:nowrap;font-size:12px}.wcai-booking-context span{font-size:12px;line-height:1.4;color:#666}.wcai-booking-section{padding:18px 24px 0}.wcai-booking-section-title{display:flex;align-items:flex-start;gap:10px;margin-bottom:12px}.wcai-booking-section-title>span{display:flex;align-items:center;justify-content:center;width:25px;height:25px;border-radius:50%;background:#202020;color:#fff;font-size:11px;flex:0 0 25px}.wcai-booking-section-title strong{display:block;font-size:15px}.wcai-booking-section-title small{display:block;margin-top:2px;color:#777;font-size:11px}.wcai-calendar{max-width:360px;margin:0 auto}.wcai-calendar-toolbar{display:grid;grid-template-columns:34px 1fr 34px;align-items:center;gap:5px;margin:0 auto 6px}.wcai-calendar-month{text-align:center;text-transform:capitalize;font-size:15px}.wcai-calendar-nav{width:34px;height:31px;border:1px solid #ddd;border-radius:8px;background:#fff;font-size:20px;line-height:1;cursor:pointer}.wcai-calendar-nav:hover:not(:disabled){border-color:#777}.wcai-calendar-nav:disabled{opacity:.35;cursor:not-allowed}.wcai-calendar.is-loading{opacity:.7}.wcai-calendar-weekdays,.wcai-calendar-grid{display:grid;grid-template-columns:repeat(7,minmax(0,1fr));gap:3px}.wcai-calendar-weekdays{margin-bottom:3px}.wcai-calendar-weekdays span{text-align:center;font-size:9px;font-weight:800;text-transform:uppercase;color:#999;padding:2px 0}.wcai-calendar-day{min-height:39px;border-radius:8px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1px;padding:3px;box-sizing:border-box}.wcai-calendar-day.is-empty{visibility:hidden}.wcai-calendar-day.is-empty-date{color:#bbb;border:1px solid transparent}.wcai-calendar-day.state-available,.wcai-calendar-day.state-few,.wcai-calendar-day.state-insufficient,.wcai-calendar-day.state-full,.wcai-calendar-day.state-closed{border:1px solid #e1e1e1;background:#fff;cursor:pointer}.wcai-calendar-day.state-available{box-shadow:inset 0 -2px 0 #3f8f5b}.wcai-calendar-day.state-few{box-shadow:inset 0 -2px 0 #c58a21}.wcai-calendar-day.state-insufficient{box-shadow:inset 0 -2px 0 #888}.wcai-calendar-day.state-full{opacity:.52;box-shadow:inset 0 -2px 0 #b54b4b}.wcai-calendar-day.state-closed{opacity:.5;box-shadow:inset 0 -2px 0 #999}.wcai-calendar-day.is-selected{outline:2px solid #222;outline-offset:1px}.wcai-calendar-day strong{font-size:13px}.wcai-calendar-day small{max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:7px;line-height:1;color:#777}.wcai-calendar-legend{display:flex;flex-wrap:wrap;gap:9px;margin:8px 0 0;color:#666;font-size:10px}.wcai-calendar-legend span{display:flex;align-items:center;gap:4px}.wcai-calendar-legend i{width:7px;height:7px;border-radius:50%;display:inline-block;border:1px solid #999}.wcai-calendar-legend .is-available{background:#3f8f5b}.wcai-calendar-legend .is-few{background:#c58a21}.wcai-calendar-legend .is-full{background:#b54b4b}.wcai-calendar-legend .is-closed{background:#999}.wcai-calendar-selection{margin-top:16px;padding-top:14px;border-top:1px solid #eee}.wcai-selection-heading{display:flex;justify-content:space-between;gap:16px;align-items:baseline;margin-bottom:9px}.wcai-selection-heading strong{font-size:15px}.wcai-selection-heading small{color:#777;font-size:11px}.wcai-time-options{display:grid;grid-template-columns:repeat(auto-fit,minmax(210px,1fr));gap:9px}.wcai-time-option{display:grid;grid-template-columns:auto 1fr;column-gap:12px;row-gap:3px;text-align:left;padding:12px;border:1px solid #e0e0e0;border-radius:10px;background:#fff;cursor:pointer;transition:.15s}.wcai-time-option:hover:not(:disabled){border-color:#777;transform:translateY(-1px)}.wcai-time-option:disabled{cursor:not-allowed;opacity:.52;background:#f8f8f8}.wcai-time-option>strong{font-size:19px;min-width:54px;grid-row:1/span 3}.wcai-time-option-content{min-width:0}.wcai-time-status{display:block;font-size:11px;font-weight:800}.wcai-time-formation{margin:6px 0 3px}.wcai-time-formation b{display:block;font-size:10px;line-height:1.3}.wcai-time-progress{display:block;height:5px;margin-top:4px;border-radius:999px;background:#ededed;overflow:hidden}.wcai-time-progress i{display:block;height:100%;border-radius:inherit;background:#222}.wcai-time-option small{display:block;font-size:9px;line-height:1.35;color:#777}.wcai-time-option.state-few{border-color:#c58a21}.wcai-time-option.state-full,.wcai-time-option.state-closed{border-color:#e2e2e2}.wcai-time-option.is-selected{border-color:#222;box-shadow:0 0 0 2px rgba(0,0,0,.07);background:#fafafa}.wcai-no-times{padding:13px;border:1px dashed #ccc;border-radius:10px;color:#666}.wcai-selected-departure{margin-top:9px;padding:11px 13px;border-radius:10px;background:#f7f7f7;border:1px solid #e5e5e5}.wcai-selected-departure>div{display:flex;justify-content:space-between;gap:12px;align-items:baseline}.wcai-selected-departure span{color:#666;font-size:11px}.wcai-selected-departure p{margin:6px 0 0;font-size:11px;line-height:1.4}.wcai-selected-departure small{display:block;margin-top:6px;color:#777}.wcai-participant-picker{margin:18px 24px 0;padding:18px;border-top:1px solid #eee;background:#fbfbfb}.wcai-participant-rows{display:grid;grid-template-columns:1fr 1fr;gap:9px}.wcai-participant-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:13px 14px;border:1px solid #e1e1e1;border-radius:10px;background:#fff}.wcai-participant-row>div:first-child strong{display:block;font-size:13px}.wcai-participant-row>div:first-child small{display:block;margin-top:2px;color:#777;font-size:10px}.wcai-stepper{display:flex;align-items:center;gap:8px}.wcai-stepper button{width:30px;height:30px;border:1px solid #d4d4d4;border-radius:50%;background:#fff;font-size:18px;line-height:1;cursor:pointer}.wcai-stepper button:disabled{opacity:.35;cursor:not-allowed}.wcai-stepper>strong{min-width:18px;text-align:center;font-size:14px}.wcai-participant-total{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:10px;padding:11px 13px;border-radius:9px;background:#fff;border:1px solid #e3e3e3}.wcai-participant-total strong{display:block;font-size:13px}.wcai-participant-total small{display:block;margin-top:2px;color:#777;font-size:10px}.wcai-participant-badge{font-size:10px;font-weight:700;color:#555}.wcai-booking-note{margin:14px 24px 20px;color:#777;font-size:10px;line-height:1.45}.single-product form.cart .single_add_to_cart_button{display:block;width:calc(100% - 48px);margin:12px 24px 20px;min-height:46px;border-radius:10px;font-weight:800}.wcai-cart-fixed-quantity{display:inline-block;min-width:28px;text-align:center;font-weight:700}.wcai-booking-empty{margin:18px 24px;padding:20px;border:1px dashed #ccc;border-radius:10px;background:#fafafa}.single-product form.cart .quantity{position:absolute!important;left:-9999px!important;width:1px!important;height:1px!important;overflow:hidden!important}.single-product form.cart .quantity input[name="quantity"]{position:absolute!important;left:0!important;width:1px!important;height:1px!important;opacity:0!important}@media(max-width:700px){.wcai-product-booking{margin:18px 0;border-radius:14px}.wcai-booking-top{display:block;padding:18px}.wcai-booking-price{text-align:left;margin-top:8px}.wcai-booking-section{padding-left:18px;padding-right:18px}.wcai-booking-context{margin-left:18px;margin-right:18px}.wcai-participant-picker{margin-left:18px;margin-right:18px;padding:14px}.wcai-participant-rows{grid-template-columns:1fr}.wcai-calendar{max-width:320px}.wcai-calendar-day{min-height:38px}.wcai-time-options{grid-template-columns:1fr}.wcai-booking-note{margin-left:18px;margin-right:18px}}'        );
    }

    private function get_submitted_participant_counts( $product_id, $quantity ) {
        $has_adults = isset( $_POST['wcai_adults'] );
        $has_children = isset( $_POST['wcai_children'] );

        if ( ! $has_adults && ! $has_children ) {
            return array(
                'adults'   => max( 1, absint( $quantity ) ),
                'children' => 0,
                'total'    => max( 1, absint( $quantity ) ),
            );
        }

        $adults = max( 0, absint( isset( $_POST['wcai_adults'] ) ? wp_unslash( $_POST['wcai_adults'] ) : 0 ) );
        $children = max( 0, absint( isset( $_POST['wcai_children'] ) ? wp_unslash( $_POST['wcai_children'] ) : 0 ) );
        $profile = $this->get_tour_profile( $product_id );

        if ( $adults < 1 ) {
            return new WP_Error( 'wcai_adults_required', 'A reserva precisa ter pelo menos 1 adulto.' );
        }

        if ( ! empty( $profile['children_allowed'] ) && ! empty( $profile['children_max'] ) && $children > $profile['children_max'] ) {
            return new WP_Error( 'wcai_children_limit', sprintf( 'Este passeio permite no máximo %d criança%s por reserva.', $profile['children_max'], 1 === $profile['children_max'] ? '' : 's' ) );
        }

        if ( empty( $profile['children_allowed'] ) && $children > 0 ) {
            return new WP_Error( 'wcai_children_not_allowed', 'Este passeio não permite crianças.' );
        }

        return array(
            'adults'   => $adults,
            'children' => $children,
            'total'    => $adults + $children,
        );
    }

    public function filter_add_to_cart_quantity( $quantity, $product_id, $variation_id = 0 ) {
        if ( ! $this->is_target_product( $product_id, $variation_id ) ) {
            return $quantity;
        }

        $counts = $this->get_submitted_participant_counts( $product_id, $quantity );

        return is_wp_error( $counts ) ? $quantity : $counts['total'];
    }

    public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id = 0 ) {
        if ( ! $this->is_target_product( $product_id, $variation_id ) ) {
            return $passed;
        }

        $counts = $this->get_submitted_participant_counts( $product_id, $quantity );

        if ( is_wp_error( $counts ) ) {
            wc_add_notice( $counts->get_error_message(), 'error' );
            return false;
        }

        $departure_id = isset( $_POST[ self::FIELD ] ) ? absint( wp_unslash( $_POST[ self::FIELD ] ) ) : 0;

        if ( ! $departure_id ) {
            wc_add_notice( 'Selecione a data e o horário da atividade.', 'error' );
            return false;
        }

        if ( ! class_exists( 'WCAI_Reservations' ) || ! WCAI_Reservations::is_available_for_product( $departure_id, $product_id, $variation_id, $counts['total'] ) ) {
            wc_add_notice( 'A saída selecionada não possui vagas suficientes para esta quantidade de participantes.', 'error' );
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

        $counts = $this->get_submitted_participant_counts( $product_id, $quantity );
        if ( ! is_wp_error( $counts ) ) {
            $cart_item_data['wcai_adults'] = $counts['adults'];
            $cart_item_data['wcai_children'] = $counts['children'];
            $cart_item_data['wcai_participant_signature'] = md5( $product_id . '|' . $variation_id . '|' . $departure_id . '|' . $counts['adults'] . '|' . $counts['children'] );
        }

        return $cart_item_data;
    }

    public function render_cart_item_quantity( $product_quantity, $cart_item_key, $cart_item ) {
        $product_id = absint( isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0 );
        $variation_id = absint( isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0 );

        if ( ! $this->is_target_product( $product_id, $variation_id ) || ! isset( $cart_item['wcai_adults'] ) ) {
            return $product_quantity;
        }

        $total = absint( $cart_item['wcai_adults'] ) + absint( isset( $cart_item['wcai_children'] ) ? $cart_item['wcai_children'] : 0 );

        return '<span class="wcai-cart-fixed-quantity">' . esc_html( $total ) . '</span>';
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
                'key'   => 'Saída',
                'value' => $label,
            );
        }

        if ( isset( $cart_item['wcai_adults'] ) ) {
            $item_data[] = array(
                'key'   => 'Adultos',
                'value' => absint( $cart_item['wcai_adults'] ),
            );
        }

        if ( isset( $cart_item['wcai_children'] ) && absint( $cart_item['wcai_children'] ) > 0 ) {
            $item_data[] = array(
                'key'   => 'Crianças',
                'value' => absint( $cart_item['wcai_children'] ),
            );
        }

        return $item_data;
    }

    public function add_order_item_data( $item, $cart_item_key, $values, $order ) {
        if ( isset( $values['wcai_adults'] ) ) {
            $item->add_meta_data( 'Adultos', absint( $values['wcai_adults'] ), true );
        }

        if ( isset( $values['wcai_children'] ) && absint( $values['wcai_children'] ) > 0 ) {
            $item->add_meta_data( 'Crianças', absint( $values['wcai_children'] ), true );
        }
    }
}

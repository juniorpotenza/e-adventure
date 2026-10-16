<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Product_Booking_Departure {

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
                ? sprintf( '%d %s — não comporta a quantidade', $available, 1 === $available ? 'vaga' : 'vagas' )
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
            'group_label'          => 'forming' === sanitize_key( $group['status'] ) ? __( 'Grupo em formação', 'wooadventure-integration' ) : sanitize_text_field( $group['label'] ),
            'group_detail'         => sanitize_text_field( $group['detail'] ),
            'duration'             => absint( get_post_meta( $departure_id, '_wcai_duration_minutes', true ) ),
            'meeting_point'        => sanitize_text_field( get_post_meta( $departure_id, '_wcai_meeting_point', true ) ),
            'guide'                => $guide ? sanitize_text_field( $guide->display_name ) : '',
            'cutoff'               => class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_booking_cutoff_label( $departure_id ) : '',
        );
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

        $this->render_trust_block(
            self::trust_rows(
                $calendar_departures,
                class_exists( 'WCAI_Settings' ) ? WCAI_Settings::get_cancellation_policy() : '',
                class_exists( 'WCAI_Settings' ) ? WCAI_Settings::get_contact_phone() : ''
            )
        );

        echo '<section class="wcai-product-booking" aria-labelledby="wcai-booking-title">';
        echo '<div class="wcai-booking-top">';
        echo '<div>';
        echo '<span class="wcai-booking-eyebrow">RESERVE SEU PASSEIO</span>';
        echo '<h2 id="wcai-booking-title">Escolha quando você quer ir</h2>';
        echo '</div>';
        echo '<div class="wcai-booking-price">' . wp_kses_post( $this->card_price_html( $product ) ) . '<small>por participante</small></div>';
        echo '</div>';

        if ( empty( $calendar_departures ) ) {
            echo '<div class="wcai-booking-empty"><strong>Nenhum passeio disponível no momento.</strong><p>Assim que uma nova data for aberta, ela aparecerá aqui.</p></div>';
            echo '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="">';
            echo '</section>';
            $this->enqueue_styles();
            return;
        }

        $this->remember_cta_label( $product );
        $profile = $this->get_tour_profile( $product->get_id() );
        $child_enabled = ! empty( $profile['children_allowed'] );
        $child_max = ! empty( $profile['children_max'] ) ? absint( $profile['children_max'] ) : 99;

        echo '<div class="wcai-booking-section">';
        echo '<div class="wcai-booking-section-title"><span>1</span><div><strong>Escolha a data</strong></div></div>';

        echo '<div class="wcai-booking-layout">';

        echo '<div class="wcai-date-picker" data-wcai-calendar data-product-id="' . esc_attr( $product->get_id() ) . '" data-child-enabled="' . esc_attr( $child_enabled ? '1' : '0' ) . '" data-child-max="' . esc_attr( $child_max ) . '" data-price="' . esc_attr( wc_get_price_to_display( $product ) ) . '" data-currency="' . esc_attr( get_woocommerce_currency() ) . '" data-ajax-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'wcai_calendar_availability' ) ) . '" data-departures="' . esc_attr( wp_json_encode( $calendar_departures ) ) . '">';
        echo '<div class="wcai-date-picker-heading"><strong>Próximas datas</strong><button type="button" class="wcai-calendar-toggle" data-calendar-toggle aria-expanded="false">Ver todas as datas</button></div>';
        echo '<div class="wcai-calendar-error" data-calendar-error role="alert" hidden></div>';
        echo '<div class="wcai-visually-hidden" data-calendar-status role="status" aria-live="polite" aria-atomic="true"></div>';
        echo '<p class="wcai-quick-legend">' . esc_html( self::availability_legend( $this->has_group_minimum( $calendar_departures ) ) ) . '</p>';
        echo '<div class="wcai-quick-wrap" data-calendar-quick-wrap>';
        echo '<button type="button" class="wcai-quick-arrow is-prev" data-calendar-scroll="-1" aria-controls="wcai-quick-dates" aria-label="Datas anteriores" hidden>&lsaquo;</button>';
        echo '<div class="wcai-quick-dates" id="wcai-quick-dates" data-calendar-quick role="radiogroup" aria-label="Datas disponíveis"></div>';
        echo '<button type="button" class="wcai-quick-arrow is-next" data-calendar-scroll="1" aria-controls="wcai-quick-dates" aria-label="Próximas datas" hidden>&rsaquo;</button>';
        echo '</div>';
        echo '<div class="wcai-calendar-full" data-calendar-full hidden>';
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
        echo '<span><i class="is-available" aria-hidden="true">✓</i> Disponível</span>';
        echo '<span><i class="is-few" aria-hidden="true">!</i> Últimas vagas</span>';
        echo '<span><i class="is-full" aria-hidden="true">×</i> Lotado</span>';
        echo '<span><i class="is-closed" aria-hidden="true">–</i> Encerrado</span>';
        echo '</div>';
        echo '</div>';

        echo '<div class="wcai-calendar-selection" data-calendar-selection hidden>';
        echo '<div class="wcai-booking-section-title"><span>2</span><div><strong>Escolha o horário</strong><small data-calendar-selected-date></small></div></div>';
        echo '<div class="wcai-time-options" data-calendar-times></div>';
        echo '<p class="wcai-group-note" data-calendar-group-note hidden></p>';
        echo '</div>';

        echo '<input type="hidden" name="' . esc_attr( self::FIELD ) . '" value="" data-wcai-departure-input required>';
        echo '<div class="wcai-selected-departure" data-calendar-selected-departure hidden></div>';
        echo '</div>';

        echo '<section class="wcai-participant-picker" data-wcai-participants data-child-enabled="' . esc_attr( $child_enabled ? '1' : '0' ) . '" data-child-max="' . esc_attr( $child_max ) . '" hidden>';
        echo '<div class="wcai-booking-section-title"><span>3</span><div><strong>Quem vai participar?</strong><small>1 vaga por pessoa. O titular já conta como adulto.</small></div></div>';
        echo '<div class="wcai-participant-rows">';
        echo '<div class="wcai-participant-row" data-participant-row="adults"><div><strong>Adultos</strong><small>Inclui o titular da reserva</small></div><div class="wcai-stepper"><button type="button" data-participant-action="minus" aria-label="Diminuir adultos">−</button><strong data-participant-value="adults">1</strong><button type="button" data-participant-action="plus" aria-label="Aumentar adultos">+</button></div></div>';
        echo '<div class="wcai-participant-row" data-participant-row="children"><div><strong>Crianças</strong><small>' . esc_html( ! empty( $profile['children_age_max'] ) ? 'Até ' . absint( $profile['children_age_max'] ) . ' anos' : 'Conforme as regras do passeio' ) . '</small><small class="wcai-row-reason" id="wcai-child-reason" data-participant-reason hidden></small></div><div class="wcai-stepper"><button type="button" data-participant-action="minus" aria-label="Diminuir crianças">−</button><strong data-participant-value="children">0</strong><button type="button" data-participant-action="plus" aria-label="Aumentar crianças">+</button></div></div>';
        echo '</div>';
        echo '<div class="wcai-participant-total"><strong data-participant-total aria-live="polite" aria-atomic="true">1 participante</strong><small data-participant-remaining></small><small class="wcai-participant-limit" id="wcai-participant-limit" data-participant-limit hidden></small></div>';
        echo '<input type="hidden" name="wcai_adults" value="1" data-participant-input="adults">';
        echo '<input type="hidden" name="wcai_children" value="0" data-participant-input="children">';
        echo '</section>';

        echo '</div>';

        echo '<div class="wcai-booking-footer" data-wcai-footer><p id="wcai-cart-hint" class="wcai-cart-hint" data-wcai-hint>' . esc_html( self::booking_i18n()['hintDate'] ) . '</p></div>';

        echo '</section>';

        $this->enqueue_styles();
        $this->enqueue_calendar_script();
    }

    public function ajax_refresh_calendar_date() {
        check_ajax_referer( 'wcai_calendar_availability', 'nonce' );

        $product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
        $date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
        $quantity = isset( $_POST['quantity'] ) ? self::clamp_quantity( wp_unslash( $_POST['quantity'] ) ) : 1;

        if ( ! $product_id || ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) || ! $this->is_target_product( $product_id ) ) {
            wp_send_json_error( array( 'message' => 'Parâmetros inválidos.' ), 400 );
        }

        if ( ! class_exists( 'WCAI_Reservations' ) ) {
            wp_send_json_error( array( 'message' => 'Disponibilidade indisponível.' ), 500 );
        }

        // A lista de IDs (get_posts sem limite) fica em cache curto; a disponibilidade é recalculada a cada chamada.
        $cache_key = 'wcai_cal_deps_' . $product_id;
        $departure_ids = get_transient( $cache_key );

        if ( ! is_array( $departure_ids ) ) {
            $departure_ids = wp_list_pluck( WCAI_Reservations::get_calendar_departures_for_product( $product_id, 0 ), 'ID' );
            set_transient( $cache_key, $departure_ids, self::DEPARTURE_IDS_TTL );
        }

        $result = array();

        foreach ( $departure_ids as $departure_id ) {
            $payload = $this->departure_payload( absint( $departure_id ), $quantity );

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

}

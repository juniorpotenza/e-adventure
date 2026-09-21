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

        echo '<section class="wcai-product-booking" aria-labelledby="wcai-booking-title">';
        echo '<div class="wcai-booking-header">';
        echo '<div><span class="wcai-booking-eyebrow">AGENDAMENTO</span><h2 id="wcai-booking-title">Escolha sua data</h2><p>Selecione a saída que melhor atende ao seu planejamento.</p></div>';
        echo '<span class="wcai-booking-step">1. Data da atividade</span>';
        echo '</div>';

        if ( empty( $departures ) ) {
            echo '<div class="wcai-booking-empty"><strong>Nenhuma saída disponível no momento.</strong><p>Assim que uma nova data for aberta, ela aparecerá aqui.</p></div>';
            echo '</section>';
            $this->enqueue_styles();
            return;
        }

        echo '<div class="wcai-departure-list" role="radiogroup" aria-label="Datas disponíveis">';

        foreach ( $departures as $departure ) {
            $available = WCAI_Reservations::get_available_quantity( $departure->ID );

            if ( $available < 1 ) {
                continue;
            }

            $starts_at = WCAI_Data_Resolver::get_departure_start( $departure->ID );
            $timestamp = WCAI_Data_Resolver::parse_timestamp( $starts_at );
            $date_label = $timestamp ? wp_date( 'D, d/m/Y', $timestamp ) : $starts_at;
            $time_label = $timestamp ? wp_date( 'H:i', $timestamp ) : '';
            $duration = absint( get_post_meta( $departure->ID, '_wcai_duration_minutes', true ) );
            $meeting_point = get_post_meta( $departure->ID, '_wcai_meeting_point', true );
            $guide_id = absint( get_post_meta( $departure->ID, '_wcai_guide_id', true ) );
            $guide = $guide_id ? get_userdata( $guide_id ) : false;
            $cutoff = WCAI_Reservations::get_booking_cutoff_label( $departure->ID );
            $radio_id = 'wcai-departure-' . absint( $departure->ID );

            echo '<label class="wcai-departure-card" for="' . esc_attr( $radio_id ) . '">';
            echo '<input class="wcai-departure-radio" id="' . esc_attr( $radio_id ) . '" type="radio" name="' . esc_attr( self::FIELD ) . '" value="' . esc_attr( $departure->ID ) . '" required>';
            echo '<span class="wcai-departure-card-main">';
            echo '<span class="wcai-departure-date">' . esc_html( $date_label ) . '</span>';
            echo '<span class="wcai-departure-time">' . esc_html( $time_label ) . '</span>';
            echo '<span class="wcai-departure-meta">';
            if ( $duration ) {
                echo '<span>' . esc_html( $duration . ' min' ) . '</span>';
            }
            if ( $meeting_point ) {
                echo '<span>' . esc_html( $meeting_point ) . '</span>';
            }
            if ( $guide ) {
                echo '<span>Guia: ' . esc_html( $guide->display_name ) . '</span>';
            }
            echo '</span>';
            echo '</span>';
            echo '<span class="wcai-departure-card-side">';
            echo '<strong>' . esc_html( $available ) . '</strong><small>' . esc_html( 1 === $available ? 'vaga disponível' : 'vagas disponíveis' ) . '</small>';
            if ( $cutoff ) {
                echo '<span class="wcai-departure-cutoff">Compra até ' . esc_html( $cutoff ) . '</span>';
            }
            echo '</span>';
            echo '</label>';
        }

        echo '</div>';
        echo '<p class="wcai-booking-note">A vaga e o horário limite são validados novamente no momento da compra.</p>';
        echo '</section>';

        $this->enqueue_styles();
    }

    private function enqueue_styles() {
        if ( wp_style_is( 'wcai-product-booking-inline', 'enqueued' ) ) {
            return;
        }

        wp_register_style( 'wcai-product-booking-inline', false, array(), WCAI_VERSION );
        wp_enqueue_style( 'wcai-product-booking-inline' );
        wp_add_inline_style(
            'wcai-product-booking-inline',
            '.wcai-product-booking{margin:28px 0;padding:24px;border:1px solid #ddd;border-radius:14px;background:#fff}.wcai-booking-header{display:flex;justify-content:space-between;gap:20px;align-items:flex-start;margin-bottom:20px}.wcai-booking-eyebrow{font-size:11px;letter-spacing:.12em;font-weight:700;opacity:.65}.wcai-booking-header h2{margin:4px 0 6px}.wcai-booking-header p{margin:0;opacity:.75}.wcai-booking-step{font-size:12px;font-weight:600;padding:8px 10px;border-radius:20px;background:#f2f2f2;white-space:nowrap}.wcai-departure-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:12px}.wcai-departure-card{position:relative;display:flex;justify-content:space-between;gap:18px;padding:18px;border:2px solid #e3e3e3;border-radius:12px;cursor:pointer;transition:border-color .15s,box-shadow .15s;background:#fff}.wcai-departure-card:hover{border-color:#999}.wcai-departure-card:has(.wcai-departure-radio:checked){border-color:#222;box-shadow:0 0 0 2px rgba(0,0,0,.06)}.wcai-departure-radio{position:absolute;opacity:0}.wcai-departure-card-main{display:flex;flex-direction:column;gap:5px;min-width:0}.wcai-departure-date{font-weight:700;font-size:16px}.wcai-departure-time{font-size:22px;font-weight:700}.wcai-departure-meta{display:flex;flex-wrap:wrap;gap:6px;font-size:12px;opacity:.75}.wcai-departure-meta span{padding-right:6px;border-right:1px solid #ddd}.wcai-departure-meta span:last-child{border-right:0}.wcai-departure-card-side{display:flex;flex-direction:column;align-items:flex-end;text-align:right;min-width:105px}.wcai-departure-card-side strong{font-size:20px}.wcai-departure-card-side small{font-size:11px}.wcai-departure-cutoff{margin-top:10px;font-size:11px;line-height:1.35;opacity:.7}.wcai-booking-note{margin:16px 0 0;font-size:12px;opacity:.7}.wcai-booking-empty{padding:20px;border:1px dashed #ccc;border-radius:10px}.wcai-booking-empty p{margin-bottom:0}@media(max-width:600px){.wcai-booking-header{display:block}.wcai-booking-step{display:inline-block;margin-top:12px}.wcai-departure-card{padding:14px;display:block}.wcai-departure-card-side{align-items:flex-start;text-align:left;margin-top:12px}.wcai-departure-cutoff{margin-top:5px}}'
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

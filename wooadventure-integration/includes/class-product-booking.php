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

        echo '<div class="wcai-product-booking" style="margin:20px 0;padding:18px;border:1px solid #ddd;border-radius:6px;">';
        echo '<h3 style="margin-top:0;">Escolha sua data</h3>';

        if ( empty( $departures ) ) {
            echo '<p>Não há datas disponíveis para agendamento no momento.</p>';
            echo '</div>';
            return;
        }

        echo '<label for="' . esc_attr( self::FIELD ) . '"><strong>Data e horário da atividade</strong></label>';
        echo '<select id="' . esc_attr( self::FIELD ) . '" name="' . esc_attr( self::FIELD ) . '" required style="width:100%;margin-top:8px;">';
        echo '<option value="">Selecione uma saída</option>';

        foreach ( $departures as $departure ) {
            $available = WCAI_Reservations::get_available_quantity( $departure->ID );
            if ( $available < 1 ) {
                continue;
            }

            $starts_at = WCAI_Data_Resolver::get_departure_start( $departure->ID );
            $timestamp = WCAI_Data_Resolver::parse_timestamp( $starts_at );
            $label = $timestamp ? wp_date( 'd/m/Y H:i', $timestamp ) : $starts_at;

            $cutoff = WCAI_Reservations::get_booking_cutoff_label( $departure->ID );
            if ( $cutoff ) {
                $label .= ' — compra até ' . $cutoff;
            }

            $label .= sprintf( ' — %d vaga%s', $available, 1 === $available ? '' : 's' );

            echo '<option value="' . esc_attr( $departure->ID ) . '">' . esc_html( $label ) . '</option>';
        }

        echo '</select>';
        echo '<p class="description" style="margin-bottom:0;">A disponibilidade e o horário limite são validados novamente no momento da compra.</p>';
        echo '</div>';
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

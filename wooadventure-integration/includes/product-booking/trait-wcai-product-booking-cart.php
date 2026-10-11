<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Product_Booking_Cart {

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

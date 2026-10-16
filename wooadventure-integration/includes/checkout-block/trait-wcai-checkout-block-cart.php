<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Checkout_Block_Cart {

    public function cart_item_data( $cart_item ) {
        $product_id = absint( isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0 );
        $variation_id = absint( isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0 );
        $quantity = max( 1, absint( isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1 ) );
        $target_ids = WCAI_Settings::get_product_ids();
        $enabled = in_array( $product_id, $target_ids, true ) || in_array( $variation_id, $target_ids, true );

        if ( ! $enabled ) {
            return array(
                'enabled' => false,
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'quantity' => $quantity,
                'adults' => max( 1, $quantity ),
                'children' => 0,
                'departure_id' => 0,
                'departure_label' => '',
                'departures' => array(),
            );
        }

        $selected_departure_id = absint( isset( $cart_item['wcai_departure_id'] ) ? $cart_item['wcai_departure_id'] : 0 );
        $adults = max( 1, absint( isset( $cart_item['wcai_adults'] ) ? $cart_item['wcai_adults'] : $quantity ) );
        $children = max( 0, absint( isset( $cart_item['wcai_children'] ) ? $cart_item['wcai_children'] : 0 ) );
        $departures = array();

        if ( class_exists( 'WCAI_Reservations' ) ) {
            foreach ( WCAI_Reservations::get_open_departures_for_product( $product_id, $variation_id ) as $departure ) {
                $available = WCAI_Reservations::get_available_quantity( $departure->ID );

                if ( $available < $quantity ) {
                    continue;
                }

                $starts_at = WCAI_Data_Resolver::get_departure_start( $departure->ID );
                $timestamp = WCAI_Data_Resolver::parse_timestamp( $starts_at );
                $label = $timestamp ? wp_date( 'd/m/Y H:i', $timestamp ) : $starts_at;

                $departures[] = array(
                    'id' => absint( $departure->ID ),
                    'label' => sanitize_text_field( $label ),
                    'available' => $available,
                );
            }
        }

        $selected_label = '';

        foreach ( $departures as $departure ) {
            if ( absint( $departure['id'] ) === $selected_departure_id ) {
                $selected_label = $departure['label'];
                break;
            }
        }

        if ( ! $selected_label && $selected_departure_id ) {
            $selected_start = WCAI_Data_Resolver::get_departure_start( $selected_departure_id );
            $selected_timestamp = WCAI_Data_Resolver::parse_timestamp( $selected_start );
            $selected_label = $selected_timestamp ? wp_date( 'd/m/Y H:i', $selected_timestamp ) : $selected_start;
        }

        return array(
            'enabled' => true,
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'quantity' => $quantity,
            'adults' => $adults,
            'children' => $children,
            'departure_id' => $selected_departure_id,
            'departure_label' => sanitize_text_field( $selected_label ),
            'departures' => $departures,
        );
    }

    public function cart_item_schema() {
        return array(
            'enabled' => array( 'type' => 'boolean', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'product_id' => array( 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'variation_id' => array( 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'quantity' => array( 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'adults' => array( 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'children' => array( 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'departure_id' => array( 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'departure_label' => array( 'type' => 'string', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'departures' => array(
                'type' => 'array',
                'context' => array( 'view', 'edit' ),
                'readonly' => true,
                'items' => array(
                    'type' => 'object',
                    'properties' => array(
                        'id' => array( 'type' => 'integer', 'readonly' => true ),
                        'label' => array( 'type' => 'string', 'readonly' => true ),
                        'available' => array( 'type' => 'integer', 'readonly' => true ),
                    ),
                ),
            ),
        );
    }

    private function has_target_cart_item() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return false;
        }

        $target_ids = WCAI_Settings::get_product_ids();

        foreach ( WC()->cart->get_cart() as $item ) {
            if ( in_array( absint( $item['product_id'] ), $target_ids, true ) || in_array( absint( $item['variation_id'] ), $target_ids, true ) ) {
                return true;
            }
        }

        return false;
    }

}

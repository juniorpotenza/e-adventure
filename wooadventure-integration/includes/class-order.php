<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Order {

    public function __construct() {
        add_action( 'woocommerce_checkout_create_order', array( $this, 'add_tour_date_meta' ), 20, 2 );
        add_filter( 'woocommerce_order_number', array( $this, 'custom_order_number' ), 10, 2 );
    }

    public function add_tour_date_meta( $order, $data ) {
        foreach ( $order->get_items() as $item ) {
            $departure_id = WCAI_Data_Resolver::get_departure_id_for_order_item( $item );

            if ( $departure_id ) {
                $start = WCAI_Data_Resolver::get_departure_start( $departure_id );

                if ( $start ) {
                    // Mantém o campo legado para aplicações existentes.
                    $order->update_meta_data( 'tour_date', $start );
                    return;
                }
            }

            $tour_date = $item->get_meta( 'tour_date', true );
            if ( $tour_date ) {
                $order->update_meta_data( 'tour_date', $tour_date );
                return;
            }
        }
    }

    public function custom_order_number( $order_number, $order ) {
        $datetime = '';

        foreach ( $order->get_items() as $item ) {
            $datetime = WCAI_Data_Resolver::get_event_datetime_for_order_item( $order, $item );
            if ( $datetime ) {
                break;
            }
        }

        if ( $datetime ) {
            $timestamp = WCAI_Data_Resolver::parse_timestamp( $datetime );

            if ( $timestamp ) {
                return $order_number . ' - ' . wp_date( 'd/m/Y H:i', $timestamp );
            }
        }

        return $order_number;
    }
}

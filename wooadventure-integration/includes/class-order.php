<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Order {
    public function __construct() {
        add_action( 'woocommerce_checkout_create_order', array( $this, 'add_tour_date_meta' ), 20, 2 );
        add_filter( 'woocommerce_order_number', array( $this, 'custom_order_number' ), 10, 2 );
    }

    public function add_tour_date_meta( $order, $data ) {
        foreach ( $order->get_items() as $item ) {
            $tour_date = $item->get_meta( 'tour_date' );
            if ( $tour_date ) {
                $order->update_meta_data( 'tour_date', $tour_date );
                break; 
            }
        }
    }

    public function custom_order_number( $order_number, $order ) {
        $tour_date = $order->get_meta( 'tour_date' );
        if ( $tour_date ) {
            $timestamp = strtotime( $tour_date );
            $formatted_date = date( 'd/m/Y H:i', $timestamp );
            return $order_number . ' - ' . $formatted_date;
        }
        return $order_number;
    }
}

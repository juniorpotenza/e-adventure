<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Order {

    public function __construct() {
        add_action( 'woocommerce_checkout_create_order', array( $this, 'add_tour_date_meta' ), 20, 2 );
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
}

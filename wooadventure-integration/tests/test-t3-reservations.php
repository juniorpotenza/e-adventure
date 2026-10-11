<?php
/**
 * T3 - reservas e capacidade. Requer a suíte WP_UnitTestCase (wp-phpunit) com o plugin carregado.
 */
class Test_T3_Reservations extends WP_UnitTestCase {

    private function make_departure( $capacity = 1 ) {
        $id = self::factory()->post->create( array( 'post_type' => WCAI_Departures::POST_TYPE, 'post_status' => 'publish' ) );
        update_post_meta( $id, '_wcai_departure_status', 'open' );
        update_post_meta( $id, '_wcai_capacity', $capacity );
        update_post_meta( $id, '_wcai_starts_at', gmdate( 'Y-m-d H:i', time() + 30 * DAY_IN_SECONDS ) );
        return $id;
    }

    private function status_of( $reservation_id ) {
        return WCAI_Reservations::get_by_id( $reservation_id )->status;
    }

    private function expire_now( $reservation_id ) {
        global $wpdb;
        $wpdb->update( WCAI_Reservations::get_table_name(), array( 'expires_at' => gmdate( 'Y-m-d H:i:s', time() - 60 ) ), array( 'id' => $reservation_id ) );
    }

    public function test_two_orders_compete_for_last_seat_second_fails() {
        $departure = $this->make_departure( 1 );

        $first = WCAI_Reservations::create( $departure, 1, 'hold', 101, 1001 );
        $second = WCAI_Reservations::create( $departure, 1, 'hold', 102, 1002 );

        $this->assertIsInt( $first );
        $this->assertWPError( $second );
        $this->assertSame( 'wcai_capacity_exceeded', $second->get_error_code() );
    }

    public function test_hold_has_expiry_and_expired_hold_frees_seat() {
        $departure = $this->make_departure( 1 );
        $id = WCAI_Reservations::create( $departure, 1, 'hold', 101, 1001 );

        $this->assertNotEmpty( WCAI_Reservations::get_by_id( $id )->expires_at );
        $this->assertSame( 0, WCAI_Reservations::get_available_quantity( $departure ) );

        $this->expire_now( $id );
        WCAI_Reservations::expire_holds();

        $this->assertSame( 'expired', $this->status_of( $id ) );
        $this->assertSame( 1, WCAI_Reservations::get_available_quantity( $departure ) );
    }

    public function test_confirm_order_turns_live_hold_into_confirmed_without_expiry() {
        $departure = $this->make_departure( 1 );
        $id = WCAI_Reservations::create( $departure, 1, 'hold', 101, 1001 );

        WCAI_Reservations::confirm_order( 101 );

        $row = WCAI_Reservations::get_by_id( $id );
        $this->assertSame( 'confirmed', $row->status );
        $this->assertNull( $row->expires_at );
    }

    public function test_reconfirm_without_seat_is_refused() {
        $departure = $this->make_departure( 1 );
        $a = WCAI_Reservations::create( $departure, 1, 'hold', 101, 1001 );
        WCAI_Reservations::cancel_order( 101 );
        $b = WCAI_Reservations::create( $departure, 1, 'hold', 102, 1002 );
        $this->assertIsInt( $b );

        WCAI_Reservations::confirm_order( 101 ); // pedido A volta a pago, sem vaga

        $this->assertSame( 'cancelled', $this->status_of( $a ) );
        $this->assertSame( 0, WCAI_Reservations::get_available_quantity( $departure ) );
    }

    public function test_expired_hold_confirmed_after_payment_rechecks_capacity() {
        $departure = $this->make_departure( 1 );
        $a = WCAI_Reservations::create( $departure, 1, 'hold', 101, 1001 );
        $this->expire_now( $a );
        $b = WCAI_Reservations::create( $departure, 1, 'hold', 102, 1002 );
        $this->assertIsInt( $b );

        WCAI_Reservations::confirm_order( 101 );

        $this->assertSame( 'hold', $this->status_of( $a ) );
    }

    public function test_recreate_for_same_item_revives_expired_hold_when_seat_is_free() {
        $departure = $this->make_departure( 1 );
        $id = WCAI_Reservations::create( $departure, 1, 'hold', 101, 1001 );
        $this->expire_now( $id );
        WCAI_Reservations::expire_holds();

        $again = WCAI_Reservations::create( $departure, 1, 'hold', 101, 1001 );

        $this->assertSame( $id, $again );
        $this->assertSame( 'hold', $this->status_of( $id ) );
    }

    public function test_release_order_expires_earlier_unpaid_reservations_only() {
        $d1 = $this->make_departure( 5 );
        $d2 = $this->make_departure( 5 );
        $held = WCAI_Reservations::create( $d1, 2, 'hold', 101, 1001 );
        $paid = WCAI_Reservations::create( $d2, 1, 'confirmed', 101, 1002 );

        WCAI_Reservations::release_order( 101 );

        $this->assertSame( 'expired', $this->status_of( $held ) );
        $this->assertSame( 'confirmed', $this->status_of( $paid ) );
        $this->assertSame( 5, WCAI_Reservations::get_available_quantity( $d1 ) );
    }

    // Pedido com status 'processing' sem disparar confirm_order na gravação.
    private function make_paid_order() {
        $callback = array( 'WCAI_Reservations', 'confirm_order' );
        remove_action( 'woocommerce_order_status_processing', $callback );
        $order = wc_create_order();
        $item = new WC_Order_Item_Product();
        $item->set_name( 'Passeio' );
        $item->set_quantity( 1 );
        $order->add_item( $item );
        $order->set_status( 'processing' );
        $order->save();
        add_action( 'woocommerce_order_status_processing', $callback );
        return array( $order->get_id(), absint( $item->get_id() ) );
    }

    // Regressão: create() devolvia o id do log de auditoria em vez do id da reserva.
    public function test_create_returns_reservation_id_not_audit_log_id() {
        global $wpdb;
        $departure = $this->make_departure( 5 );

        $id = WCAI_Reservations::create( $departure, 1, 'hold', 101, 1001 );

        $this->assertSame( 1001, (int) WCAI_Reservations::get_by_id( $id )->order_item_id );
        $this->assertSame( $id, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . WCAI_Reservations::get_table_name() . ' WHERE order_item_id = %d', 1001 ) ) );
    }

    // Regressão: release 'cancelled' bloqueava o reenvio do mesmo item com wcai_reservation_closed.
    public function test_retry_after_release_order_revives_same_reservation() {
        $departure = $this->make_departure( 5 );
        $id = WCAI_Reservations::create( $departure, 2, 'hold', 101, 1001 );
        WCAI_Reservations::release_order( 101 );

        $again = WCAI_Reservations::create( $departure, 2, 'hold', 101, 1001 );

        $this->assertSame( $id, $again );
        $this->assertSame( 'hold', $this->status_of( $id ) );
    }

    public function test_cancel_order_keeps_cancelled_closed_for_same_item() {
        $departure = $this->make_departure( 5 );
        WCAI_Reservations::create( $departure, 1, 'hold', 101, 1001 );
        WCAI_Reservations::cancel_order( 101 );

        $again = WCAI_Reservations::create( $departure, 1, 'hold', 101, 1001 );

        $this->assertWPError( $again );
        $this->assertSame( 'wcai_reservation_closed', $again->get_error_code() );
    }

    public function test_resubmit_of_live_hold_renews_expiry() {
        global $wpdb;
        $departure = $this->make_departure( 5 );
        $id = WCAI_Reservations::create( $departure, 1, 'hold', 101, 1001 );
        $soon = gmdate( 'Y-m-d H:i:s', time() + 60 );
        $wpdb->update( WCAI_Reservations::get_table_name(), array( 'expires_at' => $soon ), array( 'id' => $id ) );

        $again = WCAI_Reservations::create( $departure, 1, 'hold', 101, 1001 );

        $this->assertSame( $id, $again );
        $this->assertGreaterThan( $soon, WCAI_Reservations::get_by_id( $id )->expires_at );
    }

    public function test_expire_orphans_expires_holds_of_items_no_longer_in_order() {
        $departure = $this->make_departure( 5 );
        list( $order_id, $item_id ) = $this->make_paid_order();
        $current = WCAI_Reservations::create( $departure, 1, 'hold', $order_id, $item_id );
        $orphan = WCAI_Reservations::create( $departure, 1, 'hold', $order_id, $item_id + 9000 );

        WCAI_Reservations::expire_orphans( $order_id, array( $item_id ) );

        $this->assertSame( 'hold', $this->status_of( $current ) );
        $this->assertSame( 'expired', $this->status_of( $orphan ) );
    }

    public function test_confirm_order_ignores_reservation_of_item_not_in_order() {
        $departure = $this->make_departure( 5 );
        list( $order_id, $item_id ) = $this->make_paid_order();
        $current = WCAI_Reservations::create( $departure, 1, 'hold', $order_id, $item_id );
        $orphan = WCAI_Reservations::create( $departure, 1, 'hold', $order_id, $item_id + 9000 );

        WCAI_Reservations::confirm_order( $order_id );

        $this->assertSame( 'confirmed', $this->status_of( $current ) );
        $this->assertSame( 'hold', $this->status_of( $orphan ) );
    }

    public function test_expire_holds_skips_hold_of_paid_order() {
        $departure = $this->make_departure( 5 );
        list( $order_id, $item_id ) = $this->make_paid_order();
        $id = WCAI_Reservations::create( $departure, 1, 'hold', $order_id, $item_id );
        $this->expire_now( $id );

        WCAI_Reservations::expire_holds();

        $this->assertSame( 'hold', $this->status_of( $id ) );
    }

    public function test_paid_order_without_seat_goes_on_hold_and_fires_action() {
        $departure = $this->make_departure( 1 );
        list( $order_id, $item_id ) = $this->make_paid_order();
        $a = WCAI_Reservations::create( $departure, 1, 'hold', $order_id, $item_id );
        $this->expire_now( $a );
        $this->assertIsInt( WCAI_Reservations::create( $departure, 1, 'hold', 102, 1002 ) );
        $fired = array();
        add_action( 'wcai_paid_without_seat', function( $order, $reservation ) use ( &$fired ) { $fired = array( $order, $reservation ); }, 10, 2 );

        WCAI_Reservations::confirm_order( $order_id );

        $this->assertSame( 'on-hold', wc_get_order( $order_id )->get_status() );
        $this->assertSame( array( $order_id, $a ), $fired );
    }

    public function test_retry_ignored_when_order_is_not_paid() {
        $departure = $this->make_departure( 5 );
        $order = wc_create_order(); // status 'pending'
        $id = WCAI_Reservations::create( $departure, 1, 'hold', $order->get_id(), 1001 );

        WCAI_Reservations::confirm_order( $order->get_id(), 1 );

        $this->assertSame( 'hold', $this->status_of( $id ) );
    }
}

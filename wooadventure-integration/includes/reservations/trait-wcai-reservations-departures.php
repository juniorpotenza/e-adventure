<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Reservations_Departures {

    public static function get_open_departures_for_product( $product_id, $variation_id = 0 ) {
        $product_ids = array_filter( array_unique( array( absint( $product_id ), absint( $variation_id ) ) ) );

        if ( empty( $product_ids ) ) {
            return array();
        }

        $departures = get_posts(
            array(
                'post_type' => WCAI_Departures::POST_TYPE,
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'meta_value',
                'meta_key' => '_wcai_starts_at',
                'order' => 'ASC',
                'meta_query' => array(
                    'relation' => 'AND',
                    array( 'key' => '_wcai_product_id', 'value' => $product_ids, 'compare' => 'IN' ),
                    array( 'key' => '_wcai_departure_status', 'value' => array( 'open', 'confirmed' ), 'compare' => 'IN' ),
                ),
            )
        );

        $now = current_time( 'timestamp' );

        return array_values(
            array_filter(
                $departures,
                static function( $departure ) use ( $now ) {
                    $start = WCAI_Data_Resolver::get_departure_start( $departure->ID );
                    $timestamp = WCAI_Data_Resolver::parse_timestamp( $start );

                    return $timestamp && $timestamp >= $now && self::is_booking_open( $departure->ID );
                }
            )
        );
    }

    public static function get_calendar_departures_for_product( $product_id, $variation_id = 0 ) {
        $product_ids = array_filter( array_unique( array( absint( $product_id ), absint( $variation_id ) ) ) );

        if ( empty( $product_ids ) ) {
            return array();
        }

        $departures = get_posts(
            array(
                'post_type'      => WCAI_Departures::POST_TYPE,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'meta_value',
                'meta_key'       => '_wcai_starts_at',
                'order'          => 'ASC',
                'meta_query'     => array(
                    'relation' => 'AND',
                    array(
                        'key'     => '_wcai_product_id',
                        'value'   => $product_ids,
                        'compare' => 'IN',
                    ),
                    array(
                        'key'     => '_wcai_departure_status',
                        'value'   => array( 'open', 'confirmed', 'full' ),
                        'compare' => 'IN',
                    ),
                ),
            )
        );

        $now = current_time( 'timestamp' );
        $limit = $now + ( 370 * DAY_IN_SECONDS );

        return array_values(
            array_filter(
                $departures,
                static function( $departure ) use ( $now, $limit ) {
                    $start = WCAI_Data_Resolver::get_departure_start( $departure->ID );
                    $timestamp = WCAI_Data_Resolver::parse_timestamp( $start );

                    return $timestamp && $timestamp >= $now && $timestamp <= $limit;
                }
            )
        );
    }

    public static function has_open_departures_for_product( $product_id, $variation_id = 0 ) {
        return ! empty( self::get_open_departures_for_product( $product_id, $variation_id ) );
    }

    public static function is_available_for_product( $departure_id, $product_id, $variation_id, $quantity ) {
        $departure_id = absint( $departure_id );
        $departure_product = absint( get_post_meta( $departure_id, '_wcai_product_id', true ) );
        if ( ! in_array( $departure_product, array( absint( $product_id ), absint( $variation_id ) ), true ) ) return false;

        $departure = get_post( $departure_id );
        return $departure && WCAI_Departures::POST_TYPE === $departure->post_type && 'publish' === $departure->post_status && in_array( get_post_meta( $departure_id, '_wcai_departure_status', true ), array( 'open', 'confirmed' ), true ) && self::is_booking_open( $departure_id ) && self::get_available_quantity( $departure_id ) >= absint( $quantity );
    }
}

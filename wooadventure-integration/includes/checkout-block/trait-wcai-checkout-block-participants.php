<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Checkout_Block_Participants {

    private function save_participants( $order, $item_id, $reservation_id, $billing_name, $billing_cpf, $billing_birthdate, $additional ) {
        if ( ! class_exists( 'WCAI_Participants_DB' ) ) {
            $this->fail( 'O cadastro de participantes não está disponível.' );
        }

        $participants = array(
            array(
                'nome_completo' => $billing_name,
                'cpf' => $billing_cpf,
                'data_nascimento' => $billing_birthdate,
            ),
        );

        foreach ( $additional as $participant ) {
            $participants[] = array(
                'nome_completo' => sanitize_text_field( $participant['name'] ),
                'cpf' => preg_replace( '/\\D+/', '', $participant['cpf'] ),
                'data_nascimento' => sanitize_text_field( $participant['birthdate'] ),
            );
        }

        foreach ( $participants as $participant ) {
            $result = WCAI_Participants_DB::add(
                array(
                    'order_id' => $order->get_id(),
                    'item_id' => $item_id,
                    'reservation_id' => $reservation_id,
                    'customer_id' => $order->get_customer_id(),
                    'nome_completo' => $participant['nome_completo'],
                    'cpf' => $participant['cpf'],
                    'data_nascimento' => $participant['data_nascimento'],
                )
            );

            if ( false === $result ) {
                $this->fail( 'Não foi possível registrar um participante. Tente novamente.' );
            }
        }
    }

    private function fail( $message ) {
        if ( class_exists( 'Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException' ) ) {
            throw new Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                'wcai_checkout_invalid',
                esc_html( $message ),
                400
            );
        }

        throw new Exception( esc_html( $message ) );
    }
}

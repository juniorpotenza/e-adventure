<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Checkout_Block_Reservation {

    public function render_checkout_ui( $block_content ) {
        if ( ! $this->has_target_cart_item() ) {
            return $block_content;
        }

        wp_enqueue_script( self::SCRIPT_HANDLE );

        $html  = '<div id="wcai-checkout-wizard" class="wcai-checkout-wizard" aria-label="Checkout WooAdventure">';
        $html .= '<ol class="wcai-wizard-progress" aria-label="' . esc_attr__( 'Etapas do checkout', 'wooadventure-integration' ) . '">';
        $html .= '<li><button type="button" data-step-label="1" class="is-active" aria-current="step">' . esc_html__( '1. Reserva', 'wooadventure-integration' ) . '</button></li>';
        $html .= '<li><button type="button" data-step-label="2" disabled>' . esc_html__( '2. Seus dados', 'wooadventure-integration' ) . '</button></li>';
        $html .= '<li><button type="button" data-step-label="3" disabled>' . esc_html__( '3. Participantes', 'wooadventure-integration' ) . '</button></li>';
        $html .= '<li><button type="button" data-step-label="4" disabled>' . esc_html__( '4. Revisão e pagamento', 'wooadventure-integration' ) . '</button></li>';
        $html .= '</ol>';
        $html .= '<div class="wcai-sr-only" data-wcai-live aria-live="polite" aria-atomic="true"></div>';
        $html .= '<div data-wcai-step="1" class="wcai-wizard-panel is-active"></div>';
        $html .= '<div data-wcai-step="2" class="wcai-wizard-panel"></div>';
        $html .= '<div data-wcai-step="3" class="wcai-wizard-panel"></div>';
        $html .= '<div data-wcai-step="4" class="wcai-wizard-panel"></div>';
        $html .= '</div>';

        return $html . $block_content;
    }

    public function capture_checkout_payload( $order, $request ) {
        if ( ! $order instanceof WC_Order || ! $request instanceof WP_REST_Request || ! $this->order_has_target_item( $order ) ) {
            return;
        }

        $extensions = $request->get_param( 'extensions' );
        $raw = isset( $extensions[ self::NAMESPACE ]['data'] ) ? (string) $extensions[ self::NAMESPACE ]['data'] : '';
        $payload = $raw ? json_decode( $raw, true ) : null;

        if ( ! is_array( $payload ) || empty( $payload['items'] ) || ! is_array( $payload['items'] ) ) {
            return;
        }

        if ( function_exists( 'WC' ) && WC()->session ) {
            WC()->session->set(
                'wcai_checkout_payload_' . absint( $order->get_id() ),
                $payload
            );
        }
    }

    public function process_checkout( $order ) {
        try {
            $this->reserve_order_items( $order );
        } catch ( \Throwable $e ) {
            // Falha em um item libera as reservas já criadas dos itens anteriores.
            if ( $order instanceof WC_Order && class_exists( 'WCAI_Reservations' ) ) {
                WCAI_Reservations::release_order( $order->get_id() );
            }
            throw $e;
        }
    }

    private function reserve_order_items( $order ) {
        if ( ! $order instanceof WC_Order || ! $this->order_has_target_item( $order ) ) {
            return;
        }

        // Itens do rascunho recriados com novos ids deixam reservas órfãs do pedido: expira antes de reservar.
        if ( class_exists( 'WCAI_Reservations' ) ) {
            WCAI_Reservations::expire_orphans( $order->get_id(), array_keys( $order->get_items() ) );
        }

        $payload = null;

        if ( function_exists( 'WC' ) && WC()->session ) {
            $payload = WC()->session->get( 'wcai_checkout_payload_' . absint( $order->get_id() ) );
        }

        if ( ! is_array( $payload ) ) {
            $this->fail( 'Os dados da reserva não foram recebidos. Atualize o checkout e tente novamente.' );
        }

        $billing_cpf = WCAI_Data_Resolver::get_billing_cpf( $order );
        $billing_birthdate = WCAI_Data_Resolver::get_billing_birthdate( $order );
        $billing_name = WCAI_Data_Resolver::get_billing_name( $order );

        if ( ! $billing_name ) {
            $this->fail( 'Informe o nome do titular nos dados de faturamento.' );
        }

        if ( ! WCAI_Utils::is_valid_cpf( $billing_cpf ) ) {
            $this->fail( 'Informe um CPF válido nos dados de faturamento do titular.' );
        }

        if ( WCAI_Settings::is_cpf_blocked( $billing_cpf ) ) {
            $this->fail( 'O CPF do titular está restrito para este agendamento.' );
        }

        if ( ! WCAI_Utils::is_valid_date( $billing_birthdate ) || ! WCAI_Utils::is_min_age( $billing_birthdate ) ) {
            $this->fail( 'Informe uma data de nascimento válida nos dados de faturamento do titular.' );
        }

        $target_ids = WCAI_Settings::get_product_ids();
        $used_submission_indexes = array();

        foreach ( $order->get_items() as $item_id => $item ) {
            $product_id = absint( $item->get_product_id() );
            $variation_id = absint( $item->get_variation_id() );

            if ( ! in_array( $product_id, $target_ids, true ) && ! in_array( $variation_id, $target_ids, true ) ) {
                continue;
            }

            $index = $this->find_submission( $payload['items'], $product_id, $variation_id, $used_submission_indexes );

            if ( null === $index ) {
                $this->fail( 'Não foi possível identificar uma reserva do pedido.' );
            }

            $used_submission_indexes[] = $index;
            $data = is_array( $payload['items'][ $index ] ) ? $payload['items'][ $index ] : array();
            $quantity = max( 1, absint( $item->get_quantity() ) );
            $adults = max( 1, absint( isset( $data['adults'] ) ? $data['adults'] : $item->get_meta( 'Adultos', true ) ) );
            $children = max( 0, absint( isset( $data['children'] ) ? $data['children'] : $item->get_meta( 'Crianças', true ) ) );

            if ( $adults + $children !== $quantity ) {
                $this->fail( 'A quantidade de adultos e crianças não corresponde ao total da reserva.' );
            }

            $departure_id = absint( isset( $data['departure_id'] ) ? $data['departure_id'] : 0 );

            if ( ! $departure_id ) {
                $departure_id = WCAI_Data_Resolver::get_departure_id_for_order_item( $item );
            }

            if ( ! $departure_id || ! class_exists( 'WCAI_Reservations' ) || ! WCAI_Reservations::is_available_for_product( $departure_id, $product_id, $variation_id, $quantity ) ) {
                $this->fail( 'A saída selecionada não está mais disponível ou o prazo de compra foi encerrado.' );
            }

            $additional = isset( $data['additional_participants'] ) && is_array( $data['additional_participants'] )
                ? $data['additional_participants']
                : array();

            $this->validate_additional_participants( $additional, $quantity, $billing_cpf, $product_id, $adults, $children );

            $item->update_meta_data( '_wcai_departure_id', $departure_id, true );
            $item->update_meta_data( 'Adultos', $adults, true );
            $item->update_meta_data( 'Crianças', $children, true );
            $item->save();

            $reservation = WCAI_Reservations::create( $departure_id, $quantity, 'hold', $order->get_id(), $item_id );

            if ( is_wp_error( $reservation ) ) {
                $this->fail( $reservation->get_error_message() );
            }

            $reservation_id = absint( $reservation );

            $this->save_participants(
                $order,
                $item_id,
                $reservation_id,
                $billing_name,
                $billing_cpf,
                $billing_birthdate,
                $additional
            );

            $event_datetime = WCAI_Data_Resolver::get_departure_start( $departure_id );

            if ( $event_datetime ) {
                $order->update_meta_data( 'tour_date', $event_datetime );
            }
        }

        $order->save();

        if ( function_exists( 'WC' ) && WC()->session ) {
            WC()->session->__unset( 'wcai_checkout_payload_' . absint( $order->get_id() ) );
        }
    }


}

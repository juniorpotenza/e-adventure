<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Checkout_Block_Validation {

    private function order_has_target_item( $order ) {
        $target_ids = WCAI_Settings::get_product_ids();

        foreach ( $order->get_items() as $item ) {
            if ( in_array( absint( $item->get_product_id() ), $target_ids, true ) || in_array( absint( $item->get_variation_id() ), $target_ids, true ) ) {
                return true;
            }
        }

        return false;
    }

    private function find_submission( $items, $product_id, $variation_id, $used_indexes ) {
        foreach ( $items as $index => $item ) {
            if ( in_array( $index, $used_indexes, true ) || ! is_array( $item ) ) {
                continue;
            }

            if ( absint( isset( $item['product_id'] ) ? $item['product_id'] : 0 ) !== $product_id ) {
                continue;
            }

            if ( absint( isset( $item['variation_id'] ) ? $item['variation_id'] : 0 ) !== $variation_id ) {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function validate_additional_participants( $participants, $quantity, $billing_cpf, $product_id = 0, $adults = 1, $children = 0 ) {
        $expected = max( 0, $quantity - 1 );
        $expected_additional_adults = max( 0, absint( $adults ) - 1 );
        $expected_children = absint( $children );

        if ( count( $participants ) !== $expected ) {
            $this->fail( 'Informe os dados de todos os participantes adicionais.' );
        }

        $cpfs = $billing_cpf ? array( $billing_cpf ) : array();
        $additional_adults = 0;
        $additional_children = 0;
        $age_min = max( 7, absint( get_post_meta( $product_id, '_wcai_tour_age_min', true ) ) );
        $children_allowed = 'yes' === get_post_meta( $product_id, '_wcai_tour_children_allowed', true );
        $child_age_max = absint( get_post_meta( $product_id, '_wcai_tour_children_age_max', true ) );

        foreach ( $participants as $index => $participant ) {
            if ( ! is_array( $participant ) ) {
                $this->fail( 'Os dados de um participante adicional são inválidos.' );
            }

            $position = $index + 2;
            $name = isset( $participant['name'] ) ? sanitize_text_field( $participant['name'] ) : '';
            $cpf = preg_replace( '/\D+/', '', (string) ( isset( $participant['cpf'] ) ? $participant['cpf'] : '' ) );
            $birthdate = sanitize_text_field( isset( $participant['birthdate'] ) ? $participant['birthdate'] : '' );
            $type = isset( $participant['type'] ) ? sanitize_key( $participant['type'] ) : 'adult';

            if ( ! in_array( $type, array( 'adult', 'child' ), true ) ) {
                $this->fail( sprintf( 'A categoria do participante %d é inválida.', $position ) );
            }

            if ( 'child' === $type ) {
                $additional_children++;
            } else {
                $additional_adults++;
            }

            if ( ! $name ) {
                $this->fail( sprintf( 'Informe o nome completo do participante %d.', $position ) );
            }

            if ( ! WCAI_Utils::is_valid_cpf( $cpf ) ) {
                $this->fail( sprintf( 'CPF do participante %d inválido.', $position ) );
            }

            if ( WCAI_Settings::is_cpf_blocked( $cpf ) ) {
                $this->fail( sprintf( 'O CPF do participante %d está restrito.', $position ) );
            }

            if ( in_array( $cpf, $cpfs, true ) ) {
                $this->fail( sprintf( 'O CPF do participante %d já foi usado neste pedido.', $position ) );
            }

            $cpfs[] = $cpf;

            if ( ! WCAI_Utils::is_valid_date( $birthdate ) ) {
                $this->fail( sprintf( 'A data de nascimento do participante %d é inválida.', $position ) );
            }

            $birth = DateTime::createFromFormat( 'd/m/Y', $birthdate );
            $age = $birth ? ( new DateTime() )->diff( $birth )->y : 0;

            if ( $age < $age_min ) {
                $this->fail( sprintf( 'O participante %d não atende à idade mínima de %d anos.', $position, $age_min ) );
            }

            if ( 'child' === $type ) {
                if ( ! $children_allowed ) {
                    $this->fail( 'Este passeio não permite crianças.' );
                }

                if ( $child_age_max && $age > $child_age_max ) {
                    $this->fail( sprintf( 'O participante %d ultrapassa a idade máxima de %d anos para a categoria criança.', $position, $child_age_max ) );
                }
            }
        }

        if ( $additional_adults !== $expected_additional_adults || $additional_children !== $expected_children ) {
            $this->fail( 'A quantidade de adultos e crianças informada nos participantes não corresponde à reserva.' );
        }
    }

}

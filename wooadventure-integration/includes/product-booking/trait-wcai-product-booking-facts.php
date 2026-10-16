<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Product_Booking_Facts {

    private function get_tour_profile( $product_id ) {
        return array(
            'age_min'          => absint( get_post_meta( $product_id, '_wcai_tour_age_min', true ) ),
            'age_max'          => absint( get_post_meta( $product_id, '_wcai_tour_age_max', true ) ),
            'children_allowed' => 'yes' === get_post_meta( $product_id, '_wcai_tour_children_allowed', true ),
            'children_age_max' => absint( get_post_meta( $product_id, '_wcai_tour_children_age_max', true ) ),
            'children_max'     => absint( get_post_meta( $product_id, '_wcai_tour_children_max', true ) ),
            'difficulty'       => sanitize_key( get_post_meta( $product_id, '_wcai_tour_difficulty', true ) ),
            'weather'          => sanitize_textarea_field( get_post_meta( $product_id, '_wcai_tour_weather', true ) ),
            'safety'            => sanitize_textarea_field( get_post_meta( $product_id, '_wcai_tour_safety', true ) ),
            'gear'              => sanitize_textarea_field( get_post_meta( $product_id, '_wcai_tour_gear', true ) ),
        );
    }

    private function get_age_label( $profile ) {
        $min = absint( isset( $profile['age_min'] ) ? $profile['age_min'] : 0 );
        $max = absint( isset( $profile['age_max'] ) ? $profile['age_max'] : 0 );

        if ( $min && $max ) {
            return sprintf( '%d a %d anos', $min, $max );
        }

        if ( $min ) {
            return sprintf( 'A partir de %d anos', $min );
        }

        if ( $max ) {
            return sprintf( 'Até %d anos', $max );
        }

        return '';
    }

    public function render_product_facts() {
        global $product;

        if ( ! $product || ! $this->is_target_product( $product->get_id() ) ) {
            return;
        }

        $facts = array();
        $profile = $this->get_tour_profile( $product->get_id() );
        $age_label = $this->get_age_label( $profile );

        if ( $age_label ) {
            $facts[] = array(
                'label' => 'Faixa etária',
                'value' => $age_label,
            );
        }

        if ( ! empty( $profile['children_allowed'] ) ) {
            $children_label = ! empty( $profile['children_age_max'] )
                ? sprintf( 'Até %d anos', absint( $profile['children_age_max'] ) )
                : 'Permitidas';

            if ( ! empty( $profile['children_max'] ) ) {
                $children_label .= sprintf( ' · máx. %d', absint( $profile['children_max'] ) );
            }

            $facts[] = array(
                'label' => 'Crianças',
                'value' => $children_label,
            );
        }

        foreach ( $product->get_attributes() as $attribute ) {
            if ( ! $attribute->get_visible() ) {
                continue;
            }

            $label = wc_attribute_label( $attribute->get_name() );
            $values = $attribute->is_taxonomy()
                ? wc_get_product_terms( $product->get_id(), $attribute->get_name(), array( 'fields' => 'names' ) )
                : $attribute->get_options();

            $values = array_filter( array_map( 'sanitize_text_field', (array) $values ) );

            if ( $label && ! empty( $values ) ) {
                $facts[] = array(
                    'label' => $label,
                    'value' => implode( ', ', array_slice( $values, 0, 3 ) ),
                );
            }

            if ( count( $facts ) >= 6 ) {
                break;
            }
        }

        $tour_info = array();

        $difficulty_labels = array(
            'easy'      => 'Fácil',
            'moderate'  => 'Moderada',
            'hard'      => 'Difícil',
            'very-hard' => 'Muito difícil',
        );

        if ( ! empty( $profile['difficulty'] ) && isset( $difficulty_labels[ $profile['difficulty'] ] ) ) {
            $tour_info[] = array(
                'key'   => 'difficulty',
                'label' => 'Dificuldade',
                'value' => $difficulty_labels[ $profile['difficulty'] ],
            );
        }

        if ( ! empty( $profile['weather'] ) ) {
            $tour_info[] = array(
                'key'   => 'weather',
                'label' => 'Condições climáticas',
                'value' => $profile['weather'],
            );
        }

        if ( ! empty( $profile['safety'] ) ) {
            $tour_info[] = array(
                'key'   => 'safety',
                'label' => 'Segurança',
                'value' => $profile['safety'],
            );
        }

        if ( ! empty( $profile['gear'] ) ) {
            $tour_info[] = array(
                'key'   => 'gear',
                'label' => 'O que levar',
                'value' => $profile['gear'],
            );
        }

        if ( empty( $facts ) && empty( $tour_info ) ) {
            return;
        }

        if ( ! empty( $facts ) ) {
            echo '<section class="wcai-tour-facts" aria-label="Informações do passeio">';
            echo '<div class="wcai-tour-facts-heading"><strong>Sobre este passeio</strong><small>Informações principais e regras do público</small></div>';
            echo '<div class="wcai-tour-facts-grid">';
            foreach ( $facts as $fact ) {
                echo '<div><span>' . esc_html( $fact['label'] ) . '</span><strong>' . esc_html( $fact['value'] ) . '</strong></div>';
            }
            echo '</div>';
            echo '</section>';
        }

        if ( ! empty( $tour_info ) ) {
            echo '<section class="wcai-tour-info" aria-label="Informações importantes antes de reservar">';
            echo '<div class="wcai-tour-info-heading"><strong>Antes de reservar</strong><small>Confira estas orientações da experiência</small></div>';
            echo '<div class="wcai-tour-info-grid">';

            foreach ( $tour_info as $info ) {
                echo '<article class="wcai-tour-info-card is-' . esc_attr( $info['key'] ) . '">';
                echo '<div class="wcai-tour-info-icon" aria-hidden="true">';

                switch ( $info['key'] ) {
                    case 'difficulty':
                        echo '<svg viewBox="0 0 24 24" role="presentation"><path d="M4 19h16M6 17l4-7 3 4 3-6 2 9"/><circle cx="10" cy="7" r="2"/></svg>';
                        break;
                    case 'weather':
                        echo '<svg viewBox="0 0 24 24" role="presentation"><path d="M6 18h11a4 4 0 0 0 .4-8 6 6 0 0 0-11.2-1.2A4 4 0 0 0 6 18Z"/><path d="M8 21l-1 2M12 21l-1 2M16 21l-1 2"/></svg>';
                        break;
                    case 'safety':
                        echo '<svg viewBox="0 0 24 24" role="presentation"><path d="M12 3l7 3v5c0 4.6-2.9 8.3-7 10-4.1-1.7-7-5.4-7-10V6l7-3Z"/><path d="m9 12 2 2 4-5"/></svg>';
                        break;
                    case 'gear':
                        echo '<svg viewBox="0 0 24 24" role="presentation"><path d="M8 6h8v14H8z"/><path d="M10 6V4h4v2M6 10h12M6 14h12"/></svg>';
                        break;
                }

                echo '</div>';
                echo '<div class="wcai-tour-info-card-body"><strong>' . esc_html( $info['label'] ) . '</strong><p>' . nl2br( esc_html( $info['value'] ) ) . '</p></div>';
                echo '</article>';
            }

            echo '</div>';
            echo '</section>';
        }

        $this->enqueue_styles();
    }

}

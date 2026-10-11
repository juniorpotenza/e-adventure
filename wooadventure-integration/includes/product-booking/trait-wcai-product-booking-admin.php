<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Product_Booking_Admin {

    public function add_product_data_tab( $tabs ) {
        $tabs['wcai_tour_data'] = array(
            'label'    => 'Passeio',
            'target'   => 'wcai_tour_data',
            'class'    => array(),
            'priority' => 75,
        );

        return $tabs;
    }

    public function render_product_data_panel() {
        global $post;

        if ( ! $post ) {
            return;
        }

        $age_min = absint( get_post_meta( $post->ID, '_wcai_tour_age_min', true ) );
        $age_max = absint( get_post_meta( $post->ID, '_wcai_tour_age_max', true ) );
        $children_allowed = 'yes' === get_post_meta( $post->ID, '_wcai_tour_children_allowed', true );
        $children_age_max = absint( get_post_meta( $post->ID, '_wcai_tour_children_age_max', true ) );
        $children_max = absint( get_post_meta( $post->ID, '_wcai_tour_children_max', true ) );
        $difficulty = sanitize_key( get_post_meta( $post->ID, '_wcai_tour_difficulty', true ) );
        $weather = get_post_meta( $post->ID, '_wcai_tour_weather', true );
        $safety = get_post_meta( $post->ID, '_wcai_tour_safety', true );
        $gear = get_post_meta( $post->ID, '_wcai_tour_gear', true );

        echo '<div id="wcai_tour_data" class="panel woocommerce_options_panel">';
        echo '<div class="options_group">';
        echo '<p style="padding:0 12px 8px;"><strong>Informações do público</strong><br><span style="color:#646970;">Esses dados aparecem na página do passeio para o cliente entender rapidamente para quem a experiência é indicada.</span></p>';

        woocommerce_wp_text_input(
            array(
                'id'                => 'wcai_tour_age_min',
                'label'             => 'Idade mínima',
                'type'              => 'number',
                'desc_tip'          => true,
                'description'       => 'Deixe em branco quando não houver limite mínimo.',
                'custom_attributes' => array(
                    'min'  => '0',
                    'max'  => '120',
                    'step' => '1',
                ),
                'value'             => $age_min ?: '',
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'                => 'wcai_tour_age_max',
                'label'             => 'Idade máxima',
                'type'              => 'number',
                'desc_tip'          => true,
                'description'       => 'Deixe em branco quando não houver limite máximo.',
                'custom_attributes' => array(
                    'min'  => '0',
                    'max'  => '120',
                    'step' => '1',
                ),
                'value'             => $age_max ?: '',
            )
        );

        woocommerce_wp_checkbox(
            array(
                'id'          => 'wcai_tour_children_allowed',
                'label'       => 'Permite crianças',
                'description' => 'Ative quando crianças puderem participar desta experiência.',
                'desc_tip'    => true,
                'value'       => $children_allowed ? 'yes' : 'no',
                'cbvalue'     => 'yes',
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'                => 'wcai_tour_children_age_max',
                'label'             => 'Idade máxima para categoria criança',
                'type'              => 'number',
                'desc_tip'          => true,
                'description'       => 'Opcional. Use para deixar clara a faixa de idade considerada criança.',
                'custom_attributes' => array(
                    'min'  => '0',
                    'max'  => '120',
                    'step' => '1',
                ),
                'value'             => $children_age_max ?: '',
            )
        );

        woocommerce_wp_text_input(
            array(
                'id'                => 'wcai_tour_children_max',
                'label'             => 'Máximo de crianças por reserva',
                'type'              => 'number',
                'desc_tip'          => true,
                'description'       => 'Informativo para o cliente; não altera automaticamente a quantidade de ingressos.',
                'custom_attributes' => array(
                    'min'  => '0',
                    'max'  => '100',
                    'step' => '1',
                ),
                'value'             => $children_max ?: '',
            )
        );

        echo '<div class="options_group">';
        echo '<p style="padding:8px 12px 8px;"><strong>Informações importantes para o visitante</strong><br><span style="color:#646970;">Use textos curtos e objetivos. Eles aparecem na página do passeio em cards com ícones.</span></p>';

        woocommerce_wp_select(
            array(
                'id'          => 'wcai_tour_difficulty',
                'label'       => 'Dificuldade',
                'description' => 'Indica o nível geral de exigência física/técnica da experiência.',
                'desc_tip'    => true,
                'options'     => array(
                    ''            => 'Não informado',
                    'easy'        => 'Fácil',
                    'moderate'    => 'Moderada',
                    'hard'        => 'Difícil',
                    'very-hard'   => 'Muito difícil',
                ),
                'value'       => $difficulty,
            )
        );

        woocommerce_wp_textarea_input(
            array(
                'id'          => 'wcai_tour_weather',
                'label'       => 'Condições climáticas',
                'description' => 'Orientação sobre chuva, frio, calor, terreno molhado, cancelamento ou outras condições que afetem a experiência. Não é previsão do tempo.',
                'desc_tip'    => true,
                'value'       => $weather,
                'rows'        => 4,
            )
        );

        woocommerce_wp_textarea_input(
            array(
                'id'          => 'wcai_tour_safety',
                'label'       => 'Segurança',
                'description' => 'Principais cuidados, riscos e orientações obrigatórias para participar do passeio.',
                'desc_tip'    => true,
                'value'       => $safety,
                'rows'        => 4,
            )
        );

        woocommerce_wp_textarea_input(
            array(
                'id'          => 'wcai_tour_gear',
                'label'       => 'O que levar',
                'description' => 'Equipamentos, roupas e itens recomendados para a atividade.',
                'desc_tip'    => true,
                'value'       => $gear,
                'rows'        => 4,
            )
        );

        echo '</div>';
        echo '<p style="padding:0 12px 12px;color:#646970;">A capacidade e o mínimo para formação do grupo continuam sendo definidos na Programação/Saída, porque podem variar por data. A previsão do tempo poderá ser integrada futuramente por saída/data; aqui configuramos apenas as regras e orientações permanentes do passeio.</p>';
        echo '</div>';
        echo '</div>';
    }

    public function save_product_data( $product ) {
        if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
            return;
        }

        $age_min = isset( $_POST['wcai_tour_age_min'] ) ? min( 120, max( 0, absint( wp_unslash( $_POST['wcai_tour_age_min'] ) ) ) ) : 0;
        $age_max = isset( $_POST['wcai_tour_age_max'] ) ? min( 120, max( 0, absint( wp_unslash( $_POST['wcai_tour_age_max'] ) ) ) ) : 0;

        if ( $age_min && $age_max && $age_max < $age_min ) {
            $age_max = $age_min;
        }

        $children_allowed = ! empty( $_POST['wcai_tour_children_allowed'] ) ? 'yes' : 'no';
        $children_age_max = isset( $_POST['wcai_tour_children_age_max'] ) ? min( 120, max( 0, absint( wp_unslash( $_POST['wcai_tour_children_age_max'] ) ) ) ) : 0;
        $children_max = isset( $_POST['wcai_tour_children_max'] ) ? min( 100, max( 0, absint( wp_unslash( $_POST['wcai_tour_children_max'] ) ) ) ) : 0;
        $difficulty_options = array( '', 'easy', 'moderate', 'hard', 'very-hard' );
        $difficulty = isset( $_POST['wcai_tour_difficulty'] ) ? sanitize_key( wp_unslash( $_POST['wcai_tour_difficulty'] ) ) : '';
        $difficulty = in_array( $difficulty, $difficulty_options, true ) ? $difficulty : '';
        $weather = isset( $_POST['wcai_tour_weather'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wcai_tour_weather'] ) ) : '';
        $safety = isset( $_POST['wcai_tour_safety'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wcai_tour_safety'] ) ) : '';
        $gear = isset( $_POST['wcai_tour_gear'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wcai_tour_gear'] ) ) : '';

        $product->update_meta_data( '_wcai_tour_age_min', $age_min ?: '' );
        $product->update_meta_data( '_wcai_tour_age_max', $age_max ?: '' );
        $product->update_meta_data( '_wcai_tour_children_allowed', $children_allowed );
        $product->update_meta_data( '_wcai_tour_children_age_max', ( 'yes' === $children_allowed && $children_age_max ) ? $children_age_max : '' );
        $product->update_meta_data( '_wcai_tour_children_max', ( 'yes' === $children_allowed && $children_max ) ? $children_max : '' );
        $product->update_meta_data( '_wcai_tour_difficulty', $difficulty );
        $product->update_meta_data( '_wcai_tour_weather', $weather );
        $product->update_meta_data( '_wcai_tour_safety', $safety );
        $product->update_meta_data( '_wcai_tour_gear', $gear );
    }

}

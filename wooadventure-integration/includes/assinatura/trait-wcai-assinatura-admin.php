<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Assinatura_Admin {

    public function register_cpt() {
        register_post_type(
            'wcai_assinatura',
            array(
                'labels' => array(
                    'name'          => 'Assinaturas',
                    'singular_name' => 'Assinatura',
                ),
                'public'          => false,
                'show_ui'         => true,
                'show_in_menu'    => 'woocommerce',
                'menu_position'   => 58,
                'menu_icon'      => 'dashicons-pen',
                'supports'        => array( 'title' ),
                'capability_type' => 'post',
                'map_meta_cap'    => false,
                'capabilities'    => array(
                    'edit_post'          => WCAI_Capabilities::MANAGE_LEGAL,
                    'read_post'          => WCAI_Capabilities::MANAGE_LEGAL,
                    'delete_post'        => WCAI_Capabilities::MANAGE_LEGAL,
                    'edit_posts'         => WCAI_Capabilities::MANAGE_LEGAL,
                    'create_posts'       => WCAI_Capabilities::MANAGE_LEGAL,
                    'publish_posts'      => WCAI_Capabilities::MANAGE_LEGAL,
                    'delete_posts'       => WCAI_Capabilities::MANAGE_LEGAL,
                    'edit_others_posts'  => WCAI_Capabilities::MANAGE_LEGAL,
                    'delete_others_posts'=> WCAI_Capabilities::MANAGE_LEGAL,
                ),
            )
        );
    }

    public function set_custom_columns( $columns ) {
        return array_merge(
            $columns,
            array(
                'participant_ref' => 'Participante',
                'pedido_ref'      => 'Pedido',
            )
        );
    }

    public function custom_column_content( $column, $post_id ) {
        if ( 'participant_ref' === $column ) {
            echo esc_html( get_post_meta( $post_id, '_wcai_participant_id', true ) ?: '—' );
        }

        if ( 'pedido_ref' === $column ) {
            echo esc_html( get_post_meta( $post_id, '_wcai_pedido_id', true ) ?: '—' );
        }
    }

    public function add_details_metabox() {
        add_meta_box(
            'wcai_sig_details',
            'Detalhes',
            array( $this, 'render_metabox' ),
            'wcai_assinatura',
            'normal',
            'high'
        );
    }

    public function render_metabox( $post ) {
        // Registros novos ficam em wcai-private (endpoint com capability); antigos mantêm a URL pública.
        $url = get_post_meta( $post->ID, '_wcai_assinatura_file', true )
            ? wp_nonce_url( admin_url( 'admin-post.php?action=wcai_signature_image&post_id=' . absint( $post->ID ) ), 'wcai_signature_image_' . absint( $post->ID ) )
            : get_post_meta( $post->ID, '_wcai_assinatura_url', true );
        $ip_hash = get_post_meta( $post->ID, '_wcai_ip_hash', true );
        $participant_id = absint( get_post_meta( $post->ID, '_wcai_participant_id', true ) );
        $reservation_id = absint( get_post_meta( $post->ID, '_wcai_reservation_id', true ) );
        $order_id = absint( get_post_meta( $post->ID, '_wcai_pedido_id', true ) );

        echo '<p><strong>Participante:</strong> ' . esc_html( $participant_id ?: '—' ) . '</p>';
        echo '<p><strong>Reserva:</strong> ' . esc_html( $reservation_id ?: '—' ) . '</p>';
        echo '<p><strong>Pedido histórico:</strong> ' . esc_html( $order_id ?: '—' ) . '</p>';
        echo '<p><strong>IP (hash):</strong> ' . esc_html( $ip_hash ?: '—' ) . '</p>';
        echo $url
            ? '<p><img src="' . esc_url( $url ) . '" style="max-width:300px;border:1px solid #ccc;" alt="Assinatura"></p>'
            : '<p>Sem imagem</p>';
    }
}

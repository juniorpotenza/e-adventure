<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Departures {
    const POST_TYPE = 'wcai_departure';
    const NONCE     = 'wcai_save_departure';

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_departure' ), 10, 2 );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'set_columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
    }

    public function register_post_type() {
        register_post_type( self::POST_TYPE, array(
            'labels' => array(
                'name'          => 'Saídas',
                'singular_name' => 'Saída',
                'add_new_item'  => 'Adicionar nova saída',
                'edit_item'     => 'Editar saída',
                'menu_name'     => 'Saídas',
            ),
            'public'              => false,
            'show_ui'             => true,
            'show_in_menu'        => 'woocommerce',
            'supports'            => array( 'title' ),
            'capability_type'     => 'post',
            'map_meta_cap'        => false,
            'capabilities'        => array(
                'edit_post'           => WCAI_Capabilities::MANAGE_DEPARTURES,
                'read_post'           => WCAI_Capabilities::MANAGE_DEPARTURES,
                'delete_post'         => WCAI_Capabilities::MANAGE_DEPARTURES,
                'edit_posts'          => WCAI_Capabilities::MANAGE_DEPARTURES,
                'create_posts'        => WCAI_Capabilities::MANAGE_DEPARTURES,
                'edit_others_posts'   => WCAI_Capabilities::MANAGE_DEPARTURES,
                'publish_posts'       => WCAI_Capabilities::MANAGE_DEPARTURES,
                'read_private_posts'  => WCAI_Capabilities::MANAGE_DEPARTURES,
                'delete_posts'        => WCAI_Capabilities::MANAGE_DEPARTURES,
                'delete_others_posts' => WCAI_Capabilities::MANAGE_DEPARTURES,
            ),
        ) );
    }

    public function add_meta_boxes() {
        add_meta_box( 'wcai_departure_details', 'Detalhes operacionais', array( $this, 'render_details_box' ), self::POST_TYPE, 'normal', 'high' );
    }

    public function render_details_box( $post ) {
        wp_nonce_field( self::NONCE, 'wcai_departure_nonce' );
        $values = array(
            'product_id'       => absint( get_post_meta( $post->ID, '_wcai_product_id', true ) ),
            'starts_at'        => get_post_meta( $post->ID, '_wcai_starts_at', true ),
            'duration_minutes' => absint( get_post_meta( $post->ID, '_wcai_duration_minutes', true ) ),
            'capacity'         => absint( get_post_meta( $post->ID, '_wcai_capacity', true ) ),
            'minimum_capacity' => absint( get_post_meta( $post->ID, '_wcai_minimum_capacity', true ) ),
            'meeting_point'    => get_post_meta( $post->ID, '_wcai_meeting_point', true ),
            'guide_id'         => absint( get_post_meta( $post->ID, '_wcai_guide_id', true ) ),
            'status'           => get_post_meta( $post->ID, '_wcai_departure_status', true ) ?: 'draft',
        );
        $products = function_exists( 'wc_get_products' ) ? wc_get_products( array( 'limit' => -1, 'status' => 'publish', 'return' => 'objects' ) ) : array();
        $guides = get_users( array( 'role__in' => array( 'wcai_guide', 'administrator' ), 'orderby' => 'display_name' ) );
        ?>
        <p><label for="wcai_product_id"><strong>Produto</strong></label><br>
            <select id="wcai_product_id" name="wcai_departure[product_id]" required>
                <option value="">Selecione um produto</option>
                <?php foreach ( $products as $product ) : ?>
                    <option value="<?php echo esc_attr( $product->get_id() ); ?>" <?php selected( $values['product_id'], $product->get_id() ); ?>><?php echo esc_html( $product->get_name() ); ?></option>
                <?php endforeach; ?>
            </select></p>
        <p><label for="wcai_starts_at"><strong>Início</strong></label><br>
            <input id="wcai_starts_at" type="datetime-local" name="wcai_departure[starts_at]" value="<?php echo esc_attr( $values['starts_at'] ); ?>" required></p>
        <p><label for="wcai_duration_minutes"><strong>Duração (minutos)</strong></label><br>
            <input id="wcai_duration_minutes" type="number" min="1" name="wcai_departure[duration_minutes]" value="<?php echo esc_attr( $values['duration_minutes'] ); ?>"></p>
        <p><label for="wcai_capacity"><strong>Capacidade</strong></label><br>
            <input id="wcai_capacity" type="number" min="1" name="wcai_departure[capacity]" value="<?php echo esc_attr( $values['capacity'] ); ?>" required></p>
        <p><label for="wcai_minimum_capacity"><strong>Mínimo para confirmação</strong></label><br>
            <input id="wcai_minimum_capacity" type="number" min="1" name="wcai_departure[minimum_capacity]" value="<?php echo esc_attr( $values['minimum_capacity'] ); ?>"></p>
        <p><label for="wcai_meeting_point"><strong>Ponto de encontro</strong></label><br>
            <input id="wcai_meeting_point" class="widefat" type="text" name="wcai_departure[meeting_point]" value="<?php echo esc_attr( $values['meeting_point'] ); ?>"></p>
        <p><label for="wcai_guide_id"><strong>Guia responsável</strong></label><br>
            <select id="wcai_guide_id" name="wcai_departure[guide_id]"><option value="">Não definido</option>
                <?php foreach ( $guides as $guide ) : ?><option value="<?php echo esc_attr( $guide->ID ); ?>" <?php selected( $values['guide_id'], $guide->ID ); ?>><?php echo esc_html( $guide->display_name ); ?></option><?php endforeach; ?>
            </select></p>
        <p><label for="wcai_departure_status"><strong>Status operacional</strong></label><br>
            <select id="wcai_departure_status" name="wcai_departure[status]">
                <?php foreach ( array( 'draft' => 'Rascunho', 'open' => 'Aberta', 'full' => 'Lotada', 'confirmed' => 'Confirmada', 'cancelled' => 'Cancelada', 'completed' => 'Concluída' ) as $status => $label ) : ?>
                    <option value="<?php echo esc_attr( $status ); ?>" <?php selected( $values['status'], $status ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select></p>
        <?php
    }

    public function save_departure( $post_id, $post ) {
        if ( ! isset( $_POST['wcai_departure_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wcai_departure_nonce'] ) ), self::NONCE ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( WCAI_Capabilities::MANAGE_DEPARTURES ) ) return;
        $data = isset( $_POST['wcai_departure'] ) && is_array( $_POST['wcai_departure'] ) ? wp_unslash( $_POST['wcai_departure'] ) : array();
        $capacity = isset( $data['capacity'] ) ? absint( $data['capacity'] ) : 0;
        $minimum = isset( $data['minimum_capacity'] ) ? absint( $data['minimum_capacity'] ) : 0;
        $starts_at = isset( $data['starts_at'] ) ? sanitize_text_field( $data['starts_at'] ) : '';

        if ( ! $capacity || ! $starts_at || ( $minimum && $minimum > $capacity ) ) return;

        $statuses = array( 'draft', 'open', 'full', 'confirmed', 'cancelled', 'completed' );
        $status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'draft';
        if ( ! in_array( $status, $statuses, true ) ) $status = 'draft';

        update_post_meta( $post_id, '_wcai_product_id', isset( $data['product_id'] ) ? absint( $data['product_id'] ) : 0 );
        update_post_meta( $post_id, '_wcai_starts_at', $starts_at );
        update_post_meta( $post_id, '_wcai_duration_minutes', isset( $data['duration_minutes'] ) ? absint( $data['duration_minutes'] ) : 0 );
        update_post_meta( $post_id, '_wcai_capacity', $capacity );
        update_post_meta( $post_id, '_wcai_minimum_capacity', $minimum );
        update_post_meta( $post_id, '_wcai_meeting_point', isset( $data['meeting_point'] ) ? sanitize_text_field( $data['meeting_point'] ) : '' );
        update_post_meta( $post_id, '_wcai_guide_id', isset( $data['guide_id'] ) ? absint( $data['guide_id'] ) : 0 );
        update_post_meta( $post_id, '_wcai_departure_status', $status );
        WCAI_Audit_Log::log( 'departure_saved', 'departure', $post_id, array( 'status' => $status, 'capacity' => $capacity ) );
    }

    public function set_columns( $columns ) {
        return array( 'cb' => $columns['cb'], 'title' => 'Saída', 'departure_datetime' => 'Início', 'departure_capacity' => 'Vagas', 'departure_status' => 'Status', 'date' => $columns['date'] );
    }

    public function render_column( $column, $post_id ) {
        if ( 'departure_datetime' === $column ) echo esc_html( get_post_meta( $post_id, '_wcai_starts_at', true ) ?: '—' );
        if ( 'departure_capacity' === $column ) {
            $capacity = absint( get_post_meta( $post_id, '_wcai_capacity', true ) );
            $available = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_available_quantity( $post_id ) : $capacity;
            echo esc_html( $available . ' / ' . $capacity );
        }
        if ( 'departure_status' === $column ) echo esc_html( get_post_meta( $post_id, '_wcai_departure_status', true ) ?: 'draft' );
    }
}

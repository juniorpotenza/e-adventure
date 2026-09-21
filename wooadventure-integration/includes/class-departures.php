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
            'product_id' => absint( get_post_meta( $post->ID, '_wcai_product_id', true ) ),
            'starts_at' => get_post_meta( $post->ID, '_wcai_starts_at', true ),
            'duration_minutes' => absint( get_post_meta( $post->ID, '_wcai_duration_minutes', true ) ),
            'capacity' => absint( get_post_meta( $post->ID, '_wcai_capacity', true ) ),
            'minimum_capacity' => absint( get_post_meta( $post->ID, '_wcai_minimum_capacity', true ) ),
            'meeting_point' => get_post_meta( $post->ID, '_wcai_meeting_point', true ),
            'guide_id' => absint( get_post_meta( $post->ID, '_wcai_guide_id', true ) ),
            'status' => get_post_meta( $post->ID, '_wcai_departure_status', true ) ?: 'draft',
            'booking_cutoff_value' => absint( get_post_meta( $post->ID, '_wcai_booking_cutoff_value', true ) ),
            'booking_cutoff_unit' => get_post_meta( $post->ID, '_wcai_booking_cutoff_unit', true ) ?: 'hours',
            'schedule_id' => absint( get_post_meta( $post->ID, '_wcai_schedule_id', true ) ),
            'schedule_override' => (bool) get_post_meta( $post->ID, '_wcai_schedule_override', true ),
        );

        $products = function_exists( 'wc_get_products' ) ? wc_get_products( array( 'limit' => -1, 'status' => 'publish', 'return' => 'objects' ) ) : array();
        $guides = get_users( array( 'role__in' => array( 'wcai_guide', 'administrator' ), 'orderby' => 'display_name' ) );
        $available = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_available_quantity( $post->ID ) : $values['capacity'];
        $group = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_group_summary( $post->ID ) : array(
            'capacity'             => $values['capacity'],
            'minimum'              => $values['minimum_capacity'],
            'reserved'             => 0,
            'available'            => $available,
            'remaining_to_minimum' => max( 0, $values['minimum_capacity'] ),
            'formation_percent'   => 0,
            'status'               => 'forming',
            'label'                => 'Em formação',
            'detail'               => '',
        );
        $schedule_title = $values['schedule_id'] ? get_the_title( $values['schedule_id'] ) : '';

        echo '<div class="wcai-admin-booking" style="max-width:900px;">';
        echo '<style>.wcai-admin-booking .wcai-admin-section{margin:0 0 20px;padding:18px;border:1px solid #dcdcde;border-radius:8px;background:#fff}.wcai-admin-booking h3{margin:0 0 6px;font-size:16px}.wcai-admin-booking .wcai-help{margin:0 0 16px;color:#646970}.wcai-admin-booking .wcai-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.wcai-admin-booking .wcai-field label{display:block;font-weight:600;margin-bottom:6px}.wcai-admin-booking input,.wcai-admin-booking select{max-width:100%;box-sizing:border-box}.wcai-admin-booking .wcai-wide{width:100%}.wcai-admin-booking .wcai-capacity{display:flex;gap:12px;align-items:flex-end}.wcai-admin-booking .wcai-availability{padding:10px 12px;background:#f6f7f7;border-radius:6px}.wcai-admin-booking .wcai-group-progress{margin-top:12px;padding:12px;border:1px solid #dcdcde;border-radius:8px;background:#fff}.wcai-admin-booking .wcai-group-progress-head{display:flex;justify-content:space-between;gap:12px;align-items:baseline}.wcai-admin-booking .wcai-group-progress-head strong{font-size:14px}.wcai-admin-booking .wcai-group-progress-head span{font-size:12px;color:#646970}.wcai-admin-booking .wcai-group-progress-bar{height:8px;margin:8px 0;border-radius:999px;background:#ececec;overflow:hidden}.wcai-admin-booking .wcai-group-progress-bar span{display:block;height:100%;border-radius:inherit;background:#222}.wcai-admin-booking .wcai-group-progress small{display:block;color:#646970;line-height:1.4}.wcai-admin-booking .wcai-source{font-size:13px;padding:10px 12px;background:#f0f6fc;border-left:3px solid #2271b1}.wcai-admin-booking .wcai-source-override{background:#fff8e5;border-left-color:#c58a21}.wcai-admin-booking .wcai-source-override label{display:block!important}.wcai-admin-booking .wcai-source-override p{margin:6px 0 0}.wcai-admin-booking .wcai-note{font-size:12px;color:#646970}.wcai-admin-booking .wcai-danger{color:#b32d2e}@media(max-width:700px){.wcai-admin-booking .wcai-grid{grid-template-columns:1fr}}</style>';

        echo '<div class="wcai-admin-section">';
        echo '<h3>1. Saída e produto</h3><p class="wcai-help">Esta é a ocorrência concreta que o cliente poderá reservar.</p>';
        if ( $schedule_title ) {
            echo '<div class="wcai-source"><strong>Gerada pela programação:</strong> ' . esc_html( $schedule_title ) . ' <a href="' . esc_url( get_edit_post_link( $values['schedule_id'] ) ) . '">Editar programação</a></div><br>';
            echo '<div class="wcai-source wcai-source-override"><label><input type="checkbox" name="wcai_departure[schedule_override]" value="1" ' . checked( $values['schedule_override'], true, false ) . '> <strong>Manter esta saída independente da programação</strong></label><p class="wcai-note">Ative para alterar somente esta ocorrência. Uma saída com override não será sobrescrita quando a Programação for regenerada.</p></div><br>';
        } else {
            echo '<div class="wcai-source">Saída criada manualmente. Para criar várias datas automaticamente, use <strong>WooCommerce → Programações</strong>.</div><br>';
        }
        echo '<div class="wcai-grid">';
        echo '<div class="wcai-field"><label for="wcai_product_id">Produto</label><select id="wcai_product_id" name="wcai_departure[product_id]" required><option value="">Selecione um produto</option>';
        foreach ( $products as $product ) {
            echo '<option value="' . esc_attr( $product->get_id() ) . '" ' . selected( $values['product_id'], $product->get_id(), false ) . '>' . esc_html( $product->get_name() ) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="wcai-field"><label for="wcai_starts_at">Data e horário da saída</label><input id="wcai_starts_at" type="datetime-local" name="wcai_departure[starts_at]" value="' . esc_attr( $values['starts_at'] ) . '" required></div>';
        echo '</div></div>';

        echo '<div class="wcai-admin-section">';
        echo '<h3>2. Capacidade e disponibilidade</h3><p class="wcai-help">Defina o tamanho máximo da saída e quantas reservas são necessárias para formar o grupo.</p>';
        echo '<div class="wcai-grid">';
        echo '<div class="wcai-field"><label for="wcai_capacity">Grupo máximo</label><input id="wcai_capacity" type="number" min="1" name="wcai_departure[capacity]" value="' . esc_attr( $values['capacity'] ) . '" required><p class="wcai-note">Limite de participantes desta saída.</p></div>';
        echo '<div class="wcai-field"><label for="wcai_minimum_capacity">Mínimo para formar o grupo</label><input id="wcai_minimum_capacity" type="number" min="1" name="wcai_departure[minimum_capacity]" value="' . esc_attr( $values['minimum_capacity'] ) . '"><p class="wcai-note">Mostrado ao cliente como progresso de formação do grupo.</p></div>';
        echo '</div>';
        echo '<div class="wcai-availability"><strong>Vagas disponíveis agora:</strong> ' . esc_html( $available ) . ' de ' . esc_html( $values['capacity'] ?: 0 ) . '</div>';

        echo '<div class="wcai-group-progress">';
        if ( $group['minimum'] > 1 ) {
            echo '<div class="wcai-group-progress-head"><strong>' . esc_html( $group['reserved'] . ' de ' . $group['minimum'] . ' participantes' ) . '</strong><span>' . esc_html( $group['label'] ) . '</span></div>';
            echo '<div class="wcai-group-progress-bar"><span style="width:' . esc_attr( min( 100, max( 0, absint( $group['formation_percent'] ) ) ) ) . '%"></span></div>';
            if ( $group['remaining_to_minimum'] > 0 ) {
                echo '<small>Faltam ' . esc_html( $group['remaining_to_minimum'] ) . ' participante' . ( 1 === $group['remaining_to_minimum'] ? '' : 's' ) . ' para atingir o mínimo de formação. A saída comporta até ' . esc_html( $group['capacity'] ) . ' pessoas.</small>';
            } else {
                echo '<small>' . esc_html( $group['detail'] ) . ' Ainda há ' . esc_html( $group['available'] ) . ' vaga' . ( 1 === $group['available'] ? '' : 's' ) . ' disponível' . ( 1 === $group['available'] ? '' : 'is' ) . ' até o limite de ' . esc_html( $group['capacity'] ) . '.</small>';
            }
        } elseif ( $group['capacity'] ) {
            echo '<div class="wcai-group-progress-head"><strong>' . esc_html( $group['reserved'] . ' de ' . $group['capacity'] . ' vagas ocupadas' ) . '</strong><span>Sem mínimo de formação</span></div>';
            echo '<div class="wcai-group-progress-bar"><span style="width:' . esc_attr( $group['capacity'] ? min( 100, max( 0, (int) round( ( $group['reserved'] / $group['capacity'] ) * 100 ) ) ) : 0 ) . '%"></span></div>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="wcai-admin-section">';
        echo '<h3>3. Janela de vendas</h3><p class="wcai-help">Define até quando o cliente pode comprar esta saída. A regra é aplicada no produto, carrinho/checkout e validação final da reserva.</p>';
        echo '<div class="wcai-field"><label>Fechar vendas</label><div class="wcai-capacity"><input type="number" min="0" name="wcai_departure[booking_cutoff_value]" value="' . esc_attr( $values['booking_cutoff_value'] ) . '" style="width:110px;"><select name="wcai_departure[booking_cutoff_unit]"><option value="hours" ' . selected( $values['booking_cutoff_unit'], 'hours', false ) . '>horas antes</option><option value="days" ' . selected( $values['booking_cutoff_unit'], 'days', false ) . '>dias antes</option></select></div><p class="wcai-note">Ex.: 2 horas antes de uma saída às 09:00 encerra a venda às 07:00.</p></div>';
        echo '</div>';

        echo '<div class="wcai-admin-section">';
        echo '<h3>4. Operação</h3><p class="wcai-help">Informações exibidas no booking e utilizadas pela operação da saída.</p>';
        echo '<div class="wcai-grid">';
        echo '<div class="wcai-field"><label for="wcai_duration_minutes">Duração (minutos)</label><input id="wcai_duration_minutes" type="number" min="1" name="wcai_departure[duration_minutes]" value="' . esc_attr( $values['duration_minutes'] ) . '"></div>';
        echo '<div class="wcai-field"><label for="wcai_departure_status">Status operacional</label><select id="wcai_departure_status" name="wcai_departure[status]">';
        foreach ( array( 'draft' => 'Rascunho', 'open' => 'Aberta para reservas', 'full' => 'Lotada', 'confirmed' => 'Confirmada', 'cancelled' => 'Cancelada', 'completed' => 'Concluída' ) as $status => $label ) {
            echo '<option value="' . esc_attr( $status ) . '" ' . selected( $values['status'], $status, false ) . '>' . esc_html( $label ) . '</option>';
        }
        echo '</select></div>';
        echo '<div class="wcai-field"><label for="wcai_meeting_point">Ponto de encontro</label><input id="wcai_meeting_point" class="wcai-wide" type="text" name="wcai_departure[meeting_point]" value="' . esc_attr( $values['meeting_point'] ) . '"></div>';
        echo '<div class="wcai-field"><label for="wcai_guide_id">Guia responsável</label><select id="wcai_guide_id" name="wcai_departure[guide_id]"><option value="">Não definido</option>';
        foreach ( $guides as $guide ) {
            echo '<option value="' . esc_attr( $guide->ID ) . '" ' . selected( $values['guide_id'], $guide->ID, false ) . '>' . esc_html( $guide->display_name ) . '</option>';
        }
        echo '</select></div>';
        echo '</div></div>';
        echo '</div>';
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

        $cutoff_value = absint( isset( $data['booking_cutoff_value'] ) ? $data['booking_cutoff_value'] : 0 );
        $cutoff_unit = isset( $data['booking_cutoff_unit'] ) ? sanitize_key( $data['booking_cutoff_unit'] ) : 'hours';
        if ( ! in_array( $cutoff_unit, array( 'hours', 'days' ), true ) ) {
            $cutoff_unit = 'hours';
        }
        update_post_meta( $post_id, '_wcai_booking_cutoff_value', $cutoff_value );
        update_post_meta( $post_id, '_wcai_booking_cutoff_unit', $cutoff_unit );

        if ( absint( get_post_meta( $post_id, '_wcai_schedule_id', true ) ) ) {
            if ( ! empty( $data['schedule_override'] ) ) {
                update_post_meta( $post_id, '_wcai_schedule_override', 1 );
            } else {
                delete_post_meta( $post_id, '_wcai_schedule_override' );
            }
        }

        WCAI_Audit_Log::log( 'departure_saved', 'departure', $post_id, array( 'status' => $status, 'capacity' => $capacity, 'minimum_capacity' => $minimum ) );
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

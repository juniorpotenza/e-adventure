<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Schedules {

    const POST_TYPE = 'wcai_schedule';
    const NONCE = 'wcai_save_schedule';

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_boxes' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_schedule' ), 10, 2 );
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( $this, 'set_columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( $this, 'render_column' ), 10, 2 );
    }

    public function register_post_type() {
        register_post_type(
            self::POST_TYPE,
            array(
                'labels' => array(
                    'name' => 'Programações',
                    'singular_name' => 'Programação',
                    'add_new_item' => 'Adicionar programação',
                    'edit_item' => 'Editar programação',
                    'menu_name' => 'Programações',
                ),
                'public' => false,
                'show_ui' => true,
                'show_in_menu' => 'woocommerce',
                'supports' => array( 'title' ),
                'capability_type' => 'post',
                'map_meta_cap' => false,
                'capabilities' => array(
                    'edit_post' => WCAI_Capabilities::MANAGE_DEPARTURES,
                    'read_post' => WCAI_Capabilities::MANAGE_DEPARTURES,
                    'delete_post' => WCAI_Capabilities::MANAGE_DEPARTURES,
                    'edit_posts' => WCAI_Capabilities::MANAGE_DEPARTURES,
                    'create_posts' => WCAI_Capabilities::MANAGE_DEPARTURES,
                    'edit_others_posts' => WCAI_Capabilities::MANAGE_DEPARTURES,
                    'publish_posts' => WCAI_Capabilities::MANAGE_DEPARTURES,
                    'read_private_posts' => WCAI_Capabilities::MANAGE_DEPARTURES,
                    'delete_posts' => WCAI_Capabilities::MANAGE_DEPARTURES,
                    'delete_others_posts' => WCAI_Capabilities::MANAGE_DEPARTURES,
                ),
            )
        );
    }

    public function add_meta_boxes() {
        add_meta_box(
            'wcai_schedule_details',
            'Regra de agenda',
            array( $this, 'render_details_box' ),
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_details_box( $post ) {
        wp_nonce_field( self::NONCE, 'wcai_schedule_nonce' );

        $values = array(
            'product_id' => absint( get_post_meta( $post->ID, '_wcai_schedule_product_id', true ) ),
            'recurrence' => get_post_meta( $post->ID, '_wcai_schedule_recurrence', true ) ?: 'once',
            'start_date' => get_post_meta( $post->ID, '_wcai_schedule_start_date', true ),
            'end_date' => get_post_meta( $post->ID, '_wcai_schedule_end_date', true ),
            'weekdays' => (array) get_post_meta( $post->ID, '_wcai_schedule_weekdays', true ),
            'month_day' => absint( get_post_meta( $post->ID, '_wcai_schedule_month_day', true ) ),
            'start_time' => get_post_meta( $post->ID, '_wcai_schedule_start_time', true ) ?: '09:00',
            'duration_minutes' => absint( get_post_meta( $post->ID, '_wcai_schedule_duration_minutes', true ) ) ?: 120,
            'capacity' => absint( get_post_meta( $post->ID, '_wcai_schedule_capacity', true ) ),
            'minimum_capacity' => absint( get_post_meta( $post->ID, '_wcai_schedule_minimum_capacity', true ) ),
            'meeting_point' => get_post_meta( $post->ID, '_wcai_schedule_meeting_point', true ),
            'guide_id' => absint( get_post_meta( $post->ID, '_wcai_schedule_guide_id', true ) ),
            'cutoff_value' => absint( get_post_meta( $post->ID, '_wcai_schedule_cutoff_value', true ) ),
            'cutoff_unit' => get_post_meta( $post->ID, '_wcai_schedule_cutoff_unit', true ) ?: 'hours',
            'exceptions' => get_post_meta( $post->ID, '_wcai_schedule_exceptions', true ),
            'status' => get_post_meta( $post->ID, '_wcai_schedule_status', true ) ?: 'active',
        );

        $products = function_exists( 'wc_get_products' ) ? wc_get_products(
            array(
                'limit' => -1,
                'status' => 'publish',
                'return' => 'objects',
            )
        ) : array();

        $guides = get_users(
            array(
                'role__in' => array( 'wcai_guide', 'administrator' ),
                'orderby' => 'display_name',
            )
        );

        $weekdays = array(
            1 => 'Segunda',
            2 => 'Terça',
            3 => 'Quarta',
            4 => 'Quinta',
            5 => 'Sexta',
            6 => 'Sábado',
            7 => 'Domingo',
        );
        ?>
        <p>
            <label for="wcai_schedule_product"><strong>Produto</strong></label><br>
            <select id="wcai_schedule_product" name="wcai_schedule[product_id]" required>
                <option value="">Selecione um produto</option>
                <?php foreach ( $products as $product ) : ?>
                    <option value="<?php echo esc_attr( $product->get_id() ); ?>" <?php selected( $values['product_id'], $product->get_id() ); ?>>
                        <?php echo esc_html( $product->get_name() ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="wcai_schedule_recurrence"><strong>Recorrência</strong></label><br>
            <select id="wcai_schedule_recurrence" name="wcai_schedule[recurrence]">
                <option value="once" <?php selected( $values['recurrence'], 'once' ); ?>>Única</option>
                <option value="weekly" <?php selected( $values['recurrence'], 'weekly' ); ?>>Semanal — dias da semana</option>
                <option value="monthly" <?php selected( $values['recurrence'], 'monthly' ); ?>>Mensal — dia do mês</option>
            </select>
        </p>

        <p>
            <label><strong>Período</strong></label><br>
            <input type="date" name="wcai_schedule[start_date]" value="<?php echo esc_attr( $values['start_date'] ); ?>" required>
            <span> até </span>
            <input type="date" name="wcai_schedule[end_date]" value="<?php echo esc_attr( $values['end_date'] ); ?>" required>
        </p>

        <p>
            <strong>Dias da semana</strong><br>
            <?php foreach ( $weekdays as $day => $label ) : ?>
                <label style="margin-right:12px;">
                    <input type="checkbox" name="wcai_schedule[weekdays][]" value="<?php echo esc_attr( $day ); ?>" <?php checked( in_array( (string) $day, array_map( 'strval', $values['weekdays'] ), true ) ); ?>>
                    <?php echo esc_html( $label ); ?>
                </label>
            <?php endforeach; ?>
            <br><small>Usado quando a recorrência for semanal.</small>
        </p>

        <p>
            <label for="wcai_schedule_month_day"><strong>Dia do mês</strong></label><br>
            <input id="wcai_schedule_month_day" type="number" min="1" max="31" name="wcai_schedule[month_day]" value="<?php echo esc_attr( $values['month_day'] ?: 1 ); ?>">
            <small>Usado quando a recorrência for mensal.</small>
        </p>

        <p>
            <label><strong>Horário</strong></label><br>
            <input type="time" name="wcai_schedule[start_time]" value="<?php echo esc_attr( $values['start_time'] ); ?>" required>
        </p>

        <p>
            <label><strong>Duração (minutos)</strong></label><br>
            <input type="number" min="1" name="wcai_schedule[duration_minutes]" value="<?php echo esc_attr( $values['duration_minutes'] ); ?>">
        </p>

        <p>
            <label><strong>Capacidade</strong></label><br>
            <input type="number" min="1" name="wcai_schedule[capacity]" value="<?php echo esc_attr( $values['capacity'] ); ?>" required>
        </p>

        <p>
            <label><strong>Mínimo para confirmação</strong></label><br>
            <input type="number" min="1" name="wcai_schedule[minimum_capacity]" value="<?php echo esc_attr( $values['minimum_capacity'] ); ?>">
        </p>

        <p>
            <label><strong>Ponto de encontro</strong></label><br>
            <input class="widefat" type="text" name="wcai_schedule[meeting_point]" value="<?php echo esc_attr( $values['meeting_point'] ); ?>">
        </p>

        <p>
            <label><strong>Guia responsável</strong></label><br>
            <select name="wcai_schedule[guide_id]">
                <option value="">Não definido</option>
                <?php foreach ( $guides as $guide ) : ?>
                    <option value="<?php echo esc_attr( $guide->ID ); ?>" <?php selected( $values['guide_id'], $guide->ID ); ?>>
                        <?php echo esc_html( $guide->display_name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label><strong>Fechamento das vendas</strong></label><br>
            <input type="number" min="0" name="wcai_schedule[cutoff_value]" value="<?php echo esc_attr( $values['cutoff_value'] ); ?>" style="width:100px;">
            <select name="wcai_schedule[cutoff_unit]">
                <option value="hours" <?php selected( $values['cutoff_unit'], 'hours' ); ?>>horas antes</option>
                <option value="days" <?php selected( $values['cutoff_unit'], 'days' ); ?>>dias antes</option>
            </select>
            <br><small>0 mantém a venda aberta até o horário da saída, respeitando as vagas.</small>
        </p>

        <p>
            <label><strong>Exceções</strong></label><br>
            <textarea name="wcai_schedule[exceptions]" rows="4" class="large-text code" placeholder="25/12/2026&#10;01/01/2027"><?php echo esc_textarea( $values['exceptions'] ); ?></textarea>
            <br><small>Uma data por linha. Essas datas não gerarão saídas.</small>
        </p>

        <p>
            <label><strong>Status da programação</strong></label><br>
            <select name="wcai_schedule[status]">
                <option value="active" <?php selected( $values['status'], 'active' ); ?>>Ativa</option>
                <option value="paused" <?php selected( $values['status'], 'paused' ); ?>>Pausada</option>
                <option value="draft" <?php selected( $values['status'], 'draft' ); ?>>Rascunho</option>
            </select>
        </p>
        <?php
    }

    public function save_schedule( $post_id, $post ) {
        if (
            ! isset( $_POST['wcai_schedule_nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wcai_schedule_nonce'] ) ), self::NONCE )
            || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            || wp_is_post_revision( $post_id )
            || ! current_user_can( WCAI_Capabilities::MANAGE_DEPARTURES )
        ) {
            return;
        }

        $data = isset( $_POST['wcai_schedule'] ) && is_array( $_POST['wcai_schedule'] )
            ? wp_unslash( $_POST['wcai_schedule'] )
            : array();

        $product_id = isset( $data['product_id'] ) ? absint( $data['product_id'] ) : 0;
        $recurrence = isset( $data['recurrence'] ) ? sanitize_key( $data['recurrence'] ) : 'once';
        $start_date = isset( $data['start_date'] ) ? sanitize_text_field( $data['start_date'] ) : '';
        $end_date = isset( $data['end_date'] ) ? sanitize_text_field( $data['end_date'] ) : '';
        $capacity = isset( $data['capacity'] ) ? absint( $data['capacity'] ) : 0;
        $minimum = isset( $data['minimum_capacity'] ) ? absint( $data['minimum_capacity'] ) : 0;

        if ( ! $product_id || ! $start_date || ! $end_date || $start_date > $end_date || ! $capacity || $minimum > $capacity ) {
            return;
        }

        if ( ! in_array( $recurrence, array( 'once', 'weekly', 'monthly' ), true ) ) {
            $recurrence = 'once';
        }

        $weekdays = isset( $data['weekdays'] ) && is_array( $data['weekdays'] )
            ? array_values( array_unique( array_filter( array_map( 'absint', $data['weekdays'] ), static function( $day ) { return $day >= 1 && $day <= 7; } ) ) )
            : array();

        $month_day = min( 31, max( 1, absint( isset( $data['month_day'] ) ? $data['month_day'] : 1 ) ) );
        $start_time = isset( $data['start_time'] ) ? sanitize_text_field( $data['start_time'] ) : '09:00';
        if ( ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', $start_time ) ) {
            $start_time = '09:00';
        }

        $cutoff_value = absint( isset( $data['cutoff_value'] ) ? $data['cutoff_value'] : 0 );
        $cutoff_unit = isset( $data['cutoff_unit'] ) ? sanitize_key( $data['cutoff_unit'] ) : 'hours';
        if ( ! in_array( $cutoff_unit, array( 'hours', 'days' ), true ) ) {
            $cutoff_unit = 'hours';
        }

        $status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'active';
        if ( ! in_array( $status, array( 'active', 'paused', 'draft' ), true ) ) {
            $status = 'active';
        }

        $exceptions = self::normalize_exceptions( isset( $data['exceptions'] ) ? $data['exceptions'] : '' );

        update_post_meta( $post_id, '_wcai_schedule_product_id', $product_id );
        update_post_meta( $post_id, '_wcai_schedule_recurrence', $recurrence );
        update_post_meta( $post_id, '_wcai_schedule_start_date', $start_date );
        update_post_meta( $post_id, '_wcai_schedule_end_date', $end_date );
        update_post_meta( $post_id, '_wcai_schedule_weekdays', $weekdays );
        update_post_meta( $post_id, '_wcai_schedule_month_day', $month_day );
        update_post_meta( $post_id, '_wcai_schedule_start_time', $start_time );
        update_post_meta( $post_id, '_wcai_schedule_duration_minutes', absint( isset( $data['duration_minutes'] ) ? $data['duration_minutes'] : 0 ) );
        update_post_meta( $post_id, '_wcai_schedule_capacity', $capacity );
        update_post_meta( $post_id, '_wcai_schedule_minimum_capacity', $minimum );
        update_post_meta( $post_id, '_wcai_schedule_meeting_point', isset( $data['meeting_point'] ) ? sanitize_text_field( $data['meeting_point'] ) : '' );
        update_post_meta( $post_id, '_wcai_schedule_guide_id', absint( isset( $data['guide_id'] ) ? $data['guide_id'] : 0 ) );
        update_post_meta( $post_id, '_wcai_schedule_cutoff_value', $cutoff_value );
        update_post_meta( $post_id, '_wcai_schedule_cutoff_unit', $cutoff_unit );
        update_post_meta( $post_id, '_wcai_schedule_exceptions', $exceptions );
        update_post_meta( $post_id, '_wcai_schedule_status', $status );

        $generated = self::generate_occurrences( $post_id );

        WCAI_Audit_Log::log(
            'schedule_saved',
            'schedule',
            $post_id,
            array(
                'recurrence' => $recurrence,
                'generated' => count( $generated ),
                'status' => $status,
            )
        );
    }

    public static function normalize_exceptions( $value ) {
        $lines = preg_split( '/[\r\n,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
        $clean = array();

        foreach ( $lines as $line ) {
            $line = trim( $line );
            $date = DateTimeImmutable::createFromFormat( 'd/m/Y', $line, wp_timezone() );
            if ( $date instanceof DateTimeImmutable ) {
                $clean[] = $date->format( 'Y-m-d' );
                continue;
            }

            $date = DateTimeImmutable::createFromFormat( 'Y-m-d', $line, wp_timezone() );
            if ( $date instanceof DateTimeImmutable ) {
                $clean[] = $date->format( 'Y-m-d' );
            }
        }

        return implode( "\n", array_values( array_unique( $clean ) ) );
    }

    public static function generate_occurrences( $schedule_id ) {
        $schedule_id = absint( $schedule_id );
        if ( ! $schedule_id ) {
            return array();
        }

        $status = get_post_meta( $schedule_id, '_wcai_schedule_status', true ) ?: 'active';
        $product_id = absint( get_post_meta( $schedule_id, '_wcai_schedule_product_id', true ) );
        $recurrence = get_post_meta( $schedule_id, '_wcai_schedule_recurrence', true ) ?: 'once';
        $start_date = get_post_meta( $schedule_id, '_wcai_schedule_start_date', true );
        $end_date = get_post_meta( $schedule_id, '_wcai_schedule_end_date', true );
        $weekdays = array_map( 'absint', (array) get_post_meta( $schedule_id, '_wcai_schedule_weekdays', true ) );
        $month_day = min( 31, max( 1, absint( get_post_meta( $schedule_id, '_wcai_schedule_month_day', true ) ?: 1 ) ) );
        $start_time = get_post_meta( $schedule_id, '_wcai_schedule_start_time', true ) ?: '09:00';
        $duration = absint( get_post_meta( $schedule_id, '_wcai_schedule_duration_minutes', true ) );
        $capacity = absint( get_post_meta( $schedule_id, '_wcai_schedule_capacity', true ) );
        $minimum = absint( get_post_meta( $schedule_id, '_wcai_schedule_minimum_capacity', true ) );
        $meeting_point = get_post_meta( $schedule_id, '_wcai_schedule_meeting_point', true );
        $guide_id = absint( get_post_meta( $schedule_id, '_wcai_schedule_guide_id', true ) );
        $cutoff_value = absint( get_post_meta( $schedule_id, '_wcai_schedule_cutoff_value', true ) );
        $cutoff_unit = get_post_meta( $schedule_id, '_wcai_schedule_cutoff_unit', true ) ?: 'hours';
        $exceptions = preg_split( '/[\r\n]+/', (string) get_post_meta( $schedule_id, '_wcai_schedule_exceptions', true ), -1, PREG_SPLIT_NO_EMPTY );
        $exceptions = array_map( 'trim', $exceptions );

        if ( ! $product_id || ! $start_date || ! $end_date || $start_date > $end_date || ! $capacity ) {
            return array();
        }

        $tz = wp_timezone();
        $start = DateTimeImmutable::createFromFormat( 'Y-m-d', $start_date, $tz );
        $end = DateTimeImmutable::createFromFormat( 'Y-m-d', $end_date, $tz );

        if ( ! $start || ! $end ) {
            return array();
        }

        $occurrences = array();
        $cursor = $start;
        $safety = 0;

        while ( $cursor <= $end && $safety < 5000 ) {
            $date = $cursor->format( 'Y-m-d' );
            $include = false;

            if ( in_array( $date, $exceptions, true ) ) {
                $include = false;
            } elseif ( 'once' === $recurrence ) {
                $include = $date === $start_date;
            } elseif ( 'weekly' === $recurrence ) {
                $include = in_array( (int) $cursor->format( 'N' ), $weekdays, true );
            } elseif ( 'monthly' === $recurrence ) {
                $include = (int) $cursor->format( 'j' ) === $month_day;
            }

            if ( $include ) {
                $occurrences[] = $date . ' ' . $start_time;
            }

            if ( 'once' === $recurrence ) {
                break;
            }

            $cursor = $cursor->modify( '+1 day' );
            $safety++;
        }

        $desired = array_values( array_unique( $occurrences ) );
        $existing = get_posts(
            array(
                'post_type' => WCAI_Departures::POST_TYPE,
                'post_status' => array( 'publish', 'draft', 'private' ),
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_query' => array(
                    array(
                        'key' => '_wcai_schedule_id',
                        'value' => $schedule_id,
                        'compare' => '=',
                    ),
                ),
            )
        );

        $existing_by_key = array();
        foreach ( $existing as $departure_id ) {
            $key = get_post_meta( $departure_id, '_wcai_occurrence_key', true );
            if ( $key ) {
                $existing_by_key[ $key ] = absint( $departure_id );
            }
        }

        $generated = array();

        foreach ( $desired as $occurrence ) {
            $occurrence_key = md5( $schedule_id . '|' . $occurrence );

            if ( isset( $existing_by_key[ $occurrence_key ] ) ) {
                $departure_id = $existing_by_key[ $occurrence_key ];

                if ( ! class_exists( 'WCAI_Reservations' ) || 0 === WCAI_Reservations::get_reserved_quantity( $departure_id ) ) {
                    self::update_occurrence( $departure_id, $schedule_id, $occurrence, $product_id, $duration, $capacity, $minimum, $meeting_point, $guide_id, $cutoff_value, $cutoff_unit, $status );
                }

                $generated[] = $departure_id;
                continue;
            }

            $departure_id = self::create_occurrence(
                $schedule_id,
                $occurrence_key,
                $occurrence,
                $product_id,
                $duration,
                $capacity,
                $minimum,
                $meeting_point,
                $guide_id,
                $cutoff_value,
                $cutoff_unit,
                $status
            );

            if ( $departure_id ) {
                $generated[] = $departure_id;
            }
        }

        $desired_lookup = array_fill_keys( array_map( static function( $value ) use ( $schedule_id ) {
            return md5( $schedule_id . '|' . $value );
        }, $desired ), true );

        foreach ( $existing as $departure_id ) {
            $key = get_post_meta( $departure_id, '_wcai_occurrence_key', true );
            if ( ! $key || isset( $desired_lookup[ $key ] ) ) {
                continue;
            }

            if ( class_exists( 'WCAI_Reservations' ) && WCAI_Reservations::get_reserved_quantity( $departure_id ) > 0 ) {
                continue;
            }

            $start_value = get_post_meta( $departure_id, '_wcai_starts_at', true );
            if ( $start_value && WCAI_Data_Resolver::parse_timestamp( $start_value ) > current_time( 'timestamp' ) ) {
                update_post_meta( $departure_id, '_wcai_departure_status', 'cancelled' );
            }
        }

        return $generated;
    }

    private static function create_occurrence( $schedule_id, $occurrence_key, $occurrence, $product_id, $duration, $capacity, $minimum, $meeting_point, $guide_id, $cutoff_value, $cutoff_unit, $schedule_status ) {
        $timestamp = WCAI_Data_Resolver::parse_timestamp( $occurrence );

        if ( ! $timestamp ) {
            return 0;
        }

        $status = 'active' === $schedule_status ? 'open' : 'draft';
        $post_id = wp_insert_post(
            array(
                'post_type' => WCAI_Departures::POST_TYPE,
                'post_status' => 'publish',
                'post_title' => sprintf( '%s — %s', get_the_title( $schedule_id ), wp_date( 'd/m/Y H:i', $timestamp ) ),
            ),
            true
        );

        if ( is_wp_error( $post_id ) ) {
            return 0;
        }

        self::update_occurrence( $post_id, $schedule_id, $occurrence, $product_id, $duration, $capacity, $minimum, $meeting_point, $guide_id, $cutoff_value, $cutoff_unit, $schedule_status );

        return absint( $post_id );
    }

    private static function update_occurrence( $post_id, $schedule_id, $occurrence, $product_id, $duration, $capacity, $minimum, $meeting_point, $guide_id, $cutoff_value, $cutoff_unit, $schedule_status ) {
        update_post_meta( $post_id, '_wcai_schedule_id', $schedule_id );
        update_post_meta( $post_id, '_wcai_occurrence_key', md5( $schedule_id . '|' . $occurrence ) );
        update_post_meta( $post_id, '_wcai_product_id', $product_id );
        update_post_meta( $post_id, '_wcai_starts_at', str_replace( ' ', 'T', $occurrence ) );
        update_post_meta( $post_id, '_wcai_duration_minutes', $duration );
        update_post_meta( $post_id, '_wcai_capacity', $capacity );
        update_post_meta( $post_id, '_wcai_minimum_capacity', $minimum );
        update_post_meta( $post_id, '_wcai_meeting_point', $meeting_point );
        update_post_meta( $post_id, '_wcai_guide_id', $guide_id );
        update_post_meta( $post_id, '_wcai_booking_cutoff_value', $cutoff_value );
        update_post_meta( $post_id, '_wcai_booking_cutoff_unit', $cutoff_unit );
        update_post_meta( $post_id, '_wcai_departure_status', 'active' === $schedule_status ? 'open' : 'draft' );
    }

    public function set_columns( $columns ) {
        return array(
            'cb' => $columns['cb'],
            'title' => 'Programação',
            'schedule_recurrence' => 'Recorrência',
            'schedule_period' => 'Período',
            'schedule_status' => 'Status',
            'date' => $columns['date'],
        );
    }

    public function render_column( $column, $post_id ) {
        if ( 'schedule_recurrence' === $column ) {
            echo esc_html( get_post_meta( $post_id, '_wcai_schedule_recurrence', true ) ?: 'once' );
        }

        if ( 'schedule_period' === $column ) {
            echo esc_html(
                get_post_meta( $post_id, '_wcai_schedule_start_date', true )
                . ' → '
                . get_post_meta( $post_id, '_wcai_schedule_end_date', true )
            );
        }

        if ( 'schedule_status' === $column ) {
            echo esc_html( get_post_meta( $post_id, '_wcai_schedule_status', true ) ?: 'active' );
        }
    }
}

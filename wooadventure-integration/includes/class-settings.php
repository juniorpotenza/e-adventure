<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_filter( 'option_page_capability_wcai_settings_group', array( $this, 'get_settings_capability' ) );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Configurações Adventure',
            'Adventure Config',
            WCAI_Capabilities::MANAGE_SETTINGS,
            'wcai-settings',
            array( $this, 'settings_page' )
        );
    }

    public function register_settings() {
        register_setting( 'wcai_settings_group', 'wcai_carta_oferta', array( 'sanitize_callback' => array( $this, 'sanitize_carta_oferta' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_trigger_status', array( 'sanitize_callback' => array( $this, 'sanitize_status' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_product_ids', array( 'sanitize_callback' => array( $this, 'sanitize_product_ids' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_blocked_cpfs', array( 'sanitize_callback' => array( $this, 'sanitize_blocked_cpfs' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_date_meta_key', array( 'sanitize_callback' => array( $this, 'sanitize_meta_key' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_event_date_source', array( 'sanitize_callback' => array( $this, 'sanitize_event_date_source' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_event_date_meta_key', array( 'sanitize_callback' => array( $this, 'sanitize_meta_key' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_seq_enabled', array( 'sanitize_callback' => array( $this, 'sanitize_seq_enabled' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_seq_prefix', array( 'sanitize_callback' => array( $this, 'sanitize_text' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_seq_suffix', array( 'sanitize_callback' => array( $this, 'sanitize_text' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_seq_width', array( 'sanitize_callback' => array( $this, 'sanitize_seq_width' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_seq_next', array( 'sanitize_callback' => array( $this, 'sanitize_seq_next' ) ) );
        register_setting( 'wcai_settings_group', 'wcai_calendar_statuses', array( 'sanitize_callback' => array( $this, 'sanitize_calendar_statuses' ) ) );
    }

    public function settings_page() {
        if ( ! current_user_can( WCAI_Capabilities::MANAGE_SETTINGS ) ) {
            wp_die( esc_html__( 'Você não tem permissão para acessar estas configurações.', 'wcai' ), 403 );
        }

        $existing_keys = $this->get_existing_order_meta_keys();
        $current_date_key = get_option( 'wcai_event_date_meta_key', get_option( 'wcai_date_meta_key', 'tour_date' ) );
        $current_date_source = get_option( 'wcai_event_date_source', 'departure' );
        $current_trigger = get_option( 'wcai_trigger_status', 'completed' );
        $wc_statuses = wc_get_order_statuses();

        $calendar_statuses = get_option( 'wcai_calendar_statuses' );
        if ( empty( $calendar_statuses ) || ! is_array( $calendar_statuses ) ) {
            $calendar_statuses = array( 'wc-processing', 'wc-completed' );
        }
        ?>
        <div class="wrap">
            <h1>Configurações WooAdventure Integration</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'wcai_settings_group' ); ?>
                <?php do_settings_sections( 'wcai_settings_group' ); ?>

                <h2 class="title">Integração Seguradora (Roca)</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Carta Oferta (Token)</th>
                        <td>
                            <input type="password" name="wcai_carta_oferta" value="" class="regular-text" autocomplete="new-password" placeholder="<?php echo esc_attr( get_option( 'wcai_carta_oferta' ) ? 'Token configurado — informe apenas para substituir' : 'Informe o token' ); ?>" />
                            <p class="description">Por segurança, o token salvo não é devolvido ao formulário. Deixe em branco para manter o token atual.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Gatilho de Envio API</th>
                        <td>
                            <select name="wcai_trigger_status" class="regular-text">
                                <?php foreach ( $wc_statuses as $slug => $label ) : ?>
                                    <?php $clean_slug = str_replace( 'wc-', '', $slug ); ?>
                                    <option value="<?php echo esc_attr( $clean_slug ); ?>" <?php selected( $current_trigger, $clean_slug ); ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <hr>

                <h2 class="title">Geral & Data</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">IDs dos Produtos</th>
                        <td><input type="text" name="wcai_product_ids" value="<?php echo esc_attr( get_option( 'wcai_product_ids' ) ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Fonte da data do passeio/evento</th>
                        <td>
                            <select name="wcai_event_date_source" class="regular-text">
                                <option value="departure" <?php selected( $current_date_source, 'departure' ); ?>>Saída selecionada (recomendado)</option>
                                <option value="order_meta" <?php selected( $current_date_source, 'order_meta' ); ?>>Meta do pedido</option>
                                <option value="item_meta" <?php selected( $current_date_source, 'item_meta' ); ?>>Meta do item do pedido</option>
                            </select>
                            <p class="description">Novas instalações usam a própria Saída. As opções legadas continuam disponíveis para compatibilidade com pedidos antigos.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Campo legado da data</th>
                        <td>
                            <select name="wcai_event_date_meta_key" class="regular-text">
                                <option value="">-- Selecione --</option>
                                <?php foreach ( $existing_keys as $key ) : ?>
                                    <option value="<?php echo esc_attr( $key['value'] ); ?>" <?php selected( $current_date_key, $key['value'] ); ?>>
                                        <?php echo esc_html( $key['label'] ); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="tour_date" <?php selected( $current_date_key, 'tour_date' ); ?>>tour_date — compatibilidade</option>
                            </select>
                            <p class="description">Selecione a meta somente quando a fonte acima for uma opção legada. A lista consulta metas de pedido e item disponíveis no banco.</p>
                            <input type="hidden" name="wcai_date_meta_key" value="<?php echo esc_attr( $current_date_key ); ?>">
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">CPFs Bloqueados</th>
                        <td><textarea name="wcai_blocked_cpfs" rows="3" cols="50" class="large-text code"><?php echo esc_textarea( get_option( 'wcai_blocked_cpfs' ) ); ?></textarea></td>
                    </tr>
                </table>
                <hr>

                <h2 class="title">Configurações da Agenda</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Status Visíveis</th>
                        <td>
                            <select name="wcai_calendar_statuses[]" multiple class="regular-text" style="height: 100px;">
                                <?php foreach ( $wc_statuses as $slug => $label ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>" <?php echo in_array( $slug, $calendar_statuses, true ) ? 'selected' : ''; ?>>
                                        <?php echo esc_html( $label ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Segure Ctrl/Cmd para múltiplos.</p>
                        </td>
                    </tr>
                </table>
                <hr>

                <h2 class="title">Numeração Sequencial</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Habilitar Sequencial</th>
                        <td><input type="checkbox" name="wcai_seq_enabled" value="yes" <?php checked( get_option( 'wcai_seq_enabled' ), 'yes' ); ?> />
                            <label>Ativar numeração personalizada</label></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Próximo Número</th>
                        <td><input type="number" name="wcai_seq_next" value="<?php echo esc_attr( get_option( 'wcai_seq_next', 1 ) ); ?>" class="small-text" min="1" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Prefixo</th>
                        <td><input type="text" name="wcai_seq_prefix" value="<?php echo esc_attr( get_option( 'wcai_seq_prefix' ) ); ?>" class="small-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Sufixo</th>
                        <td><input type="text" name="wcai_seq_suffix" value="<?php echo esc_attr( get_option( 'wcai_seq_suffix' ) ); ?>" class="small-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Digitos (Zeros)</th>
                        <td><input type="number" name="wcai_seq_width" value="<?php echo esc_attr( get_option( 'wcai_seq_width', 6 ) ); ?>" class="small-text" min="1" max="12" /></td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function get_existing_order_meta_keys() {
        global $wpdb;

        $results = array();
        $seen = array();

        $queries = array(
            array( $wpdb->prefix . 'wc_orders_meta', 'Pedido' ),
            array( $wpdb->postmeta, 'Pedido legado' ),
            array( $wpdb->prefix . 'woocommerce_order_itemmeta', 'Item do pedido' ),
        );

        foreach ( $queries as $query ) {
            $table = $query[0];
            $label_prefix = $query[1];
            $exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

            if ( $exists !== $table ) {
                continue;
            }

            $keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM $table WHERE meta_key NOT LIKE '\\_%' ORDER BY meta_key ASC LIMIT 200" );

            foreach ( $keys as $key ) {
                $key = sanitize_key( $key );

                if ( ! $key || isset( $seen[ $label_prefix . ':' . $key ] ) ) {
                    continue;
                }

                $seen[ $label_prefix . ':' . $key ] = true;
                $results[] = array(
                    'value' => $key,
                    'label' => $label_prefix . ': ' . $key,
                );
            }
        }

        return $results;
    }

    public function sanitize_text( $value ) {
        return sanitize_text_field( (string) $value );
    }

    public function sanitize_carta_oferta( $value ) {
        $value = trim( (string) $value );

        if ( '' === $value ) {
            return (string) get_option( 'wcai_carta_oferta', '' );
        }

        return sanitize_text_field( $value );
    }

    public function get_settings_capability() {
        return WCAI_Capabilities::MANAGE_SETTINGS;
    }

    public function sanitize_status( $value ) {
        $value = sanitize_key( $value );
        $statuses = array_keys( wc_get_order_statuses() );
        $statuses = array_map( static function( $status ) {
            return str_replace( 'wc-', '', $status );
        }, $statuses );

        return in_array( $value, $statuses, true ) ? $value : 'completed';
    }

    public function sanitize_product_ids( $value ) {
        $ids = array_filter( array_map( 'absint', preg_split( '/[s,;]+/', (string) $value ) ) );
        return implode( ',', array_unique( $ids ) );
    }

    public function sanitize_meta_key( $value ) {
        $value = sanitize_key( $value );
        return $value ?: 'tour_date';
    }

    public function sanitize_event_date_source( $value ) {
        $value = sanitize_key( $value );
        return in_array( $value, array( 'departure', 'order_meta', 'item_meta' ), true ) ? $value : 'departure';
    }

    public function sanitize_seq_enabled( $value ) {
        return 'yes' === $value ? 'yes' : 'no';
    }

    public function sanitize_seq_width( $value ) {
        return min( 12, max( 1, absint( $value ) ) );
    }

    public function sanitize_seq_next( $value ) {
        return max( 1, absint( $value ) );
    }

    public function sanitize_calendar_statuses( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $allowed = array_keys( wc_get_order_statuses() );
        $clean = array();

        foreach ( $value as $status ) {
            $status = sanitize_key( $status );
            if ( in_array( $status, $allowed, true ) ) {
                $clean[] = $status;
            }
        }

        return array_values( array_unique( $clean ) );
    }

    public function sanitize_blocked_cpfs( $value ) {
        $cpfs = preg_split( '/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY );
        $valid_cpfs = array();

        foreach ( $cpfs as $cpf ) {
            $cpf = preg_replace( '/\D+/', '', $cpf );
            if ( WCAI_Utils::is_valid_cpf( $cpf ) ) {
                $valid_cpfs[] = $cpf;
            }
        }

        return implode( "\n", array_unique( $valid_cpfs ) );
    }

    public static function get_product_ids() {
        $ids = array_map( 'absint', array_map( 'trim', explode( ',', get_option( 'wcai_product_ids', '' ) ) ) );
        return array_values( array_filter( $ids ) );
    }

    public static function is_cpf_blocked( $cpf ) {
        $cpf = preg_replace( '/\D+/', '', (string) $cpf );
        if ( ! $cpf ) {
            return false;
        }

        $blocked = preg_split( '/[\s,;]+/', (string) get_option( 'wcai_blocked_cpfs', '' ), -1, PREG_SPLIT_NO_EMPTY );
        $blocked = array_map( static function ( $blocked_cpf ) {
            return preg_replace( '/\D+/', '', $blocked_cpf );
        }, $blocked );

        return in_array( $cpf, $blocked, true );
    }

    public static function get_carta_oferta() { return get_option( 'wcai_carta_oferta', '' ); }
    public static function get_date_meta_key() { return get_option( 'wcai_event_date_meta_key', get_option( 'wcai_date_meta_key', 'tour_date' ) ); }
    public static function get_event_date_source() { return get_option( 'wcai_event_date_source', 'departure' ); }
    public static function get_event_date_meta_key() { return self::get_date_meta_key(); }
    public static function get_token() { return self::get_carta_oferta(); }
    public static function get_trigger_status() { return get_option( 'wcai_trigger_status', 'completed' ); }

    public static function get_calendar_statuses() {
        $statuses = get_option( 'wcai_calendar_statuses' );
        return ( empty( $statuses ) || ! is_array( $statuses ) ) ? array( 'wc-processing', 'wc-completed' ) : $statuses;
    }

    public static function is_seq_enabled() { return get_option( 'wcai_seq_enabled' ) === 'yes'; }
    public static function get_seq_prefix() { return get_option( 'wcai_seq_prefix', '' ); }
    public static function get_seq_suffix() { return get_option( 'wcai_seq_suffix', '' ); }
    public static function get_seq_width() { return intval( get_option( 'wcai_seq_width', 6 ) ); }
    public static function get_seq_next() { return intval( get_option( 'wcai_seq_next', 1 ) ); }
    public static function update_seq_next( $next ) { update_option( 'wcai_seq_next', intval( $next ) ); }
}

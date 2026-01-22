<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Settings {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'Configurações Adventure',
            'Adventure Config',
            'manage_options',
            'wcai-settings',
            array( $this, 'settings_page' )
        );
    }

    public function register_settings() {
        // Seguradora
        register_setting( 'wcai_settings_group', 'wcai_carta_oferta' );
        register_setting( 'wcai_settings_group', 'wcai_trigger_status' );
        
        // Geral
        register_setting( 'wcai_settings_group', 'wcai_product_ids' );
        register_setting( 'wcai_settings_group', 'wcai_blocked_cpfs' );
        register_setting( 'wcai_settings_group', 'wcai_date_meta_key' ); 
        
        // Numeração Sequencial
        register_setting( 'wcai_settings_group', 'wcai_seq_enabled' );
        register_setting( 'wcai_settings_group', 'wcai_seq_prefix' );
        register_setting( 'wcai_settings_group', 'wcai_seq_suffix' );
        register_setting( 'wcai_settings_group', 'wcai_seq_width' );
        register_setting( 'wcai_settings_group', 'wcai_seq_next' );

        // Agenda
        register_setting( 'wcai_settings_group', 'wcai_calendar_statuses' );
    }

    public function settings_page() {
        $existing_keys = $this->get_existing_order_item_meta_keys();
        $current_date_key = get_option('wcai_date_meta_key', 'tour_date');
        $current_trigger = get_option('wcai_trigger_status', 'completed');
        
        $wc_statuses = wc_get_order_statuses(); 
        
        $calendar_statuses = get_option('wcai_calendar_statuses');
        if ( empty($calendar_statuses) || !is_array($calendar_statuses) ) {
            $calendar_statuses = array('wc-processing', 'wc-completed');
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
                        <td><input type="text" name="wcai_carta_oferta" value="<?php echo esc_attr( get_option('wcai_carta_oferta') ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Gatilho de Envio API</th>
                        <td>
                            <select name="wcai_trigger_status" class="regular-text">
                                <?php foreach ( $wc_statuses as $slug => $label ) : 
                                    $clean_slug = str_replace('wc-', '', $slug); ?>
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
                        <td><input type="text" name="wcai_product_ids" value="<?php echo esc_attr( get_option('wcai_product_ids') ); ?>" class="regular-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Campo da Data</th>
                        <td>
                            <select name="wcai_date_meta_key" class="regular-text">
                                <option value="">-- Selecione --</option>
                                <?php foreach ( $existing_keys as $key ) : ?>
                                    <option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current_date_key, $key ); ?>>
                                        <?php echo esc_html( $key ); ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="tour_date" <?php selected( $current_date_key, 'tour_date' ); ?>>tour_date</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">CPFs Bloqueados</th>
                        <td><textarea name="wcai_blocked_cpfs" rows="3" cols="50" class="large-text code"><?php echo esc_textarea( get_option('wcai_blocked_cpfs') ); ?></textarea></td>
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
                                    <option value="<?php echo esc_attr( $slug ); ?>" <?php echo in_array($slug, $calendar_statuses) ? 'selected' : ''; ?>>
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
                        <td>
                            <input type="checkbox" name="wcai_seq_enabled" value="yes" <?php checked( get_option('wcai_seq_enabled'), 'yes' ); ?> />
                            <label>Ativar numeração personalizada</label>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Próximo Número</th>
                        <td><input type="number" name="wcai_seq_next" value="<?php echo esc_attr( get_option('wcai_seq_next', 1) ); ?>" class="small-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Prefixo</th>
                        <td><input type="text" name="wcai_seq_prefix" value="<?php echo esc_attr( get_option('wcai_seq_prefix') ); ?>" class="small-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Sufixo</th>
                        <td><input type="text" name="wcai_seq_suffix" value="<?php echo esc_attr( get_option('wcai_seq_suffix') ); ?>" class="small-text" /></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Digitos (Zeros)</th>
                        <td><input type="number" name="wcai_seq_width" value="<?php echo esc_attr( get_option('wcai_seq_width', 6) ); ?>" class="small-text" /></td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    private function get_existing_order_item_meta_keys() {
        global $wpdb;
        $keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key NOT LIKE '\_%' ORDER BY meta_key ASC LIMIT 50" );
        return $keys ? $keys : array();
    }

    // --- HELPERS (ESSENCIAIS PARA NÃO QUEBRAR O SITE) ---
    public static function get_product_ids() { $ids = get_option('wcai_product_ids',''); return array_map('trim', explode(',', $ids)); }
    public static function is_cpf_blocked($cpf) { return false; }
    public static function get_carta_oferta() { return get_option('wcai_carta_oferta',''); }
    public static function get_date_meta_key() { return get_option('wcai_date_meta_key','tour_date'); }
    public static function get_token() { return self::get_carta_oferta(); }
    public static function get_trigger_status() { return get_option( 'wcai_trigger_status', 'completed' ); }
    
    // Helper da Agenda
    public static function get_calendar_statuses() {
        $statuses = get_option('wcai_calendar_statuses');
        return ( empty($statuses) || !is_array($statuses) ) ? array('wc-processing', 'wc-completed') : $statuses;
    }

    // Helpers Sequencial
    public static function is_seq_enabled() { return get_option('wcai_seq_enabled') === 'yes'; }
    public static function get_seq_prefix() { return get_option('wcai_seq_prefix', ''); }
    public static function get_seq_suffix() { return get_option('wcai_seq_suffix', ''); }
    public static function get_seq_width() { return intval(get_option('wcai_seq_width', 6)); }
    public static function get_seq_next() { return intval(get_option('wcai_seq_next', 1)); }
    public static function update_seq_next( $next ) { update_option( 'wcai_seq_next', intval( $next ) ); }
}

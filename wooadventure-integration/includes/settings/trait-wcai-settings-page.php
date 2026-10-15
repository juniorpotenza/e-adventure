<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Settings_Page {

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
                        <th scope="row"><label for="wcai_operation_name"><?php echo esc_html__( 'Nome da operação', 'wooadventure-integration' ); ?></label></th>
                        <td>
                            <input type="text" id="wcai_operation_name" name="wcai_operation_name" value="<?php echo esc_attr( self::get_operation_name() ); ?>" class="regular-text" />
                            <p class="description"><?php echo esc_html__( 'Aparece no app do scanner.', 'wooadventure-integration' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wcai_terms_url"><?php echo esc_html__( 'URL do termo', 'wooadventure-integration' ); ?></label></th>
                        <td>
                            <input type="url" id="wcai_terms_url" name="wcai_terms_url" value="<?php echo esc_attr( self::get_terms_url() ); ?>" class="regular-text" placeholder="https://" />
                            <p class="description"><?php echo esc_html__( 'Link da página onde o participante assina o termo.', 'wooadventure-integration' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">CPFs Bloqueados</th>
                        <td><textarea name="wcai_blocked_cpfs" rows="3" cols="50" class="large-text code"><?php echo esc_textarea( get_option( 'wcai_blocked_cpfs' ) ); ?></textarea></td>
                    </tr>
                </table>
                <hr>

                <h2 class="title"><?php echo esc_html__( 'Página do passeio', 'wooadventure-integration' ); ?></h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><label for="wcai_cancellation_policy"><?php echo esc_html__( 'Política de cancelamento', 'wooadventure-integration' ); ?></label></th>
                        <td>
                            <textarea id="wcai_cancellation_policy" name="wcai_cancellation_policy" rows="4" cols="50" maxlength="1000" class="large-text"><?php echo esc_textarea( self::get_cancellation_policy() ); ?></textarea>
                            <p class="description"><?php echo esc_html__( 'Opcional. Se preenchido, aparece em "Antes de reservar" na página do passeio. Vazio: nada é exibido.', 'wooadventure-integration' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wcai_contact_phone"><?php echo esc_html__( 'Telefone de contato', 'wooadventure-integration' ); ?></label></th>
                        <td>
                            <input type="text" id="wcai_contact_phone" name="wcai_contact_phone" value="<?php echo esc_attr( self::get_contact_phone() ); ?>" class="regular-text" placeholder="+55 11 99999-9999" />
                            <p class="description"><?php echo esc_html__( 'Opcional. Aparece em "Antes de reservar" como link para ligar.', 'wooadventure-integration' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><label for="wcai_accent_color"><?php echo esc_html__( 'Cor de destaque', 'wooadventure-integration' ); ?></label></th>
                        <td>
                            <input type="text" id="wcai_accent_color" name="wcai_accent_color" value="<?php echo esc_attr( self::get_accent_color() ); ?>" class="regular-text" maxlength="7" pattern="#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?" placeholder="#111111" />
                            <p class="description"><?php echo esc_html__( 'Opcional. Cor hexadecimal (ex.: #0b6e4f) das datas, horários e botões selecionados. Vazio: mantém #111.', 'wooadventure-integration' ); ?></p>
                        </td>
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

                <hr>

                <h2 class="title"><?php echo esc_html__( 'Privacidade e dados', 'wooadventure-integration' ); ?></h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php echo esc_html__( 'Texto de consentimento', 'wooadventure-integration' ); ?></th>
                        <td>
                            <p><?php echo esc_html( WCAI_Privacy::consent_text() ); ?></p>
                            <p class="description"><?php echo esc_html__( 'Este texto aparece ao participante. Para alterá-lo, use o filtro wcai_third_party_consent_text.', 'wooadventure-integration' ); ?></p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php echo esc_html__( 'Excluir dados ao desinstalar', 'wooadventure-integration' ); ?></th>
                        <td>
                            <input type="hidden" name="wcai_delete_data_on_uninstall" value="" />
                            <label><input type="checkbox" name="wcai_delete_data_on_uninstall" value="yes" <?php checked( get_option( 'wcai_delete_data_on_uninstall' ), 'yes' ); ?> />
                            <?php echo esc_html__( 'Apagar todos os dados do plugin ao excluí-lo', 'wooadventure-integration' ); ?></label>
                            <p class="description" style="color:#b32d2e;"><strong><?php echo esc_html__( 'Atenção: ao excluir o plugin, esta opção apaga permanentemente participantes, assinaturas e aceites jurídicos. Faça backup antes.', 'wooadventure-integration' ); ?></strong></p>
                        </td>
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
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Frontend_Account_Admin {
    // =========================================================================
    // 4. ADMIN (LAYOUT OTIMIZADO - COLUNAS DIVIDIDAS)
    // =========================================================================
    private function is_hpos_orders_screen() {
        return class_exists( 'Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    public function register_admin_metabox() {
        if ( $this->is_hpos_orders_screen() ) {
            return;
        }

        add_meta_box('wcai_participants_box', 'Lista de Participantes (DB)', array($this, 'render_admin_content'), 'shop_order', 'normal', 'high');
    }

    public function render_hpos_admin_content( $order ) {
        // Sem HPOS o meta box já mostra a lista: imprimir aqui duplicaria tabela e nonce.
        if ( ! $order instanceof WC_Order || ! $this->is_hpos_orders_screen() ) {
            return;
        }

        $this->render_admin_content( $order );
    }

    public function render_admin_content($object) {
        $order = $object instanceof WC_Order ? $object : wc_get_order($object->ID);
        wp_nonce_field( 'wcai_admin_participants', 'wcai_admin_participants_nonce' );
        $participants = $this->get_participants_safe($order);
        $can_view_sensitive = current_user_can( WCAI_Capabilities::VIEW_SENSITIVE );

        $total = count($participants);
        $signed = 0;
        foreach($participants as $p) {
            $cpf_clean = $this->safe_cpf_pad($p['cpf']); // PAD FIX
            $meta_signed = $order->get_meta('_waiver_signed_' . $cpf_clean);
            $db_signed = isset($p['termo_assinado']) && $p['termo_assinado'] == 1;
            if ($meta_signed || $db_signed) $signed++;
        }
        $percent = ($total > 0) ? round(($signed / $total) * 100) : 0;
        $color_bar = ($signed == $total && $total > 0) ? '#46b450' : '#f0f0f1';
        $text_color = ($signed == $total && $total > 0) ? '#fff' : '#50575e';
        $border = ($signed == $total && $total > 0) ? 'none' : '1px solid #ddd';

        echo '<style>
            .wcai-table input { width: 100%; box-sizing: border-box; }
            .wcai-table .wcai-name-cell { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
            .wcai-table .wcai-name-cell input { flex: 1 1 180px; min-width: 180px; width: auto; }
            .wcai-table .wcai-name-cell .dashicons { margin: 0 !important; flex-shrink: 0; }
            .wcai-restricted { color: #50575e; cursor: help; border-bottom: 1px dotted #8c8f94; }
            .wcai-table td { padding: 8px; vertical-align: middle; }
            .wcai-col-id { width: 30px; }
            .wcai-col-cpf { width: 140px; } 
            .wcai-col-nasc { width: 110px; }
            .wcai-col-in { width: 125px; } 
            .wcai-col-out { width: 125px; }
            .wcai-status-badge { 
                display: block; 
                padding: 3px 6px; 
                border-radius: 3px; 
                font-size: 11px; 
                font-weight: bold; 
                line-height: 1;
                text-align: center;
                white-space: nowrap; 
            }
        </style>';
        
        $tour_quantity = $this->order_tour_quantity($order);
        if ($tour_quantity > 0 && $tour_quantity !== $total) {
            echo '<div class="notice notice-warning inline" style="margin:0 0 10px;"><p>' . esc_html( sprintf( 'Atenção: %d ingressos, %d participantes.', $tour_quantity, $total ) ) . '</p></div>';
        }

        if($total > 0) {
            echo '<div style="margin-bottom: 10px; padding: 8px 12px; background: '.esc_attr($color_bar).'; color:'.esc_attr($text_color).'; border-radius: 4px; border: '.esc_attr($border).'; font-weight: 500;">';
            echo '📝 <strong>Status dos Termos:</strong> ' . $signed . '/' . $total . ' Assinados (' . $percent . '%)';
            echo '</div>';
        }

        echo '<table class="widefat wcai-table">';
        echo '<thead><tr>
                <th class="wcai-col-id">#</th>
                <th>Nome / Status</th>
                <th class="wcai-col-cpf">CPF</th>
                <th class="wcai-col-nasc">Nasc.</th>
                <th class="wcai-col-in">Check-in</th>
                <th class="wcai-col-out">Check-out</th>
              </tr></thead><tbody>';
        
        if ( empty($participants) ) {
            echo '<tr><td colspan="6">Nenhum participante encontrado.</td></tr>';
        } else {
            foreach($participants as $idx => $p) {
                $val_nasc = $this->force_date_to_display($p['data_nascimento']);
                $badge = $this->get_age_badge_html($p['data_nascimento']);
                $field_key = (isset($p['id']) && is_numeric($p['id'])) ? $p['id'] : 'new_' . $idx;
                $id_input = (isset($p['id']) && is_numeric($p['id'])) ? '<input type="hidden" name="wcai_admin_p['.$p['id'].'][id]" value="'.$p['id'].'">' : '';
                
                $cpf_clean = $this->safe_cpf_pad($p['cpf']); // PAD FIX
                $meta_signed = $order->get_meta('_waiver_signed_' . $cpf_clean);
                $db_signed = isset($p['termo_assinado']) && $p['termo_assinado'] == 1;
                
                $status_icon = ($meta_signed || $db_signed) 
                    ? '<span class="dashicons dashicons-yes-alt" style="color:green; font-size:18px; margin-right:5px;" title="Termo Assinado"></span>' 
                    : '<span class="dashicons dashicons-warning" style="color:#ccc; font-size:18px; margin-right:5px;" title="Termo Pendente"></span>';

                // --- LÓGICA DE CHECK-IN ---
                $html_in = '-';
                if( !empty($p['checkin_time']) ) {
                    $hora_ent = date('d/m/y H:i', strtotime($p['checkin_time']));
                    $html_in = '<span class="wcai-status-badge" style="background:#d4edda; color:#155724;">'.$hora_ent.'</span>';
                }

                // --- LÓGICA DE CHECK-OUT ---
                $html_out = '-';
                if( !empty($p['checkout_time']) ) {
                    $hora_sai = date('d/m/y H:i', strtotime($p['checkout_time']));
                    $html_out = '<span class="wcai-status-badge" style="background:#f8d7da; color:#721c24;">'.$hora_sai.'</span>';
                }

                echo '<tr>';
                echo '<td>' . ($idx + 1) . '</td>';
                echo '<td><div class="wcai-name-cell">' . $badge . $status_icon . $id_input . '<input type="text" name="wcai_admin_p['.$field_key.'][nome_completo]" value="'.esc_attr($p['nome_completo']).'"></div></td>';
                
                // CPF ADMIN (PAD FIX)
                $cpf_val_admin = class_exists('WCAI_Utils') ? WCAI_Utils::format_cpf($cpf_clean) : $cpf_clean;
                if ( $can_view_sensitive ) {
                    echo '<td><input type="text" class="wcai-mask-cpf" name="wcai_admin_p['.$field_key.'][cpf]" value="'.esc_attr($cpf_val_admin).'"></td>';
                    echo '<td><input type="text" class="wcai-mask-date" name="wcai_admin_p['.$field_key.'][data_nascimento]" value="'.esc_attr($val_nasc).'" placeholder="dd/mm/aaaa"></td>';
                } else {
                    echo '<td>' . esc_html( WCAI_Utils::mask_cpf($cpf_clean) ) . '</td>';
                    // Sem a capability: idade em vez da data, com a razão no tooltip (nunca um '-' mudo).
                    $age_label = $this->participant_age_label( $p['data_nascimento'] );
                    echo '<td><span class="wcai-restricted" title="Data de nascimento restrita">' . esc_html( '' !== $age_label ? $age_label : 'Restrita' ) . '</span></td>';
                }
                
                // COLUNA ENTRADA
                echo '<td>' . $html_in . '</td>';
                // COLUNA SAÍDA
                echo '<td>' . $html_out . '</td>';
                
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '<p class="description">Clique em "Atualizar" no pedido para salvar.</p>';
    }

    public function admin_save($post_id) {
        if ( ! isset( $_POST['wcai_admin_p'] ) ) return;
        if ( ! current_user_can( WCAI_Capabilities::MANAGE_PARTICIPANTS ) ) return;
        if ( ! isset( $_POST['wcai_admin_participants_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wcai_admin_participants_nonce'] ) ), 'wcai_admin_participants' ) ) return;

        $order = wc_get_order( $post_id );
        if ( ! $order ) return;

        $rows = wp_unslash( $_POST['wcai_admin_p'] );
        if ( ! current_user_can( WCAI_Capabilities::VIEW_SENSITIVE ) ) {
            // Sem a capability, a tela não mostra CPF/nascimento: só edita nome de participantes existentes.
            $rows = array_map( function ( $row ) {
                return array( 'id' => isset( $row['id'] ) ? $row['id'] : null, 'nome_completo' => isset( $row['nome_completo'] ) ? $row['nome_completo'] : '' );
            }, array_filter( (array) $rows, function ( $row ) {
                return is_array( $row ) && isset( $row['id'] ) && is_numeric( $row['id'] );
            } ) );
        }

        $this->process_save( $order, $rows, 'Admin' );
    }

    // Ingressos de passeio (itens com saída ou produto configurado) para comparar com a lista de participantes.
    private function order_tour_quantity($order) {
        $target_ids = class_exists('WCAI_Settings') ? WCAI_Settings::get_product_ids() : array();
        $quantity = 0;
        foreach ($order->get_items() as $item) {
            if ($item->get_meta('_wcai_departure_id', true) || in_array($item->get_variation_id(), $target_ids) || in_array($item->get_product_id(), $target_ids)) {
                $quantity += (int) $item->get_quantity();
            }
        }
        return $quantity;
    }

    public function hide_departure_itemmeta($keys) {
        $keys[] = '_wcai_departure_id';
        return $keys;
    }

    public function render_departure_itemmeta($item_id, $item) {
        if (!is_admin() || !is_object($item) || !method_exists($item, 'get_meta')) return;
        $departure_id = absint($item->get_meta('_wcai_departure_id', true));
        if (!$departure_id) return;

        $timestamp = WCAI_Data_Resolver::parse_timestamp(WCAI_Data_Resolver::get_departure_start($departure_id));
        $label = 'Passeio: ' . $item->get_name() . ($timestamp ? ' — ' . wp_date('d/m/Y H:i', $timestamp) : '');
        echo '<div class="wcai-item-departure" style="margin-top:4px;"><strong>' . esc_html($label) . '</strong></div>';
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Frontend_Account_Save {
    // --- 5. BUSCA E SALVAMENTO ---
    private function get_participants_safe($order) {
        $list = array();
        if (class_exists('WCAI_Participants_DB')) {
            $list = WCAI_Participants_DB::get_by_order($order->get_id());
        }
        if (!is_array($list)) $list = array();
        $table_is_empty = empty($list);
        $list = array_values(array_filter($list, function ($p) { return $this->is_valid_participant_name($p['nome_completo']); }));

        $b_name = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
        $b_cpf_raw  = $order->get_meta('billing_cpf') ?: $order->get_meta('_billing_cpf');
        $b_dob  = $order->get_meta('billing_birthdate') ?: $order->get_meta('_billing_birthdate');
        // Pad titular se necessário
        $b_cpf_clean = $this->safe_cpf_pad($b_cpf_raw);

        $titular_exists = false;
        foreach ($list as $p) {
            $p_cpf_clean = $this->safe_cpf_pad($p['cpf']); // PAD FIX
            if (!empty($b_cpf_clean) && $p_cpf_clean === $b_cpf_clean) {
                $titular_exists = true; break;
            }
        }

        if (!$titular_exists && $this->is_valid_participant_name($b_name)) {
            $virtual = array('id' => 'virtual_titular', 'nome_completo' => $b_name, 'cpf' => $b_cpf_raw, 'data_nascimento' => $b_dob);
            array_unshift($list, $virtual);
        }

        // Meta legado só vale quando a tabela não tem nenhuma linha do pedido; senão duplica participantes.
        if ($table_is_empty) {
            $meta = $order->get_meta('_additional_participants');
            if (!empty($meta) && is_array($meta)) {
                foreach($meta as $k => $v) {
                    if (!is_array($v) || !$this->is_valid_participant_name(isset($v['nome_completo']) ? $v['nome_completo'] : '')) continue;
                    $list[] = array('id'=>'legacy_'.$k, 'nome_completo'=>$v['nome_completo'], 'cpf'=>isset($v['cpf']) ? $v['cpf'] : '', 'data_nascimento'=>isset($v['data_nascimento']) ? $v['data_nascimento'] : '');
                }
            }
        }
        return $list;
    }

    // Nome precisa ter ao menos uma letra ou dígito: bloqueia placeholders como "." ou "'".
    private function is_valid_participant_name($name) {
        return (bool) preg_match('/[\p{L}\p{N}]/u', (string) $name);
    }

    private function process_save($order, $data, $source) {
        if (empty($data) || !class_exists('WCAI_Participants_DB')) return;

        $has_changes = false;
        
        foreach ($data as $row) {
            if (!is_array($row)) continue;
            if (!isset($row['nome_completo']) || !$this->is_valid_participant_name($row['nome_completo'])) {
                $order->add_order_note("Participante com nome inválido ignorado ($source).");
                continue;
            }

            $item = array('nome_completo' => sanitize_text_field($row['nome_completo']));
            // Campos sensíveis são opcionais: admin sem VIEW_SENSITIVE não os envia.
            // CPF mascarado ("*") significa "não alterado": o valor guardado fica como está.
            if (isset($row['cpf']) && false === strpos((string) $row['cpf'], '*')) {
                $item['cpf'] = $this->safe_cpf_pad($row['cpf']); // Salva sempre com 11 dígitos
            }
            if (isset($row['data_nascimento'])) {
                $item['data_nascimento'] = sanitize_text_field($row['data_nascimento']);
            }

            if (isset($row['id']) && is_numeric($row['id'])) {
                // Nunca atualiza participante de outro pedido (IDOR).
                if (!$this->participant_belongs_to_order($row['id'], $order)) continue;
                WCAI_Participants_DB::update($row['id'], $item);
                $has_changes = true;
            } else {
                $item['order_id'] = $order->get_id();
                $item['customer_id'] = $order->get_customer_id();
                WCAI_Participants_DB::add($item);
                $has_changes = true;
            }
        }

        if ($has_changes) {
            $order->add_order_note("Atualização de Participantes ($source) realizada.");
            $fresh_list = WCAI_Participants_DB::get_by_order($order->get_id());
            $meta_mirror = array();
            foreach($fresh_list as $row) {
                // PAD FIX para o META também
                $cpf_p = $this->safe_cpf_pad($row['cpf']);
                $meta_mirror[] = array(
                    'nome_completo' => $row['nome_completo'],
                    'cpf' => WCAI_Utils::format_cpf($cpf_p),
                    'data_nascimento' => $row['data_nascimento']
                );
            }
            $order->update_meta_data('_additional_participants', $meta_mirror);
            $order->save();
        }
    }
}

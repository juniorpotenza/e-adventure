<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Frontend_Account_Ajax {
    public function ajax_update() {
        check_ajax_referer('wcai_update_nonce', 'security');
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        if (!$order || $order->get_customer_id() !== get_current_user_id()) wp_send_json_error('Erro permissão');
        if ($this->is_order_locked($order)) wp_send_json_error('Pedido bloqueado para edição.');

        $data = isset($_POST['participantes']) ? wp_unslash($_POST['participantes']) : array();
        $data = $this->restore_unchanged_fields($order, $data);
        $error = $this->validate_frontend_rows($order, $data);
        if ($error) wp_send_json_error($error);

        $this->process_save($order, $data, 'Frontend');
        wp_send_json_success(array('message' => 'Salvo com sucesso!'));
    }

    // O modal mostra CPF mascarado ("529.***.***-25") e, com identidade travada, só a idade (nasc_unchanged=1).
    // Aqui esses campos voltam ao valor guardado: nunca vira CPF novo, nem erro de validação, nem CPF duplicado.
    private function restore_unchanged_fields($order, $data) {
        if (!is_array($data) || !class_exists('WCAI_Participants_DB')) return $data;
        foreach ($data as $key => $row) {
            if (!is_array($row)) continue;
            $current = (isset($row['id']) && is_numeric($row['id']) && $this->participant_belongs_to_order($row['id'], $order))
                ? WCAI_Participants_DB::get_by_id($row['id']) : null;
            if (isset($row['cpf']) && false !== strpos((string) $row['cpf'], '*')) {
                $row['cpf'] = $current ? $current['cpf'] : $this->cpf_for_masked_virtual_row($order, (string) $row['cpf']);
            }
            if (!empty($row['nasc_unchanged'])) {
                $row['data_nascimento'] = ($current && $this->identity_locked($current)) ? $this->force_date_to_display($current['data_nascimento']) : '';
            }
            unset($row['nasc_unchanged']);
            $data[$key] = $row;
        }
        return $data;
    }

    // Linha ainda sem registro (titular virtual / meta legado): o CPF completo vem do pedido, achado pela máscara.
    private function cpf_for_masked_virtual_row($order, $masked) {
        foreach ($this->get_participants_safe($order) as $p) {
            if (!is_numeric($p['id']) && WCAI_Utils::mask_cpf($this->safe_cpf_pad($p['cpf'])) === $masked) return $p['cpf'];
        }
        return '';
    }

    private function is_order_locked($order) {
        return in_array( $order->get_status(), array('completed', 'cancelled', 'refunded', 'failed') );
    }

    private function participant_belongs_to_order($participant_id, $order) {
        $current = WCAI_Participants_DB::get_by_id($participant_id);
        return $current && (int) $current['order_id'] === (int) $order->get_id();
    }

    // Reaplica as regras do checkout (CPF, idade mínima, CPF duplicado). Retorna a mensagem de erro ou ''.
    private function validate_frontend_rows($order, $data) {
        if (!is_array($data) || empty($data) || !class_exists('WCAI_Participants_DB')) return 'Dados inválidos.';

        $cpfs_used = array();
        $new_rows = 0;
        foreach ($data as $row) {
            if (!is_array($row)) return 'Dados inválidos.';

            $current = null;
            if (isset($row['id']) && is_numeric($row['id'])) {
                if (!$this->participant_belongs_to_order($row['id'], $order)) {
                    return 'Participante não pertence a este pedido.';
                }
                $current = WCAI_Participants_DB::get_by_id($row['id']);
            } else {
                $new_rows++;
            }

            $nome = isset($row['nome_completo']) ? sanitize_text_field($row['nome_completo']) : '';
            $cpf  = isset($row['cpf']) ? $this->safe_cpf_pad($row['cpf']) : '';
            $data_nasc = isset($row['data_nascimento']) ? sanitize_text_field($row['data_nascimento']) : '';

            if ($nome === '') return 'Nome do participante é obrigatório.';
            if (!$this->is_valid_participant_name($nome)) return 'Nome inválido: informe o nome completo do participante (use letras ou números).';
            if (!WCAI_Utils::is_valid_cpf($cpf)) return "CPF inválido: $nome.";
            if (class_exists('WCAI_Settings') && WCAI_Settings::is_cpf_blocked($cpf)) return "CPF restrito: $nome.";
            if (!WCAI_Utils::is_valid_date($data_nasc) || !WCAI_Utils::is_min_age($data_nasc)) {
                return "Data de nascimento inválida ou idade mínima de 7 anos não atendida: $nome.";
            }
            // Com aceite ou check-in, o nome também é identidade do ingresso: só o suporte altera.
            if ($current && $this->identity_locked($current) && $nome !== sanitize_text_field($current['nome_completo'])) {
                return "O nome não pode ser alterado após o aceite do termo ou check-in. Fale com o suporte: $nome.";
            }
            // Com aceite ou check-in, CPF e nascimento são identidade do ingresso (regra do responsável legal): só o admin altera.
            if ($current && $this->identity_locked($current)
                && ($cpf !== $this->safe_cpf_pad($current['cpf']) || $this->force_date_to_db($data_nasc) !== $current['data_nascimento'])) {
                return "CPF e data de nascimento não podem ser alterados após o aceite do termo ou check-in: $nome.";
            }
            if (in_array($cpf, $cpfs_used, true)) return "CPF duplicado neste pedido: $nome.";
            $cpfs_used[] = $cpf;
        }

        // Linhas sem id criam participantes: o total não pode passar da quantidade de ingressos do pedido.
        if ($new_rows > 0 && count(WCAI_Participants_DB::get_by_order($order->get_id())) + $new_rows > $this->order_ticket_quantity($order)) {
            return 'O número de participantes excede a quantidade de ingressos do pedido.';
        }
        return '';
    }

    private function identity_locked($participant) {
        return !empty($participant['checkin_time'])
            || !empty($participant['checkin_status'])
            || (class_exists('WCAI_Legal_Acceptances') && WCAI_Legal_Acceptances::get_by_participant($participant['id']));
    }

    private function order_ticket_quantity($order) {
        $quantity = 0;
        foreach ($order->get_items() as $item) {
            $quantity += (int) $item->get_quantity();
        }
        return $quantity;
    }
}

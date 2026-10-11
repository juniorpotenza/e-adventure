<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Agenda_Tracker {

    // =========================================================================
    // MÉTODOS DE RASTREAMENTO (MODIFICADO: Ticket + Check-in + E-mail)
    // =========================================================================
    public function render_success_tracker() {
        if ( ! class_exists( 'WCAI_Assinatura' ) ) {
            return '';
        }

        $ticket_info = WCAI_Assinatura::consume_ticket_session();

        if ( ! $ticket_info || empty( $ticket_info['qr_url'] ) ) {
            return '';
        }

        // O cookie só é limpo após o envio dos headers; a trava evita reenvio a cada reload do GET.
        $mail_lock = 'wcai_ticket_mailed_' . absint( $ticket_info['participant_id'] );
        if ( false === get_transient( $mail_lock ) ) {
            set_transient( $mail_lock, 1, 10 * MINUTE_IN_SECONDS );
            $this->send_ticket_email_via_shortcode( $ticket_info );
        }

        return '
        <div style="text-align:center; padding:20px; background:#fff; border:1px solid #d4edda; border-radius:8px; box-shadow:0 2px 5px rgba(0,0,0,0.05); margin-bottom:20px;">
            <div style="color:#155724; font-size:18px; font-weight:bold; margin-bottom:15px;">✅ Assinatura Confirmada!</div>
            <p>O seu ingresso foi enviado por e-mail. Também pode guardá-lo agora:</p>
            <div style="margin:20px auto; display:inline-block; border:1px solid #ccc; padding:10px; background:#fff;">
                <img src="' . esc_url( $ticket_info['qr_url'] ) . '" alt="QR Code Ticket" style="width:200px; height:200px;">
            </div>
            <div style="font-size:12px; color:#777; margin-top:10px;">
                Participante: <strong>' . esc_html( $ticket_info['nome'] ) . '</strong><br>
                Pedido: #' . esc_html( $ticket_info['order_id'] ) . '
            </div>
            <button onclick="window.print()" style="margin-top:15px; padding:10px 20px; background:#007cba; color:#fff; border:none; border-radius:4px; cursor:pointer;">🖨️ Imprimir / Salvar</button>
        </div>';
    }

    // --- NOVA FUNÇÃO DE DISPARO DE E-MAIL ---
    private function send_ticket_email_via_shortcode($ticket_info) {
        $order_id = $ticket_info['order_id'];
        $order = wc_get_order($order_id);
        
        if (!$order) {
            error_log('[WCAI] Erro Email: Pedido não carregou.');
            return;
        }

        // O destinatário vem sempre do pedido, nunca de cookie (não assinado).
        $to = $order->get_billing_email();

        if ( empty($to) ) {
            error_log('[WCAI] Erro Fatal: Nenhum destinatário para o ingresso.');
            return;
        }

        $nome_pax = esc_html( $ticket_info['nome'] );
        $qr_img = esc_url( $ticket_info['qr_url'] );
        $subject = 'Seu Ingresso - Pedido #' . absint( $order_id );
        
        $admin_email = sanitize_email( get_option( 'admin_email' ) );
        $site_title = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            "From: $site_title <$admin_email>"
        );

        $msg = "
        <html>
        <body style='font-family:Arial, sans-serif; color:#333; background-color:#f4f4f4; padding:20px;'>
            <div style='max-width:600px; margin:0 auto; background:#fff; border:1px solid #ddd; padding:20px; border-radius:10px;'>
                <h2 style='color:#007cba; text-align:center;'>Passeio Confirmado!</h2>
                <p>Olá,</p>
                <p>O termo de responsabilidade de <strong>$nome_pax</strong> foi assinado com sucesso.</p>
                <p>Abaixo está o ingresso digital para entrada no parque.</p>
                
                <div style='text-align:center; margin:30px 0; background:#f9f9f9; padding:20px; border-radius:10px; border:1px dashed #ccc;'>
                    <img src='$qr_img' alt='QR Code Ticket' style='width:200px; height:200px;'><br>
                    <strong style='font-size:24px; letter-spacing:2px; display:block; margin-top:15px; color:#333;'>#$order_id</strong>
                    <p style='font-size:14px; color:#666; margin-top:5px;'>Apresente este código na portaria.</p>
                </div>

                <p style='text-align:center; color:#555;'>Dica: <strong>Guarde esta imagem</strong> no seu telemóvel.</p>
                <hr style='border:0; border-top:1px solid #eee; margin:20px 0;'>
                <p style='font-size:12px; color:#aaa; text-align:center;'>$site_title - Ecoturismo</p>
            </div>
        </body>
        </html>
        ";

        $sent = wp_mail($to, $subject, $msg, $headers);
        if ( ! $sent ) {
            error_log( '[WCAI] Falha no envio do ingresso por e-mail.' );
        }
    }

}

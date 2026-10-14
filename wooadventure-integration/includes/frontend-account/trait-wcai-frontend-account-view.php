<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Frontend_Account_View {
    // =========================================================================
    // 0. HELPER DE CPF (CORREÇÃO DO ZERO À ESQUERDA)
    // =========================================================================
    private function safe_cpf_pad( $cpf ) {
        // Remove tudo que não é número
        $clean = preg_replace('/[^0-9]/', '', $cpf);
        if ( empty($clean) ) return '';
        // Se tiver menos de 11 dígitos, completa com zeros à esquerda
        return str_pad($clean, 11, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    // RENDERIZAÇÃO DA LISTA (SITE E E-MAIL)
    // =========================================================================
    public function render_participants_on_thankyou( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $this->render_participants_on_order_view( $order );
        }
    }

    private function participant_age_label( $date_string ) {
        $age = $this->participant_age( $date_string );
        if ( null === $age ) return '';
        return 1 === $age ? '1 ano' : $age . ' anos';
    }

    private function participant_is_signed( $order, $p ) {
        $cpf = $this->safe_cpf_pad( $p['cpf'] );
        return ! empty( $p['ticket_hash'] ) || ( $cpf && $order->get_meta( '_waiver_signed_' . $cpf ) ) || ( isset( $p['termo_assinado'] ) && 1 == $p['termo_assinado'] );
    }

    // QR só com reserva confirmada; participante sem reserva (legado) não depende dela.
    private function participant_reservation_confirmed( $p ) {
        $reservation_id = isset( $p['reservation_id'] ) ? absint( $p['reservation_id'] ) : 0;
        if ( ! $reservation_id || ! class_exists( 'WCAI_Reservations' ) ) return true;
        $reservation = WCAI_Reservations::get_by_id( $reservation_id );
        return $reservation && 'confirmed' === $reservation->status;
    }

    // E-mail: texto puro vira linhas de texto; HTML vira tabela compacta (sem QR nem ações de edição).
    public function render_participants_in_email( $order, $sent_to_admin = false, $plain_text = false, $email = null ) {
        if ( ! is_object( $order ) ) return;
        $participants = $this->get_participants_safe( $order );
        if ( empty( $participants ) ) return;

        $rows = array();
        foreach ( $participants as $p ) {
            $rows[] = array(
                'name'   => $this->display_name( $p['nome_completo'] ),
                'age'    => $this->participant_age_label( $p['data_nascimento'] ),
                'cpf'    => WCAI_Utils::mask_cpf( $this->safe_cpf_pad( $p['cpf'] ) ),
                'signed' => $this->participant_is_signed( $order, $p ),
            );
        }

        if ( $plain_text ) {
            echo "\n" . __( 'Lista de Participantes', 'wooadventure-integration' ) . "\n";
            foreach ( $rows as $i => $r ) {
                $parts = array_filter( array( $r['name'], $r['age'], 'CPF ' . $r['cpf'] ) );
                echo sprintf( 'Visitante %d: %s', $i + 1, implode( ' — ', $parts ) ) . "\n";
            }
            echo "\n";
            return;
        }

        $cell = 'text-align:left; padding:8px; border:1px solid #e5e5e5;';
        echo '<h2 style="margin:20px 0 10px;">' . esc_html__( 'Lista de Participantes', 'wooadventure-integration' ) . '</h2>';
        echo '<table cellspacing="0" cellpadding="6" style="width:100%; border-collapse:collapse; margin-bottom:20px; border:1px solid #e5e5e5;"><thead><tr>';
        foreach ( array( 'Nome', 'Idade', 'CPF', 'Termo' ) as $h ) {
            echo '<th style="' . esc_attr( $cell ) . '">' . esc_html( $h ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ( $rows as $r ) {
            echo '<tr><td style="' . esc_attr( $cell ) . '">' . esc_html( $r['name'] ) . '</td>'
                . '<td style="' . esc_attr( $cell ) . '">' . esc_html( $r['age'] ) . '</td>'
                . '<td style="' . esc_attr( $cell ) . '">' . esc_html( $r['cpf'] ) . '</td>'
                . '<td style="' . esc_attr( $cell ) . '">' . esc_html( $r['signed'] ? 'Assinado' : 'Pendente' ) . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    // Página do pedido/obrigado: hooks diferentes podem disparar no mesmo pedido; imprime uma vez por requisição.
    // Tema de blocos (order-received): o Woo dispara o hook antes do <html>; guarda o HTML e imprime dentro do bloco.
    public function render_participants_on_order_view( $order ) {
        static $rendered = array();
        if ( ! is_object( $order ) || isset( $rendered[ $order->get_id() ] ) || ! $this->can_view_order_participants( $order ) ) return;
        $participants = $this->get_participants_safe( $order );
        if ( empty( $participants ) ) return;
        $rendered[ $order->get_id() ] = true;

        $in_blocks = $this->uses_confirmation_blocks();
        $html = $this->order_extras_html( $order, $participants, $in_blocks );
        if ( $in_blocks ) {
            self::$deferred_extras = $html;
            return;
        }
        echo $html;
    }

    // A chave do pedido vai no link: ajax_check_pax_deep exige order_key (o JS da assinatura lê ?key=).
    private function get_terms_url( $order ) {
        $url = class_exists( 'WCAI_Settings' ) ? trim( (string) WCAI_Settings::get_terms_url() ) : '';
        // Caminho relativo vale a partir da home do site (que pode ter prefixo, ex.: /teste).
        if ( 0 === strpos( $url, '/' ) && 0 !== strpos( $url, '//' ) ) $url = home_url( $url );

        return $url ? add_query_arg( 'key', $order->get_order_key(), $url ) : '';
    }

    // --- 1. ASSETS ---
    public function enqueue_scripts() {
        if ( is_account_page() ) {
            wp_enqueue_script( 'jquery-mask', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js', array( 'jquery' ), '1.14.16', true );
        }
    }

    public function enqueue_admin_scripts( $hook ) {
        $screen = get_current_screen();
        if ( $screen && ( 'shop_order' === $screen->post_type || 'woocommerce_page_wc-orders' === $screen->id ) ) {
            wp_enqueue_script( 'jquery-mask', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js', array( 'jquery' ), '1.14.16', true );
            wp_add_inline_script( 'jquery-mask', "
                jQuery(document).ready(function($){ 
                    function initWCAIMasks() {
                        $('.wcai-mask-cpf').mask('000.000.000-00'); 
                        $('.wcai-mask-date').mask('00/00/0000'); 
                    }
                    initWCAIMasks();
                    $( document ).on( 'woocommerce_post_init', function() { initWCAIMasks(); } );
                });
            ");
        }
    }

    // --- 2. HELPERS DE DATA E BADGE ---
    private function force_date_to_display( $date ) {
        if ( empty($date) || $date == '0000-00-00' || $date == '1970-01-01' ) return '';
        if ( strpos($date, '/') !== false ) return $date;
        $date_clean = explode(' ', $date)[0];
        if ( preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_clean) ) {
            $parts = explode('-', $date_clean);
            return $parts[2] . '/' . $parts[1] . '/' . $parts[0];
        }
        $ts = strtotime($date);
        return ( $ts && $ts > 0 ) ? date('d/m/Y', $ts) : '';
    }

    private function force_date_to_db( $date ) {
        if ( empty($date) ) return null;
        if ( preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ) return $date;
        if ( strpos($date, '/') !== false ) {
            $parts = explode('/', $date);
            if ( count($parts) === 3 ) return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
        return null;
    }

    private function participant_age( $date_string ) {
        if ( empty($date_string) || $date_string == '0000-00-00' ) return null;
        try {
            $calc_date = $this->force_date_to_db($date_string);
            if(!$calc_date) return null;
            return (new DateTime())->diff(new DateTime( $calc_date ))->y;
        } catch (Exception $e) { return null; }
    }

    private function get_age_badge_html( $date_string ) {
        $age = $this->participant_age( $date_string );
        if ( null === $age ) return '';

        $icon_adult = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>';
        $icon_child = '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="3"></circle><path d="M9 12h6"></path><path d="M12 21v-9"></path><path d="M8 21h8"></path><path d="M6 12v3a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-3"></path></svg>';

        if ( $age >= 18 ) { $color = '#2271b1'; $bg = '#eaf4fc'; $icon = $icon_adult; } 
        else { $color = '#d63638'; $bg = '#fbeaea'; $icon = $icon_child; }

        $style = "display: inline-flex; align-items: center; gap: 4px; font-size: 12px; font-weight: 600; color: {$color}; background: {$bg}; padding: 3px 8px; border-radius: 12px; border: 1px solid {$color}; margin-right: 8px; white-space: nowrap; flex-shrink: 0;";
        return sprintf('<span style="%s">%s %s</span>', $style, $icon, esc_html( $this->participant_age_label( $date_string ) ));
    }
}

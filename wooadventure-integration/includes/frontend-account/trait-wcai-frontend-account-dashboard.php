<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Painel "Minha conta": cartão "Próximo passo" e menu sem itens vazios.
trait WCAI_Frontend_Account_Dashboard {
    // "Downloads" só aparece para quem tem produto para baixar (o endpoint continua acessível pela URL).
    public function filter_account_menu_items( $items ) {
        if ( is_array( $items ) && isset( $items['downloads'] ) && function_exists( 'wc_get_customer_available_downloads' )
            && ! wc_get_customer_available_downloads( get_current_user_id() ) ) {
            unset( $items['downloads'] );
        }
        return $items;
    }

    // Pedido em aberto mais recente do cliente (pedido encerrado não tem próximo passo).
    private function next_open_order( $user_id ) {
        if ( ! $user_id || ! function_exists( 'wc_get_orders' ) ) return null;
        $orders = wc_get_orders( array(
            'customer_id' => $user_id,
            'status'      => array( 'pending', 'on-hold', 'processing' ),
            'orderby'     => 'date',
            'order'       => 'DESC',
            'limit'       => 1,
        ) );
        return ! empty( $orders ) ? reset( $orders ) : null;
    }

    private function terms_line( $state ) {
        if ( 'pending' === $state['state'] ) {
            return 1 === (int) $state['pending'] ? __( 'Falta 1 termo.', 'wooadventure-integration' ) : sprintf( __( 'Faltam %d termos.', 'wooadventure-integration' ), (int) $state['pending'] );
        }
        if ( 'signed' === $state['state'] ) return __( 'Todos os termos de responsabilidade estão assinados.', 'wooadventure-integration' );
        return __( 'Dados dos participantes incompletos.', 'wooadventure-integration' );
    }

    // Hook woocommerce_account_dashboard (menu clássico e conta em bloco usam a mesma ação). O CSS põe o cartão no topo.
    public function render_dashboard_next_step() {
        $order = $this->next_open_order( (int) get_current_user_id() );
        echo '<section class="wcai-next-card" aria-labelledby="wcai-next-card-title">';
        echo '<h2 id="wcai-next-card-title" class="wcai-extra-title">' . $this->title_icon_svg( 'steps' ) . '<span>' . esc_html__( 'Próximo passo', 'wooadventure-integration' ) . '</span></h2>';
        if ( ! $order ) {
            $shop = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' );
            echo '<p>' . esc_html__( 'Nenhum passeio em andamento.', 'wooadventure-integration' ) . '</p>';
            echo '<p><a class="wcai-sign-all" href="' . esc_url( $shop ) . '">' . esc_html__( 'Ver passeios', 'wooadventure-integration' ) . '</a></p></section>';
            return;
        }

        $participants = $this->get_participants_safe( $order );
        $state = $this->order_terms_state( $order, $participants );
        echo '<p class="wcai-next-card__order"><a href="' . esc_url( $order->get_view_order_url() ) . '">' . esc_html( sprintf( __( 'Pedido #%s', 'wooadventure-integration' ), $order->get_order_number() ) ) . '</a></p><ul>';
        foreach ( $this->order_departures( $order ) as $departure ) {
            if ( $departure['timestamp'] ) echo '<li>' . esc_html( sprintf( __( 'Saída: %s', 'wooadventure-integration' ), wp_date( 'd/m/Y H:i', $departure['timestamp'] ) ) ) . '</li>';
            if ( '' !== $departure['meeting_point'] ) echo '<li>' . esc_html( sprintf( __( 'Ponto de encontro: %s', 'wooadventure-integration' ), $departure['meeting_point'] ) ) . '</li>';
        }
        if ( ! empty( $participants ) ) echo '<li>' . esc_html( $this->terms_line( $state ) ) . '</li>';
        $phone = trim( (string) get_option( 'wcai_contact_phone', '' ) );
        $tel = preg_replace( '/[^0-9+]/', '', $phone );
        if ( '' !== $tel ) echo '<li>' . esc_html__( 'Dúvidas? Ligue:', 'wooadventure-integration' ) . ' <a href="' . esc_url( 'tel:' . $tel ) . '">' . esc_html( $phone ) . '</a></li>';
        echo '</ul>';
        $terms_url = $this->get_terms_url( $order );
        if ( $terms_url && $this->terms_cta_visible( $order, $participants, $state['pending'] ) ) {
            echo '<p>' . $this->terms_link_html( $terms_url, __( 'Assinar termos', 'wooadventure-integration' ), 'wcai-sign-all' ) . '</p>';
        }
        echo '</section>';
    }
}

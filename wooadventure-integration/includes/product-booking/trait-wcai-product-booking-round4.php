<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Rodada 4: classes de body, preço "por participante", barra de reserva fixa (celular) e estilos de cabeçalho/carrinho.
trait WCAI_Product_Booking_Round4 {

    private $in_booking_card = false;
    private $cta_label = null; // null = cartão de reserva não foi impresso nesta página.

    private function is_tour_single() {
        return function_exists( 'is_singular' ) && is_singular( 'product' ) && $this->is_target_product( get_queried_object_id() );
    }

    // Lógica pura (testável): só passeios ganham classe; sem foto nenhuma, a galeria some.
    public static function body_classes( array $classes, $is_tour, $has_image ) {
        if ( $is_tour ) {
            $classes[] = 'wcai-tour';

            if ( ! $has_image ) {
                $classes[] = 'wcai-no-image';
            }
        }

        return $classes;
    }

    public function filter_body_class( $classes ) {
        $is_tour = $this->is_tour_single();
        $has_image = true;

        if ( $is_tour ) {
            $id = get_queried_object_id();
            $product = wc_get_product( $id );
            $has_image = has_post_thumbnail( $id ) || ( $product && $product->get_gallery_image_ids() );
        }

        return self::body_classes( (array) $classes, $is_tour, $has_image );
    }

    // "por participante" ao lado do preço do título; só o produto principal da página do passeio.
    public function filter_price_html( $html, $product = null ) {
        if ( $this->in_booking_card || '' === (string) $html || ! is_object( $product ) || false !== strpos( (string) $html, 'wcai-price-unit' ) ) {
            return $html;
        }

        if ( ! $this->is_tour_single() || (int) get_queried_object_id() !== (int) $product->get_id() ) {
            return $html;
        }

        return $html . ' <span class="wcai-price-unit">' . esc_html__( 'por participante', 'wooadventure-integration' ) . '</span>';
    }

    // O cartão já imprime "por participante"; sem este desvio o texto repetiria.
    private function card_price_html( $product ) {
        $this->in_booking_card = true;
        $html = $product->get_price_html();
        $this->in_booking_card = false;

        return $html;
    }

    private function remember_cta_label( $product ) {
        $price = html_entity_decode( wp_strip_all_tags( wc_price( wc_get_price_to_display( $product ) ) ), ENT_QUOTES, 'UTF-8' );
        $this->cta_label = trim( $price . ' ' . __( 'por participante', 'wooadventure-integration' ) );
    }

    public static function sticky_cta_html( $label ) {
        return '<div class="wcai-sticky-cta" data-wcai-cta hidden><p data-wcai-cta-text aria-live="polite">' . esc_html( $label ) . '</p>'
            . '<button type="button" data-wcai-cta-button>' . esc_html__( 'Reservar', 'wooadventure-integration' ) . '</button></div>';
    }

    public function render_sticky_cta() {
        if ( null === $this->cta_label || ! $this->is_tour_single() ) {
            return;
        }

        echo self::sticky_cta_html( $this->cta_label ); // phpcs:ignore WordPress.Security.EscapeOutput -- escapado em sticky_cta_html().
    }

    // Cor de destaque da loja; o texto sobre ela vira preto ou branco pelo contraste.
    public static function accent_css( $hex ) {
        if ( ! preg_match( '/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $hex ) ) {
            return '';
        }

        $rgb = ltrim( $hex, '#' );
        $rgb = 3 === strlen( $rgb ) ? preg_replace( '/(.)/', '$1$1', $rgb ) : $rgb;
        $luma = ( 0.299 * hexdec( substr( $rgb, 0, 2 ) ) + 0.587 * hexdec( substr( $rgb, 2, 2 ) ) + 0.114 * hexdec( substr( $rgb, 4, 2 ) ) ) / 255;
        $contrast = $luma > 0.6 ? '#111' : '#fff';

        return '.wcai-product-booking,.wcai-sticky-cta{--wcai-accent:' . strtolower( $hex ) . ';--wcai-accent-contrast:' . $contrast . '}'
            . '.wcai-product-booking .single_add_to_cart_button{background:var(--wcai-accent);color:var(--wcai-accent-contrast)}';
    }

    public function enqueue_round4_assets() {
        // O seletor só casa inputs somente leitura; o mini-carrinho existe em todas as páginas, então não há escopo por página.
        wp_register_style( 'wcai-cart-readonly', false, array(), WCAI_VERSION );
        wp_enqueue_style( 'wcai-cart-readonly' );
        wp_add_inline_style( 'wcai-cart-readonly', self::cart_readonly_css() );

        if ( ! $this->is_tour_single() ) {
            return;
        }

        wp_register_style( 'wcai-product-booking-r4', false, array(), WCAI_VERSION );
        wp_enqueue_style( 'wcai-product-booking-r4' );
        wp_add_inline_style( 'wcai-product-booking-r4', self::round4_css() . self::accent_css( WCAI_Settings::get_accent_color() ) );

        wp_register_script( 'wcai-product-booking-cta', false, array(), WCAI_VERSION, true );
        wp_enqueue_script( 'wcai-product-booking-cta' );
        wp_add_inline_script( 'wcai-product-booking-cta', self::cta_js() );
    }
}

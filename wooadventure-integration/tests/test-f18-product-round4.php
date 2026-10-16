<?php
/**
 * F18 - Página do produto, rodada 4: cor de destaque (hex), rodapé transparente, linha única do horário escolhido,
 * "por participante" no preço, classe sem imagem e barra de reserva fixa. Layout real é verificação em navegador.
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

// Stubs de WordPress/WooCommerce (guardados: outros arquivos de teste definem os mesmos nomes).
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( '__' ) ) { function __( $t ) { return $t; } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $t ) { return htmlspecialchars( $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES, 'UTF-8' ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $n, $d = false ) { return array_key_exists( $n, $GLOBALS['wcai_test_options'] ) ? $GLOBALS['wcai_test_options'][ $n ] : $d; } }
if ( ! function_exists( 'is_singular' ) ) { function is_singular( $t = '' ) { return ! empty( $GLOBALS['wcai_f18']['singular'] ); } }
if ( ! function_exists( 'get_queried_object_id' ) ) { function get_queried_object_id() { return $GLOBALS['wcai_f18']['queried']; } }
if ( ! function_exists( 'has_post_thumbnail' ) ) { function has_post_thumbnail( $id ) { return ! empty( $GLOBALS['wcai_f18']['thumbnail'] ); } }
if ( ! function_exists( 'wc_get_product' ) ) { function wc_get_product( $id ) { return new F18_Product( $id, $GLOBALS['wcai_f18']['gallery'] ); } }
if ( ! function_exists( 'wc_price' ) ) { function wc_price( $v ) { return '<span class="amount"><bdi><span>R&#36;</span>&nbsp;' . number_format( $v, 2, ',', '.' ) . '</bdi></span>'; } }
if ( ! function_exists( 'wc_get_price_to_display' ) ) { function wc_get_price_to_display( $p ) { return 50; } }
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $t ) { return trim( strip_tags( $t ) ); } }

class F18_Product {
    private $id;
    private $gallery;
    public $booking;

    public function __construct( $id, $gallery = array() ) {
        $this->id = $id;
        $this->gallery = $gallery;
    }

    public function get_id() {
        return $this->id;
    }

    public function get_gallery_image_ids() {
        return $this->gallery;
    }

    public function get_price_html() {
        return $this->booking->filter_price_html( '<span>R$ 50,00</span>', $this );
    }
}

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-product-booking.php';

class Test_F18_Product_Round4 extends TestCase {

    protected function setUp(): void {
        $GLOBALS['wcai_test_options'] = array( 'wcai_product_ids' => '26' );
        $GLOBALS['wcai_f18'] = array( 'singular' => true, 'queried' => 26, 'thumbnail' => true, 'gallery' => array() );
    }

    private function settings() {
        return ( new ReflectionClass( 'WCAI_Settings' ) )->newInstanceWithoutConstructor();
    }

    private function booking() {
        return ( new ReflectionClass( 'WCAI_Product_Booking' ) )->newInstanceWithoutConstructor();
    }

    private function call( $object, $method, array $args = array() ) {
        $m = new ReflectionMethod( $object, $method );
        $m->setAccessible( true );

        return $m->invokeArgs( $object, $args );
    }

    private function source( $name ) {
        return file_get_contents( dirname( __DIR__ ) . '/includes/product-booking/trait-wcai-product-booking-' . $name . '.php' );
    }

    private function css() {
        $this->assertSame( 1, preg_match( "/wp_add_inline_style\(\s*'wcai-product-booking-inline',\s*'(.*)'\s*\);/s", $this->source( 'assets' ), $m ) );

        return $m[1];
    }

    private function js() {
        return $this->source( 'js-a' ) . $this->source( 'js-b' ) . $this->source( 'js-c' );
    }

    // --- cor de destaque -------------------------------------------------

    public function test_accent_sanitizer_accepts_hex_only() {
        $s = $this->settings();

        $this->assertSame( '#abc', $s->sanitize_accent_color( ' #ABC ' ) );
        $this->assertSame( '#1a56db', $s->sanitize_accent_color( '#1A56DB' ) );
        foreach ( array( '', 'red', '#12', '#12345', '#1234567', '#12345g', '1a56db', 'url(x)', '#fff;}body{x:y', '<script>#fff</script>' ) as $bad ) {
            $this->assertSame( '', $s->sanitize_accent_color( $bad ), $bad );
        }
    }

    public function test_accent_getter_defaults_to_empty_and_never_returns_unvalidated_values() {
        $this->assertSame( '', WCAI_Settings::get_accent_color() );

        $GLOBALS['wcai_test_options']['wcai_accent_color'] = '#0b6e4f';
        $this->assertSame( '#0b6e4f', WCAI_Settings::get_accent_color() );

        $GLOBALS['wcai_test_options']['wcai_accent_color'] = 'red;}x{';
        $this->assertSame( '', WCAI_Settings::get_accent_color() );
    }

    public function test_accent_setting_is_registered_and_listed_on_settings_page() {
        $root = dirname( __DIR__ ) . '/includes';

        $this->assertStringContainsString( "'wcai_accent_color'", file_get_contents( $root . '/class-settings.php' ) );
        $this->assertStringContainsString( 'name="wcai_accent_color"', file_get_contents( $root . '/settings/trait-wcai-settings-page.php' ) );
    }

    public function test_accent_css_prints_only_a_valid_color_and_picks_readable_text() {
        $this->assertSame( '', WCAI_Product_Booking::accent_css( '' ) );
        $this->assertSame( '', WCAI_Product_Booking::accent_css( 'red' ) );
        $this->assertSame( '', WCAI_Product_Booking::accent_css( '#fff;}' ) );

        $dark = WCAI_Product_Booking::accent_css( '#0b6e4f' );
        $this->assertStringContainsString( '--wcai-accent:#0b6e4f;--wcai-accent-contrast:#fff', $dark );

        $light = WCAI_Product_Booking::accent_css( '#FFEE00' );
        $this->assertStringContainsString( '--wcai-accent:#ffee00;--wcai-accent-contrast:#111', $light );
        $this->assertStringContainsString( '--wcai-accent-contrast:#fff', WCAI_Product_Booking::accent_css( '#000' ) );
    }

    public function test_default_custom_properties_live_on_the_booking_card() {
        $css = WCAI_Product_Booking::round4_css();

        $this->assertStringContainsString( '.wcai-product-booking,.wcai-sticky-cta{--wcai-accent:#111;--wcai-accent-contrast:#fff;--wcai-focus:#1a56db}', $css );
        $this->assertStringContainsString( 'var(--wcai-accent,#111)', $this->css() );
    }

    // --- CSS ---------------------------------------------------------------

    public function test_footer_is_transparent_without_double_inset_and_no_fixed_width() {
        $css = $this->css();

        $this->assertStringContainsString( '.wcai-booking-footer{margin-top:16px;padding:16px 0 0;border-top:1px solid #eee;background:transparent}', $css );
        $this->assertStringNotContainsString( '.wcai-booking-footer{padding:14px 18px 18px}', $css );
        $this->assertSame( 0, preg_match( '/\.wcai-booking-footer[^{]*\{[^}]*(?<![\w-])(?:min-|max-)?width:\s*\d+px/', $css ) );
        $this->assertSame( 0, preg_match( '/(?<![\w-])(?:min-)?width:\s*\d{3,}px/', $css ) );
    }

    public function test_slot_card_inherits_font_and_old_run_on_markup_is_gone() {
        $css = $this->css();

        $this->assertStringContainsString( '.wcai-time-option{font:inherit;font-size:14px;', $css );
        $this->assertStringNotContainsString( 'wcai-time-formation', $css . $this->js() );
        $this->assertStringContainsString( '.wcai-stepper button:disabled{opacity:.45;border-color:#bbb;cursor:not-allowed}', $css );
    }

    public function test_round4_css_has_hierarchy_rules_for_tour_pages_only() {
        $css = WCAI_Product_Booking::round4_css();

        $this->assertStringContainsString( 'body.wcai-tour .product_meta .posted_in', $css );
        $this->assertStringContainsString( 'body.wcai-no-image .woocommerce-product-gallery', $css );
        $this->assertStringContainsString( '.wcai-sticky-cta{display:none}', $css );
        $this->assertMatchesRegularExpression( '/@media\(max-width:700px\)\{body\.wcai-has-cta\{padding-bottom:/', $css );
        $this->assertStringContainsString( 'min-height:44px', $css );
        $this->assertStringContainsString( 'width:44px;height:44px', $css );
        // O estilo de cabeçalho nunca esconde a galeria de uma página sem a classe.
        $this->assertSame( 0, preg_match( '/(?:^|\})\.woocommerce-product-gallery\{display:none/', $css ) );
    }

    public function test_cart_quantity_that_cannot_be_edited_loses_the_input_look() {
        $css = WCAI_Product_Booking::cart_readonly_css();

        $this->assertStringContainsString( 'input[readonly].wc-block-components-quantity-selector__input{border:0;background:transparent;pointer-events:none;box-shadow:none}', $css );
    }

    // --- JS / microcopy ------------------------------------------------------

    public function test_selected_block_is_one_line_and_group_rule_has_a_single_home() {
        $js = $this->source( 'js-b' );
        preg_match( '/function renderSelected\(departure\) \{(.*?)\n        \}\n/s', $js, $m );

        $this->assertNotEmpty( $m );
        $this->assertSame( 1, substr_count( $m[1], "createElement('strong')" ) );
        $this->assertSame( 0, substr_count( $m[1], "createElement('p')" ) );
        $this->assertStringContainsString( 'i18n.selectedTime', $m[1] );
        $this->assertSame( 1, substr_count( $js, 'fmt(i18n.groupRule,' ) );
        $this->assertSame( '09:00 selecionado', sprintf( WCAI_Product_Booking::booking_i18n()['selectedTime'], '09:00' ) );
    }

    public function test_microcopy_and_second_line_for_secondary_slot_info() {
        $js = $this->js();
        $i18n = WCAI_Product_Booking::booking_i18n();

        $this->assertStringContainsString( "'faltam ' + forming.remaining_to_minimum + ' para confirmar'", $js );
        $this->assertStringContainsString( "'precisa de ' + withMinimum.minimum", $js );
        $this->assertStringNotContainsString( "'mín. '", $js );
        $this->assertSame( 'Grupo em formação · faltam 5 para confirmar', sprintf( $i18n['forming'], 5 ) );
        $this->assertStringContainsString( "cutoff.textContent = 'Reserva até '", $js );
        $this->assertStringContainsString( "parts.join(' · ')", $js );
        $this->assertStringContainsString( 'Grupo em formação', $this->source( 'departure' ) );
    }

    public function test_disabled_steppers_point_to_the_visible_reason() {
        $js = $this->js();
        $php = $this->source( 'departure' );

        $this->assertStringContainsString( "setAttribute('aria-describedby', reason.id)", $js );
        $this->assertStringContainsString( 'describeDisabled(adultPlus, limitEl)', $js );
        $this->assertStringContainsString( 'id="wcai-participant-limit" data-participant-limit', $php );
        $this->assertStringContainsString( 'id="wcai-child-reason" data-participant-reason', $php );
    }

    public function test_carousel_arrows_are_44px_buttons_with_labels() {
        $php = $this->source( 'departure' );

        $this->assertStringContainsString( '<button type="button" class="wcai-quick-arrow is-prev"', $php );
        $this->assertStringContainsString( 'aria-label="Próximas datas"', $php );
        $this->assertStringContainsString( 'quickDates.scrollBy(', $this->js() );
        $this->assertStringNotContainsString( 'has-next::after', $this->css() );
    }

    public function test_every_generated_script_is_valid_javascript() {
        $node = trim( (string) shell_exec( 'node --version 2>&1' ) );
        if ( 0 !== strpos( $node, 'v' ) ) {
            $this->markTestSkipped( 'node indisponível' );
        }

        $obj = $this->booking();
        $scripts = array( 'calendar' => '' );
        foreach ( array( 'calendar_js_a', 'calendar_js_b', 'calendar_js_c' ) as $method ) {
            $scripts['calendar'] .= $this->call( $obj, $method ) . "\n";
        }
        $scripts['cta'] = WCAI_Product_Booking::cta_js();

        foreach ( $scripts as $name => $js ) {
            $file = tempnam( sys_get_temp_dir(), 'wcai' ) . '.js';
            file_put_contents( $file, $js );
            $out = shell_exec( 'node --check ' . escapeshellarg( $file ) . ' 2>&1' );
            unlink( $file );

            $this->assertSame( '', trim( (string) $out ), $name . ': ' . $out );
        }
    }

    // --- preço, classes do body e barra fixa ---------------------------------

    public function test_price_html_gets_unit_only_on_the_tour_product_single_page() {
        $b = $this->booking();

        $this->assertSame( '<b>R$ 50</b> <span class="wcai-price-unit">por participante</span>', $b->filter_price_html( '<b>R$ 50</b>', new F18_Product( 26 ) ) );

        // Já tem a unidade: não repete.
        $once = $b->filter_price_html( '<b>R$ 50</b>', new F18_Product( 26 ) );
        $this->assertSame( $once, $b->filter_price_html( $once, new F18_Product( 26 ) ) );

        // Outro produto na mesma página (relacionados), produto que não é passeio, fora da página única, sem preço.
        $this->assertSame( '<b>1</b>', $b->filter_price_html( '<b>1</b>', new F18_Product( 99 ) ) );
        $GLOBALS['wcai_f18']['queried'] = 99;
        $this->assertSame( '<b>1</b>', $b->filter_price_html( '<b>1</b>', new F18_Product( 99 ) ) );
        $GLOBALS['wcai_f18']['queried'] = 26;
        $GLOBALS['wcai_f18']['singular'] = false;
        $this->assertSame( '<b>1</b>', $b->filter_price_html( '<b>1</b>', new F18_Product( 26 ) ) );
        $GLOBALS['wcai_f18']['singular'] = true;
        $this->assertSame( '', $b->filter_price_html( '', new F18_Product( 26 ) ) );
    }

    public function test_booking_card_price_does_not_repeat_the_unit() {
        $b = $this->booking();
        $product = new F18_Product( 26 );
        $product->booking = $b;

        $this->assertSame( '<span>R$ 50,00</span>', $this->call( $b, 'card_price_html', array( $product ) ) );
        $this->assertStringContainsString( 'wcai-price-unit', $b->filter_price_html( '<span>R$ 50,00</span>', $product ) );
    }

    public function test_no_image_class_only_for_tour_products_without_any_image() {
        $this->assertSame( array( 'a' ), WCAI_Product_Booking::body_classes( array( 'a' ), false, false ) );
        $this->assertSame( array( 'a', 'wcai-tour' ), WCAI_Product_Booking::body_classes( array( 'a' ), true, true ) );
        $this->assertSame( array( 'a', 'wcai-tour', 'wcai-no-image' ), WCAI_Product_Booking::body_classes( array( 'a' ), true, false ) );

        $b = $this->booking();
        $GLOBALS['wcai_f18']['thumbnail'] = false;
        $this->assertContains( 'wcai-no-image', $b->filter_body_class( array() ) );

        // Galeria sem foto destacada: a galeria real não pode sumir.
        $GLOBALS['wcai_f18']['gallery'] = array( 5 );
        $this->assertNotContains( 'wcai-no-image', $b->filter_body_class( array() ) );

        $GLOBALS['wcai_f18']['gallery'] = array();
        $GLOBALS['wcai_f18']['queried'] = 99;
        $this->assertSame( array(), $b->filter_body_class( array() ) );
    }

    public function test_sticky_cta_prints_only_on_tour_single_pages_that_have_the_card() {
        $b = $this->booking();
        $print = function () use ( $b ) {
            ob_start();
            $b->render_sticky_cta();

            return ob_get_clean();
        };

        // Sem cartão de reserva impresso: nada.
        $this->assertSame( '', $print() );

        $product = new F18_Product( 26 );
        $this->call( $b, 'remember_cta_label', array( $product ) );
        $html = $print();

        $this->assertStringContainsString( 'data-wcai-cta hidden', $html );
        $this->assertStringContainsString( "R\$\u{a0}50,00 por participante", $html );
        $this->assertStringContainsString( '<button type="button" data-wcai-cta-button>Reservar</button>', $html );

        $GLOBALS['wcai_f18']['queried'] = 99;
        $this->assertSame( '', $print() );
        $GLOBALS['wcai_f18']['queried'] = 26;
        $GLOBALS['wcai_f18']['singular'] = false;
        $this->assertSame( '', $print() );
    }

    public function test_sticky_cta_escapes_its_label() {
        $this->assertStringNotContainsString( '<img', WCAI_Product_Booking::sticky_cta_html( '<img src=x>' ) );
    }

    public function test_round4_hooks_are_wired_and_files_stay_small() {
        $main = file_get_contents( dirname( __DIR__ ) . '/includes/class-product-booking.php' );

        foreach ( array( "'body_class'", "'woocommerce_get_price_html'", "'wp_enqueue_scripts'", "'wp_footer'" ) as $hook ) {
            $this->assertStringContainsString( $hook, $main );
        }

        foreach ( glob( dirname( __DIR__ ) . '/includes/product-booking/*.php' ) as $file ) {
            $this->assertLessThan( 350, count( file( $file ) ), basename( $file ) );
        }
    }
}

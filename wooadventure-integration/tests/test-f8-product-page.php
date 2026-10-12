<?php
/**
 * F8 - Página do produto: filtro de quantidade do bloco do carrinho, legenda de
 * disponibilidade e checagens estáticas do CSS/JS inline (sem largura fixa acima do contêiner).
 * Layout real (overflow, toque 44px) é verificação manual em navegador.
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'absint' ) ) {
    function absint( $value ) {
        return abs( (int) $value );
    }
}

if ( ! class_exists( 'WCAI_Settings' ) ) {
    class WCAI_Settings {
        public static function get_product_ids() {
            return array( 10, 20 );
        }
    }
}

require_once dirname( __DIR__ ) . '/includes/class-product-booking.php';

class Test_F8_Product_Page extends TestCase {

    private function booking() {
        return ( new ReflectionClass( 'WCAI_Product_Booking' ) )->newInstanceWithoutConstructor();
    }

    private function product( $id, $parent = 0 ) {
        return new class( $id, $parent ) {
            private $id;
            private $parent;
            public function __construct( $id, $parent ) {
                $this->id = $id;
                $this->parent = $parent;
            }
            public function get_id() {
                return $this->id;
            }
            public function get_parent_id() {
                return $this->parent;
            }
        };
    }

    private function trait_source( $name ) {
        return file_get_contents( dirname( __DIR__ ) . '/includes/product-booking/trait-wcai-product-booking-' . $name . '.php' );
    }

    private function css() {
        $src = $this->trait_source( 'assets' );
        $this->assertSame( 1, preg_match( "/wp_add_inline_style\(\s*'wcai-product-booking-inline',\s*'(.*)'\s*\);/s", $src, $m ) );

        return $m[1];
    }

    public function test_cart_block_quantity_is_not_editable_for_tour_products() {
        $this->assertFalse( $this->booking()->filter_store_api_quantity_editable( true, $this->product( 10 ), array() ) );
    }

    public function test_cart_block_quantity_is_not_editable_for_tour_variation() {
        $this->assertFalse( $this->booking()->filter_store_api_quantity_editable( true, $this->product( 99, 20 ), array() ) );
    }

    public function test_cart_block_quantity_stays_editable_for_other_products() {
        $this->assertTrue( $this->booking()->filter_store_api_quantity_editable( true, $this->product( 55 ), array() ) );
        $this->assertTrue( $this->booking()->filter_store_api_quantity_editable( true, null, null ) );
    }

    public function test_quantity_filter_is_registered_and_sold_individually_is_not_used() {
        $src = file_get_contents( dirname( __DIR__ ) . '/includes/class-product-booking.php' );

        $this->assertStringContainsString( "'woocommerce_store_api_product_quantity_editable'", $src );
        $this->assertStringNotContainsString( 'woocommerce_is_sold_individually', $src );
    }

    public function test_availability_legend_mentions_minimum_only_when_a_group_minimum_exists() {
        $without = WCAI_Product_Booking::availability_legend( false );
        $with = WCAI_Product_Booking::availability_legend( true );

        $this->assertStringContainsString( 'Vagas livres', $without );
        $this->assertStringNotContainsString( 'mín.', $without );
        $this->assertStringContainsString( 'mín.', $with );
    }

    public function test_inline_css_has_no_fixed_width_above_a_phone_container() {
        $css = $this->css();

        $this->assertStringNotContainsString( 'width:768px', $css );
        // Nenhum width/min-width fixo de 3+ dígitos em px (o carrossel de datas usa 96px dentro de um contêiner rolável).
        $this->assertSame( 0, preg_match( '/(?<![\w-])(?:min-)?width:\s*\d{3,}px/', $css ) );
        $this->assertSame( 0, preg_match( '/grid-template-columns:[^;}]*\b\d{3,}px/', $css ) );
        $this->assertStringNotContainsString( 'calc(100% - 48px)', $css );
    }

    public function test_booking_card_and_scroller_are_bound_to_the_container() {
        $css = $this->css();

        $this->assertMatchesRegularExpression( '/\.wcai-product-booking\{box-sizing:border-box;[^}]*width:100%;max-width:100%;min-width:0/', $css );
        $this->assertMatchesRegularExpression( '/\.wcai-quick-dates\{width:100%;max-width:100%;min-width:0/', $css );
    }

    public function test_add_to_cart_button_is_full_width_single_line() {
        $css = $this->css();

        $this->assertMatchesRegularExpression( '/form\.cart \.single_add_to_cart_button\{[^}]*width:100%[^}]*white-space:nowrap/', $css );
        $this->assertStringContainsString( "insertAdjacentElement('beforebegin', cartHint)", $this->trait_source( 'js-c' ) );
        $this->assertStringContainsString( "'aria-describedby', 'wcai-cart-hint'", $this->trait_source( 'js-c' ) );
    }

    public function test_redundant_copy_is_removed() {
        $php = $this->trait_source( 'departure' );

        $this->assertStringNotContainsString( 'Você escolhe em 3 momentos', $php );
        $this->assertStringNotContainsString( 'wcai-booking-note', $php );
        $this->assertStringNotContainsString( 'wcai-booking-context', $php );
        $this->assertStringNotContainsString( 'wcai-booking-context', $this->css() );
    }

    public function test_date_chip_shows_free_seats_not_reserved_over_minimum() {
        $js = $this->trait_source( 'js-a' );

        $this->assertStringNotContainsString( "forming.reserved + '/' + forming.minimum", $js );
        $this->assertStringContainsString( "'mín. ' + forming.minimum + ' · faltam ' + forming.remaining_to_minimum", $js );
        $this->assertStringContainsString( "label: 'Esgotado'", $js );
    }

    public function test_rerender_keeps_participant_picker_visible_when_departure_is_selected() {
        $js = $this->trait_source( 'js-b' );

        $this->assertStringContainsString( 'participantRoot.hidden = !selectedDepartureId;', $js );
    }

    public function test_generated_calendar_script_is_valid_javascript() {
        $node = trim( (string) shell_exec( 'node --version 2>&1' ) );
        if ( 0 !== strpos( $node, 'v' ) ) {
            $this->markTestSkipped( 'node indisponível' );
        }

        $obj = $this->booking();
        $ref = new ReflectionClass( $obj );
        $js = '';
        foreach ( array( 'calendar_js_a', 'calendar_js_b', 'calendar_js_c' ) as $method ) {
            $m = $ref->getMethod( $method );
            $m->setAccessible( true );
            $js .= $m->invoke( $obj ) . "\n";
        }

        $file = tempnam( sys_get_temp_dir(), 'wcai' ) . '.js';
        file_put_contents( $file, $js );
        $out = shell_exec( 'node --check ' . escapeshellarg( $file ) . ' 2>&1' );
        unlink( $file );

        $this->assertSame( '', trim( (string) $out ), (string) $out );
    }
}

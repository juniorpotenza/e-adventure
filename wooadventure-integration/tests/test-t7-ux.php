<?php
/**
 * T7 - Passeio, conta e agenda: limite de quantity (funcional) e checagens de
 * código-fonte para SRI, modal só na conta e URL do termo vinda das Configurações.
 * Acessibilidade e layout (foco, setas, contraste) são verificação manual.
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-product-booking.php';

class Test_T7_Ux extends TestCase {

    private function source( $file ) {
        $dir = dirname( __DIR__ ) . '/includes';
        $src = file_get_contents( $dir . '/' . $file );
        // Traits live in includes/<class-name-without-prefix>/trait-*.php.
        foreach ( glob( $dir . '/' . substr( basename( $file, '.php' ), 6 ) . '/trait-*.php' ) ?: array() as $trait ) {
            $src .= file_get_contents( $trait );
        }
        return $src;
    }

    public function test_clamp_quantity_keeps_value_inside_one_and_max() {
        $max = WCAI_Product_Booking::MAX_AJAX_QUANTITY;

        $this->assertSame( 1, WCAI_Product_Booking::clamp_quantity( '1' ) );
        $this->assertSame( 7, WCAI_Product_Booking::clamp_quantity( '7' ) );
        $this->assertSame( $max, WCAI_Product_Booking::clamp_quantity( (string) $max ) );
        $this->assertSame( $max, WCAI_Product_Booking::clamp_quantity( '99999999' ) );
        $this->assertSame( $max, WCAI_Product_Booking::clamp_quantity( (string) PHP_INT_MAX ) );
    }

    public function test_clamp_quantity_rejects_non_positive_and_non_numeric_input() {
        foreach ( array( '0', '-5', '', 'abc', null, array() ) as $value ) {
            $this->assertSame( 1, WCAI_Product_Booking::clamp_quantity( $value ), var_export( $value, true ) );
        }
    }

    public function test_availability_endpoint_clamps_quantity_and_caches_departure_ids() {
        $src = $this->source( 'class-product-booking.php' );

        $this->assertStringContainsString( 'self::clamp_quantity( wp_unslash( $_POST[\'quantity\'] ) )', $src );
        $this->assertStringContainsString( 'set_transient( $cache_key, $departure_ids', $src );
    }

    public function test_loading_selector_targets_the_date_picker() {
        $src = $this->source( 'class-product-booking.php' );

        $this->assertStringContainsString( '.wcai-date-picker.is-loading', $src );
        $this->assertStringNotContainsString( '.wcai-calendar.is-loading', $src );
    }

    public function test_fullcalendar_assets_are_pinned_with_sri() {
        foreach ( WCAI_Agenda_Source::sri() as $handle => $hash ) {
            $this->assertMatchesRegularExpression( '/^sha384-[A-Za-z0-9+\/]{64}$/', $hash, $handle );
        }

        $this->assertSame( 3, substr_count( $this->source( 'class-agenda.php' ), 'fullcalendar@5.11.3/' ) );
    }

    public function test_account_modal_assets_load_only_on_account_page() {
        $src = $this->source( 'class-frontend-account.php' );
        $start = strpos( $src, 'public function modal_assets' );
        $body = substr( $src, $start, 200 );

        $this->assertMatchesRegularExpression( '/if \( ! is_account_page\(\) \) \{\s+return;/', $body );
        $this->assertStringContainsString( '.fail(function()', $src );
    }

    public function test_terms_url_comes_from_settings_not_hardcoded() {
        $this->assertStringNotContainsString( 'cachoeiradosciganos.com.br', $this->source( 'class-frontend-account.php' ) );
        $this->assertStringContainsString( "'wcai_terms_url'", $this->source( 'class-settings.php' ) );
        $this->assertStringContainsString( "'wcai_operation_name'", $this->source( 'class-settings.php' ) );
    }

    public function test_modal_notice_uses_css_classes_not_inline_colour() {
        $src = $this->source( 'class-frontend-account.php' );

        $this->assertStringContainsString( 'class="wcai-modal-notice wcai-modal-notice--', $src );

        $use = strpos( $src, '$msg_color' );
        if ( false !== $use ) {
            $define = strpos( $src, '$msg_color =' );
            $this->assertNotFalse( $define );
            $this->assertLessThan( $use, $define );
        }
    }
}

/** Lê as constantes de SRI do fonte do Agenda sem carregar a classe (ela depende do WordPress). */
class WCAI_Agenda_Source {
    public static function sri() {
        $src = file_get_contents( dirname( __DIR__ ) . '/includes/class-agenda.php' );
        preg_match_all( "/'(fullcalendar-[a-z]+)'\s*=>\s*'(sha384-[^']+)'/", $src, $m, PREG_SET_ORDER );

        $out = array();
        foreach ( $m as $row ) {
            $out[ $row[1] ] = $row[2];
        }

        return $out;
    }
}

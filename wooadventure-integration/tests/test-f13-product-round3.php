<?php
/**
 * F13 - Página do produto, rodada 3: opções de política/telefone, linhas de "Informações da reserva",
 * rótulos de duração, textos da dica de compra e checagens estáticas do CSS/JS.
 * Layout real (overflow, foco, carrossel) é verificação em navegador.
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

if ( ! function_exists( '__' ) ) {
    function __( $text ) {
        return $text;
    }
}

if ( ! function_exists( 'get_option' ) ) {
    function get_option( $name, $default = false ) {
        return array_key_exists( $name, $GLOBALS['wcai_test_options'] ) ? $GLOBALS['wcai_test_options'][ $name ] : $default;
    }
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
    function sanitize_text_field( $value ) {
        return trim( strip_tags( (string) $value ) );
    }
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
    function sanitize_textarea_field( $value ) {
        return trim( strip_tags( (string) $value ) );
    }
}

require_once dirname( __DIR__ ) . '/includes/class-settings.php';
require_once dirname( __DIR__ ) . '/includes/class-product-booking.php';

class Test_F13_Product_Round3 extends TestCase {

    protected function setUp(): void {
        $GLOBALS['wcai_test_options'] = array();
    }

    private function settings() {
        return ( new ReflectionClass( 'WCAI_Settings' ) )->newInstanceWithoutConstructor();
    }

    private function trait_source( $name ) {
        return file_get_contents( dirname( __DIR__ ) . '/includes/product-booking/trait-wcai-product-booking-' . $name . '.php' );
    }

    private function css() {
        $this->assertSame( 1, preg_match( "/wp_add_inline_style\(\s*'wcai-product-booking-inline',\s*'(.*)'\s*\);/s", $this->trait_source( 'assets' ), $m ) );

        return $m[1];
    }

    private function js() {
        return $this->trait_source( 'js-a' ) . $this->trait_source( 'js-b' ) . $this->trait_source( 'js-c' );
    }

    public function test_new_settings_default_to_empty() {
        $this->assertSame( '', WCAI_Settings::get_cancellation_policy() );
        $this->assertSame( '', WCAI_Settings::get_contact_phone() );
    }

    public function test_settings_getters_return_saved_values() {
        $GLOBALS['wcai_test_options']['wcai_cancellation_policy'] = 'Texto da política';
        $GLOBALS['wcai_test_options']['wcai_contact_phone'] = '+55 11 99999-9999';

        $this->assertSame( 'Texto da política', WCAI_Settings::get_cancellation_policy() );
        $this->assertSame( '+55 11 99999-9999', WCAI_Settings::get_contact_phone() );
    }

    public function test_policy_sanitizer_strips_tags_and_limits_length() {
        $this->assertSame( 'alert(1) ok', $this->settings()->sanitize_cancellation_policy( '<script>alert(1)</script> ok' ) );
        $this->assertSame( 1000, mb_strlen( $this->settings()->sanitize_cancellation_policy( str_repeat( 'á', 1500 ) ) ) );
        $this->assertSame( '', $this->settings()->sanitize_cancellation_policy( '' ) );
    }

    public function test_phone_sanitizer_keeps_only_phone_characters() {
        $this->assertSame( '+55 (11) 99999-9999', $this->settings()->sanitize_contact_phone( ' +55 (11) 99999-9999 ' ) );
        $this->assertSame( '11 9999', $this->settings()->sanitize_contact_phone( '11 <b>9999</b>x;' ) );
        $this->assertSame( 30, strlen( $this->settings()->sanitize_contact_phone( str_repeat( '1', 80 ) ) ) );
    }

    public function test_settings_are_registered_and_listed_on_the_settings_page() {
        $root = dirname( __DIR__ ) . '/includes';

        $this->assertStringContainsString( "'wcai_cancellation_policy'", file_get_contents( $root . '/class-settings.php' ) );
        $this->assertStringContainsString( "'wcai_contact_phone'", file_get_contents( $root . '/class-settings.php' ) );
        $this->assertStringContainsString( 'name="wcai_cancellation_policy"', file_get_contents( $root . '/settings/trait-wcai-settings-page.php' ) );
        $this->assertStringContainsString( 'name="wcai_contact_phone"', file_get_contents( $root . '/settings/trait-wcai-settings-page.php' ) );
    }

    public function test_duration_label_formats_hours_and_minutes() {
        $this->assertSame( '', WCAI_Product_Booking::duration_label( 0 ) );
        $this->assertSame( '45 min', WCAI_Product_Booking::duration_label( 45 ) );
        $this->assertSame( '1 h', WCAI_Product_Booking::duration_label( 60 ) );
        $this->assertSame( '2 h', WCAI_Product_Booking::duration_label( 120 ) );
        $this->assertSame( '1 h 30 min', WCAI_Product_Booking::duration_label( 90 ) );
    }

    public function test_trust_rows_show_nothing_without_real_data() {
        $departures = array(
            array( 'duration' => 0, 'meeting_point' => '' ),
            array( 'duration' => 0, 'meeting_point' => '' ),
        );

        $this->assertSame( array(), WCAI_Product_Booking::trust_rows( $departures, '', '' ) );
        $this->assertSame( array(), WCAI_Product_Booking::trust_rows( array(), '', '' ) );
    }

    public function test_trust_rows_use_duration_and_meeting_point_only_when_all_departures_agree() {
        $same = array(
            array( 'duration' => 120, 'meeting_point' => 'Campo Base' ),
            array( 'duration' => 120, 'meeting_point' => 'Campo Base' ),
        );
        $rows = WCAI_Product_Booking::trust_rows( $same, '', '' );

        $this->assertSame( array( 'duration', 'meeting' ), array_column( $rows, 'key' ) );
        $this->assertSame( '2 h', $rows[0]['value'] );
        $this->assertSame( 'Campo Base', $rows[1]['value'] );

        // Um passeio sem ponto de encontro (ou com outra duração) não pode herdar o valor do vizinho.
        $mixed = array(
            array( 'duration' => 120, 'meeting_point' => 'Campo Base' ),
            array( 'duration' => 90, 'meeting_point' => '' ),
        );

        $this->assertSame( array(), WCAI_Product_Booking::trust_rows( $mixed, '', '' ) );
    }

    public function test_trust_rows_show_policy_and_phone_only_when_set() {
        $departures = array( array( 'duration' => 0, 'meeting_point' => '' ) );

        $this->assertSame( array( 'policy' ), array_column( WCAI_Product_Booking::trust_rows( $departures, ' Política X ', '' ), 'key' ) );
        $this->assertSame( array( 'phone' ), array_column( WCAI_Product_Booking::trust_rows( $departures, '   ', '+55 11 1234' ), 'key' ) );
        $this->assertSame( 'Política X', WCAI_Product_Booking::trust_rows( $departures, ' Política X ', '' )[0]['value'] );
    }

    public function test_stateful_hint_and_copy_strings() {
        $i18n = WCAI_Product_Booking::booking_i18n();

        $this->assertSame( 'Escolha uma data', $i18n['hintDate'] );
        $this->assertSame( 'Escolha um horário', $i18n['hintTime'] );
        $this->assertSame( 'Pronto: 2 participantes, R$ 100,00', sprintf( $i18n['hintReady'], '2 participantes, R$ 100,00' ) );
        $this->assertSame( '1 horário disponível em dom', sprintf( $i18n['timesOne'], 1, 'dom' ) );
        $this->assertSame( '3 horários disponíveis em dom', sprintf( $i18n['timesMany'], 3, 'dom' ) );
        $this->assertSame( 'Vagas restantes depois da sua reserva: 4', sprintf( $i18n['remaining'], 4 ) );
        $this->assertSame( 'Máximo 7 (vagas restantes)', sprintf( $i18n['limit'], 7 ) );
        $this->assertStringContainsString( 'O passeio só acontece se o grupo mínimo (5) for atingido até 18/10/2026 07:00.', sprintf( $i18n['groupRule'], 5, '18/10/2026 07:00' ) );
        $this->assertStringNotContainsString( '(s)', implode( ' ', $i18n ) );
    }

    public function test_old_repeated_copy_is_gone_from_php_and_js() {
        $all = $this->trait_source( 'departure' ) . $this->trait_source( 'assets' ) . $this->js();

        $this->assertStringNotContainsString( 'Escolha data e horário', $all );
        $this->assertStringNotContainsString( 'Capacidade calculada', $all );
        $this->assertStringNotContainsString( 'horário(s)', $all );
        $this->assertStringNotContainsString( 'saída registrada', $all );
        $this->assertStringNotContainsString( 'vaga(s)', $all );
    }

    public function test_layout_fix_overrides_block_theme_grid_without_fixed_widths() {
        $css = $this->css();

        $this->assertStringContainsString( 'form.cart:has(.wcai-product-booking){grid-template-columns:minmax(0,1fr)!important', $css );
        $this->assertStringContainsString( '.wp-block-columns:has(.wcai-product-booking){flex-wrap:wrap!important}', $css );
        $this->assertStringContainsString( 'grid-auto-columns:minmax(108px,1fr)', $css );
        $this->assertStringContainsString( 'scroll-snap-type:x mandatory', $css );
        $this->assertSame( 0, preg_match( '/(?<![\w-])(?:min-)?width:\s*\d{3,}px/', $css ) );
        $this->assertSame( 0, preg_match( '/grid-template-columns:[^;}]*\b\d{3,}px/', $css ) );
    }

    public function test_css_inherits_font_has_distinct_focus_ring_and_dim_disabled_stepper() {
        $css = $this->css();

        $this->assertStringContainsString( '.wcai-product-booking button,.wcai-product-booking input,.wcai-product-booking select{font-family:inherit}', $css );
        $this->assertStringContainsString( 'outline:3px solid var(--wcai-focus,#1a56db);outline-offset:2px', $css );
        $this->assertMatchesRegularExpression( '/\.wcai-stepper button:disabled\{opacity:\.45;border-color:#bbb;cursor:not-allowed\}/', $css );
    }

    public function test_css_has_no_text_below_thirteen_pixels() {
        preg_match_all( '/font-size:\s*(\d+(?:\.\d+)?)px/', $this->css(), $m );

        $this->assertNotEmpty( $m[1] );
        $this->assertGreaterThanOrEqual( 13.0, (float) min( $m[1] ) );
    }

    public function test_js_has_radio_chips_roving_focus_debounce_and_stale_guard() {
        $js = $this->js();

        $this->assertStringContainsString( "setAttribute('role', 'radio')", $js );
        $this->assertStringContainsString( "'aria-checked'", $js );
        foreach ( array( 'ArrowLeft', 'ArrowRight', 'Home', 'End' ) as $key ) {
            $this->assertStringContainsString( "'" . $key . "'", $js );
        }
        $this->assertStringContainsString( 'var REFRESH_DELAY = 250;', $js );
        $this->assertStringContainsString( 'if (seq !== requestSeq) return;', $js );
        $this->assertStringContainsString( "setAttribute('tabindex', '-1')", $js );
        $this->assertStringContainsString( "footer.appendChild(buttons[0])", $js );
    }

    public function test_markup_has_live_total_footer_and_trust_block() {
        $php = $this->trait_source( 'departure' );

        $this->assertStringContainsString( 'data-participant-total aria-live="polite"', $php );
        $this->assertStringContainsString( 'data-wcai-footer', $php );
        $this->assertStringContainsString( 'role="radiogroup"', $php );
        $this->assertStringContainsString( '<span>2</span>', $php );
    }

    public function test_generated_calendar_script_is_valid_javascript() {
        $node = trim( (string) shell_exec( 'node --version 2>&1' ) );
        if ( 0 !== strpos( $node, 'v' ) ) {
            $this->markTestSkipped( 'node indisponível' );
        }

        $obj = ( new ReflectionClass( 'WCAI_Product_Booking' ) )->newInstanceWithoutConstructor();
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

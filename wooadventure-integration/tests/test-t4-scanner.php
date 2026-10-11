<?php
/**
 * T4 - Scanner: checagens do lado PHP (sem WordPress carregado).
 * O JS (escape de HTML) é verificado pela inspeção do código-fonte da classe.
 */

use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-scanner.php';

class Test_T4_Scanner extends TestCase {

    private function source() {
        $dir = dirname( __DIR__ ) . '/includes';
        $src = file_get_contents( $dir . '/class-scanner.php' );
        foreach ( glob( $dir . '/scanner/trait-*.php' ) as $trait ) {
            $src .= file_get_contents( $trait );
        }
        return $src;
    }

    public function test_date_query_accepts_only_today_plus_minus_one_day() {
        foreach ( array( '2026-10-09', '2026-10-10', '2026-10-11' ) as $date ) {
            $this->assertTrue( WCAI_Scanner::is_date_allowed( $date, '2026-10-10' ), $date );
        }

        foreach ( array( '2026-10-08', '2026-10-12', '2026-09-10', '2025-10-10' ) as $date ) {
            $this->assertFalse( WCAI_Scanner::is_date_allowed( $date, '2026-10-10' ), $date );
        }
    }

    public function test_date_query_range_crosses_month_boundary() {
        $this->assertTrue( WCAI_Scanner::is_date_allowed( '2026-11-01', '2026-10-31' ) );
        $this->assertTrue( WCAI_Scanner::is_date_allowed( '2026-09-30', '2026-10-01' ) );
        $this->assertFalse( WCAI_Scanner::is_date_allowed( '2026-11-02', '2026-10-31' ) );
    }

    public function test_date_query_rejects_malformed_dates() {
        foreach ( array( '', 'abc', '2026-1-1', '2026-02-30', "2026-10-10' OR 1=1", '2026-10-10 ', '2026-13-01' ) as $date ) {
            $this->assertFalse( WCAI_Scanner::is_date_allowed( $date, '2026-10-10' ), var_export( $date, true ) );
        }
    }

    public function test_date_query_requires_view_manifest_before_returning_data() {
        $this->assertMatchesRegularExpression(
            '/if \( \$date_query \) \{\s+if \( ! current_user_can\( WCAI_Capabilities::VIEW_MANIFEST \) \|\| ! self::is_date_allowed/',
            $this->source()
        );
    }

    public function test_manifest_lists_only_confirmed_reservations_and_never_sends_cpf() {
        $src = $this->source();

        $this->assertStringNotContainsString( "'pending', 'confirmed'", $src );
        $this->assertStringNotContainsString( 'p.cpf', $src );
        $this->assertSame( 2, substr_count( $src, "r.status = 'confirmed'" ) );
        $this->assertSame( 2, substr_count( $src, '{$guide_join}' ) );
    }

    public function test_agenda_details_escape_user_data() {
        $src = $this->source();
        $start = strpos( $src, 'function loadAgendaDetails' );
        $end = strpos( $src, 'function wcaiBeep' );
        $block = substr( $src, $start, $end - $start );

        $this->assertStringContainsString( 'wcaiEscapeHtml(p.nome)', $block );
        $this->assertStringContainsString( 'wcaiEscapeHtml(p.pedido)', $block );
        $this->assertStringContainsString( 'wcaiEscapeHtml(p.tour_time)', $block );
        $this->assertStringNotContainsString( '${p.nome}', $block );
        $this->assertStringNotContainsString( '${p.pedido}', $block );
        $this->assertStringNotContainsString( '${p.tour_time}', $block );
    }

    public function test_third_party_scripts_are_pinned_with_sri() {
        $this->assertMatchesRegularExpression( '/^sha384-[A-Za-z0-9+\/]{64}$/', WCAI_Scanner::JQUERY_SRI );
        $this->assertMatchesRegularExpression( '/^sha384-[A-Za-z0-9+\/]{64}$/', WCAI_Scanner::QRCODE_SRI );
        $this->assertStringContainsString( 'html5-qrcode@2.3.8', WCAI_Scanner::QRCODE_SRC );
        $this->assertStringContainsString( 'jquery-3.6.0', WCAI_Scanner::JQUERY_SRC );
        $this->assertSame( 2, substr_count( $this->source(), 'crossorigin="anonymous"' ) );
    }

    public function test_no_debug_output_alert_or_zoom_lock() {
        $src = $this->source();

        $this->assertStringNotContainsString( 'console.log', $src );
        $this->assertStringNotContainsString( 'alert(', str_replace( 'role="alertdialog"', '', $src ) );
        $this->assertStringNotContainsString( 'user-scalable', $src );
    }
}

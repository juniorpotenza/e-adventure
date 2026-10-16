<?php
// PHPUnit sem WordPress. Não foi executado nesta sessão (PHP ausente).
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/includes/class-utils.php';

class Test_T8_Utils extends TestCase {

    public function test_is_min_age_rejects_future_birth_date() {
        $tomorrow = ( new DateTime( '+1 day' ) )->format( 'd/m/Y' );
        $next_year = ( new DateTime( '+1 year' ) )->format( 'd/m/Y' );
        $this->assertFalse( WCAI_Utils::is_min_age( $tomorrow ) );
        $this->assertFalse( WCAI_Utils::is_min_age( $next_year, 0 ) );
    }

    public function test_is_min_age_accepts_adult_and_rejects_child_and_bad_format() {
        $this->assertTrue( WCAI_Utils::is_min_age( ( new DateTime( '-30 years' ) )->format( 'd/m/Y' ) ) );
        $this->assertFalse( WCAI_Utils::is_min_age( ( new DateTime( '-3 years' ) )->format( 'd/m/Y' ) ) );
        $this->assertFalse( WCAI_Utils::is_min_age( 'abc' ) );
    }
}

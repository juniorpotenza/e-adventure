<?php
// PHPUnit com stubs (sem WP). Processo separado: ver phpunit-unit.xml.dist.
// Cobre: leitura tolerante de extensions['wcai-checkout'] (string, lista, null nao quebram o checkout).
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

require_once dirname( __DIR__ ) . '/includes/checkout-block/trait-wcai-checkout-block-reservation.php';

if ( ! class_exists( 'WcaiF5Host' ) ) {
    class WcaiF5Host {
        const NAMESPACE = 'wcai-checkout';
        use WCAI_Checkout_Block_Reservation;

        public function raw( $extensions ) {
            return $this->extension_payload_raw( $extensions );
        }
    }
}

class Test_F5_Extension_Data extends TestCase {

    public function test_reads_data_when_namespace_is_object() {
        $host = new WcaiF5Host();
        $this->assertSame( '{"items":[]}', $host->raw( array( 'wcai-checkout' => array( 'data' => '{"items":[]}' ) ) ) );
    }

    public function test_namespace_as_string_returns_empty() {
        $host = new WcaiF5Host();
        $this->assertSame( '', $host->raw( array( 'wcai-checkout' => 'data' ) ) );
    }

    public function test_missing_null_or_non_array_extensions_return_empty() {
        $host = new WcaiF5Host();
        $this->assertSame( '', $host->raw( null ) );
        $this->assertSame( '', $host->raw( 'x' ) );
        $this->assertSame( '', $host->raw( array() ) );
        $this->assertSame( '', $host->raw( array( 'wcai-checkout' => null ) ) );
        $this->assertSame( '', $host->raw( array( 'wcai-checkout' => array( 'data' => array( 'a' ) ) ) ) );
    }
}

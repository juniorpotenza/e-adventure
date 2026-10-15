<?php
// PHPUnit sem WP. Suite "f19": a rodada 4 só mexe no JS do wizard; estes testes travam o contrato do arquivo servido.
use PHPUnit\Framework\TestCase;

class WcaiF19CheckoutRound4Test extends TestCase {
    private function js() {
        return (string) file_get_contents( dirname( __DIR__ ) . '/assets/js/wcai-checkout-block.js' );
    }

    public function test_native_terms_hidden_only_on_step_four_with_consent_box() {
        $this->assertStringContainsString(
            'body.wcai-checkout-step-4.wcai-consent-box .wc-block-checkout__terms:not(:has(input[type="checkbox"])){display:none!important}',
            $this->js()
        );
    }

    public function test_submit_button_has_no_disabled_look() {
        $this->assertStringNotContainsString( 'aria-disabled', $this->js() );
        $this->assertStringNotContainsString( '#6b6b6b', $this->js() );
    }

    public function test_single_consent_hint() {
        $this->assertSame( 1, substr_count( $this->js(), "setAttribute('data-wcai-consent-hint'" ) );
    }

    public function test_native_text_inputs_get_touch_size() {
        $this->assertStringContainsString( 'min-height:44px;font-size:16px', $this->js() );
    }
}

<?php
// PHPUnit com stubs (sem WP). Suite "f14"; processo separado (stubs guardados por function_exists/class_exists).
// Cobre: CPF repetido (titular/participantes) recusado no servidor; dependências do script do checkout.
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'WCAI_VERSION' ) ) {
    define( 'WCAI_VERSION', 'test' );
}

if ( ! class_exists( 'WCAI_Settings' ) ) {
    class WCAI_Settings {
        public static function get_terms_url() { return 'https://site.test/termo/'; }
        public static function get_product_ids() { return array( 99 ); }
        public static function is_cpf_blocked( $cpf ) { return false; }
    }
}
if ( ! class_exists( 'WCAI_Privacy' ) ) { class WCAI_Privacy { public static function consent_text() { return 'Texto'; } } }

$GLOBALS['wcai_f14_registered'] = array();
$GLOBALS['wcai_f14_woo_handle'] = true;

if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( (string) $v ); } }
if ( ! function_exists( 'sanitize_key' ) ) { function sanitize_key( $v ) { return strtolower( preg_replace( '/[^a-z0-9_-]/i', '', (string) $v ) ); } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_url_raw' ) ) { function esc_url_raw( $v ) { return (string) $v; } }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta( $id, $key, $single = false ) { return 'yes'; } }
if ( ! function_exists( 'is_checkout' ) ) { function is_checkout() { return true; } }
if ( ! function_exists( 'plugin_dir_path' ) ) { function plugin_dir_path( $f ) { return dirname( $f ) . '/'; } }
if ( ! function_exists( 'plugin_dir_url' ) ) { function plugin_dir_url( $f ) { return 'https://site.test/plugin/'; } }
if ( ! function_exists( 'wp_script_is' ) ) { function wp_script_is( $handle, $list ) { return 'wc-blocks-data-store' === $handle && $GLOBALS['wcai_f14_woo_handle']; } }
if ( ! function_exists( 'wp_localize_script' ) ) { function wp_localize_script() {} }
if ( ! function_exists( 'wp_register_script' ) ) {
    function wp_register_script( $handle, $src, $deps ) { $GLOBALS['wcai_f14_registered'][ $handle ] = $deps; }
}

require_once dirname( __DIR__ ) . '/includes/class-utils.php';
require_once dirname( __DIR__ ) . '/includes/checkout-block/trait-wcai-checkout-block-setup.php';
require_once dirname( __DIR__ ) . '/includes/checkout-block/trait-wcai-checkout-block-validation.php';
require_once dirname( __DIR__ ) . '/includes/checkout-block/trait-wcai-checkout-block-participants.php';

if ( ! class_exists( 'WcaiF14Host' ) ) {
    class WcaiF14Host {
        const NAMESPACE = 'wcai-checkout';
        const SCRIPT_HANDLE = 'wcai-checkout-block';
        use WCAI_Checkout_Block_Setup;
        use WCAI_Checkout_Block_Validation;
        use WCAI_Checkout_Block_Participants;
        public function validate( $participants, $quantity, $billing_cpf ) {
            $this->validate_additional_participants( $participants, $quantity, $billing_cpf, 99, $quantity, 0 );
        }
    }
}

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Test_F14_Checkout_Round3 extends TestCase {

    private function person( $cpf, $name = 'Fulano' ) {
        return array( 'type' => 'adult', 'name' => $name, 'cpf' => $cpf, 'birthdate' => '01/01/1990' );
    }

    private function rejection( array $participants, $holder = '52998224725' ) {
        try {
            ( new WcaiF14Host() )->validate( $participants, count( $participants ) + 1, $holder );
        } catch ( \Exception $e ) {
            return $e->getMessage();
        }
        return '';
    }

    public function test_additional_cpf_equal_to_holder_is_rejected_even_when_masked() {
        $message = $this->rejection( array( $this->person( '529.982.247-25' ) ) );
        $this->assertStringContainsString( 'já foi usado', $message );
        $this->assertStringContainsString( 'participante 2', $message );
    }

    public function test_two_additional_participants_with_same_cpf_are_rejected() {
        $message = $this->rejection( array( $this->person( '111.444.777-35' ), $this->person( '11144477735' ) ) );
        $this->assertStringContainsString( 'participante 3', $message );
        $this->assertStringContainsString( 'já foi usado', $message );
    }

    public function test_distinct_cpfs_are_accepted() {
        $this->assertSame( '', $this->rejection( array( $this->person( '111.444.777-35' ) ) ) );
    }

    public function test_script_declares_the_woo_data_store_dependency_when_it_exists() {
        $GLOBALS['wcai_f14_woo_handle'] = true;
        ( new WcaiF14Host() )->register_assets();
        $this->assertSame( array( 'wp-data', 'wc-blocks-data-store' ), $GLOBALS['wcai_f14_registered']['wcai-checkout-block'] );
    }

    public function test_script_never_depends_on_a_handle_woo_did_not_register() {
        $GLOBALS['wcai_f14_woo_handle'] = false;
        ( new WcaiF14Host() )->register_assets();
        $this->assertSame( array( 'wp-data' ), $GLOBALS['wcai_f14_registered']['wcai-checkout-block'] );
    }
}

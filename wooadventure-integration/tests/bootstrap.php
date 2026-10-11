<?php
/**
 * Bootstrap da suite WordPress (phpunit.xml.dist).
 * Requer WP_TESTS_DIR (instalado por bin/install-wp-tests.sh) e WooCommerce em wp-content/plugins.
 */
$tests_dir = getenv( 'WP_TESTS_DIR' ) ?: rtrim( sys_get_temp_dir(), '/\' ) . '/wordpress-tests-lib';

if ( ! file_exists( $tests_dir . '/includes/functions.php' ) ) {
    fwrite( STDERR, "WP test suite nao encontrada em $tests_dir. Rode bin/install-wp-tests.sh.\n" );
    exit( 1 );
}

// Polyfills exigidos pela suite WP (set_up/tear_down, assertIsInt etc.).
$polyfills = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills';
if ( is_dir( $polyfills ) ) {
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $polyfills );
}

require_once $tests_dir . '/includes/functions.php';

tests_add_filter(
    'muplugins_loaded',
    function () {
        $wc = getenv( 'WC_PLUGIN_FILE' ) ?: WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
        if ( file_exists( $wc ) ) {
            require $wc;
        }
        require dirname( __DIR__ ) . '/wooadventure-integration.php';
    }
);

require $tests_dir . '/includes/bootstrap.php';

// Cria as tabelas do WooCommerce na base de testes (as do plugin nascem no hook plugins_loaded).
if ( function_exists( 'WC' ) ) {
    WC_Install::install();
}

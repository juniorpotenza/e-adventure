<?php
/**
 * Plugin Name: WooAdventure Integration
 * Description: Integração completa para Ecoturismo (Checkout, API Roca, Gestão de Participantes, Agenda, Assinaturas e Check-in).
 * Version: 2.22.0
 * Author: juniorpotenza
 * Text Domain: wooadventure-integration
 * Domain Path: /languages
 * Requires PHP: 7.0
 * Requires at least: 5.3
 * WC requires at least: 8.9
 * WC tested up to: 11.1
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'before_woocommerce_init', function() {
    if ( class_exists( 'Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
        Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
    }
} );

define( 'WCAI_VERSION', '2.22.0' ); // bump dispara dbDelta (colunas de snapshot e UNIQUE em wcai_legal_acceptances)

if ( ! function_exists( 'wcai_safe_require' ) ) {
    function wcai_safe_require( $filename ) {
        $path = plugin_dir_path( __FILE__ ) . 'includes/' . $filename;
        if ( file_exists( $path ) ) {
            require_once $path;
        }
    }
}

wcai_safe_require( 'class-utils.php' );
wcai_safe_require( 'class-data-resolver.php' );
wcai_safe_require( 'class-capabilities.php' );
wcai_safe_require( 'class-audit-log.php' );
wcai_safe_require( 'class-departures.php' );
wcai_safe_require( 'class-schedules.php' );
wcai_safe_require( 'class-reservations.php' );
wcai_safe_require( 'class-product-booking.php' );
wcai_safe_require( 'class-legal-documents.php' );
wcai_safe_require( 'class-legal-acceptances.php' );
wcai_safe_require( 'class-privacy.php' );
wcai_safe_require( 'class-settings.php' );
wcai_safe_require( 'class-participants-db.php' );
wcai_safe_require( 'class-checkout.php' );
wcai_safe_require( 'class-checkout-block.php' );
wcai_safe_require( 'class-order.php' );
wcai_safe_require( 'class-api-integration.php' );
wcai_safe_require( 'class-frontend-account.php' );
wcai_safe_require( 'class-agenda.php' );
wcai_safe_require( 'class-assinatura.php' );
wcai_safe_require( 'class-scanner.php' );

function wcai_init() {
    if ( class_exists( 'WCAI_Capabilities' ) ) WCAI_Capabilities::register();
    if ( class_exists( 'WCAI_Departures' ) ) new WCAI_Departures();
    if ( class_exists( 'WCAI_Schedules' ) ) new WCAI_Schedules();
    if ( class_exists( 'WCAI_Reservations' ) ) {
        WCAI_Reservations::register_hooks();
        if ( ! wp_next_scheduled( 'wcai_expire_holds' ) ) wp_schedule_event( time() + MINUTE_IN_SECONDS, 'wcai_five_minutes', 'wcai_expire_holds' );
    }
    if ( class_exists( 'WCAI_Product_Booking' ) ) new WCAI_Product_Booking();
    if ( class_exists( 'WCAI_Legal_Documents' ) ) new WCAI_Legal_Documents();
    if ( class_exists( 'WCAI_Privacy' ) ) new WCAI_Privacy();
    if ( class_exists( 'WCAI_Settings' ) ) new WCAI_Settings();
    if ( class_exists( 'WCAI_Checkout' ) ) new WCAI_Checkout();
    if ( class_exists( 'WCAI_Checkout_Block' ) ) new WCAI_Checkout_Block();
    if ( class_exists( 'WCAI_Order' ) ) new WCAI_Order();
    if ( class_exists( 'WCAI_API_Integration' ) ) new WCAI_API_Integration();
    if ( class_exists( 'WCAI_Frontend_Account' ) ) new WCAI_Frontend_Account();
    if ( class_exists( 'WCAI_Agenda' ) ) new WCAI_Agenda();
    if ( class_exists( 'WCAI_Assinatura' ) ) new WCAI_Assinatura();
    if ( class_exists( 'WCAI_Scanner' ) ) new WCAI_Scanner();
}

add_action( 'plugins_loaded', 'wcai_init' );

function wcai_load_textdomain() {
    load_plugin_textdomain( 'wooadventure-integration', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

add_action( 'init', 'wcai_load_textdomain' );

function wcai_create_tables() {
    if ( class_exists( 'WCAI_Reservations' ) ) {
        WCAI_Reservations::create_table();
    }

    if ( class_exists( 'WCAI_Participants_DB' ) ) {
        WCAI_Participants_DB::create_table();
    }

    if ( class_exists( 'WCAI_API_Integration' ) ) {
        WCAI_API_Integration::create_tables();
    }

    if ( class_exists( 'WCAI_Audit_Log' ) ) {
        WCAI_Audit_Log::create_table();
    }

    if ( class_exists( 'WCAI_Legal_Acceptances' ) ) {
        WCAI_Legal_Acceptances::create_table();
    }
}

function wcai_backfill_links() {
    if ( class_exists( 'WCAI_Participants_DB' ) ) {
        WCAI_Participants_DB::backfill_reservation_links();
    }

    if ( class_exists( 'WCAI_Legal_Acceptances' ) ) {
        WCAI_Legal_Acceptances::backfill_reservation_links();
    }
}

function wcai_install_capabilities() {
    if ( class_exists( 'WCAI_Capabilities' ) ) {
        WCAI_Capabilities::install();
    }
}

// Mantém o menor id por participant_id + reservation_id + document_version.
// Sem isso o dbDelta falha em silêncio ao criar a UNIQUE. Linhas com reservation_id NULL não colidem na UNIQUE.
function wcai_dedupe_legal_acceptances() {
    global $wpdb;

    if ( ! class_exists( 'WCAI_Legal_Acceptances' ) ) {
        return;
    }

    $table = WCAI_Legal_Acceptances::table();

    // Sem autojoin: o suite de testes do WP usa tabelas temporárias, que o MySQL não deixa citar duas vezes.
    $groups = $wpdb->get_results(
        "SELECT participant_id, reservation_id, document_version
         FROM $table
         WHERE reservation_id IS NOT NULL
         GROUP BY participant_id, reservation_id, document_version
         HAVING COUNT(*) > 1"
    );

    foreach ( (array) $groups as $group ) {
        $ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT id FROM $table WHERE participant_id = %d AND reservation_id = %d AND document_version = %s ORDER BY id ASC",
                $group->participant_id,
                $group->reservation_id,
                $group->document_version
            )
        );

        array_shift( $ids ); // mantém o menor id

        if ( $ids ) {
            $wpdb->query( "DELETE FROM $table WHERE id IN (" . implode( ',', array_map( 'absint', $ids ) ) . ')' );
        }
    }
}

// Passos ordenados por versão. Cada passo roda uma vez, quando a versão instalada é menor que a chave.
function wcai_migration_steps() {
    return array(
        '2.21.0' => function () {
            wcai_create_tables();
            wcai_backfill_links();
            wcai_install_capabilities();
        },
        '2.22.0' => function () {
            wcai_backfill_links();          // antes da UNIQUE: o vínculo preenchido pode gerar duplicata
            wcai_dedupe_legal_acceptances();
            wcai_create_tables();           // dbDelta: colunas de snapshot/responsável + UNIQUE
            wcai_install_capabilities();
        },
    );
}

function wcai_run_migrations() {
    $installed = get_option( 'wcai_version' );
    $installed = $installed ? (string) $installed : '0';

    foreach ( wcai_migration_steps() as $version => $step ) {
        if ( version_compare( $installed, $version, '<' ) ) {
            call_user_func( $step );
        }
    }

    update_option( 'wcai_version', WCAI_VERSION );
}

function wcai_maybe_upgrade_database() {
    if ( get_option( 'wcai_version' ) === WCAI_VERSION ) {
        return;
    }

    wcai_run_migrations();
}

add_action( 'plugins_loaded', 'wcai_maybe_upgrade_database', 5 );

register_activation_hook( __FILE__, 'wcai_activate_plugin' );
register_deactivation_hook( __FILE__, 'wcai_deactivate_plugin' );

function wcai_deactivate_plugin() {
    wp_clear_scheduled_hook( 'wcai_expire_holds' );
}

function wcai_activate_plugin() {
    wcai_run_migrations();
    wcai_create_tables(); // reativação na mesma versão recria tabelas ausentes
    wcai_install_capabilities();
}

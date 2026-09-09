<?php
/**
 * Plugin Name: WooAdventure Integration
 * Description: Integração completa para Ecoturismo (Checkout, API Roca, Gestão de Participantes, Agenda, Assinaturas e Check-in).
 * Version: 2.5.1
 * Author: Seu Nome
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WCAI_VERSION', '2.5.1' );

// ... (Mantenha a função wcai_safe_require igual) ...
if ( ! function_exists( 'wcai_safe_require' ) ) {
    function wcai_safe_require( $filename ) {
        $path = plugin_dir_path( __FILE__ ) . 'includes/' . $filename;
        if ( file_exists( $path ) ) require_once $path;
    }
}

// 1. CARREGAMENTO DOS ARQUIVOS
wcai_safe_require( 'class-utils.php' );
wcai_safe_require( 'class-capabilities.php' );
wcai_safe_require( 'class-audit-log.php' );
wcai_safe_require( 'class-departures.php' );
wcai_safe_require( 'class-reservations.php' );
wcai_safe_require( 'class-settings.php' );
wcai_safe_require( 'class-participants-db.php' );
wcai_safe_require( 'class-checkout.php' );
wcai_safe_require( 'class-order.php' );
wcai_safe_require( 'class-api-integration.php' );
wcai_safe_require( 'class-frontend-account.php' );
wcai_safe_require( 'class-agenda.php' ); 
wcai_safe_require( 'class-assinatura.php' );
wcai_safe_require( 'class-scanner.php' ); // NOVO

// 2. INICIALIZAÇÃO DAS CLASSES
function wcai_init() {
    if ( class_exists( 'WCAI_Capabilities' ) ) WCAI_Capabilities::register();
    if ( class_exists( 'WCAI_Departures' ) ) new WCAI_Departures();
    if ( class_exists( 'WCAI_Reservations' ) ) WCAI_Reservations::register_hooks();
    if ( class_exists( 'WCAI_Settings' ) ) new WCAI_Settings();
    if ( class_exists( 'WCAI_Checkout' ) ) new WCAI_Checkout();
    if ( class_exists( 'WCAI_Order' ) ) new WCAI_Order();
    if ( class_exists( 'WCAI_API_Integration' ) ) new WCAI_API_Integration();
    if ( class_exists( 'WCAI_Frontend_Account' ) ) new WCAI_Frontend_Account();
    if ( class_exists( 'WCAI_Agenda' ) ) new WCAI_Agenda(); 
    if ( class_exists( 'WCAI_Assinatura' ) ) new WCAI_Assinatura(); 
    if ( class_exists( 'WCAI_Scanner' ) ) new WCAI_Scanner(); // NOVO
}
add_action( 'plugins_loaded', 'wcai_init' );

function wcai_maybe_upgrade_database() {
    if ( get_option( 'wcai_version' ) === WCAI_VERSION ) {
        return;
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
    if ( class_exists( 'WCAI_Reservations' ) ) {
        WCAI_Reservations::create_table();
    }
    if ( class_exists( 'WCAI_Capabilities' ) ) {
        WCAI_Capabilities::install();
    }

    update_option( 'wcai_version', WCAI_VERSION );
}
add_action( 'plugins_loaded', 'wcai_maybe_upgrade_database', 5 );

// 3. ATIVAÇÃO
register_activation_hook( __FILE__, 'wcai_activate_plugin' );
function wcai_activate_plugin() {
    if ( class_exists( 'WCAI_Participants_DB' ) ) WCAI_Participants_DB::create_table();
    if ( class_exists( 'WCAI_API_Integration' ) ) WCAI_API_Integration::create_tables();
    if ( class_exists( 'WCAI_Audit_Log' ) ) WCAI_Audit_Log::create_table();
    if ( class_exists( 'WCAI_Reservations' ) ) WCAI_Reservations::create_table();
    if ( class_exists( 'WCAI_Capabilities' ) ) WCAI_Capabilities::install();
    update_option( 'wcai_version', WCAI_VERSION );
}

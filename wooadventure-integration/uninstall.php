<?php
// Executado pelo WordPress ao excluir o plugin.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

// Sempre: role, capabilities e cron.
require_once __DIR__ . '/includes/class-capabilities.php';

remove_role( 'wcai_guide' );

foreach ( wp_roles()->role_objects as $role ) {
    foreach ( WCAI_Capabilities::all() as $capability ) {
        $role->remove_cap( $capability );
    }
}

wp_clear_scheduled_hook( 'wcai_expire_holds' );

// Dados (legais e pessoais) só saem com opt-in explícito.
if ( ! get_option( 'wcai_delete_data_on_uninstall' ) ) {
    return;
}

foreach ( array( 'wcai_assinatura', 'wcai_legal_document', 'wcai_departure', 'wcai_schedule' ) as $post_type ) {
    $post_ids = get_posts(
        array(
            'post_type'      => $post_type,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        )
    );

    foreach ( $post_ids as $post_id ) {
        wp_delete_post( $post_id, true );
    }
}

foreach ( array( 'wcai_participantes', 'wcai_legal_acceptances', 'wcai_reservations', 'wcai_audit_log', 'wc_evento_event_tokens' ) as $table ) {
    $wpdb->query( 'DROP TABLE IF EXISTS `' . $wpdb->prefix . $table . '`' );
}

// Inclui a própria opção wcai_delete_data_on_uninstall.
$wpdb->query( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE %s", $wpdb->esc_like( 'wcai_' ) . '%' ) );

$upload = wp_upload_dir();

foreach ( array( 'wcai-private', 'wcai-tickets' ) as $folder ) {
    $directory = trailingslashit( $upload['basedir'] ) . $folder;

    if ( ! is_dir( $directory ) ) {
        continue;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $directory, FilesystemIterator::SKIP_DOTS ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ( $items as $item ) {
        $item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
    }

    rmdir( $directory );
}

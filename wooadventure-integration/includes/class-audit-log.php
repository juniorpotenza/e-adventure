<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Audit_Log {
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'wcai_audit_log';
    }

    public static function create_table() {
        global $wpdb;
        $table = self::get_table_name();
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            actor_user_id bigint(20) unsigned DEFAULT NULL,
            action varchar(100) NOT NULL,
            object_type varchar(100) NOT NULL,
            object_id bigint(20) unsigned DEFAULT NULL,
            context longtext DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY object_lookup (object_type, object_id),
            KEY action_created (action, created_at),
            KEY actor_created (actor_user_id, created_at)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }

    public static function log( $action, $object_type, $object_id = null, $context = array() ) {
        global $wpdb;
        $allowed_context = is_array( $context ) ? $context : array();
        unset( $allowed_context['cpf'], $allowed_context['email'], $allowed_context['birthdate'], $allowed_context['signature'] );

        return $wpdb->insert(
            self::get_table_name(),
            array(
                'actor_user_id' => get_current_user_id() ?: null,
                'action'        => sanitize_key( $action ),
                'object_type'   => sanitize_key( $object_type ),
                'object_id'     => $object_id ? absint( $object_id ) : null,
                'context'       => $allowed_context ? wp_json_encode( $allowed_context ) : null,
                'created_at'    => current_time( 'mysql', true ),
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s' )
        );
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Audit_Log {
    const MAX_CONTEXT_BYTES = 8000;

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

        $allowed_context = self::sanitize_context( $context );

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

    private static function sanitize_context( $context, $depth = 0 ) {
        if ( ! is_array( $context ) || $depth > 3 ) {
            return array();
        }

        $sensitive_keys = array(
            'cpf', 'document', 'email', 'birthdate', 'data_nascimento',
            'signature', 'assinatura', 'ip', 'remote_addr',
            'user_agent', 'guardian_cpf', 'guardian_name',
        );

        $result = array();

        foreach ( $context as $key => $value ) {
            $normalized_key = strtolower( (string) $key );

            foreach ( $sensitive_keys as $sensitive_key ) {
                if (
                    $normalized_key === $sensitive_key ||
                    false !== strpos( $normalized_key, $sensitive_key )
                ) {
                    continue 2;
                }
            }

            if ( is_array( $value ) ) {
                $value = self::sanitize_context( $value, $depth + 1 );
            } elseif ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
                // Keep scalar operational values.
            } else {
                $value = sanitize_text_field( (string) $value );
            }

            $result[ sanitize_key( $key ) ] = $value;
        }

        $encoded = wp_json_encode( $result );
        if ( false === $encoded || strlen( $encoded ) > self::MAX_CONTEXT_BYTES ) {
            return array(
                'context_truncated' => true,
            );
        }

        return $result;
    }
}

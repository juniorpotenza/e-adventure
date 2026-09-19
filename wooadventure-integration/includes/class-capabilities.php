<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Capabilities {
    const MANAGE_DEPARTURES = 'wcai_manage_departures';
    const VIEW_MANIFEST     = 'wcai_view_manifest';
    const CHECK_IN          = 'wcai_checkin_participants';
    const VIEW_SENSITIVE    = 'wcai_view_sensitive_data';
    const MANAGE_LEGAL      = 'wcai_manage_legal_documents';
    const MANAGE_INCIDENTS  = 'wcai_manage_incidents';
    const MANAGE_PARTICIPANTS = 'wcai_manage_participants';
    const MANAGE_SETTINGS   = 'wcai_manage_settings';

    public static function all() {
        return array(
            self::MANAGE_DEPARTURES,
            self::VIEW_MANIFEST,
            self::CHECK_IN,
            self::VIEW_SENSITIVE,
            self::MANAGE_LEGAL,
            self::MANAGE_INCIDENTS,
            self::MANAGE_PARTICIPANTS,
            self::MANAGE_SETTINGS,
        );
    }

    public static function register() {
        $administrator = get_role( 'administrator' );
        if ( ! $administrator ) {
            return;
        }

        foreach ( self::all() as $capability ) {
            $administrator->add_cap( $capability );
        }
    }

    public static function install() {
        self::register();

        $guide = get_role( 'wcai_guide' );
        if ( ! $guide ) {
            $guide = add_role( 'wcai_guide', 'Guia de Aventura', array( 'read' => true ) );
        }

        if ( $guide ) {
            foreach (
                array(
                    self::VIEW_MANIFEST,
                    self::CHECK_IN,
                    self::MANAGE_INCIDENTS,
                ) as $capability
            ) {
                $guide->add_cap( $capability );
            }
        }
    }

    public static function can( $capability ) {
        return current_user_can( $capability );
    }
}

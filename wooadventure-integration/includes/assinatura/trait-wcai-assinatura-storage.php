<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Assinatura_Storage {

    private static function harden_directory( $directory, $deny_all ) {
        $rules = $deny_all
            ? "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n"
            : "Options -Indexes\n";

        if ( ! file_exists( $directory . '/index.php' ) ) {
            file_put_contents( $directory . '/index.php', "<?php\n// Silence is golden.\n" );
        }

        if ( ! file_exists( $directory . '/.htaccess' ) ) {
            file_put_contents( $directory . '/.htaccess', $rules );
        }
    }

    /**
     * Grava o PNG em uploads/wcai-private (negado ao público) com nome imprevisível.
     *
     * @return array|false array( 'file' => caminho, 'name' => nome do arquivo ).
     */
    private static function store_signature_png( $decoded, $participant_id, $post_id ) {
        $upload = wp_upload_dir();
        if ( ! empty( $upload['error'] ) || empty( $upload['basedir'] ) ) {
            return false;
        }

        $directory = trailingslashit( $upload['basedir'] ) . 'wcai-private';
        if ( ! wp_mkdir_p( $directory ) ) {
            return false;
        }

        self::harden_directory( $directory, true );

        $name = 'assign_' . absint( $participant_id ) . '_' . absint( $post_id ) . '_' . bin2hex( random_bytes( 16 ) ) . '.png';
        $file = $directory . '/' . $name;

        if ( false === file_put_contents( $file, $decoded ) ) {
            return false;
        }

        return array( 'file' => $file, 'name' => $name );
    }

    public function serve_signature_image() {
        if ( ! current_user_can( WCAI_Capabilities::MANAGE_LEGAL ) ) {
            wp_die( 'Acesso negado.', 403 );
        }

        $post_id = isset( $_GET['post_id'] ) ? absint( $_GET['post_id'] ) : 0;
        check_admin_referer( 'wcai_signature_image_' . $post_id );

        $upload = wp_upload_dir();
        $name = basename( (string) get_post_meta( $post_id, '_wcai_assinatura_file', true ) );
        $file = trailingslashit( $upload['basedir'] ) . 'wcai-private/' . $name;

        if ( ! $post_id || 'wcai_assinatura' !== get_post_type( $post_id ) || '' === $name || ! is_file( $file ) ) {
            wp_die( 'Assinatura não encontrada.', 404 );
        }

        nocache_headers();
        header( 'Content-Type: image/png' );
        header( 'Content-Length: ' . filesize( $file ) );
        header( 'X-Content-Type-Options: nosniff' );
        readfile( $file );
        exit;
    }
}

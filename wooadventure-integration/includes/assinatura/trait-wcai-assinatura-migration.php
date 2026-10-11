<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Assinatura_Migration {

    /**
     * Move PNGs públicos antigos (_wcai_assinatura_url) para wcai-private. Idempotente e em lotes.
     * Só remove o arquivo antigo e o meta após gravar o novo. Registro cujo arquivo não existe ou fica
     * fora de uploads recebe _wcai_assinatura_migrate_failed e sai da fila (senão o lote travaria).
     *
     * @return int Registros ainda pendentes.
     */
    public static function migrate_legacy_signatures() {
        $upload = wp_upload_dir();
        if ( ! empty( $upload['error'] ) || empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) {
            return 0;
        }

        $pending = array(
            'post_type'   => 'wcai_assinatura',
            'post_status' => 'any',
            'fields'      => 'ids',
            'meta_query'  => array(
                'relation' => 'AND',
                array( 'key' => '_wcai_assinatura_url', 'compare' => 'EXISTS' ),
                array( 'key' => '_wcai_assinatura_migrate_failed', 'compare' => 'NOT EXISTS' ),
            ),
        );

        foreach ( get_posts( $pending + array( 'posts_per_page' => self::MIGRATE_BATCH ) ) as $post_id ) {
            if ( ! self::migrate_legacy_signature( $post_id, $upload ) ) {
                update_post_meta( $post_id, '_wcai_assinatura_migrate_failed', 1 );
            }
        }

        return count( get_posts( $pending + array( 'posts_per_page' => -1 ) ) );
    }

    private static function migrate_legacy_signature( $post_id, $upload ) {
        $url = (string) get_post_meta( $post_id, '_wcai_assinatura_url', true );
        if ( 0 !== strpos( $url, $upload['baseurl'] ) ) {
            return false;
        }

        $legacy = $upload['basedir'] . substr( $url, strlen( $upload['baseurl'] ) );
        if ( false !== strpos( $legacy, '..' ) || ! is_file( $legacy ) ) {
            return false;
        }

        $bytes = file_get_contents( $legacy );
        if ( false === $bytes ) {
            return false;
        }

        $stored = self::store_signature_png( $bytes, absint( get_post_meta( $post_id, '_wcai_participant_id', true ) ), $post_id );
        if ( ! $stored ) {
            return false;
        }

        update_post_meta( $post_id, '_wcai_assinatura_file', $stored['name'] );
        wp_delete_file( $legacy );
        delete_post_meta( $post_id, '_wcai_assinatura_url' );

        return true;
    }
}

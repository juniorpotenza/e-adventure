<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Legal_Documents {
    const POST_TYPE = 'wcai_legal_document';

    public function __construct() {
        add_action( 'init', array( $this, 'register_post_type' ) );
        add_action( 'add_meta_boxes_' . self::POST_TYPE, array( $this, 'add_meta_box' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save' ), 10, 2 );
    }

    public function register_post_type() {
        register_post_type( self::POST_TYPE, array(
            'labels' => array(
                'name'          => 'Documentos Jurídicos',
                'singular_name' => 'Documento Jurídico',
                'add_new_item'  => 'Adicionar documento jurídico',
            ),
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => 'woocommerce',
            'supports'        => array( 'title', 'editor', 'revisions' ),
            'capability_type' => 'post',
            'map_meta_cap'    => false,
            'capabilities'    => array(
                'edit_post'           => WCAI_Capabilities::MANAGE_LEGAL,
                'read_post'           => WCAI_Capabilities::MANAGE_LEGAL,
                'delete_post'         => WCAI_Capabilities::MANAGE_LEGAL,
                'edit_posts'         => WCAI_Capabilities::MANAGE_LEGAL,
                'create_posts'       => WCAI_Capabilities::MANAGE_LEGAL,
                'publish_posts'      => WCAI_Capabilities::MANAGE_LEGAL,
                'delete_posts'       => WCAI_Capabilities::MANAGE_LEGAL,
                'edit_others_posts'  => WCAI_Capabilities::MANAGE_LEGAL,
                'delete_others_posts'=> WCAI_Capabilities::MANAGE_LEGAL,
            ),
        ) );
    }

    public function add_meta_box() {
        add_meta_box(
            'wcai_legal_version',
            'Versionamento',
            array( $this, 'render_meta_box' ),
            self::POST_TYPE,
            'side'
        );
    }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'wcai_save_legal_document', 'wcai_legal_document_nonce' );

        $version  = get_post_meta( $post->ID, '_wcai_document_version', true );
        $effective = get_post_meta( $post->ID, '_wcai_document_effective_at', true );

        echo '<p><label>Versão</label><input class="widefat" name="wcai_document_version" value="' . esc_attr( $version ) . '" required></p>';
        echo '<p><label>Vigente a partir de</label><input class="widefat" type="date" name="wcai_document_effective_at" value="' . esc_attr( $effective ) . '"></p>';
        echo '<p class="description">O hash da versão é recalculado a partir do título, conteúdo e versão salvos.</p>';
    }

    public function save( $post_id, $post ) {
        if (
            ! isset( $_POST['wcai_legal_document_nonce'] ) ||
            ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wcai_legal_document_nonce'] ) ), 'wcai_save_legal_document' ) ||
            ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ||
            wp_is_post_revision( $post_id ) ||
            ! current_user_can( WCAI_Capabilities::MANAGE_LEGAL )
        ) {
            return;
        }

        $version = isset( $_POST['wcai_document_version'] )
            ? sanitize_text_field( wp_unslash( $_POST['wcai_document_version'] ) )
            : '';

        $effective = isset( $_POST['wcai_document_effective_at'] )
            ? sanitize_text_field( wp_unslash( $_POST['wcai_document_effective_at'] ) )
            : '';

        if ( ! $version ) {
            return;
        }

        update_post_meta( $post_id, '_wcai_document_version', $version );
        update_post_meta( $post_id, '_wcai_document_effective_at', $effective );

        $payload = array(
            'title'   => (string) $post->post_title,
            'content' => (string) $post->post_content,
            'version' => $version,
        );

        update_post_meta(
            $post_id,
            '_wcai_document_hash',
            hash( 'sha256', wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) )
        );

        WCAI_Audit_Log::log(
            'legal_document_saved',
            'legal_document',
            $post_id,
            array( 'version' => $version )
        );
    }

    public static function get_by_id( $document_id ) {
        $document_id = absint( $document_id );
        if ( ! $document_id ) {
            return null;
        }

        $document = get_post( $document_id );
        if ( ! $document || self::POST_TYPE !== $document->post_type ) {
            return null;
        }

        return $document;
    }

    public static function get_snapshot( $document_id ) {
        $document = self::get_by_id( $document_id );
        if ( ! $document || 'publish' !== $document->post_status ) {
            return null;
        }

        $version = (string) get_post_meta( $document->ID, '_wcai_document_version', true );
        $hash    = (string) get_post_meta( $document->ID, '_wcai_document_hash', true );

        if ( ! $version || ! preg_match( '/^[a-f0-9]{64}$/', $hash ) ) {
            return null;
        }

        return array(
            'document_id'      => absint( $document->ID ),
            'document_version' => $version,
            'document_hash'    => $hash,
        );
    }

    /**
     * Retorna o documento publicado com a data de vigência mais recente
     * que já entrou em vigor.
     */
    public static function get_active_document() {
        $documents = get_posts( array(
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );

        if ( empty( $documents ) ) {
            return null;
        }

        $today = current_time( 'Y-m-d' );
        $eligible = array();

        foreach ( $documents as $document ) {
            $effective = (string) get_post_meta( $document->ID, '_wcai_document_effective_at', true );
            if ( $effective && $effective > $today ) {
                continue;
            }

            $snapshot = self::get_snapshot( $document->ID );
            if ( $snapshot ) {
                $eligible[] = array(
                    'document'  => $document,
                    'effective' => $effective ?: '0000-00-00',
                );
            }
        }

        if ( empty( $eligible ) ) {
            return null;
        }

        usort( $eligible, static function ( $a, $b ) {
            if ( $a['effective'] === $b['effective'] ) {
                return strcmp( $b['document']->post_date_gmt, $a['document']->post_date_gmt );
            }
            return strcmp( $b['effective'], $a['effective'] );
        } );

        return $eligible[0]['document'];
    }

    public static function get_active_snapshot() {
        $document = self::get_active_document();
        return $document ? self::get_snapshot( $document->ID ) : null;
    }
}

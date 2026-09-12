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
            'labels' => array( 'name' => 'Documentos Jurídicos', 'singular_name' => 'Documento Jurídico', 'add_new_item' => 'Adicionar documento jurídico' ),
            'public' => false, 'show_ui' => true, 'show_in_menu' => 'woocommerce', 'supports' => array( 'title', 'editor', 'revisions' ),
            'capability_type' => 'post', 'map_meta_cap' => false,
            'capabilities' => array( 'edit_post' => WCAI_Capabilities::MANAGE_LEGAL, 'read_post' => WCAI_Capabilities::MANAGE_LEGAL, 'delete_post' => WCAI_Capabilities::MANAGE_LEGAL, 'edit_posts' => WCAI_Capabilities::MANAGE_LEGAL, 'create_posts' => WCAI_Capabilities::MANAGE_LEGAL, 'publish_posts' => WCAI_Capabilities::MANAGE_LEGAL, 'delete_posts' => WCAI_Capabilities::MANAGE_LEGAL ),
        ) );
    }

    public function add_meta_box() { add_meta_box( 'wcai_legal_version', 'Versionamento', array( $this, 'render_meta_box' ), self::POST_TYPE, 'side' ); }

    public function render_meta_box( $post ) {
        wp_nonce_field( 'wcai_save_legal_document', 'wcai_legal_document_nonce' );
        $version = get_post_meta( $post->ID, '_wcai_document_version', true );
        $effective = get_post_meta( $post->ID, '_wcai_document_effective_at', true );
        echo '<p><label>Versão</label><input class="widefat" name="wcai_document_version" value="' . esc_attr( $version ) . '" required></p>';
        echo '<p><label>Vigente a partir de</label><input class="widefat" type="date" name="wcai_document_effective_at" value="' . esc_attr( $effective ) . '"></p>';
    }

    public function save( $post_id, $post ) {
        if ( ! isset( $_POST['wcai_legal_document_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wcai_legal_document_nonce'] ) ), 'wcai_save_legal_document' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( WCAI_Capabilities::MANAGE_LEGAL ) ) return;
        $version = isset( $_POST['wcai_document_version'] ) ? sanitize_text_field( wp_unslash( $_POST['wcai_document_version'] ) ) : '';
        $effective = isset( $_POST['wcai_document_effective_at'] ) ? sanitize_text_field( wp_unslash( $_POST['wcai_document_effective_at'] ) ) : '';
        update_post_meta( $post_id, '_wcai_document_version', $version ); update_post_meta( $post_id, '_wcai_document_effective_at', $effective );
        update_post_meta( $post_id, '_wcai_document_hash', hash( 'sha256', wp_strip_all_tags( $post->post_content ) . '|' . $version ) );
        WCAI_Audit_Log::log( 'legal_document_saved', 'legal_document', $post_id, array( 'version' => $version ) );
    }
}

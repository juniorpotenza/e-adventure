<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Assinatura_Qr {

    // Pública para o apagador LGPD. Procura em todos os Y/m: o QR pode ter sido gerado em outro mês.
    public static function delete_local_qr( $hash, $participant_id ) {
        $upload = wp_upload_dir();
        if ( ! empty( $upload['error'] ) || empty( $upload['basedir'] ) ) {
            return;
        }

        $filename = 'ticket-' . absint( $participant_id ) . '-' . substr( hash( 'sha256', $hash ), 0, 32 ) . '.png';

        foreach ( (array) glob( trailingslashit( $upload['basedir'] ) . 'wcai-tickets/*/*/' . $filename ) as $filepath ) {
            wp_delete_file( $filepath );
        }
    }

    public static function generate_local_qr( $hash, $participant_id ) {
        $hash = sanitize_text_field( $hash );
        $participant_id = absint( $participant_id );

        if ( '' === $hash || ! $participant_id ) {
            return new WP_Error( 'wcai_qr_invalid_data', 'Dados inválidos para geração do ingresso.' );
        }

        if ( ! function_exists( 'imagepng' ) || ! function_exists( 'imagecreatetruecolor' ) ) {
            return new WP_Error( 'wcai_qr_gd_missing', 'O servidor não possui suporte GD para gerar o ingresso.' );
        }

        $library = dirname( __DIR__, 2 ) . '/vendor/qrcode/qrcode.php';
        if ( ! file_exists( $library ) ) {
            return new WP_Error( 'wcai_qr_library_missing', 'Biblioteca local de QR Code indisponível.' );
        }

        require_once $library;

        if ( ! class_exists( 'WCAI_QRCode' ) ) {
            return new WP_Error( 'wcai_qr_generator_missing', 'Gerador local de QR Code indisponível.' );
        }

        $upload = wp_upload_dir();
        if ( ! empty( $upload['error'] ) || empty( $upload['basedir'] ) || empty( $upload['baseurl'] ) ) {
            return new WP_Error( 'wcai_qr_upload_dir', 'Diretório de uploads indisponível.' );
        }

        $subdir = 'wcai-tickets/' . gmdate( 'Y/m' );
        $directory = trailingslashit( $upload['basedir'] ) . $subdir;

        if ( ! wp_mkdir_p( $directory ) ) {
            return new WP_Error( 'wcai_qr_directory', 'Não foi possível preparar o diretório do ingresso.' );
        }

        // Os PNGs são públicos (QR no e-mail), mas o diretório não pode ser listado.
        self::harden_directory( trailingslashit( $upload['basedir'] ) . 'wcai-tickets', false );
        self::harden_directory( $directory, false );

        $filename = 'ticket-' . $participant_id . '-' . substr( hash( 'sha256', $hash ), 0, 32 ) . '.png';
        $filepath = trailingslashit( $directory ) . $filename;
        $url = trailingslashit( $upload['baseurl'] ) . $subdir . '/' . rawurlencode( $filename );

        if ( file_exists( $filepath ) ) {
            return $url;
        }

        try {
            $generator = new WCAI_QRCode(
                $hash,
                array(
                    's'  => 'qrl',
                    'sf' => 6,
                    'p'  => 12,
                )
            );

            $image = $generator->render_image();
            $saved = imagepng( $image, $filepath, 6 );
            imagedestroy( $image );
        } catch ( Throwable $exception ) {
            return new WP_Error( 'wcai_qr_generation_failed', 'Não foi possível gerar o ingresso.' );
        }

        if ( ! $saved || ! file_exists( $filepath ) ) {
            return new WP_Error( 'wcai_qr_write_failed', 'Não foi possível salvar o ingresso.' );
        }

        return $url;
    }
}

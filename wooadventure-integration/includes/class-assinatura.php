<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Assinatura {

    const AJAX_NONCE = 'wcai_signature';
    const LOOKUP_ERROR = 'Dados não conferem. Verifique o pedido, a chave do pedido e o CPF.';
    const SESSION_ERROR = 'Sessão de assinatura inválida ou expirada.';
    const RATE_LIMIT = 10;
    // Um grupo atrás do mesmo IP assina vários termos no mesmo pedido: limite maior para salvar.
    const RATE_LIMIT_SAVE = 30;

    public function __construct() {
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_filter( 'manage_wcai_assinatura_posts_columns', array( $this, 'set_custom_columns' ) );
        add_action( 'manage_wcai_assinatura_posts_custom_column', array( $this, 'custom_column_content' ), 10, 2 );
        add_action( 'add_meta_boxes', array( $this, 'add_details_metabox' ) );

        add_shortcode( 'wcai_painel_assinatura', array( $this, 'render_shortcode' ) );
        add_shortcode( 'wcai_script_validacao', array( $this, 'render_validation_script_shortcode' ) );

        add_action( 'wp_ajax_wcai_check_order_only', array( $this, 'ajax_check_order' ) );
        add_action( 'wp_ajax_nopriv_wcai_check_order_only', array( $this, 'ajax_check_order' ) );
        add_action( 'wp_ajax_wcai_autocheck_pax', array( $this, 'ajax_check_pax_deep' ) );
        add_action( 'wp_ajax_nopriv_wcai_autocheck_pax', array( $this, 'ajax_check_pax_deep' ) );
        add_action( 'wp_ajax_wcai_salvar_assinatura_final', array( $this, 'ajax_save_signature' ) );
        add_action( 'wp_ajax_nopriv_wcai_salvar_assinatura_final', array( $this, 'ajax_save_signature' ) );
        add_action( 'admin_post_wcai_signature_image', array( $this, 'serve_signature_image' ) );
    }

    public function render_validation_script_shortcode() {
        $id_pedido = 'esig-sif-1739375553756';
        $id_cpf    = 'esig-sif-1739375595096';
        $nonce     = wp_create_nonce( self::AJAX_NONCE );

        ob_start();
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
            var security = '<?php echo esc_js( $nonce ); ?>';
            var selPedido = 'input[name="<?php echo esc_js( $id_pedido ); ?>"]';
            var selCPF    = 'input[name="<?php echo esc_js( $id_cpf ); ?>"]';

            $(document).on('blur', selPedido, function() {
                var $this = $(this);
                var val = $this.val().trim();
                var $msg = $('#wcai-msg-pedido');

                if ( val.length === 0 ) {
                    $msg.html('');
                    return;
                }

                $msg.html('<span style="color:#666">⌛ Verificando...</span>');

                $.post( ajaxUrl, {
                    action: 'wcai_check_order_only',
                    order_id: val,
                    security: security
                }, function( response ) {
                    if ( response.success ) {
                        $msg.html('<span style="color:green;font-weight:bold">✅ Pedido Encontrado!</span>');
                    } else {
                        $msg.html('<span style="color:red;font-weight:bold">❌ Pedido não encontrado.</span>');
                        $this.val('');
                    }
                });
            });

            $(document).on('blur', selCPF, function() {
                var $this = $(this);
                var val = $this.val().replace(/\D/g, '');
                var ped = $(selPedido).val().trim();
                var orderKey = ( $('input[name="wcai_order_key"]').val() || new URLSearchParams( window.location.search ).get('key') || '' ).trim();
                var $msg = $('#wcai-msg-cpf');

                if ( val.length === 0 ) {
                    return;
                }

                if ( ! ped ) {
                    $msg.html('<span style="color:orange;font-weight:bold">⚠️ Preencha o Nº do Pedido primeiro.</span>');
                    $this.val('');
                    return;
                }

                $msg.html('<span style="color:#666">⌛ Buscando...</span>');

                $.post( ajaxUrl, {
                    action: 'wcai_autocheck_pax',
                    order_id: ped,
                    cpf: val,
                    order_key: orderKey,
                    security: security
                }, function( response ) {
                    if ( response.success ) {
                        $msg.html('<span style="color:green;font-weight:bold">✅ Confirmado: ' + $('<div>').text(response.data.nome).html() + '</span>');
                    } else {
                        $msg.html('<span style="color:red;font-weight:bold">❌ CPF não encontrado neste pedido.</span>');
                        $this.val('');
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    private function verify_ajax_request() {
        if ( ! check_ajax_referer( self::AJAX_NONCE, 'security', false ) ) {
            wp_send_json_error( 'Requisição inválida.', 403 );
        }
    }

    public function ajax_check_order() {
        $this->verify_ajax_request();

        $id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;

        if ( ! $id ) {
            wp_send_json_error( 'Pedido inválido.', 400 );
        }

        if ( $this->signature_rate_limited( 'check', $id ) ) {
            wp_send_json_error( 'Muitas tentativas. Aguarde alguns minutos e tente novamente.', 429 );
        }

        if ( wc_get_order( $id ) ) {
            wp_send_json_success();
        }

        wp_send_json_error();
    }

    public function ajax_check_pax_deep() {
        $this->verify_ajax_request();

        $order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
        $cpf_input = preg_replace( '/\D/', '', isset( $_POST['cpf'] ) ? wp_unslash( $_POST['cpf'] ) : '' );
        $order_key = isset( $_POST['order_key'] ) ? sanitize_text_field( wp_unslash( $_POST['order_key'] ) ) : '';

        if ( ! $order_id || ! $cpf_input ) {
            wp_send_json_error( self::LOOKUP_ERROR, 403 );
        }

        if ( $this->signature_rate_limited( 'autocheck', $order_id, $cpf_input ) ) {
            wp_send_json_error( 'Muitas tentativas. Aguarde alguns minutos e tente novamente.', 429 );
        }

        $order = wc_get_order( $order_id );
        // Mesma resposta para pedido inexistente, chave errada e CPF não vinculado (sem oráculo).
        $participant = ( $order && $this->order_key_matches( $order, $order_key ) )
            ? $this->get_participant_for_order( $order, $cpf_input )
            : false;

        if ( ! $participant ) {
            wp_send_json_error( self::LOOKUP_ERROR, 403 );
        }

        $this->set_session_cookie( $order_id, $participant['id'] );
        wp_send_json_success(
            array(
                'participant_id' => absint( $participant['id'] ),
                'nome'           => $participant['nome_completo'],
                'menor'          => class_exists( 'WCAI_Legal_Acceptances' ) && WCAI_Legal_Acceptances::guardian_required( $participant ),
            )
        );
    }

    public function render_shortcode( $atts ) {
        return '';
    }

    public function ajax_save_signature() {
        $this->verify_ajax_request();

        $pedido_id  = isset( $_POST['pedido'] ) ? absint( $_POST['pedido'] ) : 0;
        $cpf        = isset( $_POST['cpf'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['cpf'] ) ) : '';
        $img_base64 = isset( $_POST['assinatura'] ) ? wp_unslash( $_POST['assinatura'] ) : '';

        if ( $this->signature_rate_limited( 'save', $pedido_id, $cpf ) ) {
            wp_send_json_error( 'Muitas tentativas de assinatura. Aguarde alguns minutos e tente novamente.', 429 );
        }

        $order = wc_get_order( $pedido_id );
        $participant = $order && $cpf ? $this->get_participant_for_order( $order, $cpf ) : false;

        // A sessão só nasce após order_key + CPF em ajax_check_pax_deep; mesma resposta para qualquer falha.
        if ( ! $participant || ! $this->valid_session_for_participant( $pedido_id, $participant['id'] ) ) {
            wp_send_json_error( self::SESSION_ERROR, 403 );
        }

        if (
            class_exists( 'WCAI_Legal_Acceptances' ) &&
            'accepted' === WCAI_Legal_Acceptances::legal_status( $participant['id'], $participant['reservation_id'] )
        ) {
            wp_send_json_error( 'Este participante já possui aceite da versão jurídica vigente.' );
        }

        // Campos novos do formulário de assinatura; obrigatórios só para menor de 18 anos.
        $guardian_name = isset( $_POST['responsavel_nome'] ) ? sanitize_text_field( wp_unslash( $_POST['responsavel_nome'] ) ) : '';
        $guardian_cpf  = isset( $_POST['responsavel_cpf'] ) ? preg_replace( '/\D/', '', wp_unslash( $_POST['responsavel_cpf'] ) ) : '';

        if ( class_exists( 'WCAI_Legal_Acceptances' ) ) {
            $guardian = WCAI_Legal_Acceptances::validate_guardian( $participant, $guardian_name, $guardian_cpf );
            if ( is_wp_error( $guardian ) ) {
                wp_send_json_error( $guardian->get_error_message(), 400 );
            }
        }

        if ( empty( $img_base64 ) ) {
            wp_send_json_error( 'Assinatura vazia.' );
        }

        $parts   = explode( ';base64,', $img_base64, 2 );
        $decoded = base64_decode( isset( $parts[1] ) ? $parts[1] : $img_base64, true );

        if (
            false === $decoded ||
            strlen( $decoded ) > 2 * MB_IN_BYTES ||
            ! function_exists( 'getimagesizefromstring' )
        ) {
            wp_send_json_error( 'Assinatura inválida.' );
        }

        $image = getimagesizefromstring( $decoded );

        if ( ! $image || IMAGETYPE_PNG !== $image[2] ) {
            wp_send_json_error( 'A assinatura deve estar no formato PNG.' );
        }

        $post_id = wp_insert_post(
            array(
                'post_type'   => 'wcai_assinatura',
                'post_title'  => 'Participante #' . absint( $participant['id'] ) . ' - Pedido #' . absint( $pedido_id ),
                'post_status' => 'publish',
            ),
            true
        );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( 'Erro ao registrar a assinatura.' );
        }

        $stored = self::store_signature_png( $decoded, $participant['id'], $post_id );

        if ( ! $stored ) {
            wp_delete_post( $post_id, true );
            wp_send_json_error( 'Não foi possível armazenar a assinatura.' );
        }

        update_post_meta( $post_id, '_wcai_participant_id', absint( $participant['id'] ) );
        update_post_meta( $post_id, '_wcai_reservation_id', absint( $participant['reservation_id'] ) );
        update_post_meta( $post_id, '_wcai_pedido_id', absint( $participant['order_id'] ) );
        update_post_meta( $post_id, '_wcai_assinatura_file', $stored['name'] );

        $ip = self::client_ip();
        $ip = 'unknown' === $ip ? '' : $ip;
        $agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

        update_post_meta(
            $post_id,
            '_wcai_ip_hash',
            $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : ''
        );
        update_post_meta(
            $post_id,
            '_wcai_device_hash',
            $agent ? hash_hmac( 'sha256', $agent, wp_salt( 'auth' ) ) : ''
        );
        update_post_meta( $post_id, '_wcai_data_hora', current_time( 'mysql' ) );

        $ticket_data = $this->generate_and_save_ticket( $participant, $post_id, $guardian_name, $guardian_cpf );

        if ( is_wp_error( $ticket_data ) ) {
            if ( file_exists( $stored['file'] ) ) {
                wp_delete_file( $stored['file'] );
            }
            wp_delete_post( $post_id, true );
            wp_send_json_error( $ticket_data->get_error_message(), 500 );
        }

        $this->set_ticket_session_cookie( $pedido_id, absint( $participant['id'] ), $post_id );

        setcookie(
            'wcai_pax_session',
            '',
            array(
                'expires' => time() - HOUR_IN_SECONDS,
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );

        wp_send_json_success(
            array(
                'ticket_url' => $ticket_data['qr_url'],
                'ticket_hash' => $ticket_data['hash'],
                'participant_id' => absint( $participant['id'] ),
            )
        );
    }

    /**
     * Localiza primeiro a identidade operacional canônica.
     * Pedido + CPF servem apenas como mecanismo de entrada do formulário legado.
     */
    private function get_participant_for_order( $order, $cpf ) {
        if ( ! $order || ! class_exists( 'WCAI_Participants_DB' ) ) {
            return false;
        }

        $cpf = preg_replace( '/\D/', '', $cpf );
        if ( ! $cpf ) {
            return false;
        }

        foreach ( WCAI_Participants_DB::get_by_order( $order->get_id() ) as $participant ) {
            $participant_cpf = preg_replace( '/\D/', '', $participant['cpf'] );

            if ( $participant_cpf !== $cpf ) {
                continue;
            }

            $reservation_id = absint( $participant['reservation_id'] );
            if ( ! $reservation_id || ! class_exists( 'WCAI_Reservations' ) ) {
                continue;
            }

            $reservation = WCAI_Reservations::get_by_id( $reservation_id );
            if ( ! $reservation || 'confirmed' !== $reservation->status ) {
                continue;
            }

            return $participant;
        }

        return false;
    }

    // Prova de posse do pedido: order_key (a chave do link do pedido), além do CPF.
    private function order_key_matches( $order, $order_key ) {
        $expected = (string) $order->get_order_key();

        return '' !== $expected && '' !== $order_key && hash_equals( $expected, $order_key );
    }

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

    const MIGRATE_BATCH = 200;

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

    private static function client_ip() {
        $remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
        $trusted = (array) apply_filters( 'wcai_trusted_proxies', array() );

        // X-Forwarded-For só vale quando REMOTE_ADDR é um proxy confiável.
        if ( $remote && in_array( $remote, $trusted, true ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $hops = array_reverse( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) ) );

            foreach ( $hops as $hop ) {
                if ( filter_var( $hop, FILTER_VALIDATE_IP ) && ! in_array( $hop, $trusted, true ) ) {
                    return $hop;
                }
            }
        }

        return $remote ? $remote : 'unknown';
    }

    private function generate_and_save_ticket( $participant, $signature_post_id = 0, $guardian_name = '', $guardian_cpf = '' ) {
        if (
            empty( $participant['id'] ) ||
            ! class_exists( 'WCAI_Participants_DB' ) ||
            ! class_exists( 'WCAI_Legal_Acceptances' )
        ) {
            return new WP_Error( 'wcai_signature_dependencies_missing', 'Não foi possível concluir a assinatura.' );
        }

        $hash = ! empty( $participant['ticket_hash'] ) ? $participant['ticket_hash'] : '';

        if ( ! $hash ) {
            try {
                $hash = bin2hex( random_bytes( 32 ) );
            } catch ( Exception $exception ) {
                $hash = hash( 'sha256', wp_generate_uuid4() . '|' . wp_salt( 'auth' ) );
            }
        }

        $previous_hash = isset( $participant['ticket_hash'] ) ? (string) $participant['ticket_hash'] : '';
        $previous_signed = ! empty( $participant['termo_assinado'] );

        $updated = WCAI_Participants_DB::update(
            $participant['id'],
            array(
                'ticket_hash'   => $hash,
                'termo_assinado' => 1,
            )
        );

        if ( false === $updated ) {
            return new WP_Error( 'wcai_signature_participant_update', 'Não foi possível atualizar o participante.' );
        }

        $qr_url = self::generate_local_qr( $hash, absint( $participant['id'] ) );

        if ( is_wp_error( $qr_url ) ) {
            WCAI_Participants_DB::update(
                $participant['id'],
                array(
                    'ticket_hash'   => $previous_hash,
                    'termo_assinado' => $previous_signed ? 1 : 0,
                )
            );

            return $qr_url;
        }

        $acceptance = WCAI_Legal_Acceptances::record_for_participant(
            $participant['id'],
            0,
            'signature',
            $guardian_name,
            $guardian_cpf,
            $signature_post_id
        );

        if ( is_wp_error( $acceptance ) ) {
            WCAI_Participants_DB::update(
                $participant['id'],
                array(
                    'ticket_hash'   => $previous_hash,
                    'termo_assinado' => $previous_signed ? 1 : 0,
                )
            );

            if ( '' === $previous_hash ) {
                self::delete_local_qr( $hash, absint( $participant['id'] ) );
            }

            return $acceptance;
        }

        WCAI_Audit_Log::log(
            'participant_signature_recorded',
            'participant',
            $participant['id'],
            array(
                'reservation_id' => absint( $participant['reservation_id'] ),
                'acceptance_id'  => absint( $acceptance ),
            )
        );

        return array(
            'hash'   => $hash,
            'qr_url' => $qr_url,
            'nome'   => $participant['nome_completo'],
        );
    }

    public static function consume_ticket_session() {
        if ( empty( $_COOKIE['wcai_ticket_session'] ) ) {
            return false;
        }

        $parts = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE['wcai_ticket_session'] ) ) );
        if ( 5 !== count( $parts ) ) {
            return false;
        }

        $order_id = absint( $parts[0] );
        $participant_id = absint( $parts[1] );
        $signature_post_id = absint( $parts[2] );
        $expires = absint( $parts[3] );
        $payload = $order_id . '|' . $participant_id . '|' . $signature_post_id . '|' . $expires;
        $expected = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );

        setcookie(
            'wcai_ticket_session',
            '',
            array(
                'expires' => time() - HOUR_IN_SECONDS,
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );

        if (
            ! $order_id ||
            ! $participant_id ||
            ! $signature_post_id ||
            $expires < time() ||
            ! hash_equals( $expected, $parts[4] ) ||
            ! class_exists( 'WCAI_Participants_DB' )
        ) {
            return false;
        }

        $participant = WCAI_Participants_DB::get_by_id( $participant_id );
        if (
            ! $participant ||
            absint( $participant['order_id'] ) !== $order_id ||
            absint( $participant['reservation_id'] ) < 1
        ) {
            return false;
        }

        $reservation = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_by_id( absint( $participant['reservation_id'] ) ) : false;
        if ( ! $reservation || 'confirmed' !== $reservation->status ) {
            return false;
        }

        if (
            ! class_exists( 'WCAI_Legal_Acceptances' ) ||
            'accepted' !== WCAI_Legal_Acceptances::legal_status(
                $participant_id,
                absint( $participant['reservation_id'] )
            )
        ) {
            return false;
        }

        $hash = ! empty( $participant['ticket_hash'] ) ? (string) $participant['ticket_hash'] : '';
        if ( '' === $hash ) {
            return false;
        }

        $qr_url = self::generate_local_qr( $hash, $participant_id );
        if ( is_wp_error( $qr_url ) ) {
            return false;
        }

        return array(
            'hash'    => $hash,
            'qr_url'  => $qr_url,
            'nome'    => $participant['nome_completo'],
            'order_id' => $order_id,
            'participant_id' => $participant_id,
            'cpf'     => '',
        );
    }

    private function set_ticket_session_cookie( $order_id, $participant_id, $signature_post_id ) {
        $expires = time() + 5 * MINUTE_IN_SECONDS;
        $payload = absint( $order_id ) . '|' . absint( $participant_id ) . '|' . absint( $signature_post_id ) . '|' . $expires;
        $signature = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );

        setcookie(
            'wcai_ticket_session',
            $payload . '|' . $signature,
            array(
                'expires' => $expires,
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );
    }

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

        $library = dirname( __DIR__ ) . '/vendor/qrcode/qrcode.php';
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

    // Contadores separados por ação ('check', 'autocheck', 'save'): uma ação não esgota o limite das outras.
    private function signature_rate_limited( $action, $order_id, $identifier = '' ) {
        $ip = self::client_ip();
        $order_id = absint( $order_id );
        $identifier = preg_replace( '/\D/', '', (string) $identifier );
        $limit = 'save' === $action ? self::RATE_LIMIT_SAVE : self::RATE_LIMIT;

        $keys = array(
            'wcai_sig_rate_' . $action . '_order_' . md5( $ip . '|' . $order_id ),
        );

        if ( '' !== $identifier ) {
            $keys[] = 'wcai_sig_rate_' . $action . '_identifier_' . md5( $ip . '|' . $order_id . '|' . $identifier );
        }

        $limited = false;

        foreach ( $keys as $key ) {
            if ( self::bump_rate_counter( $key ) > $limit ) {
                $limited = true;
            }
        }

        return $limited;
    }

    private static function bump_rate_counter( $key ) {
        $ttl = 10 * MINUTE_IN_SECONDS;

        if ( wp_using_ext_object_cache() ) {
            wp_cache_add( $key, 0, 'wcai_rate', $ttl );
            $count = wp_cache_incr( $key, 1, 'wcai_rate' );

            if ( false !== $count ) {
                return (int) $count;
            }
        }

        // shortcut: sem object cache persistente o contador é transient (não atômico); instalar Redis/Memcached.
        $count = absint( get_transient( $key ) ) + 1;
        set_transient( $key, $count, $ttl );

        return $count;
    }

    private function set_session_cookie( $order_id, $participant_id ) {
        $expires = time() + HOUR_IN_SECONDS;
        $payload = absint( $order_id ) . '|' . absint( $participant_id ) . '|' . $expires;
        $signature = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );
        $value = $payload . '|' . $signature;

        setcookie(
            'wcai_pax_session',
            $value,
            array(
                'expires'  => $expires,
                'path'     => '/',
                'secure'   => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax',
            )
        );
    }

    private function valid_session_for_participant( $order_id, $participant_id ) {
        if ( empty( $_COOKIE['wcai_pax_session'] ) ) {
            return false;
        }

        $parts = explode( '|', sanitize_text_field( wp_unslash( $_COOKIE['wcai_pax_session'] ) ) );

        if ( 4 !== count( $parts ) ) {
            return false;
        }

        $payload = absint( $parts[0] ) . '|' . absint( $parts[1] ) . '|' . absint( $parts[2] );
        $expected = hash_hmac( 'sha256', $payload, wp_salt( 'auth' ) );

        return absint( $parts[0] ) === absint( $order_id)
            && absint( $parts[1] ) === absint( $participant_id )
            && absint( $parts[2] ) >= time()
            && hash_equals( $expected, $parts[3] );
    }

    public function register_cpt() {
        register_post_type(
            'wcai_assinatura',
            array(
                'labels' => array(
                    'name'          => 'Assinaturas',
                    'singular_name' => 'Assinatura',
                ),
                'public'          => false,
                'show_ui'         => true,
                'show_in_menu'    => 'woocommerce',
                'menu_position'   => 58,
                'menu_icon'      => 'dashicons-pen',
                'supports'        => array( 'title' ),
                'capability_type' => 'post',
                'map_meta_cap'    => false,
                'capabilities'    => array(
                    'edit_post'          => WCAI_Capabilities::MANAGE_LEGAL,
                    'read_post'          => WCAI_Capabilities::MANAGE_LEGAL,
                    'delete_post'        => WCAI_Capabilities::MANAGE_LEGAL,
                    'edit_posts'         => WCAI_Capabilities::MANAGE_LEGAL,
                    'create_posts'       => WCAI_Capabilities::MANAGE_LEGAL,
                    'publish_posts'      => WCAI_Capabilities::MANAGE_LEGAL,
                    'delete_posts'       => WCAI_Capabilities::MANAGE_LEGAL,
                    'edit_others_posts'  => WCAI_Capabilities::MANAGE_LEGAL,
                    'delete_others_posts'=> WCAI_Capabilities::MANAGE_LEGAL,
                ),
            )
        );
    }

    public function set_custom_columns( $columns ) {
        return array_merge(
            $columns,
            array(
                'participant_ref' => 'Participante',
                'pedido_ref'      => 'Pedido',
            )
        );
    }

    public function custom_column_content( $column, $post_id ) {
        if ( 'participant_ref' === $column ) {
            echo esc_html( get_post_meta( $post_id, '_wcai_participant_id', true ) ?: '—' );
        }

        if ( 'pedido_ref' === $column ) {
            echo esc_html( get_post_meta( $post_id, '_wcai_pedido_id', true ) ?: '—' );
        }
    }

    public function add_details_metabox() {
        add_meta_box(
            'wcai_sig_details',
            'Detalhes',
            array( $this, 'render_metabox' ),
            'wcai_assinatura',
            'normal',
            'high'
        );
    }

    public function render_metabox( $post ) {
        // Registros novos ficam em wcai-private (endpoint com capability); antigos mantêm a URL pública.
        $url = get_post_meta( $post->ID, '_wcai_assinatura_file', true )
            ? wp_nonce_url( admin_url( 'admin-post.php?action=wcai_signature_image&post_id=' . absint( $post->ID ) ), 'wcai_signature_image_' . absint( $post->ID ) )
            : get_post_meta( $post->ID, '_wcai_assinatura_url', true );
        $ip_hash = get_post_meta( $post->ID, '_wcai_ip_hash', true );
        $participant_id = absint( get_post_meta( $post->ID, '_wcai_participant_id', true ) );
        $reservation_id = absint( get_post_meta( $post->ID, '_wcai_reservation_id', true ) );
        $order_id = absint( get_post_meta( $post->ID, '_wcai_pedido_id', true ) );

        echo '<p><strong>Participante:</strong> ' . esc_html( $participant_id ?: '—' ) . '</p>';
        echo '<p><strong>Reserva:</strong> ' . esc_html( $reservation_id ?: '—' ) . '</p>';
        echo '<p><strong>Pedido histórico:</strong> ' . esc_html( $order_id ?: '—' ) . '</p>';
        echo '<p><strong>IP (hash):</strong> ' . esc_html( $ip_hash ?: '—' ) . '</p>';
        echo $url
            ? '<p><img src="' . esc_url( $url ) . '" style="max-width:300px;border:1px solid #ccc;" alt="Assinatura"></p>'
            : '<p>Sem imagem</p>';
    }
}
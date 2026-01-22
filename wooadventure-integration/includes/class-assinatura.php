<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Assinatura {

    public function __construct() {
        // 1. Painel Admin (CPT)
        add_action( 'init', array( $this, 'register_cpt' ) );
        add_filter( 'manage_wcai_assinatura_posts_columns', array( $this, 'set_custom_columns' ) );
        add_action( 'manage_wcai_assinatura_posts_custom_column', array( $this, 'custom_column_content' ), 10, 2 );
        add_action( 'add_meta_boxes', array( $this, 'add_details_metabox' ) );

        // 2. Shortcode e Scripts
        add_shortcode( 'wcai_painel_assinatura', array( $this, 'render_shortcode' ) );
        
        // SCRIPT DE VALIDAÇÃO E CAPTURA
        add_shortcode( 'wcai_script_validacao', array( $this, 'render_validation_script_shortcode' ) );

        // 3. AJAX
        add_action( 'wp_ajax_wcai_check_order_only', array($this, 'ajax_check_order') );
        add_action( 'wp_ajax_nopriv_wcai_check_order_only', array($this, 'ajax_check_order') );
        add_action( 'wp_ajax_wcai_autocheck_pax', array($this, 'ajax_check_pax_deep') );
        add_action( 'wp_ajax_nopriv_wcai_autocheck_pax', array($this, 'ajax_check_pax_deep') );
        
        // Salvamento Final
        add_action( 'wp_ajax_wcai_salvar_assinatura_final', array( $this, 'ajax_save_signature' ) );
        add_action( 'wp_ajax_nopriv_wcai_salvar_assinatura_final', array( $this, 'ajax_save_signature' ) );
    }

    // =========================================================================
    // 1. SCRIPT DE VALIDAÇÃO E CAPTURA DE DADOS (APRIMORADO)
    // =========================================================================
    public function render_validation_script_shortcode() {
        $id_pedido = 'esig-sif-1739375553756'; 
        $id_cpf    = 'esig-sif-1739375595096';
        $id_email  = 'esig-sad-email'; 

        ob_start();
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
            var selPedido = 'input[name="<?php echo $id_pedido; ?>"]';
            var selCPF    = 'input[name="<?php echo $id_cpf; ?>"]';
            
            // Seletor Robusto para o Email
            var selEmail  = '#<?php echo $id_email; ?>, input[name="<?php echo $id_email; ?>"]';

            // 1. Validação do Pedido
            $(document).on('blur', selPedido, function() {
                var $this = $(this); var val = $this.val().trim(); var $msg = $('#wcai-msg-pedido');
                if(val.length === 0) { $msg.html(''); return; }
                $msg.html('<span style="color:#666">⌛ Verificando...</span>');
                $.post(ajaxUrl, { action:'wcai_check_order_only', order_id:val }, function(r){
                    if(r.success) { $msg.html('<span style="color:green;font-weight:bold">✅ Pedido Encontrado!</span>'); } 
                    else { $msg.html('<span style="color:red;font-weight:bold">❌ Pedido não encontrado.</span>'); $this.val(''); }
                });
            });

            // 2. Validação do CPF
            $(document).on('blur', selCPF, function() {
                var $this = $(this); var val = $this.val().replace(/\D/g, ''); var ped = $(selPedido).val().trim(); var $msg = $('#wcai-msg-cpf');
                if(val.length === 0) return;
                if(!ped) { $msg.html('<span style="color:orange;font-weight:bold">⚠️ Preencha o Nº do Pedido primeiro.</span>'); $this.val(''); return; }
                $msg.html('<span style="color:#666">⌛ Buscando...</span>');
                $.post(ajaxUrl, { action:'wcai_autocheck_pax', order_id:ped, cpf:val }, function(r){
                    if(r.success) { $msg.html('<span style="color:green;font-weight:bold">✅ Confirmado: ' + r.data.nome + '</span>'); } 
                    else { $msg.html('<span style="color:red;font-weight:bold">❌ CPF não encontrado neste pedido.</span>'); $this.val(''); }
                });
            });

            // 3. CAPTURA DE E-MAIL IMEDIATA (Nova Lógica)
            // Salva o cookie assim que o usuário digita e sai do campo
            $(document).on('blur change input', selEmail, function() {
                var emailDigitado = $(this).val();
                if(emailDigitado && emailDigitado.includes('@')) {
                    // Cria o cookie manualmente via JS com validade de 1 hora
                    document.cookie = "wcai_pax_email_temp=" + encodeURIComponent(emailDigitado) + "; path=/; max-age=3600";
                    console.log('[WCAI] Cookie de e-mail atualizado: ' + emailDigitado);
                }
            });

            // Backup: Tenta injetar no AJAX também, caso o cookie falhe
            $(document).ajaxSend(function(event, jqxhr, settings) {
                if (settings.data && settings.data.indexOf('action=wcai_salvar_assinatura_final') !== -1) {
                    var emailCapturado = $(selEmail).val();
                    if(emailCapturado) {
                        settings.data += '&email_extra=' + encodeURIComponent(emailCapturado);
                    }
                }
            });
        });
        </script>
        <?php return ob_get_clean();
    }

    public function ajax_check_order() { $id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0; if(wc_get_order($id)) wp_send_json_success(); else wp_send_json_error(); }
    public function ajax_check_pax_deep() {
        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $cpf_input = preg_replace('/\D/', '', isset($_POST['cpf']) ? $_POST['cpf'] : ''); 
        if ( !$order_id || !$cpf_input ) { wp_send_json_error(); return; }
        $order = wc_get_order($order_id); if ( !$order ) { wp_send_json_error(); return; }
        $found = false; $nome_encontrado = 'Participante';
        if(class_exists('WCAI_Participants_DB')) {
            $db_pax = WCAI_Participants_DB::get_by_order($order_id);
            if(is_array($db_pax)) { foreach($db_pax as $p) { if(preg_replace('/[^0-9]/','',$p['cpf']) === $cpf_input) { $found = true; $nome_encontrado = $p['nome_completo']; break; } } }
        }
        if(!$found) {
            $b_cpf = preg_replace('/\D/', '', $order->get_meta('_billing_cpf') ?: $order->get_meta('billing_cpf'));
            if($b_cpf === $cpf_input) { $found = true; $nome_encontrado = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(); }
        }
        if ( $found ) { setcookie('wcai_pax_session', $order_id.'|'.$cpf_input, time() + 3600, '/'); wp_send_json_success( array( 'nome' => $nome_encontrado ) ); } 
        else { wp_send_json_error('CPF não vinculado.'); }
    }
    public function render_shortcode( $atts ) { return ''; }

    // =========================================================================
    // 4. SALVAMENTO NO BACKEND
    // =========================================================================
    public function ajax_save_signature() {
        $pedido_id  = sanitize_text_field($_POST['pedido']);
        $cpf        = sanitize_text_field($_POST['cpf']);
        $img_base64 = $_POST['assinatura'];
        
        // Tenta pegar o e-mail via POST (Backup)
        $email_participante = '';
        if ( isset($_POST['email_extra']) && is_email($_POST['email_extra']) ) {
            $email_participante = sanitize_email($_POST['email_extra']);
        }
        
        // Se capturou via AJAX, reforça o cookie no PHP também
        if($email_participante) {
            setcookie('wcai_pax_email_temp', $email_participante, time() + 3600, '/');
        }

        if (empty($img_base64)) wp_send_json_error('Assinatura vazia.');

        $post_id = wp_insert_post([
            'post_type' => 'wcai_assinatura',
            'post_title' => "$cpf - Pedido $pedido_id",
            'post_status' => 'publish'
        ]);
        
        if (is_wp_error($post_id)) wp_send_json_error('Erro DB');

        $parts = explode(";base64,", $img_base64);
        $decoded = base64_decode(isset($parts[1]) ? $parts[1] : $img_base64);
        $filename = "assign_{$pedido_id}_{$post_id}.png";
        $upload = wp_upload_bits($filename, null, $decoded);

        if ($upload['error']) wp_send_json_error($upload['error']);

        update_post_meta($post_id, '_wcai_pedido_id', $pedido_id);
        update_post_meta($post_id, '_wcai_cpf_cliente', $cpf);
        update_post_meta($post_id, '_wcai_assinatura_url', $upload['url']);
        update_post_meta($post_id, '_wcai_ip', $_SERVER['REMOTE_ADDR']);
        update_post_meta($post_id, '_wcai_device', $_SERVER['HTTP_USER_AGENT']);
        update_post_meta($post_id, '_wcai_data_hora', current_time('mysql'));
        
        if($email_participante) {
            update_post_meta($post_id, '_wcai_signer_email', $email_participante);
        }

        $ticket_data = $this->generate_and_save_ticket($pedido_id, $cpf);

        wp_send_json_success(['ticket_url' => $ticket_data['qr_url']]);
    }

    private function generate_and_save_ticket($order_id, $cpf_clean) {
        if(!class_exists('WCAI_Participants_DB')) return false;
        
        global $wpdb;
        $table = WCAI_Participants_DB::get_table_name();
        
        $pax = $wpdb->get_row($wpdb->prepare("SELECT id, nome_completo, ticket_hash FROM $table WHERE order_id = %d AND REPLACE(REPLACE(cpf,'.',''),'-','') = %s", $order_id, $cpf_clean));
        
        if(!$pax) {
            $order = wc_get_order($order_id);
            if(!$order) return false;

            $found_data = false;
            $b_cpf = preg_replace('/\D/', '', $order->get_meta('_billing_cpf') ?: $order->get_meta('billing_cpf'));
            
            if($b_cpf === $cpf_clean) {
                $found_data = array(
                    'order_id' => $order_id,
                    'customer_id' => $order->get_customer_id(),
                    'nome_completo' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'cpf' => $b_cpf,
                    'data_nascimento' => $order->get_meta('billing_birthdate') ?: ''
                );
            }

            if(!$found_data) {
                $meta = $order->get_meta('_additional_participants');
                if(is_array($meta)) {
                    foreach($meta as $m) {
                        if(preg_replace('/[^0-9]/','',$m['cpf']) === $cpf_clean) {
                            $found_data = array(
                                'order_id' => $order_id,
                                'customer_id' => $order->get_customer_id(),
                                'nome_completo' => $m['nome_completo'],
                                'cpf' => $m['cpf'],
                                'data_nascimento' => $m['data_nascimento']
                            );
                            break;
                        }
                    }
                }
            }

            if($found_data) {
                WCAI_Participants_DB::add($found_data);
                $pax = $wpdb->get_row($wpdb->prepare("SELECT id, nome_completo, ticket_hash FROM $table WHERE order_id = %d AND REPLACE(REPLACE(cpf,'.',''),'-','') = %s", $order_id, $cpf_clean));
            }
        }

        if(!$pax) return false;

        $hash = $pax->ticket_hash;
        if(empty($hash)) {
            $salt = wp_salt();
            $hash = md5($pax->id . $order_id . $salt . time());
            WCAI_Participants_DB::update($pax->id, array('ticket_hash' => $hash, 'termo_assinado' => 1));
        } else {
            WCAI_Participants_DB::update($pax->id, array('termo_assinado' => 1));
        }

        $qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . $hash;
        return array('hash' => $hash, 'qr_url' => $qr_url, 'nome' => $pax->nome_completo);
    }

    public function register_cpt() {
        register_post_type('wcai_assinatura', array(
            'labels' => array('name' => 'Assinaturas', 'singular_name' => 'Assinatura'),
            'public' => false, 'show_ui' => true, 'show_in_menu' => true, 'menu_position' => 58, 'menu_icon' => 'dashicons-pen',
            'supports' => array('title'), 'capabilities' => array('create_posts' => false), 'map_meta_cap' => true
        ));
    }
    public function set_custom_columns($c) { return array_merge($c, ['pedido_ref'=>'Pedido', 'cpf_ref'=>'CPF']); }
    public function custom_column_content($c, $pid) {
        if($c=='pedido_ref') echo get_post_meta($pid, '_wcai_pedido_id', true);
        if($c=='cpf_ref') echo get_post_meta($pid, '_wcai_cpf_cliente', true);
    }
    public function add_details_metabox() { add_meta_box('wcai_sig_details', 'Detalhes', array($this,'render_metabox'), 'wcai_assinatura', 'normal', 'high'); }
    public function render_metabox($post) {
        $url = get_post_meta($post->ID, '_wcai_assinatura_url', true);
        $ip = get_post_meta($post->ID, '_wcai_ip', true);
        $email = get_post_meta($post->ID, '_wcai_signer_email', true);
        echo "<p><strong>IP:</strong> $ip</p>";
        if($email) echo "<p><strong>E-mail (Participante):</strong> $email</p>";
        echo $url ? "<img src='$url' style='max-width:300px; border:1px solid #ccc;'>" : "Sem imagem";
    }
}

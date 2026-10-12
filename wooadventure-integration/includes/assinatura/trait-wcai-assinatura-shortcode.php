<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Assinatura_Shortcode {

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
                var orderKey = ( $('input[name="wcai_order_key"]').val() || new URLSearchParams( window.location.search ).get('key') || '' ).trim();

                if ( val.length === 0 ) {
                    $msg.html('');
                    return;
                }

                $msg.html('<span style="color:#666">⌛ Verificando...</span>');

                $.post( ajaxUrl, {
                    action: 'wcai_check_order_only',
                    order_id: val,
                    order_key: orderKey,
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

    public function render_shortcode( $atts ) {
        return '';
    }
}

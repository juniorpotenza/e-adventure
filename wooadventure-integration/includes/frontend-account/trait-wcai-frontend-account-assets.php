<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Frontend_Account_Assets {
    // Assets modal
    public function modal_assets() {
        if ( ! is_account_page() ) {
            return;
        }
        ?>
        <style>
            .wcai-modal-overlay { position: fixed; z-index: 99999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; }
            .wcai-modal-content { text-align: left; background: #fff; width: 90%; max-width: 800px; border-radius: 10px; display: flex; flex-direction: column; max-height: 90vh; overflow: hidden; }
            .wcai-modal-header { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; }
            .wcai-close-modal { min-width: 44px; min-height: 44px; font-size: 32px; font-weight: bold; line-height: 1; cursor: pointer; color: #50575e; padding: 0 10px; background: none; border: 0; transition: color 0.2s; }
            .wcai-close-modal:hover, .wcai-close-modal:focus { color: #000; }
            .wcai-participants-form { display: flex; flex-direction: column; flex: 1; min-height: 0; }
            .wcai-scroll-area { overflow-y: auto; padding: 20px; flex: 1; }
            .wcai-modal-notice { margin-bottom: 15px; padding: 15px; border-radius: 5px; font-size: 14px; line-height: 1.5; border: 1px solid; }
            .wcai-modal-notice--ok { background: #d4edda; color: #155724; border-color: #c3e6cb; }
            .wcai-modal-notice--info { background: #f0f0f1; color: #1d2327; border-color: #dcdcde; }
            .wcai-modal-notice--warn { background: #fff3cd; color: #664d03; border-color: #ffeeba; }
            a.wcai-modal-sign { color: inherit; font-weight: 700; text-decoration: underline; display: inline-block; margin-top: 6px; }
            .wcai-pcard { border: 1px solid #dcdcde; border-radius: 8px; padding: 12px 14px; margin: 0 0 14px; display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 12px; }
            .wcai-pcard legend { font-weight: 600; padding: 0 6px; }
            .wcai-pfield { min-width: 0; }
            .wcai-pfield label { display: block; font-weight: 600; font-size: 14px; margin-bottom: 4px; color: #1d2327; }
            .wcai-pfield input[type=text] { width: 100%; box-sizing: border-box; min-height: 44px; font-size: 16px; border: 1px solid #8c8f94; border-radius: 6px; padding: 8px; background: #fff; color: #1d2327; }
            .wcai-pfield input[readonly], .wcai-pfield input[disabled] { background: #f6f7f7; }
            .wcai-pfield small { display: block; color: #50575e; font-size: 13px; margin-top: 4px; }
            .wcai-modal-legend { margin-top: 10px; font-size: 13px; color: #50575e; }
            .wcai-modal-actions { padding: 15px 20px; border-top: 1px solid #eee; text-align: right; }
            .wcai-modal-actions .wcai-save { background: #1d2327; color: #fff; border: 0; border-radius: 6px; min-height: 48px; padding: 0 28px; font-size: 16px; font-weight: 600; cursor: pointer; }
            .wcai-modal-actions .wcai-save:focus-visible { outline: 3px solid #2271b1; outline-offset: 2px; }
            .wcai-msg { display: inline-block; margin-right: 10px; font-size: 14px; }
            a.woocommerce-button.wcai-action-success { background-color: #1e7e34 !important; color: #fff !important; border-color: #1e7e34 !important; }
            a.woocommerce-button.wcai-action-pending { background-color: #ffba00 !important; color: #1d2327 !important; border-color: #ffba00 !important; }
            @media (max-width: 600px) {
                .wcai-modal-content { width: 96%; max-height: 94vh; }
                .wcai-scroll-area { padding: 14px; }
                .wcai-pcard { grid-template-columns: 1fr; }
                .wcai-modal-actions { display: flex; flex-direction: column; gap: 8px; text-align: left; }
                .wcai-modal-actions .wcai-save { width: 100%; }
            }
        </style>
        <script>
        jQuery(document).ready(function($){
            const setupMasks = () => {
                if (!$.fn || !$.fn.mask) { return; }
                $('.wcai-mask-cpf').not('[data-masked]').mask('000.000.000-00');
                // Nascimento chega só com o ano ("**/**/1985", data-masked): a máscara de data só entra quando a pessoa digita.
                $('.wcai-mask-date').not('[data-masked]').mask('00/00/0000');
            };
            setupMasks();
            // CPF e nascimento chegam mascarados (529.***.***-25, **/**/1985): ao digitar, descarta a máscara e vira campo normal
            // (o nascimento também descarta a flag nasc_unchanged). Sem digitar, o servidor mantém o valor guardado.
            $(document).on('focusin', 'input[data-masked]', function(){ this.select(); });
            $(document).on('input', 'input[data-masked]', function(){
                var digits = this.value.replace(/\D/g, ''), isDate = $(this).hasClass('wcai-mask-date');
                $(this).removeAttr('data-masked');
                if (isDate) { $(this).closest('.wcai-pfield').find('input[name$="[nasc_unchanged]"]').remove(); }
                this.value = digits;
                if ($.fn && $.fn.mask) { $(this).mask(isDate ? '00/00/0000' : '000.000.000-00'); }
            });
            var i18n = { processing: <?php echo wp_json_encode( __( 'Processando...', 'wooadventure-integration' ) ); ?>, failed: <?php echo wp_json_encode( __( 'Não foi possível salvar. Tente novamente.', 'wooadventure-integration' ) ); ?> };
            var lastFocus = null;
            function closeModal(modal){
                modal.hide().attr('aria-hidden', 'true');
                if (lastFocus && lastFocus.focus) lastFocus.focus();
            }
            $(document).on('click', '.wcai-open-modal', function(){
                lastFocus = this;
                var modal = $('#'+$(this).data('target'));
                modal.css('display','flex').attr('aria-hidden', 'false');
                modal.find('.wcai-close-modal').trigger('focus');
            });
            $(document).on('click', '.wcai-close-modal', function(){ closeModal($(this).closest('.wcai-modal-overlay')); });
            $(document).on('keydown', function(e){
                var modal = $('.wcai-modal-overlay:visible');
                if (!modal.length) return;
                if (e.key === 'Escape') { closeModal(modal); return; }
                if (e.key !== 'Tab') return;
                var items = modal.find('a[href], button:not([disabled]), input:not([disabled]), select, textarea');
                if (!items.length) return;
                var first = items.first()[0], last = items.last()[0];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            });
            $('.wcai-participants-form').on('submit', function(e){
                e.preventDefault();
                var form = $(this), btn = form.find('button[type=submit]'), msg = form.find('.wcai-msg'), label = btn.text();
                btn.prop('disabled', true).text(i18n.processing);
                $.post(<?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>, form.serialize() + '&action=wcai_update_participants', function(r){
                    if(r.success){ msg.css('color','#1e7e34').text(r.data.message); setTimeout(()=>location.reload(), 1000); }
                    else { msg.css('color','#b32d2e').text(r.data); btn.prop('disabled', false).text(label); }
                }).fail(function(){
                    msg.css('color','#b32d2e').text(i18n.failed);
                    btn.prop('disabled', false).text(label);
                });
            });
        });
        </script>
        <?php
    }

    // CSS das páginas do cliente (lista de pedidos, "Ver pedido", obrigado). Impresso no <head>: no tema de blocos a confirmação renderiza antes do footer.
    public function print_order_pages_css() {
        $on_account = function_exists( 'is_account_page' ) && is_account_page();
        $on_received = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-received' );
        if ( ! $on_account && ! $on_received ) return;
        ?>
        <style id="wcai-order-pages-css">
            .wcai-sign-all { display: inline-block; box-sizing: border-box; min-height: 44px; background: #1d2327; color: #fff; padding: 14px 24px; border-radius: 6px; font-weight: 600; line-height: 1.2; text-decoration: none; text-align: center; }
            .wcai-sign-all:hover { background: #000; color: #fff; }
            .wcai-sign-all:focus-visible, .wcai-sign-one:focus-visible, .wcai-open-modal:focus-visible { outline: 3px solid #2271b1; outline-offset: 2px; }
            a.wcai-sign-all--list { display: block; margin-top: 8px; }
            .wcai-open-modal { min-height: 44px; }
            .wcai-pt { width: 100%; border-collapse: collapse; margin-top: 10px; border: 1px solid #e5e5e5; }
            .wcai-pt th { text-align: left; padding: 10px; border-bottom: 2px solid #eee; background: #f8f8f8; }
            .wcai-pt td { padding: 12px 10px; border-bottom: 1px solid #eee; vertical-align: middle; }
            .wcai-pt .wcai-pt-center { text-align: center; }
            .wcai-pt td strong { display: block; }
            .wcai-pt small { display: block; color: #50575e; font-size: 13px; }
            .wcai-pt .wcai-qr-note { text-align: left; max-width: 260px; margin: 0 auto; }
            .wcai-pt-action { margin-top: 6px; }
            .wcai-sign-one { white-space: nowrap; display: inline-block; box-sizing: border-box; min-height: 44px; padding: 10px 14px; border: 1px solid #1d2327; border-radius: 6px; background: #fff; color: #1d2327; font-weight: 600; line-height: 1.6; text-decoration: none; }
            .wcai-whatsapp { display: inline-block; padding: 10px 14px; border-radius: 6px; background: #0f7a3f; color: #fff; font-size: 13px; font-weight: 600; text-decoration: none; }
            @media (max-width: 768px) {
                .woocommerce-orders-table tbody th.woocommerce-orders-table__cell-order-number { display: block !important; font-size: 1.1rem; font-weight: 700; padding: 12px 10px; background: #f6f7f7; border-bottom: 1px solid #dcdcde; }
                .woocommerce-orders-table th.woocommerce-orders-table__cell-order-number::before { content: attr(data-title) " "; }
            }
            @media (max-width: 600px) {
                .wcai-pt, .wcai-pt tbody, .wcai-pt tr, .wcai-pt td { display: block; width: 100%; box-sizing: border-box; }
                .wcai-pt { border: 0; }
                .wcai-pt thead { display: none; }
                .wcai-pt tr { border: 1px solid #dcdcde; border-radius: 8px; margin-bottom: 12px; padding: 8px 12px; background: #fff; }
                .wcai-pt td { border: 0; padding: 8px 0; text-align: left; }
                .wcai-pt td + td { border-top: 1px solid #f0f0f1; }
                .wcai-pt td::before { content: attr(data-label); display: block; margin-bottom: 4px; font-size: 12px; font-weight: 600; color: #50575e; }
                .wcai-pt .wcai-qr-note { max-width: none; margin: 0; }
            }
        </style>
        <?php
    }
}

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
            .wcai-modal-content { background: #fff; width: 90%; max-width: 800px; border-radius: 10px; display: flex; flex-direction: column; max-height: 90vh; overflow: hidden; }
            .wcai-modal-header { padding: 15px 20px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; background: #f8f9fa; }
            .wcai-close-modal { min-width: 44px; min-height: 44px; font-size: 32px; font-weight: bold; line-height: 1; cursor: pointer; color: #50575e; padding: 0 10px; background: none; border: 0; transition: color 0.2s; }
            .wcai-close-modal:hover, .wcai-close-modal:focus { color: #000; }
            .wcai-participants-form { display: flex; flex-direction: column; flex: 1; min-height: 0; }
            .wcai-scroll-area { overflow-y: auto; padding: 20px; flex: 1; }
            .wcai-frontend-table { width: 100%; border-collapse: collapse; }
            .wcai-frontend-table td, .wcai-frontend-table th { padding: 12px 8px; border-bottom: 1px solid #eee; }
            .wcai-modal-actions { padding: 15px 20px; border-top: 1px solid #eee; text-align: right; }
            a.woocommerce-button.wcai-action-success { background-color: #1e7e34 !important; color: #fff !important; border-color: #1e7e34 !important; }
            a.woocommerce-button.wcai-action-pending { background-color: #ffba00 !important; color: #1d2327 !important; border-color: #ffba00 !important; }
            @media (max-width: 600px) { .wcai-frontend-table thead { display: none; } .wcai-frontend-table td { display: block; width: 100%; border: 0; } }
        </style>
        <script>
        jQuery(document).ready(function($){
            const setupMasks = () => {
                $('.wcai-mask-cpf').mask('000.000.000-00');
                $('.wcai-mask-date').mask('00/00/0000');
            };
            setupMasks();
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
}

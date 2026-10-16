<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Scanner_Page {

    public function render_scanner_app() {

        if ( ! is_user_logged_in() ) {

            ob_start(); wp_login_form(['redirect' => add_query_arg('fullscreen','1',get_permalink())]); return '<div style="padding:40px;"><h3>Login Necessário</h3>'.ob_get_clean().'</div>';

        }

        if ( ! current_user_can( WCAI_Capabilities::CHECK_IN ) ) return '<div style="padding:20px;color:red">Acesso Negado</div>';



        $ajax_url = admin_url('admin-ajax.php', 'https');
        $ajax_nonce = wp_create_nonce( 'wcai_scanner_nonce' );

        $logout_url = wp_logout_url(add_query_arg('fullscreen','1',get_permalink()));



        ob_start();

        $this->scanner_html( $logout_url );
        $this->scanner_css();
        $this->scanner_js_core( $ajax_url, $ajax_nonce );
        $this->scanner_js_list();
        $this->scanner_js_calendar();


        return ob_get_clean();

    }

}

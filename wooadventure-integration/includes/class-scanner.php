<?php

if ( ! defined( 'ABSPATH' ) ) exit;



require_once __DIR__ . '/scanner/trait-wcai-scanner-pwa.php';
require_once __DIR__ . '/scanner/trait-wcai-scanner-page.php';
require_once __DIR__ . '/scanner/trait-wcai-scanner-page-html.php';
require_once __DIR__ . '/scanner/trait-wcai-scanner-page-css.php';
require_once __DIR__ . '/scanner/trait-wcai-scanner-js-core.php';
require_once __DIR__ . '/scanner/trait-wcai-scanner-js-list.php';
require_once __DIR__ . '/scanner/trait-wcai-scanner-js-calendar.php';
require_once __DIR__ . '/scanner/trait-wcai-scanner-data.php';

class WCAI_Scanner {

    const JQUERY_SRC = 'https://code.jquery.com/jquery-3.6.0.min.js';
    const JQUERY_SRI = 'sha384-vtXRMe3mGCbOeY7l30aIg8H9p3GdeSe4IFlP6G8JMa7o7lXvnz3GFKzPxzJdPfGK';
    const QRCODE_SRC = 'https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js';
    const QRCODE_SRI = 'sha384-c9d8RFSL+u3exBOJ4Yp3HUJXS4znl9f+z66d1y54ig+ea249SpqR+w1wyvXz/lk+';



    public function __construct() {

        add_shortcode( 'wcai_scanner_app', array( $this, 'render_scanner_app' ) );

        

        // AJAX Endpoints

        add_action( 'wp_ajax_wcai_get_calendar_data', array( $this, 'ajax_get_calendar_data' ) );

        add_action( 'wp_ajax_wcai_sync_data', array( $this, 'ajax_sync_data' ) );

        

        // PWA

        add_action( 'template_redirect', array( $this, 'handle_requests' ) );

        

        // Limpeza de Scripts (Iubenda Killer)

        add_action( 'wp_enqueue_scripts', array( $this, 'dequeue_unwanted_scripts' ), 999 );

        add_action( 'wp_footer', array( $this, 'remove_footer_scripts' ), 1 );

    }

    use WCAI_Scanner_Pwa, WCAI_Scanner_Page, WCAI_Scanner_PageHtml, WCAI_Scanner_PageCss, WCAI_Scanner_JsCore, WCAI_Scanner_JsList, WCAI_Scanner_JsCalendar, WCAI_Scanner_Data;

}

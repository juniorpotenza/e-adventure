<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Scanner_Pwa {

    public function dequeue_unwanted_scripts() {

        if ( isset($_GET['fullscreen']) && $_GET['fullscreen'] == '1' ) {

            $handles = ['iubenda-cs', 'iubenda-cookie-solution', 'cookie-notice', 'gdpr-cookie-compliance'];

            foreach($handles as $h) { wp_dequeue_script($h); wp_deregister_script($h); }

        }

    }

    

    public function remove_footer_scripts() {

        if ( isset($_GET['fullscreen']) && $_GET['fullscreen'] == '1' ) {

            remove_all_actions('wp_footer'); 

        }

    }



    public function handle_requests() {

        if ( isset($_GET['wcai_pwa']) ) {

            if($_GET['wcai_pwa'] == 'manifest') { $this->render_manifest_json(); exit; }

            if($_GET['wcai_pwa'] == 'sw') { $this->render_service_worker(); exit; }

        }

        if ( isset($_GET['fullscreen']) && $_GET['fullscreen'] == '1' && is_page() ) {

            global $post;

            if ( has_shortcode( $post->post_content, 'wcai_scanner_app' ) ) {

                $this->render_clean_layout();

                exit; 

            }

        }

    }



    private function render_manifest_json() {

        header('Content-Type: application/json');

        $icon = get_site_icon_url(192) ?: 'https://cdn-icons-png.flaticon.com/512/3126/3126504.png';

        $start = $this->get_scanner_url_automagic();

        $start = add_query_arg('fullscreen', '1', $start);

        

        echo json_encode([

            "name" => $this->get_operation_name(),

            "short_name" => mb_substr( $this->get_operation_name(), 0, 12 ),

            "start_url" => $start,

            "display" => "standalone",

            "background_color" => "#ffffff",

            "theme_color" => "#007cba",

            "icons" => [[ "src" => $icon, "sizes" => "192x192", "type" => "image/png" ]]

        ]);

    }



    private function get_scanner_url_automagic() {

        global $wpdb;

        $id = $wpdb->get_var("SELECT ID FROM $wpdb->posts WHERE post_content LIKE '%[wcai_scanner_app]%' AND post_status='publish' LIMIT 1");

        return $id ? get_permalink($id) : site_url();

    }



    private function render_service_worker() {

        header('Content-Type: application/javascript');

        header('Service-Worker-Allowed: /');

        $page = add_query_arg( 'fullscreen', '1', $this->get_scanner_url_automagic() );

        // Página: rede primeiro, cache como fallback offline. Scripts: cache primeiro.
        echo 'const CACHE_NAME=' . wp_json_encode( 'wcai-op-v38' ) . ';const PAGE=' . wp_json_encode( $page ) . ';const URLS=' . wp_json_encode( array( self::JQUERY_SRC, self::QRCODE_SRC ) ) . ';'
            . "self.addEventListener('install',e=>{self.skipWaiting();e.waitUntil(caches.open(CACHE_NAME).then(c=>c.addAll(URLS)))});"
            . "self.addEventListener('activate',e=>{e.waitUntil(caches.keys().then(ks=>Promise.all(ks.filter(k=>k.startsWith('wcai-op-')&&k!==CACHE_NAME).map(k=>caches.delete(k)))).then(()=>self.clients.claim()))});"
            . "self.addEventListener('fetch',e=>{const r=e.request;if(r.method!=='GET')return;"
            . "if(r.mode==='navigate'&&r.url===PAGE){e.respondWith(fetch(r).then(x=>{if(x.ok){const k=x.clone();caches.open(CACHE_NAME).then(c=>c.put(r,k))}return x}).catch(()=>caches.match(r)));return}"
            . "if(URLS.includes(r.url))e.respondWith(caches.match(r).then(x=>x||fetch(r)))});";

    }



    private function render_clean_layout() {

        $man = site_url('/?wcai_pwa=manifest'); $sw = site_url('/?wcai_pwa=sw');

        ?>

        <!DOCTYPE html>

        <html lang="pt-BR">

        <head>

            <meta charset="UTF-8">

            <meta name="viewport" content="width=device-width, initial-scale=1.0">

            <title><?php echo esc_html( $this->get_operation_name() ); ?></title>

            <link rel="manifest" href="<?php echo $man; ?>">

            <meta name="theme-color" content="#007cba">

            <meta name="apple-mobile-web-app-capable" content="yes">

            <script src="<?php echo esc_url( self::JQUERY_SRC ); ?>" integrity="<?php echo esc_attr( self::JQUERY_SRI ); ?>" crossorigin="anonymous"></script>

            <script src="<?php echo esc_url( self::QRCODE_SRC ); ?>" integrity="<?php echo esc_attr( self::QRCODE_SRI ); ?>" crossorigin="anonymous"></script>

            <?php wp_head(); ?>

            <style>

                body,html{margin:0;padding:0;background:#f4f7f6;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;height:100%;overscroll-behavior:none;user-select:none;}

                #wpadminbar{display:none!important}

                .wcai-clean-wrapper{max-width:600px;margin:0 auto;background:#fff;height:100vh;display:flex;flex-direction:column;position:relative;}

                

                #iubenda-cs-banner, .iubenda-cs-container, .iubenda-ibadge, #iubenda-iframe, .iubenda-cs-content, .iubenda-banner-content, iframe[title*="Iubenda"], .iubenda-cs-opt-group {

                    display: none !important; opacity: 0 !important; visibility: hidden !important; pointer-events: none !important; z-index: -99999 !important; width: 0 !important; height: 0 !important; position: absolute !important; left: -9999px !important;

                }

            </style>

        </head>

        <body><div class="wcai-clean-wrapper"><?php echo do_shortcode('[wcai_scanner_app]'); ?></div>

        <script>if('serviceWorker' in navigator)navigator.serviceWorker.register('<?php echo $sw; ?>');</script>

        <?php wp_footer(); ?></body></html>

        <?php

    }

}

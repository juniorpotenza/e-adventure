<?php
// PHPUnit: stubs mínimos de WordPress (sem WP_UnitTestCase). Suite "f20"; processo separado (stubs guardados por class_exists).
// Cobre (rodada 4 das páginas do cliente): estado único do termo (pedido encerrado, sem participantes), nome em title-case no modal,
// nascimento mascarado (só o ano) com flag "não alterado", cartão "Próximo passo" do painel, menu sem "Downloads",
// links do termo em nova aba e renomeações ("Termo de responsabilidade", "Ingresso (QR)").
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! class_exists( 'WC_Order' ) ) {
    class WC_Order {}
}

if ( ! class_exists( 'WCAI_Participants_DB' ) ) {
    class WCAI_Participants_DB {
        public static $rows = array();
        public static $updates = array();
        public static $added = array();
        public static function get_by_order( $order_id ) { return self::$rows; }
        public static function get_by_id( $id ) {
            foreach ( self::$rows as $r ) {
                if ( (int) $r['id'] === (int) $id ) { return $r; }
            }
            return null;
        }
        public static function update( $id, $item ) { self::$updates[ $id ] = $item; }
        public static function add( $item ) { self::$added[] = $item; }
    }
}

if ( ! class_exists( 'WCAI_Legal_Acceptances' ) ) {
    class WCAI_Legal_Acceptances {
        public static $accepted = array();
        public static function get_by_participant( $id ) { return in_array( (int) $id, self::$accepted, true ) ? array( 'id' => 1 ) : array(); }
    }
}

if ( ! class_exists( 'WCAI_Reservations' ) ) {
    class WCAI_Reservations {
        public static function get_by_id( $id ) { return null; }
    }
}

if ( ! class_exists( 'WCAI_Settings' ) ) {
    class WCAI_Settings {
        public static function get_terms_url() { return 'https://site.test/termo/'; }
        public static function is_cpf_blocked( $cpf ) { return false; }
    }
}

if ( ! class_exists( 'WCAI_Assinatura' ) ) {
    class WCAI_Assinatura {
        public static function generate_local_qr( $hash, $id ) { return 'https://site.test/qr/' . $hash . '.png'; }
    }
}

if ( ! class_exists( 'WcaiF20Item' ) ) {
    class WcaiF20Item {
        public function get_id() { return 1; }
        public function get_quantity() { return 3; }
        public function get_variation_id() { return 0; }
        public function get_product_id() { return 99; }
        public function get_meta( $key, $single = true ) { return '_wcai_departure_id' === $key ? 5 : ''; }
    }
}

if ( ! class_exists( 'WcaiF20Order' ) ) {
    class WcaiF20Order extends WC_Order {
        public $status;
        public function __construct( $status = 'processing' ) { $this->status = $status; }
        public function get_id() { return 101; }
        public function get_meta( $key ) { return 'billing_cpf' === $key ? '52998224725' : ''; }
        public function get_payment_method() { return 'cod'; }
        public function get_status() { return $this->status; }
        public function get_order_key() { return 'wc_order_abc'; }
        public function get_order_number() { return '101'; }
        public function get_view_order_url() { return 'https://site.test/minha-conta/view-order/101/'; }
        public function get_customer_id() { return 7; }
        public function get_items() { return array( new WcaiF20Item() ); }
        public function get_formatted_order_total() { return 'R$ 100,00'; }
        public function get_billing_first_name() { return 'Titular'; }
        public function get_billing_last_name() { return 'Teste'; }
        public function add_order_note() {}
        public function update_meta_data() {}
        public function save() {}
    }
}

$GLOBALS['wcai_f20_order'] = null;
$GLOBALS['wcai_f20_open'] = array();
$GLOBALS['wcai_f20_downloads'] = array();
$GLOBALS['wcai_f20_options'] = array( 'wcai_contact_phone' => '+55 41 99808-0605' );

if ( ! function_exists( 'add_filter' ) ) { function add_filter() {} }
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'add_query_arg' ) ) { function add_query_arg( $k, $v, $url ) { return $url . '?' . $k . '=' . $v; } }
if ( ! function_exists( 'home_url' ) ) { function home_url( $path = '' ) { return 'https://site.test/teste' . $path; } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( (string) $v ); } }
if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $v ) { return $v; } }
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $v ) { return (string) $v; } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $v ) { return esc_html( $v ); } }
if ( ! function_exists( 'esc_attr__' ) ) { function esc_attr__( $v ) { return esc_attr( $v ); } }
if ( ! function_exists( '__' ) ) { function __( $v ) { return $v; } }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $v ) { return false; } }
if ( ! function_exists( 'is_wc_endpoint_url' ) ) { function is_wc_endpoint_url() { return false; } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $key, $default = false ) { return isset( $GLOBALS['wcai_f20_options'][ $key ] ) ? $GLOBALS['wcai_f20_options'][ $key ] : $default; } }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta( $id, $key ) { return array( '_wcai_starts_at' => '2026-10-20 09:00', '_wcai_meeting_point' => 'Portaria do parque' )[ $key ]; } }
if ( ! function_exists( 'wp_date' ) ) { function wp_date( $format, $ts ) { return gmdate( $format, $ts ); } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 7; } }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can() { return false; } }
if ( ! function_exists( 'wc_get_order_status_name' ) ) { function wc_get_order_status_name( $s ) { $names = array( 'completed' => 'Concluído', 'cancelled' => 'Cancelado' ); return isset( $names[ $s ] ) ? $names[ $s ] : $s; } }
if ( ! function_exists( 'wc_get_order' ) ) { function wc_get_order() { return $GLOBALS['wcai_f20_order']; } }
if ( ! function_exists( 'wc_get_orders' ) ) { function wc_get_orders() { return $GLOBALS['wcai_f20_open']; } }
if ( ! function_exists( 'wc_get_page_permalink' ) ) { function wc_get_page_permalink() { return 'https://site.test/loja/'; } }
if ( ! function_exists( 'wc_get_customer_available_downloads' ) ) { function wc_get_customer_available_downloads() { return $GLOBALS['wcai_f20_downloads']; } }
if ( ! function_exists( 'wp_nonce_field' ) ) { function wp_nonce_field() {} }

require_once dirname( __DIR__ ) . '/includes/class-utils.php';
require_once dirname( __DIR__ ) . '/includes/class-capabilities.php';
require_once dirname( __DIR__ ) . '/includes/class-data-resolver.php';
require_once dirname( __DIR__ ) . '/includes/class-frontend-account.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Test_F20_Account_Round4 extends TestCase {

    const HOLDER_CPF = '52998224725';
    const OTHER_CPF  = '11144477735';
    const GENERIC    = 'Dados do participante inválidos.';

    private function row( $id, $name, $cpf, $extra = array() ) {
        return array_merge(
            array(
                'id' => $id, 'order_id' => 101, 'nome_completo' => $name, 'cpf' => $cpf,
                'data_nascimento' => '1990-05-15', 'termo_assinado' => 0, 'ticket_hash' => '',
                'reservation_id' => 0, 'checkin_time' => null, 'checkin_status' => 0,
            ),
            $extra
        );
    }

    private function account() { return new WCAI_Frontend_Account(); }

    private function call( $method, $args = array() ) {
        $m = new ReflectionMethod( 'WCAI_Frontend_Account', $method );
        $m->setAccessible( true );
        return $m->invokeArgs( $this->account(), $args );
    }

    private function set_rows( $rows ) {
        WCAI_Participants_DB::$rows = $rows;
        WCAI_Participants_DB::$updates = array();
        WCAI_Legal_Acceptances::$accepted = array();
    }

    private function two_pending() {
        return array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ), $this->row( 2, 'Bia Lima', self::OTHER_CPF ) );
    }

    private function state( $order, $rows ) {
        $this->set_rows( $rows );
        return $this->call( 'order_terms_state', array( $order, $rows ) );
    }

    private function modal( $order, $is_locked = false ) {
        $GLOBALS['wcai_f20_order'] = $order;
        ob_start();
        $this->call( 'render_modal_html', array( 101, WCAI_Participants_DB::$rows, $is_locked, 'wcai-modal-101' ) );
        return ob_get_clean();
    }

    private function cell( $order ) {
        ob_start();
        $this->account()->column_content( $order );
        return ob_get_clean();
    }

    private function dashboard() {
        ob_start();
        $this->account()->render_dashboard_next_step();
        return ob_get_clean();
    }

    private function assertOpensInNewTab( $html ) {
        preg_match_all( '/<a [^>]*href="https:\/\/site\.test\/termo\/[^>]*>/', $html, $links );
        $this->assertNotEmpty( $links[0], 'nenhum link do termo' );
        foreach ( $links[0] as $link ) {
            $this->assertStringContainsString( 'target="_blank" rel="noopener"', $link );
        }
        $this->assertSame( count( $links[0] ), substr_count( $html, '<span class="wcai-sr"> (abre em nova aba)</span></a>' ) );
    }

    // --- 2. estado único do termo ---

    public function test_state_rules_cover_every_case() {
        $closed = $this->state( new WcaiF20Order( 'completed' ), $this->two_pending() );
        $this->assertSame( 'closed', $closed['state'] );

        $none = $this->state( new WcaiF20Order(), array( $this->row( 1, 'Junior Potenza', '', array( 'data_nascimento' => '' ) ) ) );
        $this->assertSame( 'none', $none['state'] );

        $pending = $this->state( new WcaiF20Order(), $this->two_pending() );
        $this->assertSame( 'pending', $pending['state'] );
        $this->assertSame( 2, $pending['pending'] );

        $signed = array( $this->row( 1, 'Ana', self::HOLDER_CPF, array( 'termo_assinado' => 1 ) ) );
        $this->assertSame( 'signed', $this->state( new WcaiF20Order(), $signed )['state'] );

        $mixed = array_merge( $signed, array( $this->row( 2, 'Antigo', '', array( 'data_nascimento' => '' ) ) ) );
        $this->assertSame( 'incomplete', $this->state( new WcaiF20Order(), $mixed )['state'] );
    }

    public function test_list_chip_is_neutral_without_participant_data_and_never_a_green_zero_of_zero() {
        $state = $this->state( new WcaiF20Order(), array( $this->row( 1, 'Junior Potenza', '', array( 'data_nascimento' => '' ) ) ) );
        $html = $this->call( 'terms_chip_summary_html', array( $state, new WcaiF20Order() ) );

        $this->assertStringContainsString( 'Sem participantes', $html );
        $this->assertStringNotContainsString( '0/0', $html );
        $this->assertStringNotContainsString( '#e6f4ea', $html );
        $this->assertStringContainsString( '#f0f0f1', $html );
    }

    public function test_closed_order_says_the_status_everywhere_and_never_pending_term() {
        $order = new WcaiF20Order( 'completed' );
        $this->set_rows( $this->two_pending() );
        $GLOBALS['wcai_f20_order'] = $order;
        $list = $this->cell( $order );
        $view = $this->call( 'order_extras_html', array( $order, WCAI_Participants_DB::$rows, false ) );

        foreach ( array( $list, $view ) as $html ) {
            $this->assertStringContainsString( 'Concluído', $html );
            $this->assertStringNotContainsString( 'Termo pendente', $html );
            $this->assertStringNotContainsString( 'Dados incompletos', $html );
            $this->assertStringNotContainsString( 'Assinar termo', $html );
        }
        ob_start();
        $this->account()->append_status_column( $order );
        $this->assertSame( 'Concluído', ob_get_clean() );
    }

    // --- 9. painel ---

    public function test_dashboard_card_shows_next_departure_pending_terms_phone_and_sign_button() {
        $this->set_rows( $this->two_pending() );
        $GLOBALS['wcai_f20_open'] = array( new WcaiF20Order() );
        $html = $this->dashboard();

        $this->assertStringContainsString( 'Próximo passo', $html );
        $this->assertStringContainsString( '>Pedido #101</a>', $html );
        $this->assertStringContainsString( 'Saída: 20/10/2026 09:00', $html );
        $this->assertStringContainsString( 'Ponto de encontro: Portaria do parque', $html );
        $this->assertStringContainsString( 'Faltam 2 termos.', $html );
        $this->assertStringContainsString( 'href="tel:+5541998080605"', $html );
        $this->assertStringContainsString( '>Assinar termos<', $html );
        $this->assertOpensInNewTab( $html );
    }

    public function test_dashboard_card_without_an_open_order_links_to_the_shop() {
        $GLOBALS['wcai_f20_open'] = array();
        $html = $this->dashboard();

        $this->assertStringContainsString( 'Nenhum passeio em andamento.', $html );
        $this->assertStringContainsString( 'href="https://site.test/loja/">Ver passeios</a>', $html );
        $this->assertStringNotContainsString( 'Assinar termos', $html );
    }

    // --- 10. menu ---

    public function test_downloads_menu_item_is_hidden_without_downloadable_products() {
        $items = array( 'dashboard' => 'Painel', 'orders' => 'Pedidos', 'downloads' => 'Downloads', 'customer-logout' => 'Sair' );

        $GLOBALS['wcai_f20_downloads'] = array();
        $this->assertSame( array( 'dashboard', 'orders', 'customer-logout' ), array_keys( $this->account()->filter_account_menu_items( $items ) ) );

        $GLOBALS['wcai_f20_downloads'] = array( array( 'download_id' => 'x' ) );
        $this->assertArrayHasKey( 'downloads', $this->account()->filter_account_menu_items( $items ) );
    }

    // --- 11. e 12. links em nova aba e rótulos ---

    public function test_every_sign_link_opens_in_a_new_tab_with_hidden_notice() {
        $this->set_rows( $this->two_pending() );
        $order = new WcaiF20Order();
        $GLOBALS['wcai_f20_order'] = $order;

        $this->assertOpensInNewTab( $this->cell( $order ) );
        $this->assertOpensInNewTab( $this->call( 'order_extras_html', array( $order, WCAI_Participants_DB::$rows, false ) ) );
        $this->assertOpensInNewTab( $this->modal( $order ) );
    }

    public function test_labels_use_termo_de_responsabilidade_and_ingresso_qr_with_one_qr_explanation() {
        $this->set_rows( $this->two_pending() );
        $html = $this->call( 'order_extras_html', array( new WcaiF20Order(), WCAI_Participants_DB::$rows, false ) );

        $this->assertStringContainsString( '>Termo de responsabilidade</th>', $html );
        $this->assertStringContainsString( '>Ingresso (QR)</th>', $html );
        $this->assertStringNotContainsString( 'QR Code)', $html );
        $this->assertSame( 1, substr_count( $html, 'class="wcai-qr-intro"' ) );
        $this->assertSame( 2, substr_count( $html, '>QR após assinar</small>' ) );
        $this->assertStringNotContainsString( '👥', $html );
        $this->assertSame( 2, substr_count( $html, '<h3 ' ) );
        $this->assertStringContainsString( '<svg aria-hidden="true"', $html );
    }

    public function test_orders_list_has_one_primary_sign_button_and_plain_secondary_buttons() {
        $this->set_rows( $this->two_pending() );
        $order = new WcaiF20Order();
        $GLOBALS['wcai_f20_order'] = $order;
        $html = $this->cell( $order );

        $this->assertSame( 1, substr_count( $html, 'wcai-sign-all--list' ) );
        $this->assertStringContainsString( '>Editar participantes</button>', $html );
        $this->assertStringNotContainsString( '#ffba00', $html );
        $this->assertStringNotContainsString( 'style="width:100%', $html );
    }
}

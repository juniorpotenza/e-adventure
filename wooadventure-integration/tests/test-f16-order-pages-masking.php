<?php
// PHPUnit: stubs mínimos de WordPress (sem WP_UnitTestCase). Suite "f16"; processo separado (stubs guardados por class_exists).
// Cobre (rodada 3 das páginas do cliente): CPF mascarado no modal e "não alterado" no servidor, nome em title-case,
// "Dados incompletos", botão principal oculto em pedido encerrado, filtro gettext, próximos passos compartilhados,
// renomeações de rótulos, aria-label da lista, URL relativa do termo e adiamento no tema de blocos.
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
        public static function get_by_order_item( $id ) { return null; }
    }
}

if ( ! class_exists( 'WCAI_Settings' ) ) {
    class WCAI_Settings {
        public static function get_terms_url() { return isset( $GLOBALS['wcai_f15_terms'] ) ? $GLOBALS['wcai_f15_terms'] : 'https://site.test/termo/'; }
        public static function is_cpf_blocked( $cpf ) { return false; }
    }
}

if ( ! class_exists( 'WCAI_Assinatura' ) ) {
    class WCAI_Assinatura {
        public static function generate_local_qr( $hash, $id ) { return 'https://site.test/qr/' . $hash . '.png'; }
    }
}

if ( ! class_exists( 'WcaiF16Item' ) ) {
    class WcaiF16Item {
        public function get_id() { return 1; }
        public function get_quantity() { return 3; }
        public function get_variation_id() { return 0; }
        public function get_product_id() { return 99; }
        public function get_meta( $key, $single = true ) { return '_wcai_departure_id' === $key ? 5 : ''; }
    }
}

if ( ! class_exists( 'WcaiF16Order' ) ) {
    class WcaiF16Order extends WC_Order {
        public $status; public $method;
        public function __construct( $status = 'processing', $method = 'cod' ) { $this->status = $status; $this->method = $method; }
        public function get_id() { return 101; }
        public function get_meta( $key ) { return 'billing_cpf' === $key ? '52998224725' : ''; }
        public function get_payment_method() { return $this->method; }
        public function get_status() { return $this->status; }
        public function get_order_key() { return 'wc_order_abc'; }
        public function get_order_number() { return '101'; }
        public function get_customer_id() { return 7; }
        public function get_items() { return array( new WcaiF16Item() ); }
        public function get_formatted_order_total() { return 'R$ 100,00'; }
        public function get_billing_first_name() { return 'Titular'; }
        public function get_billing_last_name() { return 'Teste'; }
        public function add_order_note() {}
        public function update_meta_data() {}
        public function save() {}
    }
}

$GLOBALS['wcai_f15_received'] = false;
$GLOBALS['wcai_f15_block_theme'] = false;
$GLOBALS['wcai_f15_meeting'] = 'Portaria do parque';
$GLOBALS['wcai_f15_options'] = array();
$GLOBALS['wcai_f15_order'] = null;

if ( ! function_exists( 'add_filter' ) ) { function add_filter() {} }
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'add_query_arg' ) ) { function add_query_arg( $k, $v, $url ) { return $url . '?' . $k . '=' . $v; } }
if ( ! function_exists( 'home_url' ) ) { function home_url( $path = '' ) { return 'https://site.test/teste' . $path; } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( (string) $v ); } }
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $v ) { return (string) $v; } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $v ) { return esc_html( $v ); } }
if ( ! function_exists( 'esc_attr__' ) ) { function esc_attr__( $v ) { return esc_attr( $v ); } }
if ( ! function_exists( '__' ) ) { function __( $v ) { return $v; } }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $v ) { return false; } }
if ( ! function_exists( 'is_wc_endpoint_url' ) ) { function is_wc_endpoint_url() { return $GLOBALS['wcai_f15_received']; } }
if ( ! function_exists( 'wp_is_block_theme' ) ) { function wp_is_block_theme() { return $GLOBALS['wcai_f15_block_theme']; } }
if ( ! function_exists( 'did_action' ) ) { function did_action() { return 0; } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $key, $default = false ) { return isset( $GLOBALS['wcai_f15_options'][ $key ] ) ? $GLOBALS['wcai_f15_options'][ $key ] : $default; } }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta( $id, $key ) { return array( '_wcai_starts_at' => '2026-10-20 09:00', '_wcai_meeting_point' => $GLOBALS['wcai_f15_meeting'] )[ $key ]; } }
if ( ! function_exists( 'wp_date' ) ) { function wp_date( $format, $ts ) { return gmdate( $format, $ts ); } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 7; } }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can() { return false; } }
if ( ! function_exists( 'wc_get_order_status_name' ) ) { function wc_get_order_status_name( $s ) { return $s; } }
if ( ! function_exists( 'wc_get_order' ) ) { function wc_get_order() { return $GLOBALS['wcai_f15_order']; } }
if ( ! function_exists( 'wp_nonce_field' ) ) { function wp_nonce_field() {} }

require_once dirname( __DIR__ ) . '/includes/class-utils.php';
require_once dirname( __DIR__ ) . '/includes/class-capabilities.php';
require_once dirname( __DIR__ ) . '/includes/class-data-resolver.php';
require_once dirname( __DIR__ ) . '/includes/class-frontend-account.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Test_F16_Order_Pages_Masking extends TestCase {

    const HOLDER_CPF   = '52998224725';
    const OTHER_CPF    = '11144477735';

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

    private function modal( $order, $is_locked = false ) {
        $GLOBALS['wcai_f15_order'] = $order;
        ob_start();
        $this->call( 'render_modal_html', array( 101, WCAI_Participants_DB::$rows, $is_locked, 'wcai-modal-101' ) );
        return ob_get_clean();
    }

    // --- 10. dados incompletos / pedido encerrado ---

    public function test_row_without_valid_cpf_and_birth_date_is_incomplete_and_not_a_pending_term() {
        $order = new WcaiF16Order();
        $old = $this->row( 3, 'Junior Potenza', '', array( 'data_nascimento' => '' ) );
        $zero = $this->row( 4, 'Junior Potenza', '00000000000', array( 'data_nascimento' => '' ) );
        $ok = $this->row( 1, 'Ana Souza', self::HOLDER_CPF );
        $cpf_only = $this->row( 5, 'Bia', self::OTHER_CPF, array( 'data_nascimento' => '' ) );

        $this->assertTrue( $this->call( 'participant_is_incomplete', array( $order, $old ) ) );
        $this->assertTrue( $this->call( 'participant_is_incomplete', array( $order, $zero ) ) );
        $this->assertFalse( $this->call( 'participant_is_incomplete', array( $order, $ok ) ) );
        $this->assertFalse( $this->call( 'participant_is_incomplete', array( $order, $cpf_only ) ) );
        $this->assertSame( array( 'total' => 2, 'signed' => 0, 'pending' => 2, 'incomplete' => 2 ), $this->call( 'terms_summary', array( $order, array( $old, $zero, $ok, $cpf_only ) ) ) );
    }

    public function test_incomplete_row_shows_neutral_chip_and_no_sign_link() {
        $this->set_rows( array( $this->row( 3, 'Junior Potenza', '', array( 'data_nascimento' => '' ) ) ) );
        $html = $this->call( 'order_extras_html', array( new WcaiF16Order(), WCAI_Participants_DB::$rows, false ) );

        $this->assertStringContainsString( 'Dados incompletos', $html );
        $this->assertStringNotContainsString( 'Termo pendente', $html );
        $this->assertStringNotContainsString( 'Assinar termo', $html );
    }

    public function test_primary_sign_button_is_hidden_on_closed_orders() {
        $pending = array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) );
        foreach ( array( 'completed', 'cancelled', 'refunded', 'failed' ) as $status ) {
            $this->assertFalse( $this->call( 'terms_cta_visible', array( new WcaiF16Order( $status ), $pending, 1 ) ), $status );
            $html = $this->call( 'order_extras_html', array( new WcaiF16Order( $status ), $pending, false ) );
            $this->assertStringNotContainsString( 'Assinar termos', $html, $status );
            $this->assertStringNotContainsString( 'Próximos passos', $html, $status );
        }
        $this->assertTrue( $this->call( 'terms_cta_visible', array( new WcaiF16Order( 'processing' ), $pending, 1 ) ) );
    }

    // --- 2. gettext ---

    public function test_gettext_filter_fixes_only_the_wrong_woocommerce_translation() {
        $account = $this->account();

        $this->assertSame( 'Nº do pedido:', $account->fix_order_number_label( 'Pedidor #:', 'Order number:', 'woocommerce' ) );
        $this->assertSame( 'Nº do pedido', $account->fix_order_number_label( 'Pedidor #', 'Order #', 'woocommerce' ) );
        $this->assertSame( 'Pedidor #:', $account->fix_order_number_label( 'Pedidor #:', 'x', 'outro-plugin' ) );
        $this->assertSame( 'Pedido', $account->fix_order_number_label( 'Pedido', 'Order', 'woocommerce' ) );
    }

    // --- 9. próximos passos compartilhados ---

    public function test_next_steps_are_shared_by_thank_you_and_view_order_with_policy_and_phone() {
        $GLOBALS['wcai_f15_options'] = array( 'wcai_cancellation_policy' => 'Cancelamento grátis até 48h.', 'wcai_contact_phone' => '(41) 99808-0605' );
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ) );

        foreach ( array( true, false ) as $received ) {
            $GLOBALS['wcai_f15_received'] = $received;
            $html = $this->call( 'order_extras_html', array( new WcaiF16Order(), WCAI_Participants_DB::$rows, false ) );
            $this->assertSame( 1, substr_count( $html, 'id="wcai-next-steps-title"' ) );
            $this->assertStringContainsString( 'Data e horário de saída: 20/10/2026 09:00.', $html );
            $this->assertStringContainsString( 'Ponto de encontro: Portaria do parque.', $html );
            $this->assertStringContainsString( 'Política de cancelamento: Cancelamento grátis até 48h.', $html );
            $this->assertStringContainsString( '<a href="tel:41998080605">(41) 99808-0605</a>', $html );
            $this->assertSame( $received, false !== strpos( $html, 'Você receberá este resumo por e-mail.' ) );
        }
    }

    public function test_next_steps_omit_empty_policy_phone_and_meeting_point() {
        $GLOBALS['wcai_f15_meeting'] = '';
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ) );
        $html = $this->call( 'order_extras_html', array( new WcaiF16Order(), WCAI_Participants_DB::$rows, false ) );

        $this->assertStringNotContainsString( 'Ponto de encontro', $html );
        $this->assertStringNotContainsString( 'Política de cancelamento', $html );
        $this->assertStringNotContainsString( 'tel:', $html );
    }

    // --- 1. adiamento no tema de blocos ---

    public function test_block_theme_order_received_defers_html_and_prints_it_once_inside_the_block() {
        $GLOBALS['wcai_f15_block_theme'] = true;
        $GLOBALS['wcai_f15_received'] = true;
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ) );
        $_GET['key'] = 'wc_order_abc';
        $account = $this->account();

        ob_start();
        $account->render_participants_on_order_view( new WcaiF16Order() );
        $early = ob_get_clean();
        $this->assertSame( '', $early );

        $first = $account->append_extras_to_confirmation_block( '<div>totais</div>' );
        $this->assertStringStartsWith( '<div>totais</div>', $first );
        $this->assertStringContainsString( 'class="wcai-order-extra alignwide"', $first );
        $this->assertStringContainsString( 'Lista de Participantes', $first );
        $this->assertStringContainsString( 'Próximos passos', $first );
        $this->assertSame( '<p>info</p>', $account->append_extras_to_confirmation_block( '<p>info</p>' ) );
    }

    public function test_classic_theme_prints_in_place() {
        $GLOBALS['wcai_f15_received'] = true;
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ) );
        $_GET['key'] = 'wc_order_abc';
        ob_start();
        $this->account()->render_participants_on_order_view( new WcaiF16Order() );

        $this->assertStringContainsString( 'Lista de Participantes', ob_get_clean() );
    }

    // --- 12. rótulos ---

    private function cell( $order ) {
        ob_start();
        $this->account()->column_content( $order );
        return ob_get_clean();
    }

    public function test_orders_list_labels_and_separate_sign_link() {
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ), $this->row( 2, 'Bia Lima', self::OTHER_CPF ) ) );
        $GLOBALS['wcai_f15_order'] = new WcaiF16Order();
        $html = $this->cell( new WcaiF16Order() );

        $this->assertStringContainsString( '>Editar participantes</button>', $html );
        $this->assertStringContainsString( 'Termo pendente (2)', $html );
        $this->assertStringNotContainsString( 'Assinar (0/2)', $html );
        $this->assertStringContainsString( 'class="wcai-sign-all wcai-sign-all--list" href="https://site.test/termo/?key=wc_order_abc" target="_blank" rel="noopener">Assinar termos<span class="wcai-sr"> (abre em nova aba)</span></a>', $html );
        $this->assertStringNotContainsString( 'Ver Dados', $html );
    }

    public function test_orders_list_closed_order_says_participantes_and_has_no_sign_link() {
        $this->set_rows( array( $this->row( 1, 'Ana Souza', self::HOLDER_CPF ) ) );
        $GLOBALS['wcai_f15_order'] = new WcaiF16Order( 'completed' );
        $html = $this->cell( new WcaiF16Order( 'completed' ) );

        $this->assertStringContainsString( '>Participantes</button>', $html );
        $this->assertStringNotContainsString( 'Assinar termos', $html );
    }

    public function test_modal_notice_uses_term_count_and_first_name_link() {
        $this->set_rows( array( $this->row( 1, 'ANA SOUZA', self::HOLDER_CPF ), $this->row( 2, 'Bia Lima', self::OTHER_CPF ) ) );
        $html = $this->modal( new WcaiF16Order() );

        $this->assertStringContainsString( 'Faltam 2 termos.', $html );
        $this->assertStringNotContainsString( 'Faltam assinaturas', $html );
        $this->assertStringContainsString( 'Assinar termo de Ana<span class="wcai-sr"> (abre em nova aba)</span></a>', $html );
        $this->assertStringNotContainsString( 'Clique aqui', $html );
    }

    public function test_view_row_sign_link_names_the_participant_in_its_aria_label() {
        $this->set_rows( array( $this->row( 1, 'ana souza', self::HOLDER_CPF ) ) );
        $html = $this->call( 'order_extras_html', array( new WcaiF16Order(), WCAI_Participants_DB::$rows, false ) );

        $this->assertStringContainsString( 'target="_blank" rel="noopener" aria-label="Assinar termo de Ana Souza (abre em nova aba)">Assinar termo<span class="wcai-sr"> (abre em nova aba)</span></a>', $html );
        $this->assertStringContainsString( '<strong>Ana Souza</strong>', $html );
    }

    // --- 13. aria-label ---

    public function test_orders_action_aria_label_starts_with_the_visible_text() {
        $actions = array( 'view' => array( 'url' => 'x', 'name' => 'Visualizar', 'aria-label' => 'Ver pedido 101' ), 'pay' => array( 'name' => 'Pagar' ) );
        $out = $this->account()->fix_orders_action_labels( $actions, new WcaiF16Order() );

        $this->assertSame( 'Visualizar pedido 101', $out['view']['aria-label'] );
        $this->assertArrayNotHasKey( 'aria-label', $out['pay'] );
    }

    // --- 14. URL do termo ---

    public function test_terms_url_with_leading_slash_resolves_against_home_url() {
        $GLOBALS['wcai_f15_terms'] = '/termo/';
        $order = new WcaiF16Order();

        $this->assertSame( 'https://site.test/teste/termo/?key=wc_order_abc', $this->call( 'get_terms_url', array( $order ) ) );
    }

    public function test_terms_url_absolute_is_kept_as_is() {
        $GLOBALS['wcai_f15_terms'] = 'https://www.site.test/termo';
        $order = new WcaiF16Order();

        $this->assertSame( 'https://www.site.test/termo?key=wc_order_abc', $this->call( 'get_terms_url', array( $order ) ) );
        $GLOBALS['wcai_f15_terms'] = '//cdn.site.test/termo';
        $this->assertSame( '//cdn.site.test/termo?key=wc_order_abc', $this->call( 'get_terms_url', array( $order ) ) );
    }
}

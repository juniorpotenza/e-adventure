<?php
// PHPUnit: stubs mínimos de WordPress (sem WP_UnitTestCase). Suite "f10"; processo separado (stubs guardados por class_exists).
// Cobre: chips de termo, explicação do QR, botão "Assinar termos", linha de participantes da lista de pedidos,
// coluna "Ações" oculta, máscara de CPF nas páginas do cliente e próximos passos.
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
        public static function get_by_order( $order_id ) { return self::$rows; }
        public static function get_by_id( $id ) { return null; }
    }
}

if ( ! class_exists( 'WCAI_Legal_Acceptances' ) ) {
    class WCAI_Legal_Acceptances {
        public static function get_by_participant( $id ) { return array(); }
    }
}

if ( ! class_exists( 'WCAI_Reservations' ) ) {
    class WCAI_Reservations {
        public static $status = array();
        public static function get_by_id( $id ) { return isset( self::$status[ $id ] ) ? (object) array( 'status' => self::$status[ $id ] ) : null; }
    }
}

if ( ! class_exists( 'WCAI_Settings' ) ) {
    class WCAI_Settings {
        public static function get_terms_url() { return 'https://site.test/termo/'; }
    }
}

if ( ! class_exists( 'WCAI_Assinatura' ) ) {
    class WCAI_Assinatura {
        public static function generate_local_qr( $hash, $id ) { return 'https://site.test/qr/' . $hash . '.png'; }
    }
}

if ( ! class_exists( 'WcaiF10Item' ) ) {
    class WcaiF10Item {
        public function get_id() { return 1; }
        public function get_name() { return 'Passeio'; }
        public function get_meta( $key, $single = true ) { return '_wcai_departure_id' === $key ? 5 : ''; }
    }
}

if ( ! class_exists( 'WcaiF10Order' ) ) {
    class WcaiF10Order extends WC_Order {
        public $meta; public $method; public $status;
        public function __construct( $meta = array(), $method = 'bacs', $status = 'on-hold' ) { $this->meta = array_merge( array( 'billing_cpf' => '11144477731' ), $meta ); $this->method = $method; $this->status = $status; }
        public function get_id() { return 94; }
        public function get_meta( $key ) { return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : ''; }
        public function get_payment_method() { return $this->method; }
        public function get_status() { return $this->status; }
        public function get_order_key() { return 'wc_order_abc'; }
        public function get_customer_id() { return 7; }
        public function get_items() { return array( new WcaiF10Item() ); }
        public function get_formatted_order_total() { return '<span class="amount"><bdi><span>R$&nbsp;</span>100,00</bdi></span>'; }
        public function get_billing_first_name() { return 'Titular'; }
        public function get_billing_last_name() { return 'Teste'; }
    }
}

$GLOBALS['wcai_f10_received'] = false;
$GLOBALS['wcai_f10_options'] = array();

if ( ! function_exists( 'add_filter' ) ) { function add_filter() {} }
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'add_query_arg' ) ) { function add_query_arg( $k, $v, $url ) { return $url . '?' . $k . '=' . $v; } }
if ( ! function_exists( 'absint' ) ) { function absint( $v ) { return abs( (int) $v ); } }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( (string) $v ); } }
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); } }
if ( ! function_exists( 'esc_html' ) ) { function esc_html( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_attr' ) ) { function esc_attr( $v ) { return htmlspecialchars( (string) $v, ENT_QUOTES ); } }
if ( ! function_exists( 'esc_url' ) ) { function esc_url( $v ) { return (string) $v; } }
if ( ! function_exists( 'esc_html__' ) ) { function esc_html__( $v ) { return esc_html( $v ); } }
if ( ! function_exists( '__' ) ) { function __( $v ) { return $v; } }
if ( ! function_exists( 'is_wp_error' ) ) { function is_wp_error( $v ) { return false; } }
if ( ! function_exists( 'is_wc_endpoint_url' ) ) { function is_wc_endpoint_url() { return $GLOBALS['wcai_f10_received']; } }
if ( ! function_exists( 'get_post_meta' ) ) { function get_post_meta( $id, $key ) { return array( '_wcai_starts_at' => '2026-10-20 09:00', '_wcai_meeting_point' => 'Portaria do parque' )[ $key ]; } }
if ( ! function_exists( 'wp_date' ) ) { function wp_date( $format, $ts ) { return gmdate( $format, $ts ); } }
if ( ! function_exists( 'get_option' ) ) { function get_option( $key, $default = false ) { return isset( $GLOBALS['wcai_f10_options'][ $key ] ) ? $GLOBALS['wcai_f10_options'][ $key ] : $default; } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 7; } }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can() { return false; } }
if ( ! function_exists( 'wc_get_order_status_name' ) ) { function wc_get_order_status_name( $s ) { return $s; } }

require_once dirname( __DIR__ ) . '/includes/class-utils.php';
require_once dirname( __DIR__ ) . '/includes/class-capabilities.php';
require_once dirname( __DIR__ ) . '/includes/class-data-resolver.php';
require_once dirname( __DIR__ ) . '/includes/class-frontend-account.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Test_F10_Order_Pages extends TestCase {

    const HOLDER_CPF = '52998224725';

    private function row( $id, $name, $extra = array() ) {
        return array_merge(
            array(
                'id' => $id, 'order_id' => 94, 'nome_completo' => $name, 'cpf' => '1114447773' . $id,
                'data_nascimento' => ( new DateTime( '-36 years -10 days' ) )->format( 'Y-m-d' ),
                'termo_assinado' => 0, 'ticket_hash' => '', 'reservation_id' => 0,
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

    private function render( $order, $rows ) {
        WCAI_Participants_DB::$rows = $rows;
        ob_start();
        $this->account()->render_participants_on_order_view( $order );
        return ob_get_clean();
    }

    // --- chips e explicação do QR ---

    public function test_term_chip_has_text_label_for_both_states() {
        $this->assertStringContainsString( 'Termo assinado', $this->call( 'term_chip_html', array( true ) ) );
        $this->assertStringContainsString( 'Termo pendente', $this->call( 'term_chip_html', array( false ) ) );
    }

    public function test_qr_explanation_names_the_exact_missing_condition() {
        $base = 'O ingresso (QR) é liberado depois que o pagamento é confirmado e o termo de responsabilidade é assinado.';

        $this->assertSame( $base . ' Falta: pagamento pendente e termo pendente.', $this->call( 'qr_explanation', array( false, false ) ) );
        $this->assertSame( $base . ' Falta: pagamento pendente.', $this->call( 'qr_explanation', array( true, false ) ) );
        $this->assertSame( $base . ' Falta: termo pendente.', $this->call( 'qr_explanation', array( false, true ) ) );
        $this->assertStringNotContainsString( 'Falta:', $this->call( 'qr_explanation', array( true, true ) ) );
    }

    // --- botão principal ---

    public function test_sign_all_button_needs_pending_and_confirmed_cod_or_processing() {
        $pending = array( $this->row( 1, 'Ana' ) );

        $this->assertFalse( $this->call( 'terms_cta_visible', array( new WcaiF10Order(), $pending, 0 ) ) );
        $this->assertFalse( $this->call( 'terms_cta_visible', array( new WcaiF10Order( array(), 'bacs', 'on-hold' ), $pending, 1 ) ) );
        $this->assertTrue( $this->call( 'terms_cta_visible', array( new WcaiF10Order( array(), 'cod', 'on-hold' ), $pending, 1 ) ) );
        $this->assertTrue( $this->call( 'terms_cta_visible', array( new WcaiF10Order( array(), 'bacs', 'processing' ), $pending, 1 ) ) );

        WCAI_Reservations::$status = array( 9 => 'confirmed' );
        $confirmed = array( $this->row( 1, 'Ana', array( 'reservation_id' => 9 ) ) );
        $this->assertTrue( $this->call( 'terms_cta_visible', array( new WcaiF10Order(), $confirmed, 1 ) ) );
    }

    public function test_order_view_shows_sign_buttons_with_order_key_and_pending_count() {
        $html = $this->render( new WcaiF10Order( array(), 'cod', 'processing' ), array( $this->row( 1, 'Ana' ), $this->row( 2, 'Bia' ) ) );

        $this->assertStringContainsString( 'Assinar termos (2 pendentes)', $html );
        $this->assertSame( 3, substr_count( $html, 'href="https://site.test/termo/?key=wc_order_abc"' ) );
        $this->assertStringContainsString( 'Termo pendente', $html );
        $this->assertStringContainsString( 'Falta: termo pendente.', $html );
    }

    // --- coluna "Ações" ---

    public function test_actions_column_is_hidden_when_no_row_has_an_action() {
        $html = $this->render( new WcaiF10Order( array(), 'cod', 'processing' ), array( $this->row( 1, 'Ana' ) ) );

        $this->assertStringNotContainsString( 'Ações', $html );
        $this->assertStringNotContainsString( 'wa.me', $html );
        // A regra do ingresso (QR) aparece uma vez acima da lista; a linha leva só a nota curta.
        $this->assertSame( 1, substr_count( $html, 'class="wcai-qr-intro">O ingresso (QR) é liberado' ) );
        $this->assertStringContainsString( '>QR após assinar</small>', $html );
        $this->assertStringNotContainsString( 'Pendente</div>', $html );
    }

    public function test_actions_column_is_shown_when_a_row_has_a_ticket() {
        WCAI_Reservations::$status = array( 9 => 'confirmed' );
        $html = $this->render( new WcaiF10Order( array(), 'cod', 'processing' ), array( $this->row( 1, 'Ana', array( 'termo_assinado' => 1, 'ticket_hash' => 'abc', 'reservation_id' => 9 ) ) ) );

        $this->assertStringContainsString( 'Ações', $html );
        $this->assertStringContainsString( 'wa.me', $html );
        $this->assertStringContainsString( 'Termo assinado', $html );
        $this->assertStringNotContainsString( 'Assinar termo', $html );
    }

    public function test_signed_participant_waiting_for_payment_gets_payment_note_only() {
        WCAI_Reservations::$status = array( 9 => 'pending' );
        $html = $this->render( new WcaiF10Order(), array( $this->row( 1, 'Ana', array( 'termo_assinado' => 1, 'ticket_hash' => 'abc', 'reservation_id' => 9 ) ) ) );

        $this->assertStringContainsString( 'Falta: pagamento pendente.', $html );
        $this->assertStringNotContainsString( 'Ações', $html );
    }

    // --- lista de pedidos ---

    public function test_orders_list_line_shows_two_names_with_ages_and_remaining_count() {
        $two = array( $this->row( 1, 'Nome A' ), $this->row( 2, 'Nome B' ) );
        $four = array_merge( $two, array( $this->row( 3, 'Nome C' ), $this->row( 4, 'Nome D' ) ) );

        $this->assertSame( 'Nome A (36), Nome B (36)', $this->call( 'participants_summary_line', array( $two ) ) );
        $this->assertSame( 'Nome A (36), Nome B (36) +2', $this->call( 'participants_summary_line', array( $four ) ) );
    }

    public function test_orders_list_terms_chip_has_tooltip_and_counts() {
        $html = $this->call( 'terms_chip_summary_html', array( array( 'total' => 2, 'signed' => 0, 'pending' => 2 ) ) );

        $this->assertStringContainsString( 'Termo pendente (2)', $html );
        $this->assertStringContainsString( 'title="Termos de responsabilidade assinados: 0 de 2.', $html );
    }

    public function test_status_column_shows_only_the_status_name() {
        WCAI_Participants_DB::$rows = array( $this->row( 1, 'Ana' ), $this->row( 2, 'Bia' ) );
        ob_start();
        $this->account()->append_status_column( new WcaiF10Order() );
        $html = ob_get_clean();

        $this->assertStringNotContainsString( 'Termo pendente', $html );
    }

    // --- CPF mascarado nas páginas do cliente ---

    public function test_cpf_is_masked_in_additional_fields_block() {
        $block = '<dl><dt>CPF do titular</dt><dd>529.982.247-25</dd><dt>Nascimento</dt><dd>15/05/1990</dd></dl>';
        $out = $this->account()->mask_cpf_in_additional_fields_block( $block );

        $this->assertStringContainsString( '<dd>529.***.***-25</dd>', $out );
        $this->assertStringNotContainsString( '982.247', $out );
        $this->assertStringContainsString( '<dd>**/**/1990</dd>', $out );
        $this->assertStringNotContainsString( '15/05', $out );
        $this->assertStringContainsString( '529.***.***-25', $this->account()->mask_cpf_in_additional_fields_block( '<dd>52998224725</dd>' ) );
    }

    public function test_classic_buffer_masks_holder_cpf_but_keeps_phone_and_closes() {
        $account = $this->account();
        $order = new WcaiF10Order( array( 'billing_cpf' => self::HOLDER_CPF ) );
        $level = ob_get_level();

        ob_start();
        $account->begin_cpf_mask_buffer( $order );
        echo '<p>41998080605</p><dd>529.982.247-25</dd><dd>52998224725</dd>';
        $account->end_cpf_mask_buffer( $order );
        $out = ob_get_clean();

        $this->assertSame( $level, ob_get_level() );
        $this->assertSame( '<p>41998080605</p><dd>529.***.***-25</dd><dd>529.***.***-25</dd>', $out );
    }

    public function test_buffer_end_without_begin_prints_nothing_and_touches_nothing() {
        ob_start();
        $this->account()->end_cpf_mask_buffer( new WcaiF10Order() );

        $this->assertSame( '', ob_get_clean() );
    }

    // --- próximos passos ---

    public function test_next_steps_for_cod_include_total_departure_meeting_point_and_email_note() {
        $lines = $this->call( 'next_steps_lines', array( new WcaiF10Order( array(), 'cod' ) ) );

        $this->assertSame( 'Pagamento na entrega: leve o valor de R$ 100,00 no dia do passeio.', $lines[0] );
        $this->assertContains( 'Data e horário de saída: 20/10/2026 09:00.', $lines );
        $this->assertContains( 'Ponto de encontro: Portaria do parque.', $lines );
        $this->assertSame( 'Você receberá este resumo por e-mail.', end( $lines ) );
    }

    public function test_next_steps_skip_payment_line_for_other_methods() {
        $lines = $this->call( 'next_steps_lines', array( new WcaiF10Order( array(), 'bacs' ) ) );

        $this->assertStringNotContainsString( 'Pagamento na entrega', implode( ' ', $lines ) );
    }

    public function test_next_steps_render_on_view_order_page_without_the_email_note() {
        $GLOBALS['wcai_f10_received'] = false;
        $html = $this->render( new WcaiF10Order( array(), 'cod' ), array( $this->row( 1, 'Ana' ) ) );

        $this->assertStringContainsString( 'Próximos passos', $html );
        $this->assertStringContainsString( 'Ponto de encontro: Portaria do parque.', $html );
        $this->assertStringNotContainsString( 'Você receberá este resumo por e-mail.', $html );
    }

    public function test_next_steps_render_on_order_received_page() {
        $GLOBALS['wcai_f10_received'] = true;
        $html = $this->render( new WcaiF10Order( array(), 'cod' ), array( $this->row( 1, 'Ana' ) ) );

        $this->assertStringContainsString( '<h3 id="wcai-next-steps-title"', $html );
        $this->assertStringContainsString( 'Você receberá este resumo por e-mail.', $html );
    }
}

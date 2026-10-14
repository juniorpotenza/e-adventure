<?php
// PHPUnit: stubs mínimos de WordPress (sem WP_UnitTestCase). Suite "f17"; processo separado (stubs guardados por class_exists).
// Cobre: CPF/nascimento mascarado ("*") no endpoint do cliente nunca chega à validação, à duplicidade nem à comparação;
// linha desconhecida mascarada e linha malformada dão a MESMA resposta; identidade travada não muda por máscara.
use PHPUnit\Framework\TestCase;

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! class_exists( 'WC_Order' ) ) {
    class WC_Order {}
}

if ( ! class_exists( 'WCAI_Utils' ) ) {
    // Espia: guarda todo argumento que as validações recebem.
    class WCAI_Utils {
        public static $seen = array();
        public static function mask_cpf( $cpf ) {
            $clean = preg_replace( '/[^0-9]/', '', $cpf );
            return 11 !== strlen( $clean ) ? '***.***.***-**' : substr( $clean, 0, 3 ) . '.***.***-' . substr( $clean, -2 );
        }
        public static function format_cpf( $cpf ) { return $cpf; }
        public static function is_valid_cpf( $cpf ) {
            self::$seen[] = (string) $cpf;
            $cpf = preg_replace( '/[^0-9]/', '', $cpf );
            if ( 11 !== strlen( $cpf ) || preg_match( '/^(\d)\1{10}$/', $cpf ) ) { return false; }
            for ( $t = 9; $t < 11; $t++ ) {
                $sum = 0;
                for ( $i = 0; $i < $t; $i++ ) { $sum += (int) $cpf[ $i ] * ( $t + 1 - $i ); }
                if ( (int) $cpf[ $t ] !== ( ( 10 * $sum ) % 11 ) % 10 ) { return false; }
            }
            return true;
        }
        public static function is_valid_date( $date ) {
            self::$seen[] = (string) $date;
            $d = DateTime::createFromFormat( 'd/m/Y', $date );
            return $d && $d->format( 'd/m/Y' ) === $date;
        }
        public static function is_min_age( $date, $years = 7 ) {
            $d = DateTime::createFromFormat( 'd/m/Y', $date );
            return $d && ( new DateTime() )->diff( $d )->y >= $years;
        }
    }
}

if ( ! class_exists( 'WCAI_Participants_DB' ) ) {
    class WCAI_Participants_DB {
        public static $rows = array();
        public static $updates = array();
        public static $added = array();
        public static function get_by_order( $order_id ) { return array_values( array_filter( self::$rows, function ( $r ) use ( $order_id ) { return (int) $r['order_id'] === (int) $order_id; } ) ); }
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

if ( ! class_exists( 'WCAI_Settings' ) ) {
    class WCAI_Settings {
        public static $seen = array();
        public static function is_cpf_blocked( $cpf ) { self::$seen[] = (string) $cpf; return false; }
    }
}

if ( ! class_exists( 'WcaiF17Item' ) ) {
    class WcaiF17Item {
        public function get_quantity() { return 3; }
    }
}

if ( ! class_exists( 'WcaiF17Order' ) ) {
    class WcaiF17Order extends WC_Order {
        public $status;
        public function __construct( $status = 'processing' ) { $this->status = $status; }
        public function get_id() { return 101; }
        public function get_meta( $key ) { return 'billing_cpf' === $key ? '52998224725' : ''; }
        public function get_status() { return $this->status; }
        public function get_customer_id() { return 7; }
        public function get_items() { return array( new WcaiF17Item() ); }
        public function get_billing_first_name() { return 'Titular'; }
        public function get_billing_last_name() { return 'Teste'; }
        public function add_order_note() {}
        public function update_meta_data() {}
        public function save() {}
    }
}

if ( ! class_exists( 'WcaiF17Json' ) ) {
    class WcaiF17Json extends Exception {
        public $ok;
        public function __construct( $ok, $payload ) { parent::__construct( is_array( $payload ) ? json_encode( $payload ) : (string) $payload ); $this->ok = $ok; }
    }
}

$GLOBALS['wcai_f17_order'] = null;

if ( ! function_exists( 'add_filter' ) ) { function add_filter() {} }
if ( ! function_exists( 'add_action' ) ) { function add_action() {} }
if ( ! function_exists( 'sanitize_text_field' ) ) { function sanitize_text_field( $v ) { return trim( (string) $v ); } }
if ( ! function_exists( 'wp_unslash' ) ) { function wp_unslash( $v ) { return $v; } }
if ( ! function_exists( 'wp_strip_all_tags' ) ) { function wp_strip_all_tags( $v ) { return trim( strip_tags( (string) $v ) ); } }
if ( ! function_exists( 'check_ajax_referer' ) ) { function check_ajax_referer() { return 1; } }
if ( ! function_exists( 'wc_get_order' ) ) { function wc_get_order() { return $GLOBALS['wcai_f17_order']; } }
if ( ! function_exists( 'get_current_user_id' ) ) { function get_current_user_id() { return 7; } }
if ( ! function_exists( 'wp_send_json_error' ) ) { function wp_send_json_error( $data = null ) { throw new WcaiF17Json( false, $data ); } }
if ( ! function_exists( 'wp_send_json_success' ) ) { function wp_send_json_success( $data = null ) { throw new WcaiF17Json( true, $data ); } }

require_once dirname( __DIR__ ) . '/includes/class-frontend-account.php';

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class Test_F17_Masked_Input_Oracle extends TestCase {

    const STORED_CPF = '52998224725';
    const GENERIC    = 'Dados do participante inválidos.';

    protected function setUp(): void {
        WCAI_Participants_DB::$rows = array( array(
            'id' => 1, 'order_id' => 101, 'nome_completo' => 'Ana Souza', 'cpf' => self::STORED_CPF,
            'data_nascimento' => '1990-05-15', 'checkin_time' => null, 'checkin_status' => 0,
        ), array(
            // Pertence a outro pedido.
            'id' => 2, 'order_id' => 202, 'nome_completo' => 'Bia Lima', 'cpf' => '11144477735',
            'data_nascimento' => '1990-05-15', 'checkin_time' => null, 'checkin_status' => 0,
        ) );
        WCAI_Participants_DB::$updates = array();
        WCAI_Participants_DB::$added = array();
        WCAI_Legal_Acceptances::$accepted = array();
        WCAI_Utils::$seen = array();
        WCAI_Settings::$seen = array();
        $GLOBALS['wcai_f17_order'] = new WcaiF17Order();
    }

    // Executa ajax_update com as linhas dadas; devolve array( ok, mensagem ).
    private function post( $rows ) {
        $_POST = array( 'order_id' => 101, 'participantes' => $rows );
        try {
            ( new WCAI_Frontend_Account() )->ajax_update();
        } catch ( WcaiF17Json $e ) {
            return array( $e->ok, $e->getMessage() );
        }
        $this->fail( 'ajax_update deveria responder.' );
    }

    private function assertNothingMasked() {
        foreach ( array_merge( WCAI_Utils::$seen, WCAI_Settings::$seen ) as $value ) {
            $this->assertStringNotContainsString( '*', $value, 'Valor mascarado chegou à validação.' );
        }
    }

    public function test_masked_unknown_rows_and_malformed_rows_get_the_same_response() {
        $masked_other_order = $this->post( array( array( 'id' => '2', 'nome_completo' => 'Ana', 'cpf' => '111.***.***-35', 'data_nascimento' => '15/05/1990' ) ) );
        $masked_no_id       = $this->post( array( array( 'nome_completo' => 'Ana', 'cpf' => '999.***.***-99', 'data_nascimento' => '15/05/1990' ) ) );
        $masked_bad_ref     = $this->post( array( array( 'vref' => 'legacy_9', 'nome_completo' => 'Ana', 'cpf' => '529.***.***-25', 'data_nascimento' => '15/05/1990' ) ) );
        $masked_missing_id  = $this->post( array( array( 'id' => '999', 'nome_completo' => 'Ana', 'cpf' => '529.***.***-25', 'data_nascimento' => '15/05/1990' ) ) );
        $masked_birth       = $this->post( array( array( 'id' => '999', 'nome_completo' => 'Ana', 'cpf' => '52998224725', 'data_nascimento' => '**/**/1990' ) ) );
        $unlocked_flag      = $this->post( array( array( 'id' => '1', 'nome_completo' => 'Ana Souza', 'cpf' => '529.***.***-25', 'nasc_unchanged' => '1' ) ) );
        $malformed          = $this->post( array( 'texto solto' ) );

        $expected = array( false, self::GENERIC );
        foreach ( array( $masked_other_order, $masked_no_id, $masked_bad_ref, $masked_missing_id, $masked_birth, $unlocked_flag, $malformed ) as $i => $response ) {
            $this->assertSame( $expected, $response, "resposta $i" );
        }
        // Id conhecido: a máscara errada é ignorada (vale o CPF guardado), igual à máscara certa; nunca "CPF inválido".
        $wrong = $this->post( array( array( 'id' => '1', 'nome_completo' => 'Ana Souza', 'cpf' => '000.***.***-00', 'data_nascimento' => '15/05/1990' ) ) );
        $right = $this->post( array( array( 'id' => '1', 'nome_completo' => 'Ana Souza', 'cpf' => '529.***.***-25', 'data_nascimento' => '15/05/1990' ) ) );
        $this->assertSame( $right, $wrong );
        $this->assertTrue( $right[0] );
        $this->assertNothingMasked();
    }

    public function test_masked_cpf_on_locked_participant_cannot_change_anything() {
        WCAI_Legal_Acceptances::$accepted = array( 1 );

        // Máscara + data nova: o CPF volta ao guardado, mas a data mudou, então a identidade travada recusa.
        $changed_birth = $this->post( array( array( 'id' => '1', 'nome_completo' => 'Ana Souza', 'cpf' => '529.***.***-25', 'data_nascimento' => '01/01/2000' ) ) );
        $this->assertFalse( $changed_birth[0] );
        $this->assertStringContainsString( 'não podem ser alterados', $changed_birth[1] );

        // Máscara + nome novo: nome travado.
        $changed_name = $this->post( array( array( 'id' => '1', 'nome_completo' => 'Outra Pessoa', 'cpf' => '529.***.***-25', 'nasc_unchanged' => '1' ) ) );
        $this->assertFalse( $changed_name[0] );
        $this->assertStringContainsString( 'nome não pode ser alterado', $changed_name[1] );
        $this->assertSame( array(), WCAI_Participants_DB::$updates );

        // Máscara + flag sem mudar nada: grava só o CPF e o nascimento guardados.
        $same = $this->post( array( array( 'id' => '1', 'nome_completo' => 'Ana Souza', 'cpf' => '529.***.***-25', 'nasc_unchanged' => '1' ) ) );
        $this->assertTrue( $same[0] );
        $this->assertSame( self::STORED_CPF, WCAI_Participants_DB::$updates[1]['cpf'] );
        $this->assertSame( '15/05/1990', WCAI_Participants_DB::$updates[1]['data_nascimento'] );
        $this->assertNothingMasked();
    }

    public function test_masked_virtual_holder_row_uses_internal_ref_never_the_mask() {
        WCAI_Participants_DB::$rows = array();
        $ok = $this->post( array( array( 'vref' => 'virtual_titular', 'nome_completo' => 'Titular Teste', 'cpf' => '000.***.***-00', 'data_nascimento' => '15/05/1990' ) ) );

        $this->assertTrue( $ok[0] );
        $this->assertSame( self::STORED_CPF, WCAI_Participants_DB::$added[0]['cpf'] );
        $this->assertNothingMasked();
    }

    public function test_validation_rejects_a_masked_string_before_any_check() {
        $account = new WCAI_Frontend_Account();
        $m = new ReflectionMethod( 'WCAI_Frontend_Account', 'validate_frontend_rows' );
        $m->setAccessible( true );
        $row = array( 'id' => '1', 'nome_completo' => 'Ana Souza', 'data_nascimento' => '15/05/1990' );

        $this->assertSame( self::GENERIC, $m->invoke( $account, new WcaiF17Order(), array( array_merge( $row, array( 'cpf' => '529.***.***-25' ) ) ) ) );
        $this->assertSame( self::GENERIC, $m->invoke( $account, new WcaiF17Order(), array( array_merge( $row, array( 'cpf' => self::STORED_CPF, 'data_nascimento' => '**/**/1990' ) ) ) ) );
        $this->assertSame( array(), WCAI_Utils::$seen );
        $this->assertSame( array(), WCAI_Settings::$seen );
    }

    public function test_typed_values_still_validate_and_error_messages_leak_no_stored_data() {
        $typed = $this->post( array( array( 'id' => '1', 'nome_completo' => 'Ana Souza', 'cpf' => '111.111.111-11', 'data_nascimento' => '15/05/1990' ) ) );
        $this->assertSame( array( false, 'CPF inválido: Ana Souza.' ), $typed );

        $valid = $this->post( array( array( 'id' => '1', 'nome_completo' => 'Ana Souza', 'cpf' => '111.444.777-35', 'data_nascimento' => '15/05/1990' ) ) );
        $this->assertTrue( $valid[0] );
    }
}

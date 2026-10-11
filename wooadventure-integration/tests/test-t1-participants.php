<?php
/**
 * T1 - Acesso a participantes: IDOR, pedido travado, validacao, CPF com zero.
 */
class Test_T1_Participants extends WP_Ajax_UnitTestCase {

    const CPF_VALID_A = '52998224725';
    const CPF_VALID_B = '11144477735';
    const CPF_ZERO    = '01234567890';

    private $user_a;
    private $user_b;

    public function set_up() {
        parent::set_up();
        WCAI_Participants_DB::create_table();
        new WCAI_Frontend_Account();
        $this->user_a = self::factory()->user->create( array( 'role' => 'customer' ) );
        $this->user_b = self::factory()->user->create( array( 'role' => 'customer' ) );
    }

    private function make_order( $user_id, $status = 'processing' ) {
        $order = wc_create_order( array( 'customer_id' => $user_id ) );
        $order->set_status( $status );
        $order->save();
        return $order;
    }

    private function make_participant( $order, $cpf = self::CPF_VALID_B ) {
        return WCAI_Participants_DB::add( array(
            'order_id'        => $order->get_id(),
            'customer_id'     => $order->get_customer_id(),
            'nome_completo'   => 'Original',
            'cpf'             => $cpf,
            'data_nascimento' => '1990-01-01',
        ) );
    }

    private function post_update( $order, $rows ) {
        $this->_setRole( 'customer' );
        wp_set_current_user( $order->get_customer_id() );
        $_POST['security']      = wp_create_nonce( 'wcai_update_nonce' );
        $_POST['order_id']      = $order->get_id();
        $_POST['participantes'] = $rows;
        try {
            $this->_handleAjax( 'wcai_update_participants' );
        } catch ( WPAjaxDieContinueException $e ) {
            unset( $e );
        }
        return json_decode( $this->_last_response, true );
    }

    public function test_cliente_nao_edita_participante_de_outro_pedido() {
        $order_a = $this->make_order( $this->user_a );
        $order_b = $this->make_order( $this->user_b );
        $pid_b   = $this->make_participant( $order_b );

        $resp = $this->post_update( $order_a, array(
            $pid_b => array( 'id' => $pid_b, 'nome_completo' => 'Hack', 'cpf' => self::CPF_VALID_A, 'data_nascimento' => '01/01/1990' ),
        ) );

        $this->assertFalse( $resp['success'] );
        $row = WCAI_Participants_DB::get_by_id( $pid_b );
        $this->assertSame( 'Original', $row['nome_completo'] );
        $this->assertSame( self::CPF_VALID_B, $row['cpf'] );
    }

    public function test_pedido_travado_recusa_edicao() {
        $order = $this->make_order( $this->user_a, 'completed' );
        $pid   = $this->make_participant( $order );

        $resp = $this->post_update( $order, array(
            $pid => array( 'id' => $pid, 'nome_completo' => 'Novo', 'cpf' => self::CPF_VALID_A, 'data_nascimento' => '01/01/1990' ),
        ) );

        $this->assertFalse( $resp['success'] );
        $this->assertSame( 'Original', WCAI_Participants_DB::get_by_id( $pid )['nome_completo'] );
    }

    public function test_recusa_cpf_invalido_menor_de_7_e_cpf_duplicado() {
        $order = $this->make_order( $this->user_a );
        $pid   = $this->make_participant( $order );
        $young = gmdate( 'd/m/Y', strtotime( '-3 years' ) );

        $bad_cpf = $this->post_update( $order, array(
            $pid => array( 'id' => $pid, 'nome_completo' => 'X', 'cpf' => '11111111111', 'data_nascimento' => '01/01/1990' ),
        ) );
        $this->assertFalse( $bad_cpf['success'] );

        $too_young = $this->post_update( $order, array(
            $pid => array( 'id' => $pid, 'nome_completo' => 'X', 'cpf' => self::CPF_VALID_A, 'data_nascimento' => $young ),
        ) );
        $this->assertFalse( $too_young['success'] );

        $duplicate = $this->post_update( $order, array(
            'a' => array( 'id' => $pid, 'nome_completo' => 'X', 'cpf' => self::CPF_VALID_A, 'data_nascimento' => '01/01/1990' ),
            'b' => array( 'nome_completo' => 'Y', 'cpf' => self::CPF_VALID_A, 'data_nascimento' => '01/01/1990' ),
        ) );
        $this->assertFalse( $duplicate['success'] );
    }

    public function test_edicao_valida_do_proprio_pedido_e_salva() {
        $order = $this->make_order( $this->user_a );
        $pid   = $this->make_participant( $order );

        $resp = $this->post_update( $order, array(
            $pid => array( 'id' => $pid, 'nome_completo' => 'Novo', 'cpf' => self::CPF_VALID_A, 'data_nascimento' => '01/01/1990' ),
        ) );

        $this->assertTrue( $resp['success'] );
        $this->assertSame( 'Novo', WCAI_Participants_DB::get_by_id( $pid )['nome_completo'] );
    }

    public function test_update_mantem_zero_a_esquerda_do_cpf() {
        $order = $this->make_order( $this->user_a );
        $pid   = $this->make_participant( $order );

        WCAI_Participants_DB::update( $pid, array( 'cpf' => '012.345.678-90' ) );

        $this->assertSame( self::CPF_ZERO, WCAI_Participants_DB::get_by_id( $pid )['cpf'] );
    }
}

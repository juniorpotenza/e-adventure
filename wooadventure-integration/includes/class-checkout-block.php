<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Checkout_Block {
    const NAMESPACE = 'wcai-checkout';
    const SCRIPT_HANDLE = 'wcai-checkout-block';

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'woocommerce_blocks_loaded', array( $this, 'register_store_api' ) );
        add_filter( 'render_block_woocommerce/checkout-actions-block', array( $this, 'render_checkout_ui' ), 20 );
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'process_checkout' ), 10, 2 );
    }

    public function register_assets() {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) return;
        $file = plugin_dir_path( dirname( __FILE__ ) ) . 'assets/js/wcai-checkout-block.js';
        wp_register_script(
            self::SCRIPT_HANDLE,
            plugin_dir_url( dirname( __FILE__ ) ) . 'assets/js/wcai-checkout-block.js',
            array( 'wp-data' ),
            file_exists( $file ) ? (string) filemtime( $file ) : WCAI_VERSION,
            true
        );
    }

    public function register_store_api() {
        if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) return;

        $cart_item_schema = 'Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CartItemSchema';
        $checkout_schema  = 'Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CheckoutSchema';

        if ( class_exists( $cart_item_schema ) ) {
            woocommerce_store_api_register_endpoint_data( array(
                'endpoint'        => $cart_item_schema::IDENTIFIER,
                'namespace'       => self::NAMESPACE,
                'data_callback'   => array( $this, 'cart_item_data' ),
                'schema_callback' => array( $this, 'cart_item_schema' ),
                'schema_type'     => ARRAY_A,
            ) );
        }

        if ( class_exists( $checkout_schema ) ) {
            woocommerce_store_api_register_endpoint_data( array(
                'endpoint'        => $checkout_schema::IDENTIFIER,
                'namespace'       => self::NAMESPACE,
                'data_callback'   => function() { return array( 'data' => '' ); },
                'schema_callback' => function() {
                    return array(
                        'data' => array(
                            'description' => __( 'WooAdventure checkout payload.', 'wooadventure-integration' ),
                            'type'        => 'string',
                            'context'     => array( 'view', 'edit' ),
                            'readonly'    => false,
                            'optional'    => true,
                        ),
                    );
                },
                'schema_type'     => ARRAY_A,
            ) );
        }
    }

    public function cart_item_data( $cart_item ) {
        $product_id   = absint( isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0 );
        $variation_id = absint( isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0 );
        $quantity     = max( 1, absint( isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1 ) );
        $target_ids   = WCAI_Settings::get_product_ids();
        $enabled      = in_array( $product_id, $target_ids, true ) || in_array( $variation_id, $target_ids, true );
        $departures   = array();

        if ( $enabled && class_exists( 'WCAI_Reservations' ) ) {
            foreach ( WCAI_Reservations::get_open_departures_for_product( $product_id, $variation_id ) as $departure ) {
                $available = WCAI_Reservations::get_available_quantity( $departure->ID );
                if ( $available < $quantity ) continue;
                $departures[] = array(
                    'id'        => absint( $departure->ID ),
                    'label'     => sanitize_text_field( get_post_meta( $departure->ID, '_wcai_starts_at', true ) ),
                    'available' => $available,
                );
            }
        }

        return array(
            'enabled'      => $enabled,
            'product_id'   => $product_id,
            'variation_id' => $variation_id,
            'quantity'     => $quantity,
            'departures'   => $departures,
        );
    }

    public function cart_item_schema() {
        return array(
            'enabled' => array( 'type' => 'boolean', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'product_id' => array( 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'variation_id' => array( 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'quantity' => array( 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'departures' => array(
                'type' => 'array', 'context' => array( 'view', 'edit' ), 'readonly' => true,
                'items' => array(
                    'type' => 'object',
                    'properties' => array(
                        'id' => array( 'type' => 'integer', 'readonly' => true ),
                        'label' => array( 'type' => 'string', 'readonly' => true ),
                        'available' => array( 'type' => 'integer', 'readonly' => true ),
                    ),
                ),
            ),
        );
    }

    private function has_target_cart_item() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) return false;
        $target_ids = WCAI_Settings::get_product_ids();
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( in_array( absint( $item['product_id'] ), $target_ids, true ) || in_array( absint( $item['variation_id'] ), $target_ids, true ) ) return true;
        }
        return false;
    }

    public function render_checkout_ui( $block_content ) {
        if ( ! $this->has_target_cart_item() ) return $block_content;
        wp_enqueue_script( self::SCRIPT_HANDLE );
        $html  = '<section id="wcai-checkout-integration" class="wcai-checkout-integration" aria-label="Dados da atividade">';
        $html .= '<p>Carregando os dados da atividade...</p></section>';
        return $html . $block_content;
    }

    public function process_checkout( $order, $request ) {
        if ( ! $order instanceof WC_Order || ! $request instanceof WP_REST_Request ) return;
        if ( WP_REST_Server::CREATABLE !== $request->get_method() ) return;

        $extensions = $request->get_param( 'extensions' );
        $raw = isset( $extensions[ self::NAMESPACE ]['data'] ) ? (string) $extensions[ self::NAMESPACE ]['data'] : '';
        $payload = $raw ? json_decode( $raw, true ) : null;

        if ( ! is_array( $payload ) || empty( $payload['items'] ) || ! is_array( $payload['items'] ) ) {
            if ( $this->order_has_target_item( $order ) ) $this->fail( 'Selecione a saída e informe os dados de todos os participantes.' );
            return;
        }

        $target_ids = WCAI_Settings::get_product_ids();
        $used = array();

        foreach ( $order->get_items() as $item_id => $item ) {
            $product_id   = absint( $item->get_product_id() );
            $variation_id = absint( $item->get_variation_id() );
            if ( ! in_array( $product_id, $target_ids, true ) && ! in_array( $variation_id, $target_ids, true ) ) continue;

            $index = $this->find_submission( $payload['items'], $product_id, $variation_id, $used );
            if ( null === $index ) $this->fail( 'Não foi possível identificar os dados da atividade para um dos ingressos.' );
            $used[] = $index;
            $data = $payload['items'][ $index ];
            $quantity = max( 1, absint( $item->get_quantity() ) );
            $departure_id = absint( isset( $data['departure_id'] ) ? $data['departure_id'] : 0 );

            if ( ! $departure_id || ! class_exists( 'WCAI_Reservations' ) || ! WCAI_Reservations::is_available_for_product( $departure_id, $product_id, $variation_id, $quantity ) ) {
                $this->fail( 'A saída selecionada não está mais disponível ou não possui vagas suficientes. Atualize a página e tente novamente.' );
            }

            $participants = isset( $data['participants'] ) && is_array( $data['participants'] ) ? $data['participants'] : array();
            $this->validate_participants( $participants, $quantity );

            $item->update_meta_data( '_wcai_departure_id', $departure_id, true );
            $item->save();

            $reservation = WCAI_Reservations::create( $departure_id, $quantity, 'pending', $order->get_id(), $item_id );
            if ( is_wp_error( $reservation ) ) $this->fail( $reservation->get_error_message() );
            $this->save_participants( $order, $item_id, absint( $reservation ), $participants );
        }
    }

    private function order_has_target_item( $order ) {
        $target_ids = WCAI_Settings::get_product_ids();
        foreach ( $order->get_items() as $item ) {
            if ( in_array( absint( $item->get_product_id() ), $target_ids, true ) || in_array( absint( $item->get_variation_id() ), $target_ids, true ) ) return true;
        }
        return false;
    }

    private function find_submission( $items, $product_id, $variation_id, $used ) {
        foreach ( $items as $index => $item ) {
            if ( in_array( $index, $used, true ) || ! is_array( $item ) ) continue;
            if ( absint( isset( $item['product_id'] ) ? $item['product_id'] : 0 ) !== $product_id ) continue;
            if ( absint( isset( $item['variation_id'] ) ? $item['variation_id'] : 0 ) !== $variation_id ) continue;
            return $index;
        }
        return null;
    }

    private function validate_participants( $participants, $quantity ) {
        if ( count( $participants ) !== $quantity ) $this->fail( 'Informe os dados de todos os participantes.' );
        $cpfs = array();
        foreach ( $participants as $index => $participant ) {
            if ( ! is_array( $participant ) ) $this->fail( 'Os dados de um participante são inválidos.' );
            $cpf = preg_replace( '/[^0-9]/', '', (string) ( isset( $participant['cpf'] ) ? $participant['cpf'] : '' ) );
            $birthdate = sanitize_text_field( isset( $participant['birthdate'] ) ? $participant['birthdate'] : '' );
            if ( ! WCAI_Utils::is_valid_cpf( $cpf ) ) $this->fail( sprintf( 'CPF do participante %d inválido.', $index + 1 ) );
            if ( WCAI_Settings::is_cpf_blocked( $cpf ) ) $this->fail( sprintf( 'O CPF do participante %d está restrito.', $index + 1 ) );
            if ( in_array( $cpf, $cpfs, true ) ) $this->fail( sprintf( 'O CPF do participante %d já foi usado neste pedido.', $index + 1 ) );
            $cpfs[] = $cpf;
            if ( ! WCAI_Utils::is_valid_date( $birthdate ) || ! WCAI_Utils::is_min_age( $birthdate ) ) $this->fail( sprintf( 'A data de nascimento do participante %d é inválida ou não atende à idade mínima de 7 anos.', $index + 1 ) );
            if ( $index > 0 && empty( trim( sanitize_text_field( isset( $participant['name'] ) ? $participant['name'] : '' ) ) ) ) $this->fail( sprintf( 'Informe o nome completo do participante %d.', $index + 1 ) );
        }
    }

    private function save_participants( $order, $item_id, $reservation_id, $participants ) {
        if ( ! class_exists( 'WCAI_Participants_DB' ) ) $this->fail( 'O cadastro de participantes não está disponível.' );
        foreach ( $participants as $index => $participant ) {
            $name = 0 === $index ? trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ) : sanitize_text_field( $participant['name'] );
            $result = WCAI_Participants_DB::add( array(
                'order_id' => $order->get_id(), 'item_id' => $item_id, 'reservation_id' => $reservation_id,
                'customer_id' => $order->get_customer_id(), 'nome_completo' => $name,
                'cpf' => preg_replace( '/[^0-9]/', '', $participant['cpf'] ), 'data_nascimento' => sanitize_text_field( $participant['birthdate'] ),
            ) );
            if ( false === $result ) $this->fail( 'Não foi possível registrar um dos participantes. Tente novamente.' );
        }
    }

    private function fail( $message ) {
        if ( class_exists( 'Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException' ) ) {
            throw new Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'wcai_checkout_invalid', esc_html( $message ), 400 );
        }
        throw new Exception( esc_html( $message ) );
    }
}

<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Checkout_Block {

    const NAMESPACE = 'wcai-checkout';
    const SCRIPT_HANDLE = 'wcai-checkout-block';

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
        add_action( 'woocommerce_blocks_loaded', array( $this, 'register_store_api' ) );
        add_filter( 'render_block_woocommerce/checkout-fields-block', array( $this, 'render_checkout_ui' ), 20 );
        add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'process_checkout' ), 10, 2 );
    }

    public function register_assets() {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return;
        }

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
        if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
            return;
        }

        $cart_item_schema = 'Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CartItemSchema';
        $checkout_schema = 'Automattic\\WooCommerce\\StoreApi\\Schemas\\V1\\CheckoutSchema';

        if ( class_exists( $cart_item_schema ) ) {
            woocommerce_store_api_register_endpoint_data(
                array(
                    'endpoint'        => $cart_item_schema::IDENTIFIER,
                    'namespace'       => self::NAMESPACE,
                    'data_callback'   => array( $this, 'cart_item_data' ),
                    'schema_callback' => array( $this, 'cart_item_schema' ),
                    'schema_type'     => ARRAY_A,
                )
            );
        }

        if ( class_exists( $checkout_schema ) ) {
            woocommerce_store_api_register_endpoint_data(
                array(
                    'endpoint'        => $checkout_schema::IDENTIFIER,
                    'namespace'       => self::NAMESPACE,
                    'data_callback'   => function() {
                        return array( 'data' => '' );
                    },
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
                )
            );
        }
    }

    public function cart_item_data( $cart_item ) {
        $product_id = absint( isset( $cart_item['product_id'] ) ? $cart_item['product_id'] : 0 );
        $variation_id = absint( isset( $cart_item['variation_id'] ) ? $cart_item['variation_id'] : 0 );
        $quantity = max( 1, absint( isset( $cart_item['quantity'] ) ? $cart_item['quantity'] : 1 ) );
        $target_ids = WCAI_Settings::get_product_ids();
        $enabled = in_array( $product_id, $target_ids, true ) || in_array( $variation_id, $target_ids, true );

        if ( ! $enabled ) {
            return array(
                'enabled' => false,
                'product_id' => $product_id,
                'variation_id' => $variation_id,
                'quantity' => $quantity,
                'departure_id' => 0,
                'departure_label' => '',
                'departures' => array(),
            );
        }

        $selected_departure_id = absint( isset( $cart_item['wcai_departure_id'] ) ? $cart_item['wcai_departure_id'] : 0 );
        $departures = array();

        if ( class_exists( 'WCAI_Reservations' ) ) {
            foreach ( WCAI_Reservations::get_open_departures_for_product( $product_id, $variation_id ) as $departure ) {
                $available = WCAI_Reservations::get_available_quantity( $departure->ID );

                if ( $available < $quantity ) {
                    continue;
                }

                $starts_at = WCAI_Data_Resolver::get_departure_start( $departure->ID );
                $timestamp = WCAI_Data_Resolver::parse_timestamp( $starts_at );
                $label = $timestamp ? wp_date( 'd/m/Y H:i', $timestamp ) : $starts_at;

                $departures[] = array(
                    'id' => absint( $departure->ID ),
                    'label' => sanitize_text_field( $label ),
                    'available' => $available,
                );
            }
        }

        $selected_label = '';

        foreach ( $departures as $departure ) {
            if ( absint( $departure['id'] ) === $selected_departure_id ) {
                $selected_label = $departure['label'];
                break;
            }
        }

        if ( ! $selected_label && $selected_departure_id ) {
            $selected_start = WCAI_Data_Resolver::get_departure_start( $selected_departure_id );
            $selected_timestamp = WCAI_Data_Resolver::parse_timestamp( $selected_start );
            $selected_label = $selected_timestamp ? wp_date( 'd/m/Y H:i', $selected_timestamp ) : $selected_start;
        }

        return array(
            'enabled' => true,
            'product_id' => $product_id,
            'variation_id' => $variation_id,
            'quantity' => $quantity,
            'departure_id' => $selected_departure_id,
            'departure_label' => sanitize_text_field( $selected_label ),
            'departures' => $departures,
        );
    }

    public function cart_item_schema() {
        return array(
            'enabled' => array( 'type' => 'boolean', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'product_id' => array( 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'variation_id' => array( 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'quantity' => array( 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'departure_id' => array( 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'departure_label' => array( 'type' => 'string', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
            'departures' => array(
                'type' => 'array',
                'context' => array( 'view', 'edit' ),
                'readonly' => true,
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
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return false;
        }

        $target_ids = WCAI_Settings::get_product_ids();

        foreach ( WC()->cart->get_cart() as $item ) {
            if ( in_array( absint( $item['product_id'] ), $target_ids, true ) || in_array( absint( $item['variation_id'] ), $target_ids, true ) ) {
                return true;
            }
        }

        return false;
    }

    public function render_checkout_ui( $block_content ) {
        if ( ! $this->has_target_cart_item() ) {
            return $block_content;
        }

        wp_enqueue_script( self::SCRIPT_HANDLE );

        $html  = '<div id="wcai-checkout-wizard" class="wcai-checkout-wizard" aria-label="Checkout WooAdventure">';
        $html .= '<div class="wcai-wizard-progress" role="list">';
        $html .= '<span data-step-label="1" class="is-active">1. Reserva</span>';
        $html .= '<span data-step-label="2">2. Seus dados</span>';
        $html .= '<span data-step-label="3">3. Participantes</span>';
        $html .= '<span data-step-label="4">4. Revisão</span>';
        $html .= '<span data-step-label="5">5. Pagamento</span>';
        $html .= '</div>';
        $html .= '<div data-wcai-step="1" class="wcai-wizard-panel is-active"></div>';
        $html .= '<div data-wcai-step="2" class="wcai-wizard-panel"></div>';
        $html .= '<div data-wcai-step="3" class="wcai-wizard-panel"></div>';
        $html .= '<div data-wcai-step="4" class="wcai-wizard-panel"></div>';
        $html .= '<div data-wcai-step="5" class="wcai-wizard-panel"></div>';
        $html .= '</div>';

        return $html . $block_content;
    }

    public function process_checkout( $order, $request ) {
        if ( ! $order instanceof WC_Order || ! $request instanceof WP_REST_Request ) {
            return;
        }

        $extensions = $request->get_param( 'extensions' );
        $raw = isset( $extensions[ self::NAMESPACE ]['data'] ) ? (string) $extensions[ self::NAMESPACE ]['data'] : '';
        $payload = $raw ? json_decode( $raw, true ) : null;

        if ( ! is_array( $payload ) || empty( $payload['items'] ) || ! is_array( $payload['items'] ) ) {
            if ( $this->order_has_target_item( $order ) ) {
                $this->fail( 'Selecione a saída e informe os dados dos participantes.' );
            }
            return;
        }

        $billing_cpf = WCAI_Data_Resolver::get_billing_cpf( $order );
        $billing_birthdate = WCAI_Data_Resolver::get_billing_birthdate( $order );
        $billing_name = WCAI_Data_Resolver::get_billing_name( $order );

        if ( ! $billing_name ) {
            $this->fail( 'Informe o nome do titular nos dados de faturamento.' );
        }

        if ( ! WCAI_Utils::is_valid_cpf( $billing_cpf ) ) {
            $this->fail( 'Informe um CPF válido nos dados de faturamento do titular.' );
        }

        if ( WCAI_Settings::is_cpf_blocked( $billing_cpf ) ) {
            $this->fail( 'O CPF do titular está restrito para este agendamento.' );
        }

        if ( ! WCAI_Utils::is_valid_date( $billing_birthdate ) || ! WCAI_Utils::is_min_age( $billing_birthdate ) ) {
            $this->fail( 'Informe uma data de nascimento válida nos dados de faturamento do titular.' );
        }

        $target_ids = WCAI_Settings::get_product_ids();
        $used_submission_indexes = array();

        foreach ( $order->get_items() as $item_id => $item ) {
            $product_id = absint( $item->get_product_id() );
            $variation_id = absint( $item->get_variation_id() );

            if ( ! in_array( $product_id, $target_ids, true ) && ! in_array( $variation_id, $target_ids, true ) ) {
                continue;
            }

            $index = $this->find_submission( $payload['items'], $product_id, $variation_id, $used_submission_indexes );
            if ( null === $index ) {
                $this->fail( 'Não foi possível identificar uma reserva do pedido.' );
            }

            $used_submission_indexes[] = $index;
            $data = is_array( $payload['items'][ $index ] ) ? $payload['items'][ $index ] : array();
            $quantity = max( 1, absint( $item->get_quantity() ) );
            $departure_id = absint( isset( $data['departure_id'] ) ? $data['departure_id'] : 0 );

            if ( ! $departure_id ) {
                $departure_id = WCAI_Data_Resolver::get_departure_id_for_order_item( $item );
            }

            if ( ! $departure_id || ! class_exists( 'WCAI_Reservations' ) || ! WCAI_Reservations::is_available_for_product( $departure_id, $product_id, $variation_id, $quantity ) ) {
                $this->fail( 'A saída selecionada não está mais disponível ou o prazo de compra foi encerrado.' );
            }

            $additional = isset( $data['additional_participants'] ) && is_array( $data['additional_participants'] )
                ? $data['additional_participants']
                : array();

            $this->validate_additional_participants( $additional, $quantity, $billing_cpf );

            $item->update_meta_data( '_wcai_departure_id', $departure_id, true );
            $item->save();

            $reservation = WCAI_Reservations::create( $departure_id, $quantity, 'pending', $order->get_id(), $item_id );

            if ( is_wp_error( $reservation ) ) {
                $this->fail( $reservation->get_error_message() );
            }

            $reservation_id = absint( $reservation );

            $this->save_participants(
                $order,
                $item_id,
                $reservation_id,
                $billing_name,
                $billing_cpf,
                $billing_birthdate,
                $additional
            );

            $event_datetime = WCAI_Data_Resolver::get_departure_start( $departure_id );
            if ( $event_datetime ) {
                $order->update_meta_data( 'tour_date', $event_datetime );
            }
        }

        $order->save();
    }

    private function order_has_target_item( $order ) {
        $target_ids = WCAI_Settings::get_product_ids();

        foreach ( $order->get_items() as $item ) {
            if ( in_array( absint( $item->get_product_id() ), $target_ids, true ) || in_array( absint( $item->get_variation_id() ), $target_ids, true ) ) {
                return true;
            }
        }

        return false;
    }

    private function find_submission( $items, $product_id, $variation_id, $used_indexes ) {
        foreach ( $items as $index => $item ) {
            if ( in_array( $index, $used_indexes, true ) || ! is_array( $item ) ) {
                continue;
            }

            if ( absint( isset( $item['product_id'] ) ? $item['product_id'] : 0 ) !== $product_id ) {
                continue;
            }

            if ( absint( isset( $item['variation_id'] ) ? $item['variation_id'] : 0 ) !== $variation_id ) {
                continue;
            }

            return $index;
        }

        return null;
    }

    private function validate_additional_participants( $participants, $quantity, $billing_cpf ) {
        $expected = max( 0, $quantity - 1 );

        if ( count( $participants ) !== $expected ) {
            $this->fail( 'Informe os dados de todos os participantes adicionais.' );
        }

        $cpfs = $billing_cpf ? array( $billing_cpf ) : array();

        foreach ( $participants as $index => $participant ) {
            if ( ! is_array( $participant ) ) {
                $this->fail( 'Os dados de um participante adicional são inválidos.' );
            }

            $position = $index + 2;
            $name = isset( $participant['name'] ) ? sanitize_text_field( $participant['name'] ) : '';
            $cpf = preg_replace( '/\\D+/', '', (string) ( isset( $participant['cpf'] ) ? $participant['cpf'] : '' ) );
            $birthdate = sanitize_text_field( isset( $participant['birthdate'] ) ? $participant['birthdate'] : '' );

            if ( ! $name ) {
                $this->fail( sprintf( 'Informe o nome completo do participante %d.', $position ) );
            }

            if ( ! WCAI_Utils::is_valid_cpf( $cpf ) ) {
                $this->fail( sprintf( 'CPF do participante %d inválido.', $position ) );
            }

            if ( WCAI_Settings::is_cpf_blocked( $cpf ) ) {
                $this->fail( sprintf( 'O CPF do participante %d está restrito.', $position ) );
            }

            if ( in_array( $cpf, $cpfs, true ) ) {
                $this->fail( sprintf( 'O CPF do participante %d já foi usado neste pedido.', $position ) );
            }

            $cpfs[] = $cpf;

            if ( ! WCAI_Utils::is_valid_date( $birthdate ) || ! WCAI_Utils::is_min_age( $birthdate ) ) {
                $this->fail( sprintf( 'A data de nascimento do participante %d é inválida ou não atende à idade mínima.', $position ) );
            }
        }
    }

    private function save_participants( $order, $item_id, $reservation_id, $billing_name, $billing_cpf, $billing_birthdate, $additional ) {
        if ( ! class_exists( 'WCAI_Participants_DB' ) ) {
            $this->fail( 'O cadastro de participantes não está disponível.' );
        }

        $participants = array(
            array(
                'nome_completo' => $billing_name,
                'cpf' => $billing_cpf,
                'data_nascimento' => $billing_birthdate,
            ),
        );

        foreach ( $additional as $participant ) {
            $participants[] = array(
                'nome_completo' => sanitize_text_field( $participant['name'] ),
                'cpf' => preg_replace( '/\\D+/', '', $participant['cpf'] ),
                'data_nascimento' => sanitize_text_field( $participant['birthdate'] ),
            );
        }

        foreach ( $participants as $participant ) {
            $result = WCAI_Participants_DB::add(
                array(
                    'order_id' => $order->get_id(),
                    'item_id' => $item_id,
                    'reservation_id' => $reservation_id,
                    'customer_id' => $order->get_customer_id(),
                    'nome_completo' => $participant['nome_completo'],
                    'cpf' => $participant['cpf'],
                    'data_nascimento' => $participant['data_nascimento'],
                )
            );

            if ( false === $result ) {
                $this->fail( 'Não foi possível registrar um participante. Tente novamente.' );
            }
        }
    }

    private function fail( $message ) {
        if ( class_exists( 'Automattic\\WooCommerce\\StoreApi\\Exceptions\\RouteException' ) ) {
            throw new Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
                'wcai_checkout_invalid',
                esc_html( $message ),
                400
            );
        }

        throw new Exception( esc_html( $message ) );
    }
}

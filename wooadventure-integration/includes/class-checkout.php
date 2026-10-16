<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Checkout {
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_filter( 'script_loader_tag', array( $this, 'add_mask_sri' ), 10, 2 );
        add_action( 'woocommerce_after_order_notes', array( $this, 'render_fields' ) );
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_fields' ) );
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_consent' ) );
        add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_departure_to_order_item' ), 10, 4 );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_fields_and_notes' ), 10, 1 );
    }

    public function enqueue_scripts() {
        if ( is_checkout() ) {
            wp_enqueue_script( 'jquery-mask', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js', array( 'jquery' ), '1.14.16', true );
            wp_add_inline_script( 'jquery-mask', "
                jQuery(document).ready(function($) {
                    if (!$.fn.mask) { return; }
                    $('input[name^=\"cpf_\"]').mask('000.000.000-00');
                    $('input[name^=\"data_nascimento_\"]').mask('00/00/0000');
                });
            " );
        }
    }

    // SRI sha384 de jquery.mask.min.js 1.14.16 (cdnjs). Recalcule se a versão mudar.
    public function add_mask_sri( $tag, $handle ) {
        if ( 'jquery-mask' !== $handle ) {
            return $tag;
        }

        return preg_replace( '/ src=/', ' integrity="sha384-6LwNpGeYDjlORU0Q5rfxEC8SQO6/FTh/VecUcvFvNx1gLMdX5dm8y1Y739D3lFSW" crossorigin="anonymous" src=', $tag, 1 );
    }

    // Carrinho com passeio: o consentimento (dados de terceiros) é obrigatório neste checkout clássico,
    // igual ao do bloco (assert_consent). O wc-ajax=checkout também atende quem pula a página do bloco.
    private function cart_has_target_item() {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) return false;
        $target_ids = WCAI_Settings::get_product_ids();
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( in_array( absint( $item['product_id'] ), $target_ids, true ) || in_array( absint( isset( $item['variation_id'] ) ? $item['variation_id'] : 0 ), $target_ids, true ) ) return true;
        }
        return false;
    }

    private function consent_posted() {
        return isset( $_POST['wcai_consent'] ) && '1' === $_POST['wcai_consent']; // phpcs:ignore WordPress.Security.NonceVerification -- nonce do checkout já validado pelo Woo.
    }

    public function validate_consent() {
        if ( $this->cart_has_target_item() && ! $this->consent_posted() ) {
            wc_add_notice( 'Aceite o consentimento para o uso dos dados dos participantes para finalizar o pedido.', 'error' );
        }
    }

    public function render_fields( $checkout ) {
        $items = WC()->cart->get_cart();
        $target_ids = WCAI_Settings::get_product_ids();
        $rendered = false;

        foreach ( $items as $item ) {
            if ( in_array( $item['variation_id'], $target_ids ) || in_array( $item['product_id'], $target_ids ) ) {
                $qty = isset($item['quantity']) ? $item['quantity'] : 1;
                $departure_field = 'wcai_departure_' . sanitize_key( $item['key'] );
                $selected_departure = ! empty( $item['wcai_departure_id'] ) ? absint( $item['wcai_departure_id'] ) : 0;
                $departures = class_exists( 'WCAI_Reservations' ) ? WCAI_Reservations::get_open_departures_for_product( $item['product_id'], $item['variation_id'] ) : array();

                if ( ! $selected_departure && ! empty( $departures ) ) {
                    $options = array( '' => 'Selecione a data e horário' );
                    foreach ( $departures as $departure ) {
                        $starts_at = get_post_meta( $departure->ID, '_wcai_starts_at', true );
                        $available = WCAI_Reservations::get_available_quantity( $departure->ID );
                        if ( $available > 0 ) {
                            $options[ $departure->ID ] = sprintf( '%s (%d vaga%s)', $starts_at, $available, 1 === $available ? '' : 's' );
                        }
                    }
                    if ( count( $options ) > 1 ) {
                        echo '<div class="wcai-departure-box"><h3>Escolha sua saída</h3>';
                        woocommerce_form_field( $departure_field, array( 'type' => 'select', 'class' => array( 'form-row-wide' ), 'label' => 'Data e horário da atividade', 'required' => true, 'options' => $options ), $checkout->get_value( $departure_field ) );
                        echo '</div>';
                    }
                }
                
                if ( $qty > 1 ) {
                    $rendered = true;
                    echo '<div id="wcai_custom_fields"><h3>Dados dos Visitantes Adicionais</h3>';
                    // Começa do 2, pois o 1 é o titular (billing)
                    for ( $i = 2; $i <= $qty; $i++ ) {
                        echo '<div class="wcai-visitor-box" style="margin-bottom:20px; padding:15px; border:1px solid #ddd;">';
                        echo '<h4>' . esc_html( sprintf( __( 'Visitante %d', 'wooadventure-integration' ), $i ) ) . '</h4>';
                        
                        woocommerce_form_field( 'nome_completo_' . $i, array(
                            'type' => 'text', 'class' => array('form-row-wide'), 'label' => 'Nome Completo', 'required' => true, 'autocomplete' => 'off'
                        ), $checkout->get_value( 'nome_completo_' . $i ) );

                        woocommerce_form_field( 'cpf_' . $i, array(
                            'type' => 'text', 'class' => array('form-row-wide'), 'label' => 'CPF', 'required' => true, 'autocomplete' => 'off', 'custom_attributes' => array( 'inputmode' => 'numeric' )
                        ), $checkout->get_value( 'cpf_' . $i ) );

                        woocommerce_form_field( 'data_nascimento_' . $i, array(
                            'type' => 'text', 'class' => array('form-row-wide'), 'label' => 'Data de Nascimento', 'required' => true, 'placeholder' => 'dd/mm/aaaa', 'autocomplete' => 'off', 'custom_attributes' => array( 'inputmode' => 'numeric' )
                        ), $checkout->get_value( 'data_nascimento_' . $i ) );
                        echo '</div>';
                    }
                    echo '</div>';
                }
            }
        }

        if ( $this->cart_has_target_item() ) {
            woocommerce_form_field( 'wcai_consent', array(
                'type' => 'checkbox', 'class' => array( 'form-row-wide' ), 'required' => true,
                'label' => esc_html( WCAI_Privacy::consent_text() ),
            ), $checkout->get_value( 'wcai_consent' ) );
        }
    }

    public function validate_fields() {
        // Validação do Billing (Titular)
        $billing_cpf = isset( $_POST['billing_cpf'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_cpf'] ) ) : '';

        if ( empty( $billing_cpf ) ) {
            wc_add_notice( 'CPF do titular é obrigatório.', 'error' );
        } elseif ( ! WCAI_Utils::is_valid_cpf( $billing_cpf ) ) {
            wc_add_notice( 'CPF de faturamento inválido.', 'error' );
        } elseif ( WCAI_Settings::is_cpf_blocked( $billing_cpf ) ) {
            wc_add_notice( 'Não é possível seguir com o agendamento (CPF Restrito).', 'error' );
        }

        $billing_birthdate = isset( $_POST['billing_birthdate'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_birthdate'] ) ) : '';

        if ( '' === $billing_birthdate && function_exists( 'WC' ) && WC()->customer ) {
            $billing_birthdate = sanitize_text_field( WC()->customer->get_meta( 'billing_birthdate' ) );
        }

        if ( ! $billing_birthdate || ! WCAI_Utils::is_valid_date( $billing_birthdate ) || ! WCAI_Utils::is_min_age( $billing_birthdate ) ) {
            wc_add_notice( 'Data de nascimento do titular é obrigatória e deve atender à idade mínima de 7 anos.', 'error' );
        }

        // Validação dos Visitantes
        $items = WC()->cart->get_cart();
        $target_ids = WCAI_Settings::get_product_ids();
        $cpfs_used = array();
        if($billing_cpf) $cpfs_used[] = WCAI_Utils::format_cpf($billing_cpf);

        foreach ( $items as $item ) {
            if ( in_array( $item['variation_id'], $target_ids ) || in_array( $item['product_id'], $target_ids ) ) {
                $qty = $item['quantity'];
                $departure_field = 'wcai_departure_' . sanitize_key( $item['key'] );
                $has_departures = class_exists( 'WCAI_Reservations' ) && WCAI_Reservations::has_open_departures_for_product( $item['product_id'], $item['variation_id'] );
                $departure_id = ! empty( $item['wcai_departure_id'] ) ? absint( $item['wcai_departure_id'] ) : 0;
                if ( ! $departure_id && isset( $_POST[ $departure_field ] ) ) {
                    $departure_id = absint( wp_unslash( $_POST[ $departure_field ] ) );
                }

                if ( ! $departure_id || ! WCAI_Reservations::is_available_for_product( $departure_id, $item['product_id'], $item['variation_id'], $qty ) ) {
                    wc_add_notice( 'Selecione uma saída disponível com vagas suficientes e dentro do prazo de compra.', 'error' );
                }
                if ( $qty > 1 ) {
                    for ( $i = 2; $i <= $qty; $i++ ) {
                        if ( empty( $_POST[ 'nome_completo_' . $i ] ) ) wc_add_notice( "Nome do Visitante $i é obrigatório.", 'error' );
                        
                        $cpf = isset($_POST[ 'cpf_' . $i ]) ? $_POST[ 'cpf_' . $i ] : '';
                        if ( empty( $cpf ) ) {
                            wc_add_notice( "CPF do Visitante $i é obrigatório.", 'error' );
                        } else {
                            if ( ! WCAI_Utils::is_valid_cpf( $cpf ) ) wc_add_notice( "CPF do Visitante $i inválido.", 'error' );
                            if ( WCAI_Settings::is_cpf_blocked( $cpf ) ) wc_add_notice( "Visitante $i: CPF Restrito.", 'error' );
                            
                            $cpf_fmt = WCAI_Utils::format_cpf($cpf);
                            if ( in_array( $cpf_fmt, $cpfs_used ) ) {
                                wc_add_notice( "CPF do Visitante $i já foi usado neste pedido.", 'error' );
                            }
                            $cpfs_used[] = $cpf_fmt;
                        }

                        $data = isset($_POST[ 'data_nascimento_' . $i ]) ? $_POST[ 'data_nascimento_' . $i ] : '';
                        if ( empty( $data ) ) {
                            wc_add_notice( "Data de nascimento do Visitante $i obrigatória.", 'error' );
                        } elseif ( ! WCAI_Utils::is_valid_date( $data ) ) {
                            wc_add_notice( "Data do Visitante $i inválida.", 'error' );
                        } elseif ( ! WCAI_Utils::is_min_age( $data ) ) {
                            wc_add_notice( "Visitante $i deve ter no mínimo 7 anos.", 'error' );
                        }
                    }
                }
            }
        }
    }

    public function add_departure_to_order_item( $item, $cart_item_key, $values, $order ) {
        $departure_id = ! empty( $values['wcai_departure_id'] ) ? absint( $values['wcai_departure_id'] ) : 0;
        $field = 'wcai_departure_' . sanitize_key( $cart_item_key );

        if ( ! $departure_id && isset( $_POST[ $field ] ) ) {
            $departure_id = absint( wp_unslash( $_POST[ $field ] ) );
        }

        if ( $departure_id ) {
            $item->add_meta_data( '_wcai_departure_id', $departure_id, true );
        }
    }

    public function save_fields_and_notes( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $participants_meta = array(); 
        $note_content = ""; 
        $reservation_error = '';
        $items = $order->get_items();
        $target_ids = WCAI_Settings::get_product_ids();

        // Falha fechada: sem consentimento (ex.: validate_consent removido por outro código) o pedido com passeio não segue.
        $has_target = false;
        foreach ( $items as $item ) {
            if ( in_array( $item->get_variation_id(), $target_ids ) || in_array( $item->get_product_id(), $target_ids ) ) { $has_target = true; break; }
        }
        if ( $has_target ) {
            if ( ! $this->consent_posted() ) {
                $order->update_status( 'failed', 'Consentimento para dados dos participantes ausente.' );
                return;
            }
            // Mesmo formato do checkout em bloco (store_consent).
            $order->update_meta_data( '_wcai_third_party_consent', array( 'accepted_at' => gmdate( 'c' ), 'text_sha256' => hash( 'sha256', WCAI_Privacy::consent_text() ) ) );
            $order->save();
        }

        // Dados do Titular (Billing)
        // Tentamos pegar do POST (mais atual) ou do Objeto Order
        $billing_first_name = isset($_POST['billing_first_name']) ? sanitize_text_field($_POST['billing_first_name']) : $order->get_billing_first_name();
        $billing_last_name  = isset($_POST['billing_last_name']) ? sanitize_text_field($_POST['billing_last_name']) : $order->get_billing_last_name();
        $billing_cpf        = isset($_POST['billing_cpf']) ? sanitize_text_field($_POST['billing_cpf']) : WCAI_Data_Resolver::get_billing_cpf( $order );
        $billing_birthdate  = isset($_POST['billing_birthdate']) ? sanitize_text_field($_POST['billing_birthdate']) : WCAI_Data_Resolver::get_billing_birthdate( $order );

        foreach ( $items as $item_id => $item ) {
            // Verifica se este item é um produto de turismo configurado
            if ( in_array( $item->get_variation_id(), $target_ids ) || in_array( $item->get_product_id(), $target_ids ) ) {
                $qty = $item->get_quantity();
                $departure_id = absint( $item->get_meta( '_wcai_departure_id', true ) );
                $reservation_id = 0;

                if ( $departure_id && class_exists( 'WCAI_Reservations' ) ) {
                    $reservation = WCAI_Reservations::create( $departure_id, $qty, 'hold', $order_id, $item_id );

                    if ( is_wp_error( $reservation ) ) {
                        $order->add_order_note( 'Reserva de saída não criada: ' . $reservation->get_error_message() );
                        $reservation_error = $reservation->get_error_message();

                        // Não cria participantes sem a reserva operacional correspondente.
                        continue;
                    }

                    $reservation_id = absint( $reservation );
                }

                // --- AQUI ESTÁ A CORREÇÃO ---
                // Salvar o Titular (Participante 1) na Tabela DB para este Item
                // Isso garante que ele apareça na lista de participantes do evento
                if ( class_exists('WCAI_Participants_DB') && !empty($billing_cpf) ) {
                     WCAI_Participants_DB::add( array(
                        'order_id'      => $order_id,
                        'item_id'       => $item_id,
                        'reservation_id' => $reservation_id,
                        'customer_id'   => $order->get_customer_id(),
                        'nome_completo' => $billing_first_name . ' ' . $billing_last_name,
                        'cpf'           => $billing_cpf,
                        'data_nascimento' => $billing_birthdate // Certifique-se que o formato venha correto ou a classe DB converte
                    ));
                }
                // ---------------------------

                if ( $qty > 1 ) {
                    $note_content .= "--- Visitantes Adicionais (Produto: " . $item->get_name() . ") ---\n";
                    
                    for ( $i = 2; $i <= $qty; $i++ ) {
                        if ( isset( $_POST['nome_completo_' . $i] ) ) {
                            $nome = sanitize_text_field( $_POST['nome_completo_' . $i] );
                            $cpf = sanitize_text_field( $_POST['cpf_' . $i] ); 
                            $nasc = sanitize_text_field( $_POST['data_nascimento_' . $i] );

                            // Salva na Tabela DB
                            if ( class_exists('WCAI_Participants_DB') ) {
                                WCAI_Participants_DB::add( array(
                                    'order_id'      => $order_id,
                                    'item_id'       => $item_id,
                                    'reservation_id' => $reservation_id,
                                    'customer_id'   => $order->get_customer_id(),
                                    'nome_completo' => $nome,
                                    'cpf'           => $cpf,
                                    'data_nascimento' => $nasc
                                ));
                            }

                            // Meta legado (Backup)
                            $cpf_fmt = WCAI_Utils::format_cpf($cpf);
                            $participants_meta['visitante_' . $i] = array(
                                'nome_completo' => $nome,
                                'cpf'           => $cpf_fmt,
                                'data_nascimento' => $nasc
                            );

                            $note_content .= "Visitante $i: $nome - CPF: $cpf_fmt - Nasc: $nasc\n";
                        }
                    }
                }
            }
        }

        if ( ! empty( $participants_meta ) ) {
            $order->update_meta_data( '_additional_participants', $participants_meta );
            $order->save();
        }
        if ( ! empty( $note_content ) ) {
            $order->add_order_note( $note_content, 0, true );
        }
        if ( $reservation_error ) {
            $order->update_status( 'failed', 'Reserva indisponível: ' . $reservation_error );
        }
    }
}

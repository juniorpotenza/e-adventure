<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WCAI_Checkout {
    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'woocommerce_after_order_notes', array( $this, 'render_fields' ) );
        add_action( 'woocommerce_checkout_process', array( $this, 'validate_fields' ) );
        add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_fields_and_notes' ), 10, 1 );
    }

    public function enqueue_scripts() {
        if ( is_checkout() ) {
            wp_enqueue_script( 'jquery-mask', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js', array( 'jquery' ), '1.14.16', true );
            wp_add_inline_script( 'jquery-mask', "
                jQuery(document).ready(function($) {
                    $('input[name^=\"cpf_\"]').mask('000.000.000-00');
                    $('input[name^=\"data_nascimento_\"]').mask('00/00/0000');
                });
            " );
        }
    }

    public function render_fields( $checkout ) {
        $items = WC()->cart->get_cart();
        $target_ids = WCAI_Settings::get_product_ids();
        $rendered = false;

        foreach ( $items as $item ) {
            if ( in_array( $item['variation_id'], $target_ids ) || in_array( $item['product_id'], $target_ids ) ) {
                $qty = isset($item['quantity']) ? $item['quantity'] : 1;
                
                if ( $qty > 1 ) {
                    $rendered = true;
                    echo '<div id="wcai_custom_fields"><h3>Dados dos Visitantes Adicionais</h3>';
                    // Começa do 2, pois o 1 é o titular (billing)
                    for ( $i = 2; $i <= $qty; $i++ ) {
                        echo '<div class="wcai-visitor-box" style="margin-bottom:20px; padding:15px; border:1px solid #ddd;">';
                        echo '<h4>Visitante ' . $i . '</h4>';
                        
                        woocommerce_form_field( 'nome_completo_' . $i, array(
                            'type' => 'text', 'class' => array('form-row-wide'), 'label' => 'Nome Completo', 'required' => true
                        ), $checkout->get_value( 'nome_completo_' . $i ) );

                        woocommerce_form_field( 'cpf_' . $i, array(
                            'type' => 'text', 'class' => array('form-row-wide'), 'label' => 'CPF', 'required' => true
                        ), $checkout->get_value( 'cpf_' . $i ) );

                        woocommerce_form_field( 'data_nascimento_' . $i, array(
                            'type' => 'text', 'class' => array('form-row-wide'), 'label' => 'Data de Nascimento', 'required' => true, 'placeholder' => 'dd/mm/aaaa'
                        ), $checkout->get_value( 'data_nascimento_' . $i ) );
                        echo '</div>';
                    }
                    echo '</div>';
                }
            }
        }
    }

    public function validate_fields() {
        // Validação do Billing (Titular)
        $billing_cpf = isset( $_POST['billing_cpf'] ) ? $_POST['billing_cpf'] : '';
        if ( ! empty( $billing_cpf ) ) {
            if ( ! WCAI_Utils::is_valid_cpf( $billing_cpf ) ) {
                wc_add_notice( 'CPF de faturamento inválido.', 'error' );
            }
            if ( WCAI_Settings::is_cpf_blocked( $billing_cpf ) ) {
                wc_add_notice( 'Não é possível seguir com o agendamento (CPF Restrito).', 'error' );
            }
        }

        // Validação dos Visitantes
        $items = WC()->cart->get_cart();
        $target_ids = WCAI_Settings::get_product_ids();
        $cpfs_used = array();
        if($billing_cpf) $cpfs_used[] = WCAI_Utils::format_cpf($billing_cpf);

        foreach ( $items as $item ) {
            if ( in_array( $item['variation_id'], $target_ids ) || in_array( $item['product_id'], $target_ids ) ) {
                $qty = $item['quantity'];
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

    public function save_fields_and_notes( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $participants_meta = array(); 
        $note_content = ""; 
        $items = $order->get_items();
        $target_ids = WCAI_Settings::get_product_ids();

        // Dados do Titular (Billing)
        // Tentamos pegar do POST (mais atual) ou do Objeto Order
        $billing_first_name = isset($_POST['billing_first_name']) ? sanitize_text_field($_POST['billing_first_name']) : $order->get_billing_first_name();
        $billing_last_name  = isset($_POST['billing_last_name']) ? sanitize_text_field($_POST['billing_last_name']) : $order->get_billing_last_name();
        $billing_cpf        = isset($_POST['billing_cpf']) ? sanitize_text_field($_POST['billing_cpf']) : $order->get_meta('billing_cpf'); // Assumindo campo padrão BR
        $billing_birthdate  = isset($_POST['billing_birthdate']) ? sanitize_text_field($_POST['billing_birthdate']) : $order->get_meta('billing_birthdate'); // Campo comum em plugins BR

        foreach ( $items as $item_id => $item ) {
            // Verifica se este item é um produto de turismo configurado
            if ( in_array( $item->get_variation_id(), $target_ids ) || in_array( $item->get_product_id(), $target_ids ) ) {
                $qty = $item->get_quantity();
                
                // --- AQUI ESTÁ A CORREÇÃO ---
                // Salvar o Titular (Participante 1) na Tabela DB para este Item
                // Isso garante que ele apareça na lista de participantes do evento
                if ( class_exists('WCAI_Participants_DB') && !empty($billing_cpf) ) {
                     WCAI_Participants_DB::add( array(
                        'order_id'      => $order_id,
                        'item_id'       => $item_id,
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
    }
}

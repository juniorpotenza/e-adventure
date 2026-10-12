<?php
if ( ! defined( 'ABSPATH' ) ) exit;

trait WCAI_Checkout_Block_Setup {

    public function register_additional_checkout_fields() {
        if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
            return;
        }

        woocommerce_register_additional_checkout_field(
            array(
                'id'         => 'wcai/billing-cpf',
                'label'      => __( 'CPF do titular', 'wooadventure-integration' ),
                'location'   => 'contact',
                'type'       => 'text',
                'required'   => true,
                'attributes' => array(
                    'autocomplete' => 'off',
                ),
            )
        );

        woocommerce_register_additional_checkout_field(
            array(
                'id'         => 'wcai/billing-birthdate',
                'label'      => __( 'Data de nascimento do titular', 'wooadventure-integration' ),
                'location'   => 'contact',
                'type'       => 'text',
                'required'   => true,
                'attributes' => array(
                    'autocomplete' => 'bday',
                ),
            )
        );
    }

    public function sync_identity_field( $key, $value, $group, $wc_object ) {
        if ( ! $wc_object || ! is_object( $wc_object ) ) {
            return;
        }

        if ( 'wcai/billing-cpf' === $key ) {
            $wc_object->update_meta_data( 'billing_cpf', preg_replace( '/\\D+/', '', (string) $value ) );
        }

        if ( 'wcai/billing-birthdate' === $key ) {
            $wc_object->update_meta_data( 'billing_birthdate', sanitize_text_field( (string) $value ) );
        }
    }

    public function register_assets() {
        if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
            return;
        }

        $file = plugin_dir_path( dirname( __DIR__ ) ) . 'assets/js/wcai-checkout-block.js';

        wp_register_script(
            self::SCRIPT_HANDLE,
            plugin_dir_url( dirname( __DIR__ ) ) . 'assets/js/wcai-checkout-block.js',
            array( 'wp-data' ),
            file_exists( $file ) ? (string) filemtime( $file ) : WCAI_VERSION,
            true
        );

        wp_localize_script(
            self::SCRIPT_HANDLE,
            'wcaiCheckout',
            array(
                'consentText' => class_exists( 'WCAI_Privacy' ) ? WCAI_Privacy::consent_text() : '',
                'termsUrl'    => esc_url_raw( (string) WCAI_Settings::get_terms_url() ),
            )
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

}

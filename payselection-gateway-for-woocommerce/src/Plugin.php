<?php

namespace Payselection;

defined( 'ABSPATH' ) || exit;

class Plugin
{
    public function __construct()
    {
        // Gateway register
        add_action("woocommerce_init", function () {
            if (class_exists("\Payselection\Gateway")) {
                add_filter("woocommerce_payment_gateways", function ($methods) {
                    $methods[] = "\Payselection\Gateway";
                    return $methods;
                });
            }
        });

        // Widget scripts
        if (class_exists("\Payselection\Widget")) {
            add_action("wp_enqueue_scripts", "\Payselection\Widget::enqueue_scripts");
        }

        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);

        add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);

        add_action('woocommerce_blocks_loaded', [$this, 'woocommerce_blocks_support']);

    }

    public function enqueue_scripts() {
        wp_enqueue_script("payselection-gateway-woo-main", PAYSELECTION_WOO_URL . 'assets/js/main.js', ['jquery'], PAYSELECTION_WOO_VERSION, true);

        wp_localize_script('payselection-gateway-woo-main', 'payselection',
            [
                'payselection_error' => esc_html__('Payselection Error:', 'payselection-gateway-for-woocommerce'). ' ',
                'payselection_widget_errors' => [
                    'PAY_WIDGET:CREATE_INVALID_PARAMS' => esc_html__('Parameter error', 'payselection-gateway-for-woocommerce'), //onError
                    'PAY_WIDGET:CREATE_BAD_REQUEST_ERROR' => esc_html__('System error', 'payselection-gateway-for-woocommerce'), //onError
                    'PAY_WIDGET:CREATE_NETWORK_ERROR' => esc_html__('Network error', 'payselection-gateway-for-woocommerce'), //onError
                    'PAY_WIDGET:TRANSACTION_FAIL' => esc_html__('Transaction error', 'payselection-gateway-for-woocommerce'), //onError
                    'PAY_WIDGET:CLOSE_COMMON_ERROR' => esc_html__('Close after an error', 'payselection-gateway-for-woocommerce'), //onClose
                    'PAY_WIDGET:CLOSE_BEFORE_PAY' => esc_html__('Payment not completed', 'payselection-gateway-for-woocommerce'), //onClose
                    'PAY_WIDGET:CLOSE_AFTER_FAIL' => esc_html__('Close after fail', 'payselection-gateway-for-woocommerce'), //onClose
                    'PAY_WIDGET:CLOSE_AFTER_SUCCESS' => esc_html__('Close after success', 'payselection-gateway-for-woocommerce'), //onClose
                ],
            ]
        );
    }

    /**
     * Declares HPOS compatibility
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', PAYSELECTION_WOO_URL, true);
        }
    }

    /**
     * Support Cart and Checkout blocks from WooCommerce Blocks.
     */
    public function woocommerce_blocks_support() {
        if (class_exists('\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType') && class_exists('\Payselection\BlocksSupport')) {
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function(\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                    $payment_method_registry->register(new \Payselection\BlocksSupport());
                }
            );
        }
    }
}

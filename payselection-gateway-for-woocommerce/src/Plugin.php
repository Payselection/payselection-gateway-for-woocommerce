<?php

namespace Payselection;

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

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

        $option = (object) get_option('woocommerce_wc_payselection_gateway_settings');
        $paykassa_order_status = $option->paykassa_order_status ?? 'completed';
        if ('delivered' === $paykassa_order_status && !in_array('wc-delivered', get_post_statuses())) {
            add_action( 'init', [$this, 'register_delivered_status'] );
            add_filter( 'wc_order_statuses', [$this, 'add_status_to_list'] );
        }

    }

    public function enqueue_scripts() {
        wp_enqueue_script("payselection-gateway-woo-main", PAYSELECTION_WOO_URL . 'js/main.js', ['jquery'], PAYSELECTION_WOO_VERSION, true);
    }

    public function register_delivered_status() {
        register_post_status(
            'wc-delivered',
            array(
                'label'		=> esc_html__('Delivered', 'payselection-gateway-for-woocommerce'),
                'public'	=> true,
                'show_in_admin_status_list' => true,
                'label_count'	=> _n_noop( 'Delivered (%s)', 'Delivered (%s)' )
            )
        );
    }

    public function add_status_to_list($order_statuses) {

        $new = [];

        foreach ( $order_statuses as $id => $label ) {
            
            if ( 'wc-completed' === $id ) { 
                $new[ 'wc-delivered' ] = esc_html__('Delivered', 'payselection-gateway-for-woocommerce');
            }
            
            $new[ $id ] = $label;

        }

        return $new;
    }
}

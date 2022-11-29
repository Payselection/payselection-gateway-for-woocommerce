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

    }

    public function enqueue_scripts() {
        wp_enqueue_script("payselection-gateway-main", PAYSELECTION_URL . '/js/main.js', ['jquery'], time(), true);
    }
}

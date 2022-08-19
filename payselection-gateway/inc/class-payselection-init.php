<?php

if (!defined('ABSPATH')) {
    exit;
}

class Payselection_Init {
    
    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'load']);
    }
    
    public static function load() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Load translations
        load_plugin_textdomain('payselection', false, PAYSELECTION_DIR . 'languages');

        // Add gateway to WooCommerce
        add_filter('woocommerce_payment_gateways', [__CLASS__, 'add_gateway_class']);
        
        require(PAYSELECTION_DIR . 'inc/class-payselection-webhook.php');
        require(PAYSELECTION_DIR . 'inc/wc-payselection-gateway.php');
    }
    
    public static function add_gateway_class($methods) {
        $methods[] = 'WC_Payselection_Gateway';   
        return $methods;
    }
}
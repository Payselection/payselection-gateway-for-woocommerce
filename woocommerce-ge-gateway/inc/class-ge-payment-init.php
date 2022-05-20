<?php

class GateExpress_Init {
    
    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'GateExpress']);
    }
    
    public static function GateExpress() {
        if (!class_exists('WooCommerce')) {
            return;
        }

        add_filter('woocommerce_payment_gateways', [__CLASS__, 'add_gateway_class']);
        
        require(GE_PLUGIN_DIR . 'inc/wc-gate-express-gateway.php');
    }
    
    public static function add_gateway_class($methods) {
        $methods[] = 'WC_GateExpress_Gateway';   
        return $methods;
    }
}
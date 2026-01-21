<?php
/**
 * Plugin Name: Payselection Gateway for WooCommerce
 * Plugin URI: https://payselection.com/
 * Description: Payselection Gateway for WooCommerce
 * Version: 1.3.1
 * License: GNU GPLv3
 * Text Domain: payselection-gateway-for-woocommerce
 * Domain Path: /languages
 */

use \Payselection\Plugin;

defined('ABSPATH') or die('Ooops!');

define('PAYSELECTION_WOO_VERSION', '1.3.1');
define('PAYSELECTION_WOO_URL', plugin_dir_url(__FILE__));
define('PAYSELECTION_WOO_DIR', plugin_dir_path(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Payselection\\';

    $base_dir = PAYSELECTION_WOO_DIR. 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relative_class = substr($class, $len);
    
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// Run
new Plugin();
 
// Load language
add_action('plugins_loaded', function() {
    load_plugin_textdomain('payselection-gateway-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages'); 
});
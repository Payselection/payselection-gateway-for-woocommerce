<?php
/**
 * Plugin Name: Payselection Gateway for WooCommerce
 * Plugin URI: https://payselection.com/
 * Description: Payselection Gateway for WooCommerce
 * Version: 0.3.0
 * License: GNU GPLv3
 * Text Domain: payselection
 * Domain Path: /languages
 */

use \Payselection\Plugin;

defined('ABSPATH') or die('Ooops!');

define('PAYSELECTION_VERSION', '0.3.0');
define('PAYSELECTION_URL', plugin_dir_url(__FILE__));
define('PAYSELECTION_DIR', plugin_dir_path(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'Payselection\\';

    $base_dir = PAYSELECTION_DIR. 'src/';

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
    load_plugin_textdomain('payselection', false, dirname(plugin_basename(__FILE__)) . '/languages'); 
});
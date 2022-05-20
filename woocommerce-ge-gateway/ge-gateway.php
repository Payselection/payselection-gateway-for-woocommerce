<?php
/**
 * Plugin Name: Gate Express Gateway for WooCommerce
 * Plugin URI: https://solbeg.com
 * Description: GE Gateway for WooCommerce
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('GE_PLUGIN_DIR', plugin_dir_path(__FILE__));

require(GE_PLUGIN_DIR . 'inc/class-ge-payment-init.php');

if (class_exists('GateExpress_Init')) {
    GateExpress_Init::init();
}
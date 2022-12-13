<?php

namespace Payselection\Traits;

trait Options {
    
    public static function get_options() {
        return (object) get_option("woocommerce_wc_payselection_gateway_settings");
    }
}
<?php

namespace Payselection;

use Payselection\Api;

class Webhook extends Api
{
    public static function handle()
    {
        $options = (object) get_option("woocommerce_wc_payselection_gateway_settings");
    }
}

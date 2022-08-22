<?php

namespace Payselection;

use Payselection\Order;
use Payselection\Options;

class Widget
{
    use Traits\Options;

    /**
     * handle Widget page handler
     *
     * @return void
     */
    public static function handle()
    {
        require PAYSELECTION_DIR . "templates/widget.php";
        die();
    }

    /**
     * enqueue_scripts Custom scripts
     *
     * @return void
     */
    public static function enqueue_scripts()
    {
        if (empty($_REQUEST["paywidget"])) {
            return;
        }

        wp_enqueue_script("payselection-widget", "https://widget.payselection.com/lib/pay-widget.js", [], time(), false);
        wp_add_inline_script("payselection-widget", self::widget_js());
    }

    /**
     * widget_js JS pay handler
     *
     * @return void
     */
    public static function widget_js()
    {
        global $woocommerce;

        // Get plugin options
        $options = self::get_options();

        // Parse order ID from request
        $order_id = (int) $_REQUEST["order_id"];

        // Retrive order
        $order = new Order($order_id);

        // If order not found redirect to homepage
        if (!$order) {
            return "
                window.location.href = '" . home_url() . "';
            ";
        }

        // Get order data
        $data = $order->getRequestData();

        // Set service ID
        $data["ServiceId"] = (string) $options->site_id;

        return "
            const data = " .
            json_encode($data, JSON_UNESCAPED_UNICODE) .
            ";
            const widget = new pw.WidgetCreate();
            widget.pay(data, {
                onSuccess: () => {
                    window.location.href = '" . $data["PaymentRequest"]["ExtraData"]["SuccessUrl"] . "';
                },
                onError: () => {
                    window.location.href = '" . $data["PaymentRequest"]["ExtraData"]["CancelUrl"] . "';
                }
            });
        ";
    }
}

<?php

namespace Payselection;

use Payselection\Order;

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
        // Parse order ID from request
        $order_id = (int) $_REQUEST["order_id"];

        // Retrive order
        $order = new Order($order_id);

        if (!$order) {
            wp_redirect(home_url());
            exit;
        }

        if ('pending' != $order->get_status()) {
            wp_redirect($order->get_checkout_order_received_url());
            exit;
        }

        require PAYSELECTION_WOO_DIR . "templates/widget.php";
        die();
    }

    /**
     * enqueue_scripts Custom scripts
     *
     * @return void
     */
    public static function enqueue_scripts()
    {
        $options = self::get_options();

        if (empty($_REQUEST["paywidget"])) {
            return;
        }

        wp_enqueue_script("payselection-widget", $options->widget_url, [], time(), false);
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

        return "
            document.addEventListener('DOMContentLoaded', () => {
                const data = " .
                json_encode($data, JSON_UNESCAPED_UNICODE) .
                ";
                const widget = new pw.PayWidget();
                widget.pay({
                    serviceId: '" . esc_attr($options->site_id) . "',
                    key: '" . esc_attr($options->widget_key) . "'
                }, data, {
                    onSuccess: () => {
                        window.location.href = '" . esc_url($data["PaymentRequest"]["ExtraData"]["SuccessUrl"]) . "';
                    },
                    onError: (res) => {
                        localStorage.payselectionWidgetError = res.code;
                        localStorage.payselectionWidgetUrl = window.location.href;
                        window.location.href = '" . esc_url($data["PaymentRequest"]["ExtraData"]["CancelUrl"]) . "';
                    },
                    onClose: () => {
                        window.location.href = '" . esc_url($data["PaymentRequest"]["ExtraData"]["CancelUrl"]) . "';
                    }
                });
            });
        ";
    }
}

<?php

namespace Payselection;

use Payselection\Api;

class Webhook extends Api
{
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * handle Webhook handler
     *
     * @return void
     */
    public function handle()
    {
        $request = file_get_contents('php://input');
        $headers = getallheaders();

        $this->debug(wc_print_r($request, true));
        $this->debug(wc_print_r($headers, true));

        if (
            empty($request) ||
            empty($headers['X-SITE-ID']) ||
            $this->options->site_id != $headers['X-SITE-ID'] ||
            empty($headers['X-WEBHOOK-SIGNATURE'])
        )
            wp_die(esc_html__('Not found', 'payselection-gateway-for-woocommerce'), '', array('response' => 404));
        
        // Check signature
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        $signBody = $request_method . PHP_EOL . home_url('/wc-api/wc_payselection_gateway_webhook') . PHP_EOL . $this->options->site_id . PHP_EOL . $request;

        if ($headers['X-WEBHOOK-SIGNATURE'] !== self::getSignature($signBody, $this->options->key))
            wp_die(esc_html__('Signature error', 'payselection-gateway-for-woocommerce'), '', array('response' => 403));

        $request = json_decode($request, true);

        if (!$request)
            wp_die(esc_html__('Can\'t decode JSON', 'payselection-gateway-for-woocommerce'), '', array('response' => 403));
        
        $requestOrder = explode('-', $request['OrderId']);

        if (count($requestOrder) !== 3)
            wp_die(esc_html__('Order id error', 'payselection-gateway-for-woocommerce'), '', array('response' => 404));

        $order_id = (int) $requestOrder[0];
        $order = new \WC_Order($order_id);

        if (empty($order))
            wp_die(esc_html__('Order not found', 'payselection-gateway-for-woocommerce'), '', array('response' => 404));

        if ($request['Event'] === 'Fail' || $request['Event'] === 'Payment') {
            $order->add_order_note(sprintf(esc_html__("Payselection Webhook:\nEvent: %s\nOrderId: %s\nTransaction: %s", "payselection-gateway-for-woocommerce"), $request['Event'], esc_html($request['OrderId']), esc_html($request['TransactionId'])));
        }

        switch ($request['Event'])
        {
            case 'Payment':
                $order->add_order_note(sprintf(esc_html__('Payment approved (ID: %s)', 'payselection-gateway-for-woocommerce'), esc_html($request['TransactionId'])));
                $order->update_meta_data('TransactionId', sanitize_text_field($request['TransactionId']));
                self::payment($order, 'completed');
                break;

            case 'Fail':
                self::payment($order, 'fail');
                break;

            case 'Block':
                $order->update_meta_data('BlockTransactionId', sanitize_text_field($request['TransactionId']));
                self::payment($order, 'hold');
                break;

            case 'Refund':
                self::payment($order, 'refund');
                break;

            case 'Cancel':
                self::payment($order, 'cancel');
                break;

            default:
                wp_die(esc_html__('There is no handler for this event', 'payselection-gateway-for-woocommerce'), '', array('response' => 404));
                break;
        }
    }
    
    /**
     * payment Set order status
     *
     * @param  mixed $order
     * @param  mixed $status
     * @return void
     */
    private static function payment($order, $status = 'completed')
    {
        if ('completed' == $order->get_status() && $status !== 'refund') {
            wp_die(esc_html__('Ok', 'payselection-gateway-for-woocommerce'), '', array('response' => 200));
        }

        switch ($status)
        {
            case 'completed':
                $order->payment_complete();
                break;

            case 'hold':
                $order->update_status('on-hold');
                break;

            case 'cancel':
            case 'refund':
                $order->update_status('cancelled');
                break;

            default:
                $order->update_status('pending');
                break;
        }        
        
        wp_die(esc_html__('Ok', 'payselection-gateway-for-woocommerce'), '', array('response' => 200));
    }
}

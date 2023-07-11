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
        //$headers = getallheaders();
        $headers = $this->key_tolower(getallheaders());

        $this->debug(esc_html__('Webhook request', 'payselection-gateway-for-woocommerce'));
        $this->debug(wc_print_r($request, true));
        $this->debug(wc_print_r($headers, true));
        $this->debug(wc_print_r(getallheaders(), true));

        if (
            empty($request) ||
            empty($headers['x-site-id']) ||
            $this->options->site_id != $headers['x-site-id'] ||
            empty($headers['x-webhook-signature'])
        )
            wp_die(esc_html__('Not found', 'payselection-gateway-for-woocommerce'), '', array('response' => 404));
        
        // Check signature
        $request_method = isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_METHOD'])) : '';
        $signBody = $request_method . PHP_EOL . home_url('/wc-api/wc_payselection_gateway_webhook') . PHP_EOL . $this->options->site_id . PHP_EOL . $request;
        if ($headers['x-webhook-signature'] !== self::getSignature($signBody, $this->options->key))
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

        if ($request['Event'] === 'Fail' || $request['Event'] === 'Payment' || $request['Event'] === 'Refund') {
            $order->add_order_note(sprintf(esc_html__("Payselection Webhook:\nEvent: %s\nOrderId: %s\nTransaction: %s", "payselection-gateway-for-woocommerce"), $request['Event'], esc_html($request['OrderId']), esc_html($request['TransactionId'])));
        }

        switch ($request['Event'])
        {
            case 'Payment':
                $order->update_meta_data('TransactionId', sanitize_text_field($request['TransactionId']));
                if (is_callable([$order, 'save'])) {
                    $order->save();
                }
                $order->add_order_note(sprintf(esc_html__('Payment approved (Payment ID: %s)', 'payselection-gateway-for-woocommerce'), esc_html($request['TransactionId'])));
                self::payment($order, 'completed');
                break;

            case 'Fail':
                self::payment($order, 'fail');
                break;

            case 'Block':
                $order->add_order_note(
                    sprintf(
                        esc_html__( 'Payselection payment intent created (Payment Intent ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'payselection-gateway-for-woocommerce' ),
                        $request['TransactionId']
                    )
                );
                $order->update_meta_data('BlockTransactionId', sanitize_text_field($request['TransactionId']));
                self::payment($order, 'hold');
                break;

            case 'Refund':
                self::payment($order, 'refund'); 
                break;

            case 'Cancel':
                $order->add_order_note( sprintf( esc_html__( 'Pre-Authorization for %s voided.', 'payselection-gateway-for-woocommerce' ), $request['Amount']));
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
                //$order->payment_complete($order->get_meta('TransactionId', true));
                $order->payment_complete();
                break;

            case 'fail':
                $order->update_status('failed');
                break;

            case 'hold':
                $order->update_status('on-hold');
                break;

            case 'cancel':
                $order->update_status('cancelled');
                break;

            case 'refund':
                break;

            default:
                $order->update_status('pending');
                break;
        }        
        
        wp_die(esc_html__('Ok', 'payselection-gateway-for-woocommerce'), '', array('response' => 200));
    }

    public function key_tolower($array = []) {
        foreach ($array as $key=>$value) {
            $new_key = strtolower($key);
            $array[$new_key] = $array[$key];
        }
    }
}

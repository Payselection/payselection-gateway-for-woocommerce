<?php

namespace Payselection;

use Payselection\Api;

class Webhook extends Api
{
    public function __construct() {
        parent::__construct();
    }

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
            wp_die('Not found', 'payselection', array('response' => 404));
        
        // Check signature
        $signBody = $_SERVER['REQUEST_METHOD'] . PHP_EOL . home_url('/wc-api/wc_payselection_gateway_webhook') . PHP_EOL . $this->options->site_id . PHP_EOL . $request;

        if ($headers['X-WEBHOOK-SIGNATURE'] !== self::getSignature($signBody, $this->options->key))
            wp_die('Signature error', 'payselection', array('response' => 403));

        $request = json_decode($request, true);

        if (!$request)
            wp_die('Can\'t decode JSON', 'payselection', array('response' => 403));
        
        $requestOrder = explode('-', $request['OrderId']);

        if (count($requestOrder) !== 3)
            wp_die('Order id error', 'payselection', array('response' => 404));

        $order_id = (int) $requestOrder[0];
        $order = new \WC_Order($order_id);

        if (empty($order))
            wp_die('Order not found', 'payselection', array('response' => 404));

        if ($request['Event'] === 'Fail' || $request['Event'] === 'Payment') {
            $order->add_order_note(sprintf("Payselection Webhook:\nEvent: %s\nOrderId: %s\nTransaction: %s", $request['Event'], esc_sql($request['OrderId']), esc_sql($request['TransactionId'])));
        }

        switch ($request['Event'])
        {
            case 'Payment':
                $order->add_order_note(sprintf('Payment approved (ID: %s)', esc_sql($request['TransactionId'])));
                $order->update_meta_data('TransactionId', esc_sql($request['TransactionId']));
                self::payment($order, 'completed');
                break;

            case 'Fail':
                self::payment($order, 'fail');
                break;

            case 'Block':
                $order->update_meta_data('BlockTransactionId', esc_sql($request['TransactionId']));
                self::payment($order, 'hold');
                break;

            case 'Refund':
                self::payment($order, 'refund');
                break;

            case 'Cancel':
                self::payment($order, 'cancel');
                break;

            default:
                wp_die('There is no handler for this event', 'payselection', array('response' => 404));
                break;
        }
    }

    private static function payment($order, $status = 'completed')
    {
        if ('completed' == $order->get_status() && $status !== 'refund') {
            wp_die('Ok', 'payselection', array('response' => 200));
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
        
        wp_die('Ok', 'payselection', array('response' => 200));
    }
}

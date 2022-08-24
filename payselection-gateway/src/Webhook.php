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
            
        $order_id = (int) $request['OrderId'];
        $order = new \WC_Order($order_id);

        if (empty($order))
            wp_die('Order not found', 'payselection', array('response' => 404));

        switch ($request['Event'])
        {
            case 'Payment':
                self::payment($order, 'completed', $request['TransactionId']);
                break;

            case 'Fail':
                self::payment($order, 'fail');
                break;

            case 'Block':
                self::payment($order, 'hold');
                break;

            case 'Refund':
            case 'Cancel':
                self::payment($order, 'cancel');
                break;

            default:
                wp_die('There is no handler for this event', 'payselection', array('response' => 404));
                break;
        }
    }

    private static function payment($order, $status = 'completed', $transaction = false)
    {
        if ('completed' == $order->get_status()) {
            wp_die('Ok', 'payselection', array('response' => 200));
        }

        switch ($status)
        {
            case 'completed':
                $order->payment_complete();
                $order->add_order_note(sprintf('Payment approved (ID: %s)', $transaction));
                break;

            case 'hold':
                $order->update_status('wc-on-hold');
                break;

            case 'cancel':
                $order->update_status('wc-cancelled');
                break;

            default:
                $order->update_status('wc-pending');
                break;
        }        
        
        wp_die('Ok', 'payselection', array('response' => 200));
    }
}

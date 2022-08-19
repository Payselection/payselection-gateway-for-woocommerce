<?php

if (!defined('ABSPATH')) {
    exit;
}

class Payselection_Webhook {

    public function handle()
    {
        $request = file_get_contents('php://input');
        $headers = getallheaders();

        if (
            empty($request) ||
            empty($headers['X-SITE-ID']) ||
            $this->site_id != $headers['X-SITE-ID'] ||
            empty($headers['X-WEBHOOK-SIGNATURE'])
        )
            wp_die('Not found', 'payselection', array('response' => 404));
        
        // Check signature
        if ($headers['X-WEBHOOK-SIGNATURE'] !== self::getSignature($request, $this->key))
            wp_die('Signature error', 'payselection', array('response' => 403));

        $request = json_decode($request, true);

        if (!$requst)
            wp_die('Can\'t decode JSON', 'payselection', array('response' => 403));
            
        $order_id = (int) $request['OrderId'];
        $order = new WC_Order($order_id);

        if (empty($order))
            wp_die('Order not found', 'payselection', array('response' => 404));

        switch ($request['Event']) {

            case 'Payment':
                $this->payment($order);
                break;

            default:
                wp_die('There is no hadler for this event', 'payselection', array('response' => 404));
                break;
        }

    }

    private function payment($order)
    {
        if ('on-hold'== $order->get_status()) {
            $order->update_status('processing');
        }

        if ('completed' == $order->get_status()) {
            wp_die('Ok', 'payselection', array('response' => 200));
        }

        $order->payment_complete();
        $order->add_order_note(sprintf('Payment approved (ID: %s)', $request['TransactionId']));
        
        wp_die('Ok', 'payselection', array('response' => 200));
    }
}
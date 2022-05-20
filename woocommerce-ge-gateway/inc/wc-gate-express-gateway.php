<?php

class WC_GateExpress_Gateway extends WC_Payment_Gateway {
    
    public function __construct() {
        $this->id                   = 'wc_gateexpress_gateway';
        $this->has_fields           = true;
        $this->icon                 = GE_PLUGIN_URL . 'ge.svg';
        $this->method_title         = __('Gate Express', 'gate_express');
        $this->method_description   = __('Pay via Gate Express', 'gate_express');
        
        $this->init_form_fields();
        $this->init_settings();        

        $this->enabled      = $this->get_option('enabled');
        $this->host         = $this->get_option('host');
        $this->create_host  = $this->get_option('create_host');
        $this->check_host   = $this->get_option('check_host');
        $this->site_id      = $this->get_option('site_id');
        $this->key          = $this->get_option('key');
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . $this->id . '_webhook', array($this, 'webhook'));
        add_action('woocommerce_thankyou', array($this, 'check_payment'), 10);
    }
    
        
    /**
     * init_form_fields Create settings page fields
     *
     * @return void
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled'   => array(
                'title'   => __('Enable/Disable', 'gate_express'),
                'type'    => 'checkbox',
                'label'   => __('Enable GateExpress', 'gate_express'),
                'default' => 'yes'
            ),
            'webhook'   => array(
                'title'       => __('Webhook url', 'gate_express'),
                'type'        => 'text',
                'default'     => home_url('/wc-api/' . $this->id . '_webhook'),
                'custom_attributes' => ['readonly' => 'readonly']
            ),
            'host'      => array(
                'title'       => __('API host', 'gate_express'),
                'type'        => 'text',
                'description' => __('API hostname', 'gate_express'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'create_host'   => array(
                'title'       => __('Create Payment host', 'gate_express'),
                'type'        => 'text',
                'description' => __('Leave blank if you dont know what you do', 'gate_express'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'check_host'   => array(
                'title'       => __('Check Payment host', 'gate_express'),
                'type'        => 'text',
                'description' => __('Leave blank if you dont know what you do', 'gate_express'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'site_id'        => array(
                'title'       => __('Site ID', 'gate_express'),
                'type'        => 'text',
                'description' => __('Your site ID on Gate Express', 'gate_express'),
                'default'     => '',
                'desc_tip'    => false,
            ),
            'key'        => array(
                'title'       => __('Key', 'gate_express'),
                'type'        => 'text',
                'description' => __('Your Key on Gate Express', 'gate_express'),
                'default'     => '',
                'desc_tip'    => false,
            ),
            'title'            => array(
                'title'       => __('Title', 'gate_express'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'gate_express'),
                'default'     => __('Pay via Gate Express', 'gate_express'),
                'desc_tip'    => true,
            ),
            'description'      => array(
                'title'       => __('Description', 'gate_express'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'gate_express'),
                'default'     => __('To pay for the order, you will be redirected to the GateExpress service page.', 'gate_express'),
                'desc_tip'    => true,
            ),
        );
    }
    
    /**
     * guidv4 Create uuid unique id
     * Ref: https://www.uuidgenerator.net/dev-corner/php   
     *
     * @param  array|null $data - Random 16 bytes
     * @return string uuid
     */
    private static function guidv4($data = null) {
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
        
    /**
     * getSignature Get signature by request body and key
     *
     * @param  string $body
     * @param  string $secretKey
     * @return void
     */
    private static function getSignature(string $body, string $secretKey) {
        $hash = hash_hmac('sha256', $body, $secretKey, false);
        return $hash;
    }
    
    /**
     * process_payment Create payment link and redirect
     *
     * @param  int $order_id
     * @return void
     */
    public function process_payment($order_id) {
        global $woocommerce;
        $order = new WC_Order($order_id);

        if (!$order)
            return false;
        
        $body = [
            'MetaData'          => [
                'PaymentType'       => 'Pay'
            ],
            'PaymentRequest'    => [
                'OrderId'           => (string) $order->get_id(),
                'Amount'            => number_format($order->get_total(), 2, '.', ''),
                'Currency'          => $order->get_currency(),
                'Description'       => 'Order payment #' . $order_id,
                'RebillFlag'        => true,
                'ExtraData'         => [
                    'ReturnUrl'         => $this->get_return_url($order)
                ]
            ],
            'CustomerInfo'      => [
                'Email'             => $order->get_billing_email(),
                'Phone'             => $order->get_billing_phone(),
                'Language'          => substr(determine_locale(), 0, 2),
                'Address'           => $order->get_billing_address_1(),
                'Town'              => $order->get_billing_city(),
                'ZIP'               => $order->get_billing_postcode(),
                // 'Country'           => $order->get_billing_country(),
                'FirstName'         => $order->get_billing_first_name(),
                'LastName'          => $order->get_billing_last_name(),
            ]
        ];

        $body_json = json_encode($body, JSON_UNESCAPED_UNICODE);

        $headers = [
            'X-SITE-ID'             => $this->site_id,
            'X-REQUEST-ID'          => self::guidv4(),
            'X-REQUEST-SIGNATURE'   => self::getSignature($body_json, $this->key)
        ];

        $host = !empty($this->create_host) ? $this->create_host : $this->host;
        $response = wp_remote_post($host . '/webpayments/create',
            array(
                'timeout'     => 30,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => $headers,
                'body'        => $body_json
            )
        );

        if (is_wp_error($response)) {
            wc_add_notice(__('Gate Express error:', 'gate_express') . $response->get_error_message());
            return false;
        }

        // Decode response
        $response['body'] = json_decode($response['body'], true);

        $code = $response['response']['code'];

        switch ($code) {

            // Link created, redirect
            case 201:
                if (empty($response['body'])) {
                    wc_add_notice(__('Gate Express error:', 'gate_express') . ' ' . __("can't get payment link", 'gate_express'), 'error');
                } else {
                    if ('processing'== $order->get_status()) {
                        $order->update_status('on-hold');
                    }
                    return array(
                        'result'   => 'success',
                        'redirect' => $response['body']
                    );
                }
                break;
            
            // Maybe error
            default:
                wc_add_notice(__('Gate Express error:', 'gate_express') . ' ' . $response['body']['Code'] . ($response['body']['Description'] ? " " . $response['body']['Description'] : ''), 'error');
                break;
        }

        return false;
    }

        
    /**
     * check_payment Check payment on Thank you page
     *
     * @param  mixed $order_id
     * @return void
     */
    public function check_payment($order_id) {
        global $woocommerce;
        $order = new WC_Order($order_id);

        if ($order->get_payment_method() !== $this->id || 'completed' == $order->get_status())
            return;

        if ('on-hold'== $order->get_status()) {
            $order->update_status('processing');
        }
        
        $headers = [
            'X-SITE-ID'             => $this->site_id,
            'X-REQUEST-ID'          => self::guidv4(),
            'X-REQUEST-SIGNATURE'   => ';'
        ];

        $host = !empty($this->check_host) ? $this->check_host : $this->host;
        $response = wp_remote_get(rtrim($host, '/') . '/orders/' . $order->get_id(),
            array(
                'timeout'     => 30,
                'redirection' => 5,
                'httpversion' => '1.0',
                'blocking'    => true,
                'headers'     => $headers
            )
        );

        $code = $response['response']['code'];

        if ($code === 200) {
            $results = json_decode($response['body'], true);
            if (is_array($results)) {
                foreach ($results as $result) {
                    if ($result['TransactionState'] == 'success') {
                        $order->payment_complete();
                        $order->add_order_note(sprintf('Payment approved (ID: %s)', $result['TransactionId']));
                        break;
                    }
                }
            }
        }
    }
    
    /**
     * webhook API webhook endpoint
     *
     * @return void
     */
    public function webhook() {
        $request = file_get_contents('php://input');
        $headers = getallheaders();
        if (
            empty($request) ||
            empty($headers['X-SITE-ID']) ||
            $this->site_id != $headers['X-SITE-ID'] ||
            empty($headers['X-WEBHOOK-SIGNATURE'])
        )
            wp_die('Not found', 'gate_express', array('response' => 404));
        
        // Check signature
        if ($headers['X-WEBHOOK-SIGNATURE'] !== self::getSignature($request, $this->key))
            wp_die('Signature error', 'gate_express', array('response' => 404));

        $request = json_decode($request, true);
        
        $order_id = (int) $request['OrderId'];
        $order = new WC_Order($order_id);

        if (empty($order))
            wp_die('Order not found', 'gate_express', array('response' => 404));
        
        if ('completed' == $order->get_status()) {
            wp_die('Ok', 'gate_express', array('response' => 200));
        }

        if ('on-hold'== $order->get_status()) {
            $order->update_status('processing');
        }

        if ($request['Event'] === 'Payment') {
            $order->payment_complete();
            $order->add_order_note(sprintf('Payment approved (ID: %s)', $request['TransactionId']));
        }
        
        wp_die('Ok', 'gate_express', array('response' => 200));
        die();
    }
}
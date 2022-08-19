<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Payselection_Gateway extends WC_Payment_Gateway {
    
    public function __construct() {
        $this->id                   = 'wc_payselection_gateway';
        $this->has_fields           = true;
        $this->icon                 = PAYSELECTION_URL . 'logo.svg';
        $this->method_title         = __('Payselection', 'payselection');
        $this->method_description   = __('Pay via Payselection', 'payselection');
        
        $this->init_form_fields();
        $this->init_settings();        

        $this->enabled      = $this->get_option('enabled');
        $this->redirect     = $this->get_option('redirect');
        $this->host         = $this->get_option('host');
        $this->create_host  = $this->get_option('create_host');
        $this->check_host   = $this->get_option('check_host');
        $this->site_id      = $this->get_option('site_id');
        $this->key          = $this->get_option('key');
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');

        $webhook = new Payselection_Webhook();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_' . $this->id . '_webhook', array($webhook, 'handle'));
        add_action('woocommerce_api_' . $this->id . '_widget', array($this, 'widget'));
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
                'title'   => __('Enable/Disable', 'payselection'),
                'type'    => 'checkbox',
                'label'   => __('Enable Payselection', 'payselection'),
                'default' => 'yes'
            ),
            'redirect'   => array(
                'title'   => __('Widget/Redirect', 'payselection'),
                'type'    => 'checkbox',
                'label'   => __('Redirect to Payselection', 'payselection'),
                'default' => 'no'
            ),
            'webhook'   => array(
                'title'       => __('Webhook URL', 'payselection'),
                'type'        => 'text',
                'default'     => home_url('/wc-api/' . $this->id . '_webhook'),
                'custom_attributes' => ['readonly' => 'readonly']
            ),
            'host'      => array(
                'title'       => __('API host', 'payselection'),
                'type'        => 'text',
                'description' => __('API hostname', 'payselection'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'create_host'   => array(
                'title'       => __('Create Payment host', 'payselection'),
                'type'        => 'text',
                'description' => __('Leave blank if you dont know what you do', 'payselection'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'check_host'   => array(
                'title'       => __('Check Payment host', 'payselection'),
                'type'        => 'text',
                'description' => __('Leave blank if you dont know what you do', 'payselection'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'site_id'        => array(
                'title'       => __('Site ID', 'payselection'),
                'type'        => 'text',
                'description' => __('Your site ID on Payselection', 'payselection'),
                'default'     => '',
                'desc_tip'    => false,
            ),
            'key'        => array(
                'title'       => __('Key', 'payselection'),
                'type'        => 'text',
                'description' => __('Your Key on Payselection', 'payselection'),
                'default'     => '',
                'desc_tip'    => false,
            ),
            'language'         => array(
                'title'   => __('Widget language', 'payselection'),
                'type'    => 'select',
                'default' => 'en',
                'options' => array(
                    'ru' => __('Russian', 'payselection'),
                    'en' => __('English', 'payselection'),
                ),
            ),
            'title'            => array(
                'title'       => __('Title', 'payselection'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'payselection'),
                'default'     => __('Pay via Payselection', 'payselection'),
                'desc_tip'    => true,
            ),
            'description'      => array(
                'title'       => __('Description', 'payselection'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'payselection'),
                'default'     => __('To pay for the order, you will be redirected to the Payselection service page.', 'payselection'),
                'desc_tip'    => true,
            ),
        );
    }

    public function widget() {
        require(PAYSELECTION_URL . 'templates/widget.php');
        die();
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
            wc_add_notice(__('Payselection error:', 'payselection') . $response->get_error_message());
            return false;
        }

        // Decode response
        $response['body'] = json_decode($response['body'], true);

        $code = $response['response']['code'];

        switch ($code) {

            // Link created, redirect
            case 201:
                if (empty($response['body'])) {
                    wc_add_notice(__('Payselection error:', 'payselection') . ' ' . __("can't get payment link", 'payselection'), 'error');
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
                wc_add_notice(__('Payselection error:', 'payselection') . ' ' . $response['body']['Code'] . ($response['body']['Description'] ? " " . $response['body']['Description'] : ''), 'error');
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
}
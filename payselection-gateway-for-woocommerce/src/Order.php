<?php

namespace Payselection;

class Order extends \WC_Order
{
    use Traits\Options;
    
    /**
     * getRequestData Create order data for Payselection
     *
     * @return void
     */
    public function getRequestData()
    {
        // Get plugin options
        $options = self::get_options();

        $successUrl = $this->get_checkout_order_received_url();
        $cancelUrl = esc_url(wc_get_checkout_url());

        // Redirect links
        $extraData = [
            "WebhookUrl"    => home_url('/wc-api/wc_payselection_gateway_webhook'),
            "SuccessUrl"    => $successUrl,
            "CancelUrl"     => $cancelUrl,
            "DeclineUrl"    => $cancelUrl,
            "FailUrl"       => $cancelUrl,
        ];

        $data = [
            "MetaData" => [
                "PaymentType" => !empty($options->type) ? $options->type : "Pay",
            ],
            "PaymentRequest" => [
                "OrderId" => implode("-",[$this->get_id(), $options->site_id, time()]),
                "Amount" => number_format($this->get_total(), 2, ".", ""),
                "Currency" => $this->get_currency(),
                "Description" => esc_html__('Order payment #', 'payselection-gateway-for-woocommerce') . $this->get_id(),
                "PaymentMethod" => "Card",
                "RebillFlag" => !empty($options->rebill) ? !!$options->rebill : false,
                "ExtraData" => $extraData,
            ],
            "CustomerInfo" => [
                "Language" => !empty($options->language) ? $options->language : "en",
            ],
        ];

        if (!empty($billing_email = $this->get_billing_email())) {
            $data['CustomerInfo']['Email'] = $billing_email;
        }

        if (!empty($billing_phone = $this->get_billing_phone())) {
            $data['CustomerInfo']['Phone'] = str_replace(array('(', ')', ' ', '-'), '', $billing_phone);
        }

        if (!empty($billing_address = $this->get_billing_address_1())) {
            $data['CustomerInfo']['Address'] = $billing_address;
        }

        if (!empty($billing_city = $this->get_billing_city())) {
            $data['CustomerInfo']['Town'] = $billing_city;
        }

        if (!empty($billing_zip = $this->get_billing_postcode())) {
            $data['CustomerInfo']['ZIP'] = $billing_zip;
        }

        if ($options->receipt === 'yes') {
            $data['ReceiptData'] = $this->getReceiptData($options);
        }

        return $data;
    }
    
    /**
     * getReceiptData Create receipt data
     *
     * @param  mixed $options
     * @return void
     */
    public function getReceiptData(object $options)
    {
        $items = [];
        $cart = $this->get_items();

        $payment_method = $options->payment_method ?? 'full_prepayment';
        $payment_object = $options->payment_object ?? 'commodity';

        foreach ($cart as $item_data) {
            $product = $item_data->get_product();
            $items[] = [
                'name'           => mb_substr($product->get_name(), 0, 120),
                'sum'            => (float) number_format(floatval($item_data->get_total()), 2, '.', ''),
                'price'          => (float) number_format($product->get_price(), 2, '.', ''),
                'quantity'       => (int) $item_data->get_quantity(),
                'payment_method' => $payment_method,
                'payment_object' => $payment_object,
                'vat'            => [
                    'type'          => $options->company_vat,
                ] 
            ];
        }
        
        if ($this->get_total_shipping()) {
			$items[] = [
                'name'           => esc_html__('Shipping', 'payselection-gateway-for-woocommerce'),
                'sum'            => (float) number_format($this->get_total_shipping(), 2, '.', ''),
                'price'          => (float) number_format($this->get_total_shipping(), 2, '.', ''),
                'quantity'       => 1,
                'payment_method' => $payment_method,
                'payment_object' => 'service',
                'vat'            => [
                    'type'          => $options->company_vat,
                ]  
            ];
        }

        $data = [
            'timestamp' => date('d.m.Y H:i:s'),
            'external_id' => (string) $this->get_id(),
            'receipt' => [
                'client' => [
                    'email' => $this->get_billing_email(),
                ],
                'company' => [
                    'email' => $options->company_email,
                    'inn' => $options->company_inn,
                    'sno' => $options->company_tax_system,
                    'payment_address' => $options->company_address,
                ],
                'items' => $items,
                'payments' => [
                    [
                        'type' => 1,
                        'sum' => (float) number_format($this->get_total(), 2, '.', ''),
                    ]
                ],
                'total' => (float) number_format($this->get_total(), 2, '.', ''),
            ],
        ];

        if (!empty($this->get_total_discount())) {

            $data['receipt']['payments'][] = [
                'type' => 2,
                'sum' => (float) number_format($this->get_total_discount(false), 2, '.', ''),
            ];
            
        }

        return $data;
    }
    
    /**
     * getChargeCancelData Create data for Charge or Cancel
     *
     * @return void
     */
    public function getChargeCancelData()
    {
        return [
            "TransactionId" => $this->get_meta('BlockTransactionId'),
            "Amount"        => number_format($this->get_total(), 2, ".", ""),
            "Currency"      => $this->get_currency(),
            "WebhookUrl"    => home_url('/wc-api/wc_payselection_gateway_webhook'),
        ];
    }

    /**
     * getRefundData Create data for Refund
     *
     * @return void
     */
    public function getPayselectionRefundData($amount)
    {
        // Get plugin options
        $options = self::get_options();

        $items = [];

        $data = [
            "TransactionId" => $this->get_meta('TransactionId', true),
            "Amount"        => number_format($amount, 2, ".", ""),
            "Currency"      => $this->get_currency(),
            "WebhookUrl"    => home_url('/wc-api/wc_payselection_gateway_webhook'),
        ];

        if ($options->receipt === 'yes') {

            $payment_method = $options->payment_method ?? 'full_prepayment';
            $payment_object = $options->payment_object ?? 'commodity';

            $items[] = [
                'name'           => esc_html__('Refund', 'payselection-gateway-for-woocommerce'),
                'sum'            => (float) number_format(floatval($amount), 2, '.', ''),
                'price'          => (float) number_format($amount, 2, '.', ''),
                'quantity'       => 1,
                'payment_method' => $payment_method,
                'payment_object' => $payment_object,
                'vat'            => [
                    'type'          => (string) $options->company_vat,
                ] 
            ];

            $data['ReceiptData'] = [
                'timestamp' => date('d.m.Y H:i:s'),
                'external_id' => (string) $this->get_id(),
                'receipt' => [
                    'client' => [
                        'email' => $this->get_billing_email(),
                    ],
                    'company' => [
                        'email' => (string) $options->company_email,
                        'inn' => (string) $options->company_inn,
                        'sno' => (string) $options->company_tax_system,
                        'payment_address' => (string) $options->company_address,
                    ],
                    'items' => $items,
                    'payments' => [
                        [
                            'type' => 1,
                            'sum' => (float) number_format($amount, 2, '.', ''),
                        ]
                    ],
                    'total' => (float) number_format($amount, 2, '.', ''),
                ],
            ];
        }

        return $data;
    }

    /**
     * getPaykassaReceiptData Create receipt data
     *
     * @param  mixed $options
     * @return void
     */
    public function getPaykassaReceiptData()
    {
        // Get plugin options
        $options = self::get_options();
        
        $payment_method = $options->payment_method ?? 'full_prepayment';
        $payment_object = $options->payment_object ?? 'commodity';
        $company_email  = $options->company_email ?? '';

        $items = [];
        $cart = $this->get_items();

        foreach ($cart as $item_data) {
            $product = $item_data->get_product();
            $items[] = [
                'name'           => mb_substr($product->get_name(), 0, 120),
                'sum'            => (float) number_format(floatval($item_data->get_total() + $item_data->get_total_tax()), 2, '.', ''),
                //'price'            => (float) number_format(floatval($item_data->get_total()/$item_data->get_quantity()), 2, '.', ''),
                'price'          => (float) number_format($product->get_price(), 2, '.', ''),
                'quantity'       => (int) $item_data->get_quantity(),
                'payment_method' => $payment_method,
                'payment_object' => $payment_object,
                'vat'            => [
                    'type'          => $options->company_vat,
                ],
                'measure'        => 0
            ];
        }
        
        if ($this->get_total_shipping()) {
			$items[] = [
                'name'           => esc_html__('Shipping', 'payselection-gateway-for-woocommerce'),
                'sum'            => (float) number_format($this->get_total_shipping(), 2, '.', ''),
                'price'          => (float) number_format($this->get_total_shipping(), 2, '.', ''),
                'quantity'       => 1,
                'payment_method' => $payment_method,
                'payment_object' => 'service',
                'vat'            => [
                    'type'          => $options->company_vat,
                ]  
            ];
        }

        $data = [
            'operation_type' => 'Income',
            'external_id' => (string) $this->get_id(),
            'receipt' => [
                'client' => [
                    'email' => $this->get_billing_email(),
                ],
                'company' => [
                    'inn' => $options->company_inn,
                    'sno' => $options->company_tax_system,
                    'payment_address' => $options->company_address,
                ],
                'items' => $items,
                'payments' => [
                    [
                        'type' => 1,
                        'sum' => (float) number_format($this->get_total(), 2, '.', ''),
                    ]
                ],
                'total' => (float) number_format($this->get_total(), 2, '.', ''),
            ],
        ];

        if (!empty($company_email)) {

            $data['receipt']['company']['email'] = $company_email;
            
        }

        // if (!empty($total_discount = $this->get_total_discount(false))) {

        //     $data['receipt']['payments'][] = [
        //         'type' => 2,
        //         'sum' => (float) number_format($total_discount, 2, '.', ''),
        //     ];
            
        // }

        return $data;
    }
    
}

<?php

namespace Payselection;

use Payselection\Api;
use Payselection\Order;

class Gateway extends \WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = "wc_payselection_gateway";
        $this->has_fields = true;
        $this->icon = PAYSELECTION_WOO_URL . "logo.svg";
        $this->method_title = esc_html__("Payselection", "payselection-gateway-for-woocommerce");
        $this->method_description = esc_html__("Pay via Payselection", "payselection-gateway-for-woocommerce");

        $this->supports = [
			'products',
			'refunds',
		];

        $this->init_form_fields();
        $this->init_settings();

        $this->enabled = $this->get_option("enabled");
        $this->redirect = $this->get_option("redirect");
        $this->title = $this->get_option("title");
        $this->description = $this->get_option("description");
        
        $this->payselection = new Api();

        add_action("woocommerce_update_options_payment_gateways_" . $this->id, [$this, "process_admin_options"]);
        add_action("woocommerce_order_status_changed", array($this, "update_order_status"), 10, 3);
        add_action("woocommerce_api_" . $this->id . "_webhook", [new Webhook(), "handle"]);
        add_action("woocommerce_api_" . $this->id . "_widget", "\Payselection\Widget::handle");
    }

    /**
     * init_form_fields Create settings page fields
     *
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = [
            "enabled" => [
                "title" => esc_html__("Enable/Disable", "payselection-gateway-for-woocommerce"),
                "type" => "checkbox",
                "label" => esc_html__("Enable Payselection", "payselection-gateway-for-woocommerce"),
                "default" => "yes",
            ],
            "redirect" => [
                "title" => esc_html__("Widget/Redirect", "payselection-gateway-for-woocommerce"),
                "type" => "checkbox",
                "label" => esc_html__("Redirect to Payselection", "payselection-gateway-for-woocommerce"),
                "default" => "no",
            ],
            "type" => [
                "title" => esc_html__("Payment type", "payselection-gateway-for-woocommerce"),
                "type" => "select",
                "default" => "Pay",
                "options" => [
                    "Pay" => esc_html__("Pay", "payselection-gateway-for-woocommerce"),
                    "Block" => esc_html__("Block", "payselection-gateway-for-woocommerce"),
                ],
            ],
            "webhook" => [
                "title" => esc_html__("Webhook URL", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "default" => home_url("/wc-api/" . $this->id . "_webhook"),
                "custom_attributes" => ["readonly" => "readonly"],
            ],
            "host" => [
                "title" => esc_html__("API host", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => esc_html__("API hostname", "payselection-gateway-for-woocommerce"),
                "default" => "https://gw.payselection.com",
                "desc_tip" => true,
            ],
            "create_host" => [
                "title" => esc_html__("Create Payment host", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => esc_html__("Create Payment hostname", "payselection-gateway-for-woocommerce"),
                "default" => "https://webform.payselection.com",
                "desc_tip" => true,
            ],
            "site_id" => [
                "title" => esc_html__("Site ID", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => esc_html__("Your site ID on Payselection", "payselection-gateway-for-woocommerce"),
                "default" => "",
                "desc_tip" => false,
            ],
            "key" => [
                "title" => esc_html__("Secret Key", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => esc_html__("Your Key on Payselection", "payselection-gateway-for-woocommerce"),
                "default" => "",
                "desc_tip" => false,
            ],
            "widget_url" => [
                "title" => esc_html__("Widget URL", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "default" => "https://widget.payselection.com/lib/pay-widget.js",
                "desc_tip" => true,
            ],
            "widget_key" => [
                "title" => esc_html__("Public Key", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => esc_html__("Your Public Key on Payselection", "payselection-gateway-for-woocommerce"),
                "default" => "",
                "desc_tip" => false,
            ],
            "language" => [
                "title" => esc_html__("Widget language", "payselection-gateway-for-woocommerce"),
                "type" => "select",
                "default" => "en",
                "options" => [
                    "ru" => esc_html__("Russian", "payselection-gateway-for-woocommerce"),
                    "en" => esc_html__("English", "payselection-gateway-for-woocommerce"),
                ],
            ],
            "receipt" => [
                "title" => esc_html__("Fiscalization", "payselection-gateway-for-woocommerce"),
                "type" => "checkbox",
                "label" => esc_html__("If this option is enabled order receipts will be created and sent to your customer and to the revenue service via Payselection", "payselection-gateway-for-woocommerce"),
                "default" => "no",
            ],
            "company_inn" => [
                "title" => esc_html__("INN organization", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "default" => "",
                "desc_tip" => false,
            ],
            "company_email" => [
                "title" => esc_html__("Email organization", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "default" => "",
                "desc_tip" => false,
            ],
            "company_address" => [
                "title" => esc_html__("Legal address", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "default" => "",
                "desc_tip" => false,
            ],
            "company_tax_system" => [
                "title" => esc_html__("Taxation system", "payselection-gateway-for-woocommerce"),
                "type" => "select",
                "default" => "0",
                "options" => [
                    "osn"                   => esc_html__("General", "payselection-gateway-for-woocommerce"),
                    "usn_income"            => esc_html__("Simplified, income", "payselection-gateway-for-woocommerce"),
                    "usn_income_outcome"    => esc_html__("Simplified, income minus expences", "payselection-gateway-for-woocommerce"),
                    "envd"                  => esc_html__("Unified tax on imputed income", "payselection-gateway-for-woocommerce"),
                    "esn"                   => esc_html__("Unified agricultural tax", "payselection-gateway-for-woocommerce"),
                    "patent"                => esc_html__("Patent taxation system", "payselection-gateway-for-woocommerce"),
                ],
            ],
            "company_vat" => [
                "title" => esc_html__("Item-dependent tax (VAT)", "payselection-gateway-for-woocommerce"),
                "type" => "select",
                "label" => esc_html__("Be sure to specify if you use receipt printing through Payselection", "payselection-gateway-for-woocommerce"),
                "default" => "0",
                "options" => [
                    "none"      => esc_html__("Tax excluded", "payselection-gateway-for-woocommerce"),
                    "vat0"      => esc_html__("VAT at 0%", "payselection-gateway-for-woocommerce"),
                    "vat10"     => esc_html__("VAT receipt at rate 10%", "payselection-gateway-for-woocommerce"),
                    "vat18"     => esc_html__("VAT receipt at rate 18%", "payselection-gateway-for-woocommerce"),
                    "vat110"    => esc_html__("VAT check at the estimated rate 10/110", "payselection-gateway-for-woocommerce"),
                    "vat118"    => esc_html__("VAT check at the estimated rate 18/118", "payselection-gateway-for-woocommerce"),
                ],
            ],
            "debug" => [
                "title" => esc_html__("Enable DEBUG", "payselection-gateway-for-woocommerce"),
                "type" => "checkbox",
                "label" => esc_html__("Enable DEBUG", "payselection-gateway-for-woocommerce"),
                "default" => "no",
            ],
            "title" => [
                "title" => esc_html__("Title", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => esc_html__("This controls the title which the user sees during checkout.", "payselection-gateway-for-woocommerce"),
                "default" => esc_html__("Pay via Payselection", "payselection-gateway-for-woocommerce"),
                "desc_tip" => true,
            ],
            "description" => [
                "title" => esc_html__("Description", "payselection-gateway-for-woocommerce"),
                "type" => "textarea",
                "description" => esc_html__("Payment method description that the customer will see on your checkout.", "payselection-gateway-for-woocommerce"),
                "default" => esc_html__("To pay for the order, you will be redirected to the Payselection service page.", "payselection-gateway-for-woocommerce"),
                "desc_tip" => true,
            ],
        ];
    }

    /**
     * process_payment Create payment link and redirect
     *
     * @param  int $order_id
     * @return void
     */
    public function process_payment($order_id)
    {
        global $woocommerce;
        $order = new Order($order_id);

        if (!$order) {
            return false;
        }

        if (empty($this->get_option('host'))) {
            wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('API host', 'payselection-gateway-for-woocommerce')));
            return false;
        }

        if (empty($this->get_option('create_host'))) {
            wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Create Payment host', 'payselection-gateway-for-woocommerce')));
            return false;
        }

        if (empty($this->get_option('site_id'))) {
            wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Site ID', 'payselection-gateway-for-woocommerce')));
            return false;
        }

        if (empty($this->get_option('key'))) {
            wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Secret Key', 'payselection-gateway-for-woocommerce')));
            return false;
        }

        if ($this->get_option('receipt') === 'yes') {

            if (empty($this->get_option('company_inn'))) {
                wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('INN organization', 'payselection-gateway-for-woocommerce')));
                return false;
            }

            if (empty($this->get_option('company_address'))) {
                wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Legal address', 'payselection-gateway-for-woocommerce')));
                return false;
            }

        }

        // Widget payment
        if (empty($this->redirect) || $this->redirect !== 'yes')  {

            if (empty($this->get_option('widget_url'))) {
                wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Widget URL', 'payselection-gateway-for-woocommerce')));
                return false;
            }

            if (empty($this->get_option('widget_key'))) {
                wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Public Key', 'payselection-gateway-for-woocommerce')));
                return false;
            }

            $args = [
                "paywidget" => 1,
                "order_id" => $order_id
            ];

            return [
                "result" => "success",
                "redirect" => home_url("/wc-api/" . $this->id . "_widget?") . http_build_query($args),
            ];
        }

        // Redirect payment
        $response = $this->payselection->getPaymentLink($order->getRequestData());

        if (is_wp_error($response)) {
            $this->payselection->debug(wc_print_r($order->getRequestData(), true));
            $this->payselection->debug(wc_print_r($response, true));
            wc_add_notice(esc_html__('Payselection error:', 'payselection-gateway-for-woocommerce') . " " . $response->get_error_message());
            return false;
        }

        return array(
            'result'   => 'success',
            'redirect' => $response
        );
    }
        
    /**
     * update_order_status Update order action
     *
     * @param  mixed $order_id
     * @param  mixed $old_status
     * @param  mixed $new_status
     * @return void
     */
    public function update_order_status($order_id, $old_status, $new_status)
    {
        if ($old_status === 'on-hold') {
            global $woocommerce;
            $order = new Order($order_id);
            if ($order->meta_exists('BlockTransactionId')) {
                switch ($new_status)
                {
                    case "processing":
                        $response = $this->payselection->charge($order->getChargeCancelData());
                        break;

                    default:
                        $response = $this->payselection->cancel($order->getChargeCancelData());
                        break;
                }
                $this->payselection->debug(wc_print_r($response, true));

                if (is_wp_error($response)) {
                    $this->payselection->debug(wc_print_r($order->getChargeCancelData(), true));
                    $this->payselection->debug(wc_print_r($response, true));
                    return false;
                }
                
                return true;
            }
        }
    }

    public function process_refund($order_id, $amount = null, $reason = '') {

		$order = wc_get_order($order_id);

		if (!($order && $order->meta_exists('TransactionId'))) {
            return new \WP_Error( 'payselection-refund-error', __( 'Refund failed.', 'payselection-gateway-for-woocommerce' ) );
		}

        $items[] = [
            'name'           => esc_html__('Refund', 'payselection-gateway-for-woocommerce'),
            'sum'            => (float) number_format(floatval($amount), 2, '.', ''),
            'price'          => (float) number_format($amount, 2, '.', ''),
            'quantity'       => 1,
            'payment_method' => 'full_prepayment',
            'payment_object' => 'commodity',
            'vat'            => [
                'type'          => 'none',
            ] 
        ];

        $data = [
            "TransactionId" => $order->get_meta('TransactionId', true),
            "Amount"        => number_format($amount, 2, ".", ""),
            "Currency"      => $order->get_currency(),
            "WebhookUrl"    => home_url('/wc-api/wc_payselection_gateway_webhook'),
            "ReceiptData"   => [
                'timestamp' => date('d.m.Y H:i:s'),
                //'external_id' => (string) $order->get_id(),
                'external_id' => implode('-',[$order->get_id(), time()]),
                'receipt' => [
                    'client' => [
                        'email' => $order->get_billing_email(),
                    ],
                    'company' => [
                        'email' => 'none',
                        'inn' => 'none',
                        'sno' => 'none',
                        'payment_address' => 'none',
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
            ]
        ];

		$result = $this->payselection->refund($data);


        $file = get_template_directory() . '/payselection-errors2.txt'; 
        $current = file_get_contents($file);
        if (is_wp_error($result)) {
            $current .= $result->get_error_message()."\n";
        } else {
            $current .= $result ."\n";
        }
        
        $open = file_put_contents($file, $current);

        if (is_wp_error($result)) {

            $this->payselection->debug(wc_print_r($order->getRefundData($amount), true));
            $this->payselection->debug(wc_print_r($result, true));
			return new \WP_Error('payselection-refund-error', $result->get_error_message());

        } elseif (!empty( $result['TransactionId'])) { 
            return false;

			$formatted_amount = wc_price( $result['Amount'] );
			$order->update_meta_data( 'RefundTransactionId', sanitize_text_field($result['TransactionId']) );
			$refund_message = sprintf( __( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'payselection-gateway-for-woocommerce' ), $formatted_amount, $result['TransactionId'], $reason );
			$order->add_order_note( $refund_message );

			return true;

		}

        return false;
	}
    
}

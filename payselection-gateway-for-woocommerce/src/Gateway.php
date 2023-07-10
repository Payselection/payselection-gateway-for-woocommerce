<?php

namespace Payselection;

use Payselection\Api;
use Payselection\Order;
use Payselection\Paykassa\Api as PaykassaApi;

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
        $this->paykassa = new PaykassaApi();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_order_status_processing', [$this, 'capture_payment']);
		add_action('woocommerce_order_status_completed', [$this, 'capture_payment']);
        add_action('woocommerce_order_status_cancelled', [ $this, 'cancel_payment' ]);
		add_action('woocommerce_order_status_refunded', [ $this, 'cancel_payment' ]);
        add_action('woocommerce_order_status_failed', [ $this, 'cancel_payment' ]);
        add_action('woocommerce_api_' . $this->id . '_webhook', [new Webhook(), 'handle']);
        add_action('woocommerce_api_' . $this->id . '_widget', '\Payselection\Widget::handle');

        if (!empty($this->get_option("paykassa_order_status")) && 'delivered' === $this->get_option("paykassa_order_status")) {
            add_action('woocommerce_order_status_delivered', [$this, 'create_paykassa_receipt']);
        } else {
            add_action('woocommerce_order_status_completed', [$this, 'create_paykassa_receipt']);
        }
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
            "payment_method" => [
                "title" => esc_html__("Payment type", "payselection-gateway-for-woocommerce"),
                "type" => "select",
                "default" => "full_prepayment",
                "options" => [
                    "full_prepayment" => esc_html__("Full prepayment", "payselection-gateway-for-woocommerce"),
                    "prepayment" => esc_html__("Prepayment", "payselection-gateway-for-woocommerce"),
                    "advance" => esc_html__("Advance", "payselection-gateway-for-woocommerce"),
                    "full_payment" => esc_html__("Full payment", "payselection-gateway-for-woocommerce"),
                    "partial_payment" => esc_html__("Partial payment", "payselection-gateway-for-woocommerce"),
                    "credit" => esc_html__("Credit", "payselection-gateway-for-woocommerce"),
                    "credit_payment" => esc_html__("Credit payment", "payselection-gateway-for-woocommerce"),
                ],
            ],
            "payment_object" => [
                "title" => esc_html__("Type of goods and services", "payselection-gateway-for-woocommerce"),
                "type" => "select",
                "default" => "commodity",
                "options" => [
                    "commodity" => esc_html__("Commodity", "payselection-gateway-for-woocommerce"),
                    "excise" => esc_html__("Excise", "payselection-gateway-for-woocommerce"), 
                    "job" => esc_html__("Job", "payselection-gateway-for-woocommerce"), 
                    "service" => esc_html__("Service", "payselection-gateway-for-woocommerce"), 
                    "gambling_bet" => esc_html__("Gambling bet", "payselection-gateway-for-woocommerce"), 
                    "gambling_prize" => esc_html__("Gambling prize", "payselection-gateway-for-woocommerce"), 
                    "lottery" => esc_html__("Lottery", "payselection-gateway-for-woocommerce"), 
                    "lottery_prize" => esc_html__("Lottery prize", "payselection-gateway-for-woocommerce"), 
                    "intellectual_activity" => esc_html__("Intellectual activity", "payselection-gateway-for-woocommerce"), 
                    "payment" => esc_html__("Payment", "payselection-gateway-for-woocommerce"), 
                    "agent_commission" => esc_html__("Agent commission", "payselection-gateway-for-woocommerce"), 
                    "composite" => esc_html__("Composite", "payselection-gateway-for-woocommerce"), 
                    "award" => esc_html__("Award", "payselection-gateway-for-woocommerce"), 
                    "another" => esc_html__("Another", "payselection-gateway-for-woocommerce"), 
                    "property_right" => esc_html__("Property right", "payselection-gateway-for-woocommerce"), 
                    "non-operating_gain" => esc_html__("Non-operating gain", "payselection-gateway-for-woocommerce"),
                    "insurance_premium" => esc_html__("Insurance premium", "payselection-gateway-for-woocommerce"), 
                    "sales_tax" => esc_html__("Sales tax", "payselection-gateway-for-woocommerce"), 
                    "resort_fee" => esc_html__("Resort fee", "payselection-gateway-for-woocommerce"), 
                    "deposit" => esc_html__("Deposit", "payselection-gateway-for-woocommerce"), 
                    "expense" => esc_html__("Expense", "payselection-gateway-for-woocommerce"), 
                    "pension_insurance_ip" => esc_html__("Pension insurance ip", "payselection-gateway-for-woocommerce"), 
                    "pension_insurance" => esc_html__("Pension insurance", "payselection-gateway-for-woocommerce"), 
                    "medical_insurance_ip" => esc_html__("Medical insurance ip", "payselection-gateway-for-woocommerce"), 
                    "medical_insurance" => esc_html__("Medical insurance", "payselection-gateway-for-woocommerce"), 
                    "social_insurance" => esc_html__("Social insurance", "payselection-gateway-for-woocommerce"), 
                    "casino_payment" => esc_html__("Casino payment", "payselection-gateway-for-woocommerce"),
                ],
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
            "paykassa_receipt" => [
                "title" => esc_html__("Paykassa Fiscalization", "payselection-gateway-for-woocommerce"),
                "type" => "checkbox",
                "label" => esc_html__("If this option is enabled order receipts will be sent to Paykassa (if Payment type = Full prepayment, Prepayment or Advance)", "payselection-gateway-for-woocommerce"),
                "default" => "no",
            ],
            "paykassa_host" => [
                "title" => esc_html__("Paykassa API host", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => esc_html__("Paykassa API hostname", "payselection-gateway-for-woocommerce"),
                "default" => "https://api.pay-kassa.com/",
                "desc_tip" => true,
            ],
            "paykassa_merchant_id" => [
                "title" => esc_html__("Paykassa Merchant ID", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => esc_html__("Your Merchant ID on Paykassa", "payselection-gateway-for-woocommerce"),
                "default" => "",
                "desc_tip" => false,
            ],
            "paykassa_key" => [
                "title" => esc_html__("Paykassa Secret Key", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => esc_html__("Your Key on Paykassa", "payselection-gateway-for-woocommerce"),
                "default" => "",
                "desc_tip" => false,
            ],
            "paykassa_order_status" => [
                "title" => esc_html__("Order status for sending a receipt", "payselection-gateway-for-woocommerce"),
                "type" => "select",
                "label" => esc_html__("Select order status for sending a receipt (default woo status Completed or add new status Delivered)", "payselection-gateway-for-woocommerce"),
                "default" => "completed",
                "options" => [
                    "completed"      => esc_html__("Completed", "payselection-gateway-for-woocommerce"),
                    "delivered"      => esc_html__("Delivered", "payselection-gateway-for-woocommerce"),
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

        if ($this->get_option('paykassa_receipt') === 'yes') {

            if (empty($this->get_option('paykassa_host'))) {
                wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Paykassa API host', 'payselection-gateway-for-woocommerce')));
                return false;
            }

            if (empty($this->get_option('paykassa_merchant_id'))) {
                wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Paykassa merchant id', 'payselection-gateway-for-woocommerce')));
                return false;
            }

            if (empty($this->get_option('paykassa_key'))) {
                wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Paykassa secret key', 'payselection-gateway-for-woocommerce')));
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

        $this->payselection->debug(esc_html__('Payment Link request', 'payselection-gateway-for-woocommerce'));
        $this->payselection->debug(wc_print_r($order->getRequestData(), true));
        $this->payselection->debug(wc_print_r($response, true));

        if (is_wp_error($response)) {
            wc_add_notice(esc_html__('Payselection error:', 'payselection-gateway-for-woocommerce') . " " . $response->get_error_message());
            return false;
        }

        return array(
            'result'   => 'success',
            'redirect' => $response
        );
    }

    /**
	 * Capture payment when the order is changed from on-hold to complete or processing
	 *
	 * @param  int $order_id Order ID.
	 */
	public function capture_payment( $order_id ) {
		$order = new Order($order_id);

		if ( 'wc_payselection_gateway' === $order->get_payment_method() 
            && $order->meta_exists('BlockTransactionId') 
            && !$order->meta_exists('TransactionId')
        ) {
			
            $response = $this->payselection->charge($order->getChargeCancelData());

            $this->payselection->debug(esc_html__('Capture Result', 'payselection-gateway-for-woocommerce'));
            $this->payselection->debug(wc_print_r($response, true));

			if ( is_wp_error( $response ) ) {
                if ($response->get_error_message()) {
                    $error_text = $response->get_error_message();
                } else {
                    $error_text = $response->get_error_code();
                }
				/* translators: %s: Payselection gateway error message */
				$order->add_order_note(sprintf(__( 'Payment could not be captured: %s', 'payselection-gateway-for-woocommerce' ), $error_text));

                $this->payselection->debug(esc_html__('Charge request error', 'payselection-gateway-for-woocommerce'));
                $this->payselection->debug(wc_print_r($order->getChargeCancelData(), true));
                $this->payselection->debug(wc_print_r($response, true));

				return;
			}
		}
	}

    /**
	 * Cancel pre-auth on refund/cancellation.
	 *
	 * @param  int $order_id
	 */
	public function cancel_payment( $order_id ) {
		$order = new Order($order_id);

		if ( 'wc_payselection_gateway' === $order->get_payment_method() 
            && $order->meta_exists('BlockTransactionId') 
            && !$order->meta_exists('TransactionId')
        ) {
			
            $response = $this->payselection->cancel($order->getChargeCancelData());

            $this->payselection->debug(esc_html__('Cancel Result', 'payselection-gateway-for-woocommerce'));
            $this->payselection->debug(wc_print_r($response, true));

			if ( is_wp_error( $response ) ) {
                if ($response->get_error_message()) {
                    $error_text = $response->get_error_message();
                } else {
                    $error_text = $response->get_error_code();
                }
				/* translators: %s: Payselection gateway error message */
				$order->add_order_note(sprintf(__( 'Payment could not be cancelled: %s', 'payselection-gateway-for-woocommerce' ), $error_text));

                $this->payselection->debug(esc_html__('Cancel request error', 'payselection-gateway-for-woocommerce'));
                $this->payselection->debug(wc_print_r($order->getChargeCancelData(), true));
                $this->payselection->debug(wc_print_r($response, true));

				return;
			}
		}
	}

    public function process_refund($order_id, $amount = null, $reason = '') {

        $order = new Order($order_id);

		if (!($order && $order->meta_exists('TransactionId'))) {
            return new \WP_Error( 'payselection-refund-error', __( 'Refund failed.', 'payselection-gateway-for-woocommerce' ) );
		}

		$result = $this->payselection->refund($order->getPayselectionRefundData($amount));

        if (is_wp_error($result)) {

            $this->payselection->debug(esc_html__('Process refund', 'payselection-gateway-for-woocommerce'));
            $this->payselection->debug(wc_print_r($result, true));
            if ($result->get_error_message()) {
                return new \WP_Error('payselection-refund-error', $result->get_error_message());
            } else {
                return new \WP_Error('payselection-refund-error', $result->get_error_code());
            }

        } elseif (!empty( $result['TransactionId'])) { 
            
			$formatted_amount = wc_price($result['Amount']);  

            if ($order->get_total_refunded()) {
                $net_amount = $order->get_total() - $order->get_total_refunded();
                $formatted_net_amount = wc_price($net_amount);
                if ($net_amount>0) {
                    $refund_message = sprintf(__( 'Order partially refund. Refunded %1$s - Refund ID: %2$s - Reason: %3$s - Remaining amount: %4$s', 'payselection-gateway-for-woocommerce' ), $formatted_amount, $result['TransactionId'], $reason, $formatted_net_amount);
                } else {
                    $refund_message = sprintf(__( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'payselection-gateway-for-woocommerce' ), $formatted_amount, $result['TransactionId'], $reason);
                }
                $order->add_order_note($refund_message);
            }

			return true;

		}

        return false;
	}

    /**
	 * Send a receipt to Paykassa when the order is completed
	 *
	 * @param  int $order_id Order ID.
	 */
	public function create_paykassa_receipt( $order_id ) {
		$order = new Order($order_id);
        $payment_method = $this->get_option('payment_method') ?? 'full_prepayment';

		if ( 'wc_payselection_gateway' === $order->get_payment_method() 
            && $order->meta_exists('TransactionId')
            && $this->get_option('paykassa_receipt') === 'yes'
            && ('full_prepayment' === $payment_method 
                || 'prepayment' === $payment_method 
                || 'advance' === $payment_method
                )
        ) {
            

            $response = $this->paykassa->create($order->getPaykassaReceiptData());

            $this->paykassa->debug(esc_html__('Paykassa create receipt response', 'payselection-gateway-for-woocommerce'));
            $this->paykassa->debug(wc_print_r($response, true));
            $this->paykassa->debug(wc_print_r($order->getPaykassaReceiptData(), true));

			if ( is_wp_error( $response ) ) {
                if ($response->get_error_message()) {
                    $error_text = $response->get_error_message();
                } else {
                    $error_text = $response->get_error_code();
                }
				/* translators: %s: Payselection gateway error message */
				$order->add_order_note(sprintf(__( 'Paykassa request could not be sended: %s', 'payselection-gateway-for-woocommerce' ), $error_text));

				return false;
			}

            $order->add_order_note(esc_html__( 'Receipt was sent to Paykassa', 'payselection-gateway-for-woocommerce' ));
		}

        return false;
	}
    
}

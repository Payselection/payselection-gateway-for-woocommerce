<?php

namespace Payselection;

use Payselection\Api;
use Payselection\Order;
use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use Automattic\WooCommerce\StoreApi\Payments\PaymentResult;

defined( 'ABSPATH' ) || exit;

class Gateway extends \WC_Payment_Gateway
{
    public $redirect;

    protected $payselection;

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

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_order_status_processing', [$this, 'capture_payment']);
		add_action('woocommerce_order_status_completed', [$this, 'capture_payment']);
        add_action('woocommerce_order_status_cancelled', [ $this, 'cancel_payment' ]);
		add_action('woocommerce_order_status_refunded', [ $this, 'cancel_payment' ]);
        add_action('woocommerce_order_status_failed', [ $this, 'cancel_payment' ]);
        add_action('woocommerce_api_' . $this->id . '_webhook', [new Webhook(), 'handle']);
        add_action('woocommerce_api_' . $this->id . '_widget', '\Payselection\Widget::handle');
        add_action('woocommerce_rest_checkout_process_payment_with_context', [ $this, 'setup_payment_error_handler' ], 10, 2);
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
                "type" => "password",
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
            "preview_form" => [
                "title" => esc_html__("Preview Form", "payselection-gateway-for-woocommerce"),
                "type" => "checkbox",
                "label" => esc_html__("Display Preview Form", "payselection-gateway-for-woocommerce"),
                "default" => "no",
            ],
            "offer_url" => [
                "title" => esc_html__("Offer Url", "payselection-gateway-for-woocommerce"),
                "type" => "url",
                "description" => esc_html__("Your Offer Url for Preview Form", "payselection-gateway-for-woocommerce"),
                "default" => "",
                "desc_tip" => false,
            ],
            "short_description_ru" => [
                "title" => esc_html__("Short Description in Russian", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => esc_html__("Short Description of the purpose of payment", "payselection-gateway-for-woocommerce"),
                "default" => "",
                "desc_tip" => false,
            ],
            "short_description_en" => [
                "title" => esc_html__("Short Description in English", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => esc_html__("Short Description of the purpose of payment", "payselection-gateway-for-woocommerce"),
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
                "title" => esc_html__("Place of calculations. Website address", "payselection-gateway-for-woocommerce"),
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
                    "vat20"     => esc_html__("VAT receipt at rate 20%", "payselection-gateway-for-woocommerce"),
                    "vat110"    => esc_html__("VAT check at the estimated rate 10/110", "payselection-gateway-for-woocommerce"),
                    "vat120"    => esc_html__("VAT check at the estimated rate 20/120", "payselection-gateway-for-woocommerce"),
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

    public function validate_fields() {

        if (empty($this->get_option('host'))) {
            wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('API host', 'payselection-gateway-for-woocommerce')), 'error');
            return false;
        }

        if (empty($this->get_option('create_host'))) {
            wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Create Payment host', 'payselection-gateway-for-woocommerce')), 'error');
            return false;
        }

        if (empty($this->get_option('site_id'))) {
            wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Site ID', 'payselection-gateway-for-woocommerce')), 'error');
            return false;
        }

        if (empty($this->get_option('key'))) {
            wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Secret Key', 'payselection-gateway-for-woocommerce')), 'error');
            return false;
        }

        if ($this->get_option('preview_form') === 'yes') {
            if (empty($this->get_option('offer_url'))) {
                wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Offer Url', 'payselection-gateway-for-woocommerce')), 'error');
                return false;
            }
        }

        if ($this->get_option('receipt') === 'yes') {

            if (empty($this->get_option('company_inn'))) {
                wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('INN organization', 'payselection-gateway-for-woocommerce')), 'error');
                return false;
            }

            if (empty($this->get_option('company_address'))) {
                wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Legal address', 'payselection-gateway-for-woocommerce')), 'error');
                return false;
            }

        }

        if (empty($this->get_option('widget_key'))) {
            wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Public Key', 'payselection-gateway-for-woocommerce')), 'error');
            return false;
        }

        if (empty($this->redirect) || $this->redirect !== 'yes')  {

            if (empty($this->get_option('widget_url'))) {
                wc_add_notice(sprintf(esc_html__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), esc_html__('Widget URL', 'payselection-gateway-for-woocommerce')), 'error');
                return false;
            }

        }

    }

    /**
	 * Sets up a handler to add error details to the payment result.
	 * Registers an action to handle 'payselection_update_payment_result_on_error',
	 * using the payment result object from 'woocommerce_rest_checkout_process_payment_with_context'.
	 *
	 * @param PaymentContext $context The payment context.
	 * @param PaymentResult  $result  The payment result, passed by reference.
	 */
	public function setup_payment_error_handler(PaymentContext $context, PaymentResult &$result) {
		add_action(
			'payselection_update_payment_result_on_error',
			function ($error) use (&$result) {
                if ($error instanceof \Exception) {
                    throw $error;
                }
			}
		);
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
            return array(
                'result'   => 'failure',
            );
        }

        // Widget payment
        if (empty($this->redirect) || $this->redirect !== 'yes')  {

            $args = [
                "paywidget" => 1,
                "order_id" => $order_id
            ];

            return [
                "result" => "success",
                "redirect" => home_url("/wc-api/" . $this->id . "_widget?") . http_build_query($args),
            ];
        }

        $this->payselection->debug(esc_html__('Payment Link request', 'payselection-gateway-for-woocommerce'));
        $this->payselection->debug(wc_print_r($order->getRequestData(), true));

        try {
            // Redirect payment
            $response = $this->payselection->getPaymentLink($order->getRequestData());

            if (is_wp_error($response)) {
                throw new \Exception(sprintf(esc_html__('Payselection error - %s', 'payselection-gateway-for-woocommerce'), $response->get_error_message()));
            }

            if (empty($response['Url'])) {
                throw new \Exception(esc_html__('Payselection error - invalid responce', 'payselection-gateway-for-woocommerce'));
            }

            return array(
                'result'   => 'success',
                'redirect' => $response['Url']
            );
        } catch (\Exception $e) {
            do_action('payselection_update_payment_result_on_error', $e, $order);
            wc_add_notice($e->getMessage(), 'error');

			return [
				'result'   => 'failure',
				'redirect' => '',
			];
        }     
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
			$refund_message = sprintf(__( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'payselection-gateway-for-woocommerce' ), $formatted_amount, $result['TransactionId'], $reason);
			$order->add_order_note($refund_message);

			return true;

		}

        return false;
	}
    
}

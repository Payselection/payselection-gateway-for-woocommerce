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
        $this->icon = PAYSELECTION_URL . "logo.svg";
        $this->method_title = __("Payselection", "payselection-gateway-for-woocommerce");
        $this->method_description = __("Pay via Payselection", "payselection-gateway-for-woocommerce");

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
                "title" => __("Enable/Disable", "payselection-gateway-for-woocommerce"),
                "type" => "checkbox",
                "label" => __("Enable Payselection", "payselection-gateway-for-woocommerce"),
                "default" => "yes",
            ],
            "redirect" => [
                "title" => __("Widget/Redirect", "payselection-gateway-for-woocommerce"),
                "type" => "checkbox",
                "label" => __("Redirect to Payselection", "payselection-gateway-for-woocommerce"),
                "default" => "no",
            ],
            "type" => [
                "title" => __("Payment type", "payselection-gateway-for-woocommerce"),
                "type" => "select",
                "default" => "Pay",
                "options" => [
                    "Pay" => __("Pay", "payselection-gateway-for-woocommerce"),
                    "Block" => __("Block", "payselection-gateway-for-woocommerce"),
                ],
            ],
            "webhook" => [
                "title" => __("Webhook URL", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "default" => home_url("/wc-api/" . $this->id . "_webhook"),
                "custom_attributes" => ["readonly" => "readonly"],
            ],
            "host" => [
                "title" => __("API host", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => __("API hostname", "payselection-gateway-for-woocommerce"),
                "default" => "https://gw.payselection.com",
                "desc_tip" => true,
            ],
            "create_host" => [
                "title" => __("Create Payment host", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => __("Create Payment hostname", "payselection-gateway-for-woocommerce"),
                "default" => "https://webform.payselection.com",
                "desc_tip" => true,
            ],
            "site_id" => [
                "title" => __("Site ID", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => __("Your site ID on Payselection", "payselection-gateway-for-woocommerce"),
                "default" => "",
                "desc_tip" => false,
            ],
            "key" => [
                "title" => __("Secret Key", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => __("Your Key on Payselection", "payselection-gateway-for-woocommerce"),
                "default" => "",
                "desc_tip" => false,
            ],
            "widget_url" => [
                "title" => __("Widget URL", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "default" => "https://widget.payselection.com/lib/pay-widget.js",
                "desc_tip" => true,
            ],
            "widget_key" => [
                "title" => __("Public Key", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => __("Your Public Key on Payselection", "payselection-gateway-for-woocommerce"),
                "default" => "",
                "desc_tip" => false,
            ],
            "language" => [
                "title" => __("Widget language", "payselection-gateway-for-woocommerce"),
                "type" => "select",
                "default" => "en",
                "options" => [
                    "ru" => __("Russian", "payselection-gateway-for-woocommerce"),
                    "en" => __("English", "payselection-gateway-for-woocommerce"),
                ],
            ],
            "receipt" => [
                "title" => __("Fiscalization", "payselection-gateway-for-woocommerce"),
                "type" => "checkbox",
                "label" => __("If this option is enabled order receipts will be created and sent to your customer and to the revenue service via Payselection", "payselection-gateway-for-woocommerce"),
                "default" => "no",
            ],
            "company_inn" => [
                "title" => __("INN organization", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "default" => "",
                "desc_tip" => false,
            ],
            "company_email" => [
                "title" => __("Email organization", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "default" => "",
                "desc_tip" => false,
            ],
            "company_address" => [
                "title" => __("Legal address", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "default" => "",
                "desc_tip" => false,
            ],
            "company_tax_system" => [
                "title" => __("Taxation system", "payselection-gateway-for-woocommerce"),
                "type" => "select",
                "default" => "0",
                "options" => [
                    "osn"                   => __("General", "payselection-gateway-for-woocommerce"),
                    "usn_income"            => __("Simplified, income", "payselection-gateway-for-woocommerce"),
                    "usn_income_outcome"    => __("Simplified, income minus expences", "payselection-gateway-for-woocommerce"),
                    "envd"                  => __("Unified tax on imputed income", "payselection-gateway-for-woocommerce"),
                    "esn"                   => __("Unified agricultural tax", "payselection-gateway-for-woocommerce"),
                    "patent"                => __("Patent taxation system", "payselection-gateway-for-woocommerce"),
                ],
            ],
            "company_vat" => [
                "title" => __("Item-dependent tax (VAT)", "payselection-gateway-for-woocommerce"),
                "type" => "select",
                "label" => __("Be sure to specify if you use receipt printing through Payselection", "payselection-gateway-for-woocommerce"),
                "default" => "0",
                "options" => [
                    "none"      => __("Tax excluded", "payselection-gateway-for-woocommerce"),
                    "vat0"      => __("VAT at 0%", "payselection-gateway-for-woocommerce"),
                    "vat10"     => __("VAT receipt at rate 10%", "payselection-gateway-for-woocommerce"),
                    "vat18"     => __("VAT receipt at rate 18%", "payselection-gateway-for-woocommerce"),
                    "vat110"    => __("VAT check at the estimated rate 10/110", "payselection-gateway-for-woocommerce"),
                    "vat118"    => __("VAT check at the estimated rate 18/118", "payselection-gateway-for-woocommerce"),
                ],
            ],
            "debug" => [
                "title" => __("Enable DEBUG", "payselection-gateway-for-woocommerce"),
                "type" => "checkbox",
                "label" => __("Enable DEBUG", "payselection-gateway-for-woocommerce"),
                "default" => "no",
            ],
            "title" => [
                "title" => __("Title", "payselection-gateway-for-woocommerce"),
                "type" => "text",
                "description" => __("This controls the title which the user sees during checkout.", "payselection-gateway-for-woocommerce"),
                "default" => __("Pay via Payselection", "payselection-gateway-for-woocommerce"),
                "desc_tip" => true,
            ],
            "description" => [
                "title" => __("Description", "payselection-gateway-for-woocommerce"),
                "type" => "textarea",
                "description" => __("Payment method description that the customer will see on your checkout.", "payselection-gateway-for-woocommerce"),
                "default" => __("To pay for the order, you will be redirected to the Payselection service page.", "payselection-gateway-for-woocommerce"),
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
            wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), __('API host', 'payselection-gateway-for-woocommerce')));
            return false;
        }

        if (empty($this->get_option('create_host'))) {
            wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), __('Create Payment host', 'payselection-gateway-for-woocommerce')));
            return false;
        }

        if (empty($this->get_option('site_id'))) {
            wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), __('Site ID', 'payselection-gateway-for-woocommerce')));
            return false;
        }

        if (empty($this->get_option('key'))) {
            wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), __('Secret Key', 'payselection-gateway-for-woocommerce')));
            return false;
        }

        if ($this->get_option('receipt') === 'yes') {

            if (empty($this->get_option('company_inn'))) {
                wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), __('INN organization', 'payselection-gateway-for-woocommerce')));
                return false;
            }

            if (empty($this->get_option('company_address'))) {
                wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), __('Legal address', 'payselection-gateway-for-woocommerce')));
                return false;
            }

        }

        // Widget payment
        if (empty($this->redirect) || $this->redirect !== 'yes')  {

            if (empty($this->get_option('widget_url'))) {
                wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), __('Widget URL', 'payselection-gateway-for-woocommerce')));
                return false;
            }

            if (empty($this->get_option('widget_key'))) {
                wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection-gateway-for-woocommerce'), __('Public Key', 'payselection-gateway-for-woocommerce')));
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
            wc_add_notice(__('Payselection error:', 'payselection-gateway-for-woocommerce') . " " . $response->get_error_message());
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
    
}

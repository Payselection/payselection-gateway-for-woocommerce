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
        $this->method_title = __("Payselection", "payselection");
        $this->method_description = __("Pay via Payselection", "payselection");

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
                "title" => __("Enable/Disable", "payselection"),
                "type" => "checkbox",
                "label" => __("Enable Payselection", "payselection"),
                "default" => "yes",
            ],
            "redirect" => [
                "title" => __("Widget/Redirect", "payselection"),
                "type" => "checkbox",
                "label" => __("Redirect to Payselection", "payselection"),
                "default" => "no",
            ],
            "type" => [
                "title" => __("Payment type", "payselection"),
                "type" => "select",
                "default" => "Pay",
                "options" => [
                    "Pay" => __("Pay", "payselection"),
                    "Block" => __("Block", "payselection"),
                ],
            ],
            "webhook" => [
                "title" => __("Webhook URL", "payselection"),
                "type" => "text",
                "default" => home_url("/wc-api/" . $this->id . "_webhook"),
                "custom_attributes" => ["readonly" => "readonly"],
            ],
            "host" => [
                "title" => __("API host!", "payselection"),
                "type" => "text",
                "description" => __("API hostname", "payselection"),
                "default" => "https://gw.payselection.com",
                "desc_tip" => true,
            ],
            "create_host" => [
                "title" => __("Create Payment host", "payselection"),
                "type" => "text",
                "description" => __("Create Payment hostname", "payselection"),
                "default" => "https://webform.payselection.com",
                "desc_tip" => true,
            ],
            "site_id" => [
                "title" => __("Site ID", "payselection"),
                "type" => "text",
                "description" => __("Your site ID on Payselection", "payselection"),
                "default" => "",
                "desc_tip" => false,
            ],
            "key" => [
                "title" => __("Secret Key", "payselection"),
                "type" => "text",
                "description" => __("Your Key on Payselection", "payselection"),
                "default" => "",
                "desc_tip" => false,
            ],
            "widget_url" => [
                "title" => __("Widget URL", "payselection"),
                "type" => "text",
                "default" => "https://widget.payselection.com/lib/pay-widget.js",
                "desc_tip" => true,
            ],
            "widget_key" => [
                "title" => __("Public Key", "payselection"),
                "type" => "text",
                "description" => __("Your Public Key on Payselection", "payselection"),
                "default" => "",
                "desc_tip" => false,
            ],
            "language" => [
                "title" => __("Widget language", "payselection"),
                "type" => "select",
                "default" => "en",
                "options" => [
                    "ru" => __("Russian", "payselection"),
                    "en" => __("English", "payselection"),
                ],
            ],
            "receipt" => [
                "title" => __("Fiscalization", "payselection"),
                "type" => "checkbox",
                "label" => __("If this option is enabled order receipts will be created and sent to your customer and to the revenue service via Payselection", "payselection"),
                "default" => "no",
            ],
            "company_inn" => [
                "title" => __("INN organization", "payselection"),
                "type" => "text",
                "default" => "",
                "desc_tip" => false,
            ],
            "company_email" => [
                "title" => __("Email organization", "payselection"),
                "type" => "text",
                "default" => "",
                "desc_tip" => false,
            ],
            "company_address" => [
                "title" => __("Legal address", "payselection"),
                "type" => "text",
                "default" => "",
                "desc_tip" => false,
            ],
            "company_tax_system" => [
                "title" => __("Taxation system", "payselection"),
                "type" => "select",
                "default" => "0",
                "options" => [
                    "osn"                   => __("General", "payselection"),
                    "usn_income"            => __("Simplified, income", "payselection"),
                    "usn_income_outcome"    => __("Simplified, income minus expences", "payselection"),
                    "envd"                  => __("Unified tax on imputed income", "payselection"),
                    "esn"                   => __("Unified agricultural tax", "payselection"),
                    "patent"                => __("Patent taxation system", "payselection"),
                ],
            ],
            "company_vat" => [
                "title" => __("Item-dependent tax (VAT)", "payselection"),
                "type" => "select",
                "label" => __("Be sure to specify if you use receipt printing through Payselection", "payselection"),
                "default" => "0",
                "options" => [
                    "none"      => __("Tax excluded", "payselection"),
                    "vat0"      => __("VAT at 0%", "payselection"),
                    "vat10"     => __("VAT receipt at rate 10%", "payselection"),
                    "vat18"     => __("VAT receipt at rate 18%", "payselection"),
                    "vat110"    => __("VAT check at the estimated rate 10/110", "payselection"),
                    "vat118"    => __("VAT check at the estimated rate 18/118", "payselection"),
                ],
            ],
            "debug" => [
                "title" => __("Enable DEBUG", "payselection"),
                "type" => "checkbox",
                "label" => __("Enable DEBUG", "payselection"),
                "default" => "no",
            ],
            "title" => [
                "title" => __("Title", "payselection"),
                "type" => "text",
                "description" => __("This controls the title which the user sees during checkout.", "payselection"),
                "default" => __("Pay via Payselection", "payselection"),
                "desc_tip" => true,
            ],
            "description" => [
                "title" => __("Description", "payselection"),
                "type" => "textarea",
                "description" => __("Payment method description that the customer will see on your checkout.", "payselection"),
                "default" => __("To pay for the order, you will be redirected to the Payselection service page.", "payselection"),
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
            wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection'), __('API host', 'payselection')));
            return false;
        }

        if (empty($this->get_option('create_host'))) {
            wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection'), __('Create Payment host', 'payselection')));
            return false;
        }

        if (empty($this->get_option('site_id'))) {
            wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection'), __('Site ID', 'payselection')));
            return false;
        }

        if (empty($this->get_option('key'))) {
            wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection'), __('Secret Key', 'payselection')));
            return false;
        }

        if ($this->get_option('receipt') === 'yes') {

            if (empty($this->get_option('company_inn'))) {
                wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection'), __('INN organization', 'payselection')));
                return false;
            }

            if (empty($this->get_option('company_address'))) {
                wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection'), __('Legal address', 'payselection')));
                return false;
            }

        }

        // Widget payment
        if (empty($this->redirect) || $this->redirect !== 'yes')  {

            if (empty($this->get_option('widget_url'))) {
                wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection'), __('Widget URL', 'payselection')));
                return false;
            }

            if (empty($this->get_option('widget_key'))) {
                wc_add_notice(sprintf(__('Payselection settings error: %s is required.', 'payselection'), __('Public Key', 'payselection')));
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
            wc_add_notice(__('Payselection error:', 'payselection') . " " . $response->get_error_message());
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

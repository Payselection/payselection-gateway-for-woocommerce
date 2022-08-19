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
        $this->type = $this->get_option("type");
        $this->host = $this->get_option("host");
        $this->create_host = $this->get_option("create_host");
        $this->check_host = $this->get_option("check_host");
        $this->site_id = $this->get_option("site_id");
        $this->key = $this->get_option("key");
        $this->language = $this->get_option("language");
        $this->title = $this->get_option("title");
        $this->description = $this->get_option("description");

        $webhook = new Webhook();

        add_action("woocommerce_update_options_payment_gateways_" . $this->id, [$this, "process_admin_options"]);
        add_action("woocommerce_api_" . $this->id . "_webhook", "\Payselection\Webhook::handle");
        add_action("woocommerce_api_" . $this->id . "_widget", "\Payselection\Widget::handle");
        add_action("woocommerce_thankyou", [$this, "check_payment"], 10);
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
                "title" => __("API host", "payselection"),
                "type" => "text",
                "description" => __("API hostname", "payselection"),
                "default" => "",
                "desc_tip" => true,
            ],
            "create_host" => [
                "title" => __("Create Payment host", "payselection"),
                "type" => "text",
                "description" => __("Leave blank if you dont know what you do", "payselection"),
                "default" => "",
                "desc_tip" => true,
            ],
            "check_host" => [
                "title" => __("Check Payment host", "payselection"),
                "type" => "text",
                "description" => __("Leave blank if you dont know what you do", "payselection"),
                "default" => "",
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
                "title" => __("Key", "payselection"),
                "type" => "text",
                "description" => __("Your Key on Payselection", "payselection"),
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

        // Redirect payment
        $response = Api::get_payment_link($order->getRequestData());

        // Debug request
        // wc_add_notice(__("Payselection data:", "payselection") . json_encode($order->getRequestData(), JSON_UNESCAPED_UNICODE));

        if (is_wp_error($response)) {
            wc_add_notice(__('Payselection error:', 'payselection') . " " . $response->get_error_message());
            return false;
        }

        return array(
            'result'   => 'success',
            'redirect' => $response
        );
    }
}

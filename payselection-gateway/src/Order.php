<?php

namespace Payselection;

class Order extends \WC_Order
{
    use Traits\Options;

    public function getRequestData()
    {
        // Get plugin options
        $options = self::get_options();

        $successUrl = $this->get_checkout_order_received_url();
        $cancelUrl = is_user_logged_in() ? $this->get_checkout_order_received_url() : $this->get_cancel_order_url();

        // Redirect links
        $extraData = [
            "WebhookUrl"    => home_url('/wc-api/wc_payselection_gateway_webhook'),
            "SuccessUrl"    => $successUrl,
            "CancelUrl"     => $cancelUrl,
            "DeclineUrl"    => $cancelUrl,
            "FailUrl"       => $cancelUrl,
        ];

        return [
            "MetaData" => [
                "PaymentType" => !empty($options->type) ? $options->type : "Pay",
            ],
            "PaymentRequest" => [
                "OrderId" => (string) $this->get_id(),
                "Amount" => number_format($this->get_total(), 2, ".", ""),
                "Currency" => $this->get_currency(),
                "Description" => "Order payment #" . $this->get_id(),
                "PaymentMethod" => "Card",
                "RebillFlag" => !empty($options->rebill) ? !!$options->rebill : false,
                "ExtraData" => $extraData,
            ],
            "CustomerInfo" => [
                "Email" => $this->get_billing_email(),
                "Phone" => $this->get_billing_phone(),
                "Language" => !empty($options->language) ? $options->language : "en",
                "Address" => $this->get_billing_address_1(),
                "Town" => $this->get_billing_city(),
                "ZIP" => $this->get_billing_postcode(),
                "FirstName" => $this->get_billing_first_name(),
                "LastName" => $this->get_billing_last_name(),
                "IP" => \WC_Geolocation::get_ip_address(),
            ],
        ];
    }
}

<?php

namespace Payselection\Paykassa;

use Payselection\BaseApi;

class Api extends BaseApi
{

    /**
     * request Send request to Paykassa API server
     *
     * @param  string $path - API path
     * @param  array|bool $data - Request DATA
     * @return WP_Error|string
     */
    protected function request(string $host, string $path, $data = false, $method = "GET")
    {

        $bodyJSON = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : "";

        $requestID = self::guidv4();

        $signBody = $method . PHP_EOL . "/" . $path . PHP_EOL . $this->options->site_id . PHP_EOL . $requestID . PHP_EOL . $bodyJSON;

        $headers = [
            "X-SITE-ID" => $this->options->site_id,
            "X-REQUEST-ID" => $requestID,
            "X-REQUEST-SIGNATURE" => self::getSignature($signBody, $this->options->key),
        ];

        $url = $host . "/" . $path;
        $params = [
            "timeout" => 30,
            "redirection" => 5,
            "httpversion" => "1.0",
            "blocking" => true,
            "headers" => $headers,
            "body" => $bodyJSON,
        ];

        // Debug request
        $this->debug(esc_html__('Paykassa request', 'payselection-gateway-for-woocommerce'));
        $this->debug(wc_print_r($params, true));

        $response = $method === 'POST' ? wp_remote_post($url, $params) : wp_remote_get($url, $params);

        // Debug response
        $this->debug(esc_html__('Paykassa response', 'payselection-gateway-for-woocommerce'));
        $this->debug(wc_print_r($response, true));

        if (is_wp_error($response)) {
            return $response;
        }

        // Decode response
        $response["body"] = json_decode($response["body"], true);

        $code = $response["response"]["code"];

        if ($code === 200 || $code === 201) {
            return $response["body"];
        }

        return new \WP_Error("payselection-paykassa-request-error", $response["body"]["Code"] . ($response["body"]["Description"] ? " " . $response["body"]["Description"] : ""));
    }

    /**
     * create Receipt
     *
     * @param  array $data - Request params
     * @return WP_Error|string
     */
    public function create(array $data = [])
    {
        return $this->request($this->options->paykassa_host, sprintf('ca/v1/check/merchant/%s', $this->options->paykassa_merchant_id), $data, 'POST');
    }
}

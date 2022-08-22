<?php

namespace Payselection;

class Api
{
    use Traits\Options;

    /**
     * request Send request to API server
     *
     * @param  string $path - API path
     * @param  array|bool $data - Request DATA
     * @return WP_Error|string
     */
    private static function request(string $path, $data = false, $method = "GET")
    {
        // Get plugin options
        $options = self::get_options();

        $body_json = !empty($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : "";

        $headers = [
            "X-SITE-ID" => $options->site_id,
            "X-REQUEST-ID" => self::guidv4(),
            "X-REQUEST-SIGNATURE" => self::getSignature($body_json, $options->key),
        ];

        $host = !empty($options->create_host) ? $options->create_host : $options->host;

        $url = $host . "/" . $path;
        $params = [
            "timeout" => 30,
            "redirection" => 5,
            "httpversion" => "1.0",
            "blocking" => true,
            "headers" => $headers,
            "body" => $body_json,
        ];

        $response = $method === 'POST' ? wp_remote_post($url, $params) : wp_remote_get($url, $params) ;

        if (is_wp_error($response)) {
            return $response;
        }

        // Decode response
        $response["body"] = json_decode($response["body"], true);

        $code = $response["response"]["code"];

        if ($code === 200 || $code === 201) {
            return $response["body"];
        }

        return new \WP_Error("payselection", $response["body"]["Code"] . ($response["body"]["Description"] ? " " . $response["body"]["Description"] : ""));
    }

    /**
     * guidv4 Create uuid unique id
     * Ref: https://www.uuidgenerator.net/dev-corner/php
     *
     * @param  array|null $data - Random 16 bytes
     * @return string
     */
    private static function guidv4($data = null)
    {
        $data = $data ?? random_bytes(16);
        assert(strlen($data) == 16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf("%s%s-%s-%s-%s-%s%s%s", str_split(bin2hex($data), 4));
    }

    /**
     * getSignature Get signature by request body and key
     *
     * @param  string $body
     * @param  string $secretKey
     * @return string
     */
    private static function getSignature(string $body, string $secretKey)
    {
        if (empty($body)) {
            return ";";
        }

        $hash = hash_hmac("sha256", $body, $secretKey, false);
        return $hash;
    }
    
    /**
     * get_payment_link Get payment link
     *
     * @param  array $data - Request params
     * @return WP_Error|string
     */
    public static function get_payment_link(array $data = [])
    {
        return self::request('webpayments/create', $data, 'POST');
    }
}

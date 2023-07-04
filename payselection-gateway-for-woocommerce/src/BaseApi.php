<?php

namespace Payselection;

class BaseApi
{
    public $options;

    public function __construct()
    {
        $this->options = (object) get_option("woocommerce_wc_payselection_gateway_settings");
    }

    public function debug(string $data = '') {
        if ($this->options->debug === 'yes') {
            $logger = wc_get_logger();
            $logger_context = ['source' => "wc_payselection_gateway"];
            $logger->debug($data, $logger_context);
        }
    }

    /**
     * guidv4 Create uuid unique id
     * Ref: https://www.uuidgenerator.net/dev-corner/php
     *
     * @param  array|null $data - Random 16 bytes
     * @return string
     */
    protected static function guidv4($data = null)
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
    protected static function getSignature(string $body, string $secretKey)
    {
        if (empty($body)) {
            return ";";
        }

        $hash = hash_hmac("sha256", $body, $secretKey, false);
        return $hash;
    }
    
}

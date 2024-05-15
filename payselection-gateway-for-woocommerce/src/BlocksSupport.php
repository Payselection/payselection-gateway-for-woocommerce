<?php

namespace Payselection;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit;

/**
 * Payselection Support for Cart and Checkout blocks.
 *
 */

final class BlocksSupport extends AbstractPaymentMethodType {
	/**
	 * Name of the payment method.
	 *
	 * @var string
	 */
	protected $name = 'wc_payselection_gateway';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option('woocommerce_wc_payselection_gateway_settings', []);
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return !empty($this->settings['enabled']) && 'yes' === $this->settings['enabled'];
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
        wp_register_script(
			'wc-payselection-blocks-integration',
			PAYSELECTION_WOO_URL . 'assets/js/gutenberg_blocks.js',
			array(
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-html-entities',
			),
			PAYSELECTION_WOO_VERSION, 
			true
		);

		return ['wc-payselection-blocks-integration'];
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$payment_method_data = array(
			'title'       => $this->get_setting('title'),
			'description' => $this->get_setting('description'),
			'supports'    => $this->get_supported_features(),
		);

		return $payment_method_data;
	}
}
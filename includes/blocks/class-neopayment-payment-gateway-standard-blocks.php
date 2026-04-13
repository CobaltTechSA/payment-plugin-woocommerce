<?php
/**
 * Blocks Standard class for Neopayment Payment Gateway plugin.
 *
 * @package NEOPAYMENT_PAYMENT_GATEWAY
 */

namespace NboPaymentGateway\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration for the NEOPAYMENT Standard Blocks payment method.
 */
final class NEOPAYMENT_PAYMENT_GATEWAY_Standard_Blocks extends AbstractPaymentMethodType {

	/**
	 * Payment method name for the NEOPAYMENT Standard gateway.
	 *
	 * @var string
	 */
	protected $name = 'neopayment_payment_gateway_standard_gateway';


	/**
	 * Initializes the blocks standard hooks.
	 *
	 * @return void
	 */
	public function initialize() {}

	/**
	 * Handles the payment method type.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		// Blocks only auto-enqueue handles listed here; 3DS popup must load on checkout too.
		return array(
			'neopayment-payment-gateway-standard-blocks-js',
			'neopayment-payment-gateway-3ds-popup',
		);
	}

	/**
	 * Data to be passed to the payment method block.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		$gateway = WC()->payment_gateways()->payment_gateways()[ $this->name ];

		return array(
			'title'       => $gateway->title,
			'description' => $gateway->description,
			'supports'    => $gateway->supports,
			'icons'       => $this->neopayment_payment_gateway_get_icons(),
			'testmode'    => $gateway->testmode,
		);
	}

	/**
	 * Card icons.
	 */
	protected function neopayment_payment_gateway_get_icons() {
		return array(
			array(
				'id'  => 'visa',
				'src' => NEOPAYMENT_PAYMENT_GATEWAY_URL . 'assets/images/visa.svg',
				'alt' => __( 'Visa', 'neopayment-payment-gateway' ),
			),
			array(
				'id'  => 'mastercard',
				'src' => NEOPAYMENT_PAYMENT_GATEWAY_URL . 'assets/images/mastercard.svg',
				'alt' => __( 'Mastercard', 'neopayment-payment-gateway' ),
			),
		);
	}
}

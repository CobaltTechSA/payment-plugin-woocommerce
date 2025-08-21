<?php
/**
 * Blocks Standard class for CBO Payment Gateway plugin.
 *
 * @package COBALT_BANK_OPERATIONS_Payment_Gateway
 */

namespace CBO\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration for the CBO Standard Blocks payment method.
 */
final class COBALT_BANK_OPERATIONS_Standard_Blocks extends AbstractPaymentMethodType {

	/**
	 * Payment method name for the CBO Standard gateway.
	 *
	 * @var string
	 */
	protected $name = 'cobalt_bank_operations_standard_gateway';


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
		return array( 'cobalt-bank-operations-standard-blocks-js' );
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
			'icons'       => $this->get_icons(),
			'testmode'    => $gateway->testmode,
		);
	}

	/**
	 * Card icons.
	 */
	protected function get_icons() {
		return array(
			array(
				'id'  => 'visa',
				'src' => COBALT_BANK_OPERATIONS_URL . 'assets/images/visa.svg',
				'alt' => __( 'Visa', 'class-cobalt-bank-operations-payment-gateway' ),
			),
			array(
				'id'  => 'mastercard',
				'src' => COBALT_BANK_OPERATIONS_URL . 'assets/images/mastercard.svg',
				'alt' => __( 'Mastercard', 'class-cobalt-bank-operations-payment-gateway' ),
			),
		);
	}
}

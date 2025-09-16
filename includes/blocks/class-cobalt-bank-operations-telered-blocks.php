<?php
/**
 * Blocks Telered class for CBO Payment Gateway plugin.
 *
 * @package COBALT_BANK_OPERATIONS_Payment_Gateway
 */

namespace CBO\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integration for the CBO Telered Blocks payment method.
 */
final class COBALT_BANK_OPERATIONS_Telered_Blocks extends AbstractPaymentMethodType {

	/**
	 * Payment method name for the CBO Telered gateway.
	 *
	 * @var string
	 */
	protected $name = 'cobalt_bank_operations_telered_gateway';

	/**
	 * Initializes the blocks telered hooks.
	 *
	 * @return void
	 */
	public function initialize() {}

	/**
	 * Gets payment method script handles for WooCommerce Blocks.
	 *
	 * @return array Block Telered JS.
	 */
	public function get_payment_method_script_handles() {
		return array( 'cobalt-bank-operations-telered-blocks-js' );
	}

	/**
	 * Gets payment method data for WooCommerce Blocks.
	 *
	 * @return array Payment method data.
	 */
	public function get_payment_method_data() {
		return array(
			'title'       => __( 'Clave Card', 'cobalt-bank-operations-payment-gateway' ),
			'description' => __( 'Pay securely with your card', 'cobalt-bank-operations-payment-gateway' ),
			'supports'    => array( 'products' ),
		);
	}
}

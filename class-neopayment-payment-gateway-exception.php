<?php
/**
 * Exceptions for Neopayment Payment Gateway plugin.
 *
 * @package NEOPAYMENT_PAYMENT_GATEWAY
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * NEOPAYMENT Exception helper for the payment gateway.
 */
class NEOPAYMENT_PAYMENT_GATEWAY_Exception extends Exception {

	/**
	 * Stores the response data associated with the exception.
	 *
	 * @var array
	 */
	private array $response;

	/**
	 * Class constructor.
	 *
	 * @param string $message  Exception message.
	 * @param array  $response Response data associated with the exception.
	 */
	public function __construct( string $message, array $response = array() ) {
		parent::__construct( $message );
		$this->response = $response;
	}

	/**
	 * Get response.
	 *
	 * @return array
	 */
	public function getResponse(): array {
		return $this->response;
	}

	/**
	 * Verify if the response is success.
	 *
	 * @return bool
	 */
	public function isSuccessResponse(): bool {
		return 200 === $this->response['code'];
	}
}

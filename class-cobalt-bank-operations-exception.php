<?php
/**
 * Exceptions for CBO Payment Gateway plugin.
 *
 * @package COBALT_BANK_OPERATIONS_Payment_Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CBO Exception helper for the payment gateway.
 */
class COBALT_BANK_OPERATIONS_Exception extends Exception {

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

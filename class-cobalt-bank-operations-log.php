<?php
/**
 * Logger helper for CBO Payment Gateway plugin.
 *
 * @package COBALT_BANK_OPERATIONS_Payment_Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WooCommerce logs for the payment gateway.
 */
class COBALT_BANK_OPERATIONS_Log {

	/**
	 * Logger instance.
	 *
	 * @var \WC_Logger|\Psr\Log\LoggerInterface|null
	 */
	private static $instance = null;

	/**
	 * Returns the shared logger instance.
	 *
	 * @return \WC_Logger|\Psr\Log\LoggerInterface Logger instance.
	 */
	public static function get_instance() {
		if ( ! self::$instance ) {
			self::$instance = wc_get_logger();
		}

		return self::$instance;
	}

	/**
	 * Writes a message to the WooCommerce logger with the given level.
	 *
	 * @param string             $level   Log level.
	 * @param string|\Stringable $message Message to write.
	 * @return void
	 */
	public static function log( $level, $message ) {
		$logger  = self::get_instance();
		$context = array( 'source' => 'cobalt-bank-operations-payment-gateway' );
		$logger->{$level}( $message, $context );
	}

	/**
	 * Logs a debug message.
	 *
	 * @param string|\Stringable $message Message to write.
	 * @return void
	 */
	public static function debug( $message ) {
		self::log( 'debug', $message );
	}

	/**
	 * Logs an info message.
	 *
	 * @param string|\Stringable $message Message to write.
	 * @return void
	 */
	public static function info( $message ) {
		self::log( 'info', $message );
	}

	/**
	 * Logs a notice message.
	 *
	 * @param string|\Stringable $message Message to write.
	 * @return void
	 */
	public static function notice( $message ) {
		self::log( 'notice', $message );
	}

	/**
	 * Logs a warning message.
	 *
	 * @param string|\Stringable $message Message to write.
	 * @return void
	 */
	public static function warning( $message ) {
		self::log( 'warning', $message );
	}

	/**
	 * Logs an error message.
	 *
	 * @param string|\Stringable $message Message to write.
	 * @return void
	 */
	public static function error( $message ) {
		self::log( 'error', $message );
	}

	/**
	 * Logs a critical message.
	 *
	 * @param string|\Stringable $message Message to write.
	 * @return void
	 */
	public static function critical( $message ) {
		self::log( 'critical', $message );
	}

	/**
	 * Logs an alert message.
	 *
	 * @param string|\Stringable $message Message to write.
	 * @return void
	 */
	public static function alert( $message ) {
		self::log( 'alert', $message );
	}

	/**
	 * Logs an emergency message.
	 *
	 * @param string|\Stringable $message Message to write.
	 * @return void
	 */
	public static function emergency( $message ) {
		self::log( 'emergency', $message );
	}
}

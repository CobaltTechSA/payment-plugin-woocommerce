<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
class CBOPAGA_Log {

	private static $instance = null;

	/**
	 * @return null
	 */
	public static function get_instance() {
		if (!self::$instance) {
			self::$instance = wc_get_logger();
		}

		return self::$instance;
	}

	public static function log($fn, $msg) {
		$logger = self::get_instance();
		$context = array('source' => 'cbo-payment-gateway');
		$logger->{$fn}($msg, $context);
	}

	public static function debug($message) {
		self::log('debug', $message);
	}

	public static function info($message) {
		self::log('info', $message);
	}

	public static function notice($message) {
		self::log('notice', $message);
	}

	public static function warning($message) {
		self::log('warning', $message);
	}

	public static function error($message) {
		self::log('error', $message);
	}

	public static function critical($message) {
		self::log('critical', $message);
	}

	public static function alert($message) {
		self::log('alert', $message);
	}

	public static function emergency($message) {
		self::log('emergency', $message);
	}


}
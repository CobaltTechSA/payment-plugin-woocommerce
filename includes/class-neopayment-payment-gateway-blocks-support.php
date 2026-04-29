<?php

/**
 * Blocks Support class for Neopayment Payment Gateway plugin.
 *
 * @package NEOPAYMENT_PAYMENT_GATEWAY
 */

namespace NboPaymentGateway\Blocks;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WooCommerce Blocks integration for the payment gateway.
 */
final class NEOPAYMENT_PAYMENT_GATEWAY_Blocks_Support {

	/**
	 * Initializes the blocks support hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// Integrations for the NEOPAYMENT Standard and Telered Blocks payment methods.
		require_once NEOPAYMENT_PAYMENT_GATEWAY_PATH . 'includes/blocks/class-neopayment-payment-gateway-standard-blocks.php';
		require_once NEOPAYMENT_PAYMENT_GATEWAY_PATH . 'includes/blocks/class-neopayment-payment-gateway-telered-blocks.php';

		// Integrations registration.
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			array( __CLASS__, 'register_blocks' )
		);

		add_action( 'init', array( __CLASS__, 'register_scripts' ) );
		add_action( 'init', array( __CLASS__, 'register_styles' ) );
	}

	/**
	 * Initialize the payment gateway blocks.
	 *
	 * @param PaymentMethodRegistry $registry Registry for payment methods.
	 * @return void
	 */
	public static function register_blocks( PaymentMethodRegistry $registry ) {
		if ( class_exists( '\NboPaymentGateway\Blocks\NEOPAYMENT_PAYMENT_GATEWAY_Standard_Blocks' ) ) {
			$registry->register( new \NboPaymentGateway\Blocks\NEOPAYMENT_PAYMENT_GATEWAY_Standard_Blocks() );
		}
		if ( class_exists( '\NboPaymentGateway\Blocks\NEOPAYMENT_PAYMENT_GATEWAY_Telered_Blocks' ) ) {
			$registry->register( new \NboPaymentGateway\Blocks\NEOPAYMENT_PAYMENT_GATEWAY_Telered_Blocks() );
		}
	}

	/**
	 * Scripts.
	 *
	 * @return void
	 */
	public static function register_scripts() {
		// Standard Payments.
		$standard_asset = include NEOPAYMENT_PAYMENT_GATEWAY_PATH . 'build/neopayment-payment-gateway-standard.asset.php';
		wp_register_script(
			'neopayment-payment-gateway-standard-blocks-js',
			NEOPAYMENT_PAYMENT_GATEWAY_URL . 'build/neopayment-payment-gateway-standard.js',
			$standard_asset['dependencies'] ?? array(
				'react',
				'react-dom',
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-i18n',
			),
			$standard_asset['version'] ?? NEOPAYMENT_PAYMENT_GATEWAY_Constants::NEOPAYMENT_PAYMENT_GATEWAY_PLUGIN_VERSION,
			true
		);

		wp_set_script_translations(
			'neopayment-payment-gateway-standard-blocks-js',
			'neopayment-payment-gateway',
			NEOPAYMENT_PAYMENT_GATEWAY_PATH . 'i18n'
		);

		wp_register_script(
			'neopayment-payment-gateway-3ds-popup',
			NEOPAYMENT_PAYMENT_GATEWAY_URL . 'assets/js/neopayment-payment-gateway-3ds-popup.js',
			array( 'jquery', 'neopayment-payment-gateway-standard-blocks-js' ),
			file_exists( NEOPAYMENT_PAYMENT_GATEWAY_PATH . 'assets/js/neopayment-payment-gateway-3ds-popup.js' )
				? (string) filemtime( NEOPAYMENT_PAYMENT_GATEWAY_PATH . 'assets/js/neopayment-payment-gateway-3ds-popup.js' )
				: NEOPAYMENT_PAYMENT_GATEWAY_Constants::NEOPAYMENT_PAYMENT_GATEWAY_PLUGIN_VERSION,
			true
		);

		wp_localize_script(
			'neopayment-payment-gateway-standard-blocks-js',
			'neopayment_payment_gateway_3DS',
			array(
				'url_ok'   => esc_url_raw( home_url( '/wc-api/neopayment_payment_gateway_standard_gateway_status' ) ),
				'url_ko'   => esc_url_raw( home_url( '/wc-api/neopayment_payment_gateway_standard_gateway_status' ) ),
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'neopayment_payment_gateway_3ds_nonce' ),
			)
		);

		// Telered Payments.
		$telered_asset = include NEOPAYMENT_PAYMENT_GATEWAY_PATH . 'build/neopayment-payment-gateway-telered.asset.php';
		wp_register_script(
			'neopayment-payment-gateway-telered-blocks-js',
			NEOPAYMENT_PAYMENT_GATEWAY_URL . 'build/neopayment-payment-gateway-telered.js',
			$telered_asset['dependencies'] ?? array(
				'react',
				'react-dom',
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-i18n',
			),
			$telered_asset['version'] ?? NEOPAYMENT_PAYMENT_GATEWAY_Constants::NEOPAYMENT_PAYMENT_GATEWAY_PLUGIN_VERSION,
			true
		);

		wp_set_script_translations(
			'neopayment-payment-gateway-telered-blocks-js',
			'neopayment-payment-gateway',
			NEOPAYMENT_PAYMENT_GATEWAY_PATH . 'i18n'
		);
	}

	/**
	 * Styles.
	 *
	 * @return void
	 */
	public static function register_styles() {
		wp_enqueue_style(
			'neopayment-payment-gateway-card-fields-style',
			NEOPAYMENT_PAYMENT_GATEWAY_URL . 'assets/css/neopayment-payment-gateway-card-fields.css',
			array(),
			'1.0.0'
		);
	}
}

NEOPAYMENT_PAYMENT_GATEWAY_Blocks_Support::init();

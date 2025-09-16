<?php
/**
 * Blocks Support class for CBO Payment Gateway plugin.
 *
 * @package COBALT_BANK_OPERATIONS_Payment_Gateway
 */

namespace CBO\Blocks;

use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WooCommerce Blocks integration for the payment gateway.
 */
final class COBALT_BANK_OPERATIONS_Blocks_Support {

	/**
	 * Initializes the blocks support hooks.
	 *
	 * @return void
	 */
	public static function init() {
		// Integrations for the CBO Standard and Telered Blocks payment methods.
		require_once COBALT_BANK_OPERATIONS_PATH . 'includes/blocks/class-cobalt-bank-operations-standard-blocks.php';
		require_once COBALT_BANK_OPERATIONS_PATH . 'includes/blocks/class-cobalt-bank-operations-telered-blocks.php';

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
		if ( class_exists( '\CBO\Blocks\COBALT_BANK_OPERATIONS_Standard_Blocks' ) ) {
			$registry->register( new \CBO\Blocks\COBALT_BANK_OPERATIONS_Standard_Blocks() );
		}
		if ( class_exists( '\CBO\Blocks\COBALT_BANK_OPERATIONS_Telered_Blocks' ) ) {
			$registry->register( new \CBO\Blocks\COBALT_BANK_OPERATIONS_Telered_Blocks() );
		}
	}

	/**
	 * Scripts.
	 *
	 * @return void
	 */
	public static function register_scripts() {
		// Standard Payments.
		$standard_asset = include COBALT_BANK_OPERATIONS_PATH . 'build/cobalt-bank-operations-standard.asset.php';
		wp_register_script(
			'cobalt-bank-operations-standard-blocks-js',
			COBALT_BANK_OPERATIONS_URL . 'build/cobalt-bank-operations-standard.js',
			$standard_asset['dependencies'] ?? array(
				'react',
				'react-dom',
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-i18n',
			),
			$standard_asset['version'] ?? COBALT_BANK_OPERATIONS_Constants::PLUGIN_VERSION,
			true
		);

		wp_set_script_translations(
			'cobalt-bank-operations-standard-blocks-js',
			'cobalt-bank-operations-payment-gateway',
			COBALT_BANK_OPERATIONS_PATH . 'i18n'
		);

		wp_register_script(
			'cobalt-bank-operations-3ds-popup',
			COBALT_BANK_OPERATIONS_URL . 'assets/js/cobalt-bank-operations-3ds-popup.js',
			array( 'jquery', 'cobalt-bank-operations-standard-blocks-js' ),
			'2.4.0',
			true
		);

		wp_localize_script(
			'cobalt-bank-operations-standard-blocks-js',
			'CBO3DS',
			array(
				'url_ok'   => esc_url_raw( home_url( '/wc-api/cobalt_bank_operations_standard_gateway_status' ) ),
				'url_ko'   => esc_url_raw( home_url( '/wc-api/cobalt_bank_operations_standard_gateway_status' ) ),
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'cobalt_bank_operations_3ds_nonce' ),
			)
		);

		// Telered Payments.
		$telered_asset = include COBALT_BANK_OPERATIONS_PATH . 'build/cobalt-bank-operations-telered.asset.php';
		wp_register_script(
			'cobalt-bank-operations-telered-blocks-js',
			COBALT_BANK_OPERATIONS_URL . 'build/cobalt-bank-operations-telered.js',
			$telered_asset['dependencies'] ?? array(
				'react',
				'react-dom',
				'wc-blocks-registry',
				'wc-settings',
				'wp-element',
				'wp-i18n',
			),
			$telered_asset['version'] ?? COBALT_BANK_OPERATIONS_Constants::PLUGIN_VERSION,
			true
		);

		wp_set_script_translations(
			'cobalt-bank-operations-telered-blocks-js',
			'cobalt-bank-operations-payment-gateway',
			COBALT_BANK_OPERATIONS_PATH . 'i18n'
		);
	}

	/**
	 * Styles.
	 *
	 * @return void
	 */
	public static function register_styles() {
		wp_enqueue_style(
			'cobalt-bank-operations-card-fields-style',
			COBALT_BANK_OPERATIONS_URL . 'assets/css/cobalt-bank-operations-card-fields.css',
			array(),
			'1.0.0'
		);
	}
}

COBALT_BANK_OPERATIONS_Blocks_Support::init();

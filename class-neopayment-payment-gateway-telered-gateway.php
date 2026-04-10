<?php
/**
 * Telered class for Neopayment Payment Gateway plugin.
 *
 * @package NEOPAYMENT_PAYMENT_GATEWAY
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once 'class-neopayment-payment-gateway-constants.php';

/**
 * Handles WooCommerce Telered integration for the payment gateway.
 */
class NEOPAYMENT_PAYMENT_GATEWAY_Telered_Gateway extends WC_Payment_Gateway {

	/**
	 * Instance for the NEOPAYMENT Telered gateway.
	 *
	 * @var string
	 */
	protected static $instance;

	/**
	 * Class constructor, more about it in Step 3.
	 */
	public function __construct() {

		$this->id                 = NEOPAYMENT_PAYMENT_GATEWAY_Constants::NEOPAYMENT_PAYMENT_GATEWAY_TELERED_GATEWAY_ID; // payment gateway plugin ID.
		$this->icon               = ''; // URL of the icon that will be displayed on checkout page near your gateway name.
		$this->has_fields         = false; // in case you need a custom credit card form.
		$this->method_title       = 'NEOPAYMENT Clave Gateway';
		$this->method_description = __( 'Acceptance of payments with Clave', 'neopayment-payment-gateway' ); // will be displayed on the options page.

		// gateways can support subscriptions, refunds, saved payment methods but in this tutorial we begin with simple payments.
		$this->supports = array(
			'products',
		);

		// Method with all the options fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();
		$this->title             = $this->get_option( 'title' );
		$this->description       = $this->get_option( 'description' );
		$this->enabled           = $this->get_option( 'enabled' );
		$this->testmode          = 'yes' === $this->get_option( 'testmode' );
		$this->api_url           = $this->testmode ? $this->get_option( 'test_api_url' ) : $this->get_option( 'api_url' );
		$this->api_client_id     = $this->testmode ? $this->get_option( 'test_api_client_id' ) : $this->get_option( 'api_client_id' );
		$this->api_client_secret = $this->testmode ? $this->get_option( 'test_api_client_secret' ) : $this->get_option( 'api_client_secret' );

		// This action hook saves the settings.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// We need custom JavaScript to obtain a token.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

		// You can also register a webhook here.
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'neopayment_payment_gateway_webhook' ) );

		// URL OK y KO.
		add_action( 'woocommerce_api_' . $this->id . '_status', array( $this, 'neopayment_payment_gateway_callback_url' ) );

		// Add nonce field for security.
		add_action(
			'woocommerce_admin_field_neopayment_payment_gateway_nonce',
			function () {
				wp_nonce_field( 'neopayment_payment_gateway_telered_save_settings', 'neopayment_payment_gateway_telered_nonce' );
			}
		);
	}

	/**
	 * Process Admin Validate.
	 */
	public function process_admin_options() {
		if ( ! isset( $_POST['neopayment_payment_gateway_telered_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['neopayment_payment_gateway_telered_nonce'] ) ), 'neopayment_payment_gateway_telered_save_settings' ) ) {
			wp_die( esc_html__( 'Unauthorized action.', 'neopayment-payment-gateway' ), esc_html__( 'Security Error', 'neopayment-payment-gateway' ), 403 );
		}
		parent::process_admin_options();
	}

	/**
	 * Get Icon Payment Option.
	 */
	public function get_icon() {
		$path  = plugin_dir_url( __FILE__ );
		$icons = array(
			sprintf(
				'<img class="%s" src="%s" alt="%s" />',
				esc_attr( 'neopayment-payment-gateway-icon' ),
				esc_url( WC_HTTPS::force_https_url( $path . 'assets/images/clave.svg' ) ),
				esc_attr__( 'Telered', 'neopayment-payment-gateway' )
			),
		);

		$pay_icons = '<div style="vertical-align: middle; display: inline-block; margin-left: 22px">';
		foreach ( $icons as $icon ) {
			$pay_icons .= $icon;
		}

		$pay_icons .= '</div>';

		// WooCommerce core filter name; cannot be prefixed.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		return apply_filters( 'woocommerce_gateway_icon', $pay_icons, $this->id );
	}
	/**
	 * Plugin options, we deal with it in Step 3 too.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'                => array(
				'title'       => __( 'Enable/Disable', 'neopayment-payment-gateway' ),
				'label'       => __( 'Enable Neopayment Payment Gateway', 'neopayment-payment-gateway' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                  => array(
				'title'       => __( 'Title', 'neopayment-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'neopayment-payment-gateway' ),
				'default'     => 'Clave',
				'desc_tip'    => true,
			),
			'description'            => array(
				'title'       => __( 'Description', 'neopayment-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'neopayment-payment-gateway' ),
				'default'     => __( 'Pay with Clave', 'neopayment-payment-gateway' ),
			),
			'testmode'               => array(
				'title'       => __( 'Test mode', 'neopayment-payment-gateway' ),
				'label'       => __( 'Enable Test Mode', 'neopayment-payment-gateway' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'neopayment-payment-gateway' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_api_url'           => array(
				'title' => __( 'Test API URL', 'neopayment-payment-gateway' ),
				'type'  => 'text',
			),
			'test_api_client_id'     => array(
				'title' => __( 'Test API Client Id', 'neopayment-payment-gateway' ),
				'type'  => 'text',
			),
			'test_api_client_secret' => array(
				'title' => __( 'Test API Client Secret', 'neopayment-payment-gateway' ),
				'type'  => 'password',
			),
			'api_url'                => array(
				'title' => __( 'Production API URL', 'neopayment-payment-gateway' ),
				'type'  => 'text',
			),
			'api_client_id'          => array(
				'title' => __( 'Production API Client Id', 'neopayment-payment-gateway' ),
				'type'  => 'text',
			),
			'api_client_secret'      => array(
				'title' => __( 'Production API Client Secret', 'neopayment-payment-gateway' ),
				'type'  => 'password',
			),
		);
	}

	/**
	 * Admin Options Function.
	 */
	public function admin_options() {
		?>
		<h2><?php echo esc_html( $this->get_method_title() ); ?></h2>
		<p><?php echo esc_html( $this->get_method_description() ); ?></p>
		<table class="form-table">
			<?php
			// Nonce field for security.
			wp_nonce_field( 'neopayment_payment_gateway_telered_save_settings', 'neopayment_payment_gateway_telered_nonce' );

			$this->generate_settings_html();
			?>
		</table>
		<?php
	}

	/**
	 * You will need it if you want your custom credit card form, Step 4 is about it.
	 */
	public function payment_fields() {

		// ok, let's display some description before the payment form.
		if ( $this->description ) {
			// you can instructions for test mode, I mean test card numbers etc.
			if ( $this->testmode ) {
				$this->description .= ' ' . __( 'TEST MODE ENABLED', 'neopayment-payment-gateway' ) . '.';
				$this->description  = trim( $this->description );
			}
			// display the description with <p> tags etc.
			echo wp_kses_post( wpautop( $this->description ) );
		}
	}

	/**
	 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form.
	 */
	public function payment_scripts() {
	}

	/**
	 * Fields validation, more in Step 5.
	 */
	public function validate_fields() {
		return true;
	}

	/**
	 * Processes the payment and returns result + redirect URL.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array Result data with 'result' and 'redirect' keys.
	 */
	public function process_payment( $order_id ) {

		// we need it to get any order detailes.
		$order = wc_get_order( $order_id );

		NEOPAYMENT_PAYMENT_GATEWAY_Log::debug( "api_client_id=$this->api_client_id, api_client_secret=$this->api_client_secret" );
		$neopayment_payment_gateway_client = new NEOPAYMENT_PAYMENT_GATEWAY_Client( $this->api_url, $this->api_client_id, $this->api_client_secret );
		try {
			$checkout = $neopayment_payment_gateway_client->checkout( $order, NEOPAYMENT_PAYMENT_GATEWAY_Constants::NEOPAYMENT_PAYMENT_GATEWAY_PAYMENT_TYPE_TELERED );

			NEOPAYMENT_PAYMENT_GATEWAY_Log::debug( 'Checkout data: ' . wp_json_encode( $checkout ) );

			// Mark as on-hold (we're awaiting the cheque).
			// $order->update_status('on-hold', __( 'Awaiting cheque payment', 'woocommerce' ));.

			return array(
				'result'   => 'success',
				'redirect' => $this->api_url . '/checkout/' . $checkout['slug'],
			);
		} catch ( \NEOPAYMENT_PAYMENT_GATEWAY_Exception $e ) {
			if ( ! $e->isSuccessResponse() ) {
				NEOPAYMENT_PAYMENT_GATEWAY_Log::debug( $e->getMessage() . ' - ' . wp_json_encode( $e->getResponse() ) );
				wc_add_notice( 'No se ha podido generar el pago. Por favor contacte con el comercio.', 'error' );
			} else {
				wc_add_notice( 'No se ha podido procesar el pago. Por favor contacte con el comercio.', 'error' );
			}
		}
	}

	/**
	 * Callback function for show payment status
	 */
	public function neopayment_payment_gateway_callback_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id = isset( $_GET['oid'] ) ? absint( $_GET['oid'] ) : 0;

		$order = wc_get_order( $order_id );

		$start = time();
		while ( ! $order->is_paid() && ( time() - $start ) < 30 ) {
			sleep( 1 );
			$order = wc_get_order( $order_id );
		}

		if ( $order->is_paid() ) {
			wp_safe_redirect( $order->get_checkout_order_received_url() );
			exit;
		}

		wp_safe_redirect( $order->get_checkout_payment_url() );
		exit;
	}

	/**
	 * Webhook listener (updates order on gateway notification).
	 *
	 * @return void
	 */
	public function neopayment_payment_gateway_webhook() {

		$data_raw = json_decode( file_get_contents( 'php://input' ), true );
		if ( ! is_array( $data_raw ) ) {
			NEOPAYMENT_PAYMENT_GATEWAY_Log::debug( 'Webhook error: payload no es JSON' );
			status_header( 400 );
			exit;
		}

		$data = $this->neopayment_payment_gateway_recursive_sanitize( $data_raw );
		NEOPAYMENT_PAYMENT_GATEWAY_Log::debug( 'Checkout data: ' . wp_json_encode( $checkout ) );

		try {
			$transaction = $data;
			$metas       = $transaction['metadatas'] ?? array();
			$order_id    = $metas['order_id'] ?? null;
			$order       = $order_id ? wc_get_order( $order_id ) : null;

			$status         = $transaction['status'] ?? '';
			$success_status = array( 'authorized', 'notified' );

			if ( $order ) {
				$order->add_meta_data( 'neopayment_payment_gateway_bank_code', $transaction['response_code'] ?? '' );
				$order->add_meta_data( 'neopayment_payment_gateway_transaction_id', $transaction['identifier'] ?? '' );
				$order->add_meta_data( 'neopayment_payment_gateway_bank_authorization', $transaction['authorization_number'] ?? '' );

				if ( in_array( $status, $success_status, true ) ) {
					$order->update_status( 'completed', __( 'Pago completado', 'neopayment-payment-gateway' ) );
					$order->payment_complete( $transaction['identifier'] ?? '' );
					if ( function_exists( 'WC' ) && WC()->cart ) {
						WC()->cart->empty_cart();
					}
				} else {
					$order->update_status( 'failed', __( 'Pago fallido', 'neopayment-payment-gateway' ) );
				}
			}

			status_header( 204 );

		} catch ( \NEOPAYMENT_PAYMENT_GATEWAY_Exception $e ) {
			NEOPAYMENT_PAYMENT_GATEWAY_Log::debug( 'Error getting transaction ' . ( $data['tid'] ?? '' ) . ' - ' . $e->getMessage() );
			status_header( 400 );
		}
	}

	/**
	 * Recursive validation/sanitization for 3DS values.
	 *
	 * @param mixed $value Value to sanitize (array|string|scalar).
	 * @return mixed Sanitized value.
	 */
	private function neopayment_payment_gateway_recursive_sanitize( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = $this->neopayment_payment_gateway_recursive_sanitize( $v );
			}
			return $value;
		}

		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}

		return $value;
	}

	/**
	 * Instance function hooks.
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}
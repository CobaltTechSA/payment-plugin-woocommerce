<?php
/**
 * Standard class for CBO Payment Gateway plugin.
 *
 * @package COBALT_BANK_OPERATIONS_Payment_Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once 'cobalt-bank-operations-constants.php';
require_once 'cobalt-bank-operations-helpers.php';
require_once 'cobalt-bank-operations-payment-gateway-cc.php';

/**
 * Handles WooCommerce Standard integration for the payment gateway.
 */
class Cobalt_Bank_Operations_Standard_Gateway extends WC_Payment_Gateway {

	/**
	 * Instance for the CBO Standard gateway.
	 *
	 * @var string
	 */
	protected static $instance;

	/**
	 * Class constructor, more about it in Step 3.
	 */
	public function __construct() {

		$this->id                 = Cobalt_Bank_Operations_Constants::STANDARD_GATEWAY_ID; // payment gateway plugin ID.
		$this->icon               = ''; // URL of the icon that will be displayed on checkout page near your gateway name.
		$this->has_fields         = true; // in case you need a custom credit card form.
		$this->method_title       = 'CBO Standard Gateway';
		$this->method_description = __( 'Acceptance of payments with Visa / Mastercard', 'cobalt-bank-operations-payment-gateway' ); // will be displayed on the options page.

		// gateways can support subscriptions, refunds, saved payment methods, but in this tutorial we begin with simple payments.
		$this->supports = array(
			'products',
			'refunds',
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
		add_action( 'woocommerce_api_' . $this->id, array( $this, 'webhook' ) );

		// URL OK y KO.
		add_action( 'woocommerce_api_' . $this->id . '_status', array( $this, 'callback_url' ) );

		// JS Scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_scripts' ), 20 );

		// Add nonce field for security.
		add_action(
			'woocommerce_admin_field_cobalt_bank_operations_nonce',
			function () {
				wp_nonce_field( 'cobalt_bank_operations_standard_save_settings', 'cobalt_bank_operations_standard_nonce' );
			}
		);
	}

	/**
	 * Process Admin Validate.
	 */
	public function process_admin_options() {
		if ( ! isset( $_POST['cobalt_bank_operations_standard_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cobalt_bank_operations_standard_nonce'] ) ), 'cobalt_bank_operations_standard_save_settings' ) ) {
			wp_die( esc_html__( 'Unauthorized action.', 'cobalt-bank-operations-payment-gateway' ), esc_html__( 'Security Error', 'cobalt-bank-operations-payment-gateway' ), 403 );
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
				esc_attr( 'cobalt-bank-operations-gateway-icon' ),
				esc_url( WC_HTTPS::force_https_url( $path . 'assets/images/visa.svg' ) ),
				esc_attr__( 'Visa', 'cobalt-bank-operations-payment-gateway' )
			),
			sprintf(
				'<img class="%s" src="%s" alt="%s" />',
				esc_attr( 'cobalt-bank-operations-gateway-icon' ),
				esc_url( WC_HTTPS::force_https_url( $path . 'assets/images/mastercard.svg' ) ),
				esc_attr__( 'Mastercard', 'cobalt-bank-operations-payment-gateway' )
			),
		);

		$pay_icons = '<div style="vertical-align: middle; display: inline-block; margin-left: 22px">';
		foreach ( $icons as $icon ) {
			$pay_icons .= $icon;
		}

		$pay_icons .= '</div>';

		return apply_filters( 'woocommerce_gateway_icon', $pay_icons, $this->id );
	}
	/**
	 * Plugin options, we deal with it in Step 3 too
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'                => array(
				'title'       => __( 'Enable/Disable', 'cobalt-bank-operations-payment-gateway' ),
				'label'       => __( 'Enable CBO Payment Gateway', 'cobalt-bank-operations-payment-gateway' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                  => array(
				'title'       => __( 'Title', 'cobalt-bank-operations-payment-gateway' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'cobalt-bank-operations-payment-gateway' ),
				'default'     => 'VISA, Mastercard',
				'desc_tip'    => true,
			),
			'description'            => array(
				'title'       => __( 'Description', 'cobalt-bank-operations-payment-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'This controls the description which the user sees during checkout.', 'cobalt-bank-operations-payment-gateway' ),
				'default'     => __( 'Pay with your VISA or Mastercard card', 'cobalt-bank-operations-payment-gateway' ),
			),
			'testmode'               => array(
				'title'       => __( 'Test mode', 'cobalt-bank-operations-payment-gateway' ),
				'label'       => __( 'Enable Test Mode', 'cobalt-bank-operations-payment-gateway' ),
				'type'        => 'checkbox',
				'description' => __( 'Place the payment gateway in test mode using test API keys.', 'cobalt-bank-operations-payment-gateway' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_api_url'           => array(
				'title' => __( 'Test API URL', 'cobalt-bank-operations-payment-gateway' ),
				'type'  => 'text',
			),

			'test_api_client_id'     => array(
				'title' => __( 'Test API Client Id', 'cobalt-bank-operations-payment-gateway' ),
				'type'  => 'text',
			),
			'test_api_client_secret' => array(
				'title' => __( 'Test API Client Secret', 'cobalt-bank-operations-payment-gateway' ),
				'type'  => 'password',
			),
			'api_url'                => array(
				'title' => __( 'Production API URL', 'cobalt-bank-operations-payment-gateway' ),
				'type'  => 'text',
			),

			'api_client_id'          => array(
				'title' => __( 'Production API Client Id', 'cobalt-bank-operations-payment-gateway' ),
				'type'  => 'text',
			),
			'api_client_secret'      => array(
				'title' => __( 'Production API Client Secret', 'cobalt-bank-operations-payment-gateway' ),
				'type'  => 'password',
			),
		);
	}

	/**
	 * Admin options
	 */
	public function admin_options() {
		?>
		<h2><?php echo esc_html( $this->get_method_title() ); ?></h2>
		<p><?php echo esc_html( $this->get_method_description() ); ?></p>
		<table class="form-table">
			<?php
			// Nonce field for security.
			wp_nonce_field( 'cobalt_bank_operations_standard_save_settings', 'cobalt_bank_operations_standard_nonce' );

			$this->generate_settings_html();
			?>
		</table>
		<?php
	}


	/**
	 * Register scripts for the plugin.
	 * This method is called on the 'wp_enqueue_scripts' action.
	 */
	public function register_plugin_scripts() {
		if ( ! is_checkout() || is_cart() ) {
			return;
		}

		$base = plugin_dir_url( __FILE__ ) . 'assets/js/';
		$ver  = Cobalt_Bank_Operations_Constants::PLUGIN_VERSION;

		wp_register_script(
			'cobalt-bank-operations-sweetalert',
			plugins_url( 'assets/js/sweetAlert/sweetalert.min.js', __FILE__ ),
			array(),
			'2.1.2',
			true
		);

		wp_enqueue_script( 'cobalt-bank-operations-sweetalert' );

		// Script for the standard payment method.
		wp_enqueue_script(
			'cobalt-bank-operations-standard-payment',
			$base . 'cobalt-bank-operations-payment-script.js',
			array( 'jquery' ),
			$ver,
			true
		);

		// Script for the 3DS popup + classic checkout.
		wp_enqueue_script(
			'cobalt-bank-operations-3ds-popup',
			$base . 'cobalt-bank-operations-3ds-popup.js',
			array( 'jquery', 'wc-checkout', 'cobalt-bank-operations-sweetalert' ),
			$ver,
			true
		);

		// This will be used to handle the 3DS challenge response.
		$callback = esc_url_raw( home_url( "/wc-api/{$this->id}_status" ) );

		wp_localize_script(
			'cobalt-bank-operations-3ds-popup',
			'CBO3DS',
			array(
				'url_ok' => $callback,
				'url_ko' => $callback,
			)
		);
	}

	/**
	 * Credit card form checkout classic.
	 *
	 * @param array $args   args forms.
	 * @param array $fields fields args forms.
	 * @return void
	 */
	public function credit_card_form( $args = array(), $fields = array() ) {
		$cc_form           = new Cobalt_Bank_Operations_Payment_Gateway_CC();
		$cc_form->id       = $this->id;
		$cc_form->supports = $this->supports;
		$cc_form->form();
	}

	/**
	 * You will need it if you want your custom credit card form, Step 4 is about it.
	 */
	public function payment_fields() {

		// ok, let's display some description before the payment form.
		if ( $this->description ) {
			// you can instructions for test mode, I mean test card numbers etc.
			if ( $this->testmode ) {
				$this->description .= ' ' . __( 'TEST MODE ENABLED', 'cobalt-bank-operations-payment-gateway' ) . '.';
				$this->description  = trim( $this->description );
			}
			// display the description with <p> tags etc.
			echo wp_kses_post( wpautop( $this->description ) );
		}
		// nonce field for security.
		wp_nonce_field( $this->id . '_process_payment', $this->id . '_nonce' );

		$this->credit_card_form();
	}

	/**
	 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form.
	 *
	 * @return void
	 */
	public function payment_scripts() {
	}

	/**
	 * Fields validation, more in Step 5.
	 *
	 * @return bool True si es válido, false en caso contrario.
	 */
	public function validate_fields() {

		// check if the nonce is set and valid.
		if ( isset( $_POST[ $this->id . '_nonce' ] ) &&
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $this->id . '_nonce' ] ) ), $this->id . '_process_payment' ) ) {
			wc_add_notice( __( 'Security check failed. Please try again.', 'cobalt-bank-operations-payment-gateway' ), 'error' );
			return false;
		}

		// detect if the request is from a block-based checkout or classic checkout.
		$raw_input = file_get_contents( 'php://input' );
		$body      = json_decode( $raw_input, true );
		$body      = is_array( $body ) ? $body : array();

		foreach ( $body as $key => $value ) {
			if ( is_string( $value ) ) {
				$body[ $key ] = sanitize_text_field( $value );
			} elseif ( is_array( $value ) ) {
				foreach ( $value as $subkey => $subvalue ) {
					if ( is_string( $subvalue ) ) {
						$body[ $key ][ $subkey ] = sanitize_text_field( $subvalue );
					}
				}
			}
		}
		// if the request is from a block-based checkout, omit the validation.
		if ( ! empty( $body['payment_data'] ) ) {
			return true;
		}

		$card_number = isset( $_POST[ $this->id . '-card-number' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $this->id . '-card-number' ] ) ) : '';
		$card_expiry = isset( $_POST[ $this->id . '-card-expiry' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $this->id . '-card-expiry' ] ) ) : '';
		$card_cvv    = isset( $_POST[ $this->id . '-card-cvc' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $this->id . '-card-cvc' ] ) ) : '';
		$card_holder = isset( $_POST[ $this->id . '-card-holder' ] ) ? sanitize_text_field( wp_unslash( $_POST[ $this->id . '-card-holder' ] ) ) : '';

		$valid = true;

		$card_number = str_replace( ' ', '', $card_number );
		if ( ! cobalt_bank_operations_is_valid_luhn( $card_number ) ) {
			wc_add_notice( __( 'Invalid card number', 'cobalt-bank-operations-payment-gateway' ), 'error' );
			$valid = false;
		}

		$card_expiry = str_replace( ' ', '', $card_expiry );
		if ( ! cobalt_bank_operations_is_valid_expiry_date( $card_expiry ) ) {
			wc_add_notice( __( 'Invalid expiry date', 'cobalt-bank-operations-payment-gateway' ), 'error' );
			$valid = false;
		}

		if ( ! cobalt_bank_operations_is_valid_card_holder( $card_holder ) ) {
			wc_add_notice( __( 'Invalid card holder', 'cobalt-bank-operations-payment-gateway' ), 'error' );
			$valid = false;
		}

		if ( ! cobalt_bank_operations_is_valid_cvv( $card_cvv ) ) {
			wc_add_notice( __( 'Invalid card code (CVV)', 'cobalt-bank-operations-payment-gateway' ), 'error' );
			$valid = false;
		}

		return $valid;
	}

	/**
	 * Process refund.
	 *
	 * @param int       $order_id order ID.
	 * @param float|int $amount   amount to refund.
	 * @param string    $reason   reason to refund.
	 * @return bool|\WP_Error True if ok, \WP_Error on fail.
	 */
	public function process_refund( $order_id, $amount = 0, $reason = '' ) {
		if ( ! isset( $_REQUEST['security'] ) || ! check_ajax_referer( 'order-item', 'security', false ) ) {
			Cobalt_Bank_Operations_Log::debug( 'Refund rechazado: nonce inválido o ausente' );
			return new WP_Error( 'invalid_nonce', __( 'Unauthorized action.', 'cobalt-bank-operations-payment-gateway' ) );
		}

		$cobalt_bank_operations_client = new Cobalt_Bank_Operations_Client(
			$this->api_url,
			$this->api_client_id,
			$this->api_client_secret
		);

		if ( ! $order_id || ! $amount ) {
			return new WP_Error( 'invalid_order', 'Invalid order ID or amount' );
		}
		Cobalt_Bank_Operations_Log::debug( "process_refund: order_id={$order_id}, amount={$amount}, reason={$reason}" );
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error( 'invalid_order', 'Invalid order ID' );
		}
		$txn = $order->get_meta( 'cobalt_bank_operations_transaction_id' );
		if ( ! $txn ) {
			return new WP_Error( 'no_transaction_id', 'No transaction ID found for this order' );
		}

		try {
			$data = $cobalt_bank_operations_client->refund( $txn, intval( $amount * 100 ) );
		} catch ( Cobalt_Bank_Operations_Exception $e ) {
			Cobalt_Bank_Operations_Log::debug( 'Error processing refund: ' . $e->getMessage() );
			return new WP_Error( 'cobalt_bank_operations_refund_error', $e->getMessage() );
		}

		$order->add_order_note(
			sprintf(
				'Reembolso de %s realizado vía CBO (refund_id %s). Motivo: %s',
				wc_price( $amount ),
				$data['identifier'] ?? $data['id'] ?? '',
				$reason
			)
		);

		return true;
	}

	/**
	 * Processes the payment and returns result + redirect URL.
	 *
	 * @param int $order_id WooCommerce order ID.
	 * @return array Result data with 'result' and 'redirect' keys.
	 */
	public function process_payment( $order_id ) {

		// check if the nonce is set and valid.
		if ( isset( $_POST[ $this->id . '_nonce' ] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $this->id . '_nonce' ] ) ), $this->id . '_process_payment' ) ) {
				wc_add_notice( __( 'Security check failed. Please try again.', 'cobalt-bank-operations-payment-gateway' ), 'error' );
				return;
			}
		}
		// we need it to get any order details.
		Cobalt_Bank_Operations_Log::debug( 'process_payment: ' . $order_id );
		$order = wc_get_order( $order_id );

		$cobalt_bank_operations_client = new Cobalt_Bank_Operations_Client( $this->api_url, $this->api_client_id, $this->api_client_secret );
		try {
			// detect if the request is from a block-based checkout or classic checkout.
			$raw_input = file_get_contents( 'php://input' );
			$body      = json_decode( $raw_input, true );
			if ( ! is_array( $body ) ) {
				$body = array();
			}

			foreach ( $body as $key => $value ) {
				if ( is_string( $value ) ) {
					$body[ $key ] = sanitize_text_field( $value );
				} elseif ( is_array( $value ) ) {
					foreach ( $value as $subkey => $subvalue ) {
						if ( is_string( $subvalue ) ) {
							$body[ $key ][ $subkey ] = sanitize_text_field( $subvalue );
						}
					}
				}
			}
			$cobalt_bank_operations_is_block = ! empty( $body['payment_data'] );

			// if the request is from a block-based checkout, we need to handle it differently.
			if ( $cobalt_bank_operations_is_block ) {
				Cobalt_Bank_Operations_Log::debug( 'Origin: Checkout Based Blocks' );
				$pdata    = $body['payment_data'];
				$billing  = $body['billing_address'] ?? array();
				$shipping = $body['shipping_address'] ?? array();

				$data = array();
				foreach ( $pdata as $index => $field ) {
					if ( isset( $field['key'], $field['value'] ) ) {
						$data[ $field['key'] ] = wc_clean( $field['value'] );
					}
				}
				Cobalt_Bank_Operations_Log::debug( ' data: ' . wp_json_encode( $data ) );

				// card details.
				$card_number = $data['card_number'] ?? '';
				$card_expiry = $data['card_expiry'] ?? '';
				$card_cvc    = $data['card_cvc'] ?? '';
				$card_holder = $data['card_holder'] ?? '';

				$three_ds_params = array(
					'transType'                => 'goods',
					'deviceChannel'            => 'browser',
					'browserJavaEnabled'       => $data['browserJavaEnabled'] ?? null,
					'browserJavascriptEnabled' => $data['browserJavascriptEnabled'] ?? null,
					'browserLanguage'          => $data['browserLanguage'] ?? null,
					'browserColorDepth'        => $data['browserColorDepth'] ?? null,
					'browserScreenWidth'       => $data['browserScreenWidth'] ?? null,
					'browserScreenHeight'      => $data['browserScreenHeight'] ?? null,
					'browserTZ'                => $data['browserTZ'] ?? null,
					'browserUserAgent'         => $data['browserUserAgent'] ?? null,
					'challengeWindowSize'      => $data['challengeWindowSize'] ?? null,

					'browserIP'                => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
					'email'                    => $billing['email'] ?? $order->get_billing_email(),
					'billAddrCountry'          => $this->get_iso_alpha3_cc( $billing['country'] ?? $order->get_billing_country() ),
					'billAddrCity'             => $billing['city'] ?? $order->get_billing_city(),
					'billAddrState'            => cobalt_bank_operations_parse_state( $billing['state'] ?? $order->get_billing_state() ),
					'billAddrLine1'            => $billing['address_1'] ?? $order->get_billing_address_1(),
					'billAddrLine2'            => 'none',
					'billAddrPostCode'         => $billing['postcode'] ?? $order->get_billing_postcode(),
					'shipAddrCountry'          => $this->get_iso_alpha3_cc( $shipping['country'] ?? $order->get_shipping_country() ),
					'shipAddrCity'             => $shipping['city'] ?? $order->get_shipping_city(),
					'shipAddrState'            => cobalt_bank_operations_parse_state( $shipping['state'] ?? $order->get_shipping_state() ),
					'shipAddrLine1'            => $shipping['address_1'] ?? $order->get_shipping_address_1(),
					'shipAddrLine2'            => 'none',
					'shipAddrPostCode'         => $shipping['postcode'] ?? $order->get_shipping_postcode(),
				);

			} else {
				Cobalt_Bank_Operations_Log::debug( 'Origin: Classic Checkout' );
				$card_number = sanitize_text_field( wp_unslash( $_POST[ $this->id . '-card-number' ] ?? '' ) );
				$card_expiry = sanitize_text_field( wp_unslash( $_POST[ $this->id . '-card-expiry' ] ?? '' ) );
				$card_cvc    = sanitize_text_field( wp_unslash( $_POST[ $this->id . '-card-cvc' ] ?? '' ) );
				$card_holder = sanitize_text_field( wp_unslash( $_POST[ $this->id . '-card-holder' ] ?? '' ) );

				$card_number = str_replace( ' ', '', $card_number );
				$card_expiry = str_replace( ' ', '', $card_expiry );

				$three_ds_params = $this->get3DSParams();
			}
			Cobalt_Bank_Operations_Log::debug( 'three_ds_params=' . wp_json_encode( $three_ds_params ) );

			$transaction = $cobalt_bank_operations_client->sale( $order, $card_number, $card_expiry, $card_cvc, $card_holder, $three_ds_params );
			Cobalt_Bank_Operations_Log::debug( 'Checkout data: ' . wp_json_encode( $transaction ) );

			if ( 'authenticating' === ( $transaction['status'] ?? '' ) ) {
				return array(
					'result'             => 'success',
					'requires_challenge' => true,
					'challenge_url'      => $transaction['metadatas']['3ds_authentication_form'],
					'redirect'           => '',
					'callback_url'       => $callback,
				);
			} elseif ( $this->validate_payment( $transaction ) ) {
				return array(
					'result'          => 'success',
					'redirect'        => $order->get_checkout_order_received_url(),
					'additional_data' => array(),
				);

			} elseif ( 'refused' === ( $transaction['status'] ?? '' ) ) {
				wc_add_notice( __( 'We were unable to complete the payment. Please contact with commerce.', 'cobalt-bank-operations-payment-gateway' ), 'error' );
			} else {
				wc_add_notice( __( 'We were unable to complete the payment. Please check your card details or contact your bank.', 'cobalt-bank-operations-payment-gateway' ), 'error' );

			}
		} catch ( \Cobalt_Bank_Operations_Exception $e ) {
			if ( ! $e->isSuccessResponse() ) {
				Cobalt_Bank_Operations_Log::debug( $e->getMessage() . ' - ' . wp_json_encode( $e->getResponse() ) );
				wc_add_notice( __( 'Cannot generate the payment. Please, contact with commerce.', 'cobalt-bank-operations-payment-gateway' ), 'error' );
			} else {
				wc_add_notice( __( 'Cannot process the payment. Please, contact with commerce.', 'cobalt-bank-operations-payment-gateway' ), 'error' );
			}
		}
	}

	/**
	 * Get 3DS Params for payments
	 *
	 * @return array
	 */
	private function get3DSParams() {
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-process_checkout' ) ) {
			$three_ds_params['email'] = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';
		}

		$three_ds_attrs = array(
			'browserJavaEnabled',
			'browserJavascriptEnabled',
			'browserLanguage',
			'browserColorDepth',
			'browserScreenWidth',
			'browserScreenHeight',
			'browserTZ',
			'browserUserAgent',
			'challengeWindowSize',
		);

		$three_ds_params = array();

		foreach ( $three_ds_attrs as $attr ) {
			$three_ds_params[ $attr ] = isset( $_POST[ $attr ] ) ? sanitize_text_field( wp_unslash( $_POST[ $attr ] ) ) : '';
		}

		// Order additional data.
		$three_ds_params['transType']     = 'goods';
		$three_ds_params['deviceChannel'] = 'browser';
		$three_ds_params['browserIP']     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$three_ds_params['email']         = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';

		$billing_country_raw                = isset( $_POST['billing_country'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_country'] ) ) : '';
		$billing_iso3                       = ( '' !== $billing_country_raw ) ? $this->get_iso_alpha3_cc( $billing_country_raw ) : '';
		$three_ds_params['billAddrCountry'] = ( '' !== $billing_iso3 ) ? $billing_iso3 : 'DIG';

		$billing_city                    = isset( $_POST['billing_city'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_city'] ) ) : '';
		$three_ds_params['billAddrCity'] = ( '' !== $billing_city ) ? $billing_city : 'digital';

		$billing_state                    = isset( $_POST['billing_state'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_state'] ) ) : '';
		$three_ds_params['billAddrState'] = ( '' !== $billing_state ) ? cobalt_bank_operations_parse_state( $billing_state ) : 'DIG';

		$billing_line1                    = isset( $_POST['billing_address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_address_1'] ) ) : '';
		$three_ds_params['billAddrLine1'] = ( '' !== $billing_line1 ) ? $billing_line1 : 'digital';

		$billing_line2                    = isset( $_POST['billing_address_2'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_address_2'] ) ) : '';
		$three_ds_params['billAddrLine2'] = ( '' !== $billing_line2 ) ? $billing_line2 : 'none';

		$billing_post                        = isset( $_POST['billing_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_postcode'] ) ) : '';
		$three_ds_params['billAddrPostCode'] = ( '' !== $billing_post ) ? $billing_post : '0000';

		$billing_city  = $three_ds_params['billAddrCity'];
		$billing_line1 = $three_ds_params['billAddrLine1'];
		$billing_post  = $three_ds_params['billAddrPostCode'];

		$shipping_country_raw               = isset( $_POST['shipping_country'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_country'] ) ) : '';
		$shipping_iso3                      = ( '' !== $shipping_country_raw ) ? $this->get_iso_alpha3_cc( $shipping_country_raw ) : '';
		$three_ds_params['shipAddrCountry'] = ( '' !== $shipping_iso3 ) ? $shipping_iso3 : 'DIG';

		$shipping_city = isset( $_POST['shipping_city'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_city'] ) ) : '';
		if ( '' === $shipping_city ) {
			$shipping_city = ( '' !== $billing_city ) ? $billing_city : 'digital';
		}
		$three_ds_params['shipAddrCity'] = $shipping_city;

		$shipping_state                   = isset( $_POST['shipping_state'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_state'] ) ) : '';
		$three_ds_params['shipAddrState'] = ( '' !== $shipping_state ) ? cobalt_bank_operations_parse_state( $shipping_state ) : 'DIG';

		$shipping_line1 = isset( $_POST['shipping_address_1'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_address_1'] ) ) : '';
		if ( '' === $shipping_line1 ) {
			$shipping_line1 = ( '' !== $billing_line1 ) ? $billing_line1 : 'digital';
		}
		$three_ds_params['shipAddrLine1'] = $shipping_line1;

		$shipping_line2                   = isset( $_POST['shipping_address_2'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_address_2'] ) ) : '';
		$three_ds_params['shipAddrLine2'] = ( '' !== $shipping_line2 ) ? $shipping_line2 : 'none';

		$shipping_post = isset( $_POST['shipping_postcode'] ) ? sanitize_text_field( wp_unslash( $_POST['shipping_postcode'] ) ) : '';
		if ( '' === $shipping_post ) {
			$shipping_post = ( '' !== $billing_post ) ? $billing_post : '0000';
		}
		$three_ds_params['shipAddrPostCode'] = $shipping_post;

		return $three_ds_params;
	}

	/**
	 * Validate Payment
	 *
	 * @param array $transaction for all data.
	 * @return false return 'false'.
	 */
	private function validate_payment( $transaction ) {

		$metas    = $transaction['metadatas'];
		$order_id = $metas['order_id'];
		$order    = wc_get_order( $order_id );

		$status         = $transaction['status'];
		$success_status = array( 'authorized', 'notified' );
		$order->add_meta_data( 'cobalt_bank_operations_bank_code', $transaction['response_code'] );
		$order->add_meta_data( 'cobalt_bank_operations_transaction_id', $transaction['identifier'] );
		$order->add_meta_data( 'cobalt_bank_operations_bank_authorization', $transaction['authorization_number'] );

		if ( in_array( $status, $success_status, true ) ) {
			$order->update_status( 'completed', __( 'Payment completed', 'cobalt-bank-operations-payment-gateway' ) );
			$order->payment_complete( $transaction['identifier'] );
			if ( function_exists( 'WC' ) && WC()->cart ) {
				WC()->cart->empty_cart();
			}
			return true;
		} else {
			$order->update_status( 'failed', __( 'Failed payment', 'cobalt-bank-operations-payment-gateway' ) );
		}

		return false;
	}

	/**
	 * Callback function for show payment status
	 */
	public function callback_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id = isset( $_GET['oid'] ) ? absint( $_GET['oid'] ) : 0;
		$order    = wc_get_order( $order_id );

		$target = ( $order && $order->is_paid() )
			? $order->get_checkout_order_received_url()
			: $order->get_checkout_payment_url();

		$success = (bool) ( $order && $order->is_paid() );

		Cobalt_Bank_Operations_Log::debug( "callback_url: order_id=$order_id, target=$target" );

		wp_register_script( 'cobalt-bank-operations-3ds-handler', '', array(), '1.0', true );
		wp_enqueue_script( 'cobalt-bank-operations-3ds-handler' );

		$script_data = sprintf(
			'var cbo3dsData = { target: %s, success: %s };',
			wp_json_encode( $target ),
			$success ? 'true' : 'false'
		);
		wp_add_inline_script( 'cobalt-bank-operations-3ds-handler', $script_data, 'before' );

		$main_script = '
			document.addEventListener("DOMContentLoaded", function() {
				if (window.opener && !window.opener.closed) {
					window.opener.postMessage({
						cbo3ds: cbo3dsData.success ? "success" : "fail",
						redirect_to: cbo3dsData.target,
						source: "cobalt_bank_operations_3ds_handler"
					}, "' . esc_url( home_url( '/' ) ) . '");
					setTimeout(function() { window.close(); }, 300);
				} else {
					window.location.href = cbo3dsData.target;
				}
			});
		';
		wp_add_inline_script( 'cobalt-bank-operations-3ds-handler', $main_script );

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<title><?php esc_html_e( 'Processing 3DS…', 'cobalt-bank-operations-payment-gateway' ); ?></title>
			<?php wp_head(); ?>
		</head>
		<body>
			<div class="cobalt-bank-operations-3ds-loading">
				<div class="cobalt-bank-operations-3ds-spinner"></div>
				<noscript>
					<p><?php esc_html_e( 'Please enable JavaScript to complete your payment. You will be automatically redirected...', 'cobalt-bank-operations-payment-gateway' ); ?></p>
					<meta http-equiv="refresh" content="3;url=<?php echo esc_url( $target ); ?>">
				</noscript>
			</div>
			<?php wp_footer(); ?>
		</body>
		</html>
		<?php
		exit;
	}

	/**
	 * In case you need a webhook, like PayPal IPN etc.
	 */
	public function webhook() {
		$raw_input = file_get_contents( 'php://input' );
		$data      = json_decode( $raw_input, true );

		if ( ! is_array( $data ) ) {
			Cobalt_Bank_Operations_Log::debug( 'Webhook error: input no es array válido' );
			status_header( 400 );
			exit;
		}

		foreach ( $data as $key => $value ) {
			if ( is_string( $value ) ) {
				$data[ $key ] = sanitize_text_field( $value );
			}
		}

		Cobalt_Bank_Operations_Log::debug( 'Webhook recibido: ' . wp_json_encode( $data ) );

		try {
			$valid_transaction = $this->validate_payment( $data );
			status_header( 204 );
		} catch ( \Cobalt_Bank_Operations_Exception $e ) {
			$tid = isset( $data['tid'] ) ? $data['tid'] : 'N/A';
			Cobalt_Bank_Operations_Log::debug( "Error en webhook. TID: $tid - " . $e->getMessage() );
			status_header( 400 );
		}
		exit;
	}

	/**
	 * Get ISO alpha-3 country code for 3DS.
	 *
	 * @param string $country ISO 3166-1 alpha-2 code (e.g. 'US').
	 * @return string ISO 3166-1 alpha-3 code (e.g. 'USA'). Returns the input if not mapped.
	 */
	public function get_iso_alpha3_cc( $country ) {
		return Cobalt_Bank_Operations_Constants::COUNTRIES[ $country ] ?? $country;
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

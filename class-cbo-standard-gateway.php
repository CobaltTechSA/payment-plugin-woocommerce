<?php

if ( ! defined( 'ABSPATH' ) ) exit;
include_once 'cbo-constants.php';
include_once 'cbo-helpers.php';
include_once 'class-cbo-payment-gateway-cc.php';

class CBOPAGA_Standard_Gateway extends WC_Payment_Gateway {

	protected static $instance;

	/**
	 * Class constructor, more about it in Step 3
	 */
	public function __construct() {

		$this->id = CBOPAGA_Constants::STANDARD_GATEWAY_ID; // payment gateway plugin ID
		$this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
		$this->has_fields = true; // in case you need a custom credit card form
		$this->method_title = 'CBO Standard Gateway';
		$this->method_description = __("Acceptance of payments with Visa / Mastercard", "cbo-payment-gateway"); // will be displayed on the options page

		// gateways can support subscriptions, refunds, saved payment methods,
		// but in this tutorial we begin with simple payments
		$this->supports = array(
			'products',
			'refunds'
		);

		// Method with all the options fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();
		$this->title = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->enabled = $this->get_option( 'enabled' );
		$this->testmode = 'yes' === $this->get_option( 'testmode' );
		$this->api_url = $this->testmode ? $this->get_option( 'test_api_url' ) : $this->get_option( 'api_url' );
		$this->api_client_id = $this->testmode ? $this->get_option( 'test_api_client_id' ) : $this->get_option( 'api_client_id' );
		$this->api_client_secret = $this->testmode ? $this->get_option( 'test_api_client_secret' ) : $this->get_option( 'api_client_secret' );

		// This action hook saves the settings
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		// We need custom JavaScript to obtain a token
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

		// You can also register a webhook here
		add_action( "woocommerce_api_" . $this->id, array( $this, 'webhook' ) );

		//URL OK y KO
		add_action( "woocommerce_api_" . $this->id . '_status', array( $this, 'callback_url' ) );

        //JS Scripts
		add_action('wp_enqueue_scripts', [ $this, 'register_plugin_scripts' ], 20);

		// Add nonce field for security
		add_action('woocommerce_admin_field_cbopaga_nonce', function () {
    	wp_nonce_field('cbopaga_standard_save_settings', 'cbopaga_standard_nonce');
		});

	}

	public function process_admin_options() {
		if ( ! isset( $_POST['cbopaga_standard_nonce'] ) || 
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cbopaga_standard_nonce'] ) ), 'cbopaga_standard_save_settings' ) ) {
			wp_die(esc_html__( 'Unauthorized action.', 'cbo-payment-gateway' ), esc_html__( 'Security Error', 'cbo-payment-gateway' ), 403);
		}
		parent::process_admin_options();
	}


	public function get_icon() {
		$path = plugin_dir_url( __FILE__ );
		$icons = array(
			sprintf(
				'<img class="%s" src="%s" alt="%s" />',
				esc_attr( 'cbo-gateway-icon' ),
				esc_url( WC_HTTPS::force_https_url( $path . 'assets/images/visa.svg' ) ),
				esc_attr__( 'Visa', 'cbo-payment-gateway' )
			),
			sprintf(
				'<img class="%s" src="%s" alt="%s" />',
				esc_attr( 'cbo-gateway-icon' ),
				esc_url( WC_HTTPS::force_https_url( $path . 'assets/images/mastercard.svg' ) ),
				esc_attr__( 'Mastercard', 'cbo-payment-gateway' )
			),
		);

		$payIcons = '<div style="vertical-align: middle; display: inline-block; margin-left: 22px">';
		foreach ($icons as $icon) {
			$payIcons .= $icon;
		}

		$payIcons .= '</div>';

		return apply_filters ('woocommerce_gateway_icon', $payIcons, $this->id);
	}
	/**
	 * Plugin options, we deal with it in Step 3 too
	 */
	public function init_form_fields(){

		$this->form_fields = array(
			'enabled' => array(
				'title'       => __('Enable/Disable', 'cbo-payment-gateway'),
				'label'       => __('Enable CBO Payment Gateway', 'cbo-payment-gateway'),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no'
			),
			'title' => array(
				'title'       => __('Title', 'cbo-payment-gateway'),
				'type'        => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'cbo-payment-gateway'),
				'default'     => 'VISA, Mastercard',
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __('Description', 'cbo-payment-gateway'),
				'type'        => 'textarea',
				'description' => __('This controls the description which the user sees during checkout.', 'cbo-payment-gateway'),
				'default'     => __('Pay with your VISA or Mastercard card', 'cbo-payment-gateway'),
			),
			'testmode' => array(
				'title'       => __('Test mode', 'cbo-payment-gateway'),
				'label'       => __('Enable Test Mode', 'cbo-payment-gateway'),
				'type'        => 'checkbox',
				'description' => __('Place the payment gateway in test mode using test API keys.', 'cbo-payment-gateway'),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_api_url' => array(
				'title'       => __('Test API URL', 'cbo-payment-gateway'),
				'type'        => 'text'
			),

            'test_api_client_id' => array(
                'title'       => __('Test API Client Id', 'cbo-payment-gateway'),
                'type'        => 'text',
            ),
            'test_api_client_secret' => array(
                'title'       => __('Test API Client Secret', 'cbo-payment-gateway'),
                'type'        => 'password',
            ),
			'api_url' => array(
				'title'       => __('Production API URL', 'cbo-payment-gateway'),
				'type'        => 'text'
			),

            'api_client_id' => array(
                'title'       => __('Production API Client Id', 'cbo-payment-gateway'),
                'type'        => 'text',
            ),
            'api_client_secret' => array(
                'title'       => __('Production API Client Secret', 'cbo-payment-gateway'),
                'type'        => 'password',
            )
		);
	}

	public function admin_options() {
		?>
		<h2><?php echo esc_html( $this->get_method_title() ); ?></h2>
		<p><?php echo esc_html( $this->get_method_description() ); ?></p>
		<table class="form-table">
			<?php
			// Nonce field for security
			wp_nonce_field( 'cbopaga_standard_save_settings', 'cbopaga_standard_nonce' );

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
		$ver  = CBOPAGA_Constants::PLUGIN_VERSION;

		wp_register_script(
			'cbopaga-sweetalert',
			plugins_url( 'assets/js/sweetAlert/sweetalert.min.js', __FILE__ ),
			[],
			'2.1.2', 
			true
		);

		wp_enqueue_script('cbo-sweetalert');

		// Script for the standard payment method
		wp_enqueue_script(
			'cbopaga-standard-payment',
			$base . 'cbo-payment-script.js',
			[ 'jquery' ],
			$ver,
			true
		);

		// Script for the 3DS popup + classic checkout
		wp_enqueue_script(
			'cbopaga-3ds-popup',
			$base . 'cbo-3ds-popup.js',
			[ 'jquery', 'wc-checkout', 'cbopaga-sweetalert' ],
			$ver,
			true
		);

		// This will be used to handle the 3DS challenge response
		$callback = esc_url_raw( home_url( "/wc-api/{$this->id}_status" ) );

		wp_localize_script(
			'cbopaga-3ds-popup',
			'CBOPAGA3DS',
			[
				'url_ok' => $callback,
				'url_ko' => $callback,
			]
		);
	}

    /**
     * @param $args
     * @param $fields
     * @return void
     */
    public function credit_card_form($args = array(), $fields = array())
    {
        $cc_form           = new CBOPAGA_Payment_Gateway_CC();
        $cc_form->id       = $this->id;
        $cc_form->supports = $this->supports;
        $cc_form->form();
    }

    /**
	 * You will need it if you want your custom credit card form, Step 4 is about it
	 */
	public function payment_fields() {

		// ok, let's display some description before the payment form
		if ( $this->description ) {
			// you can instructions for test mode, I mean test card numbers etc.
			if ( $this->testmode ) {
				$this->description .= ' ' . __('TEST MODE ENABLED', 'cbo-payment-gateway') . '.';
				$this->description  = trim( $this->description );
			}
			// display the description with <p> tags etc.
			echo wp_kses_post( wpautop( $this->description ) );
		}
		// nonce field for security
		wp_nonce_field($this->id . '_process_payment', $this->id . '_nonce');

        $this->credit_card_form();
	}

	/*
	 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
	 */
	public function payment_scripts() {

	}

	/*
	  * Fields validation, more in Step 5
	 */
	public function validate_fields() {

		// check if the nonce is set and valid
		if ( isset( $_POST[$this->id . '_nonce'] ) && 
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[$this->id . '_nonce'] ) ), $this->id . '_process_payment' ) ) {
			wc_add_notice( __( 'Security check failed. Please try again.', 'cbo-payment-gateway' ), 'error' );
			return false;
		}

		// detect if the request is from a block-based checkout or classic checkout
		$raw_input = file_get_contents('php://input');
		$body      = json_decode($raw_input, true) ?: [];

		foreach ($body as $key => $value) {
			if (is_string($value)) {
				$body[$key] = sanitize_text_field($value);
			} elseif (is_array($value)) {
				foreach ($value as $subkey => $subvalue) {
					if (is_string($subvalue)) {
						$body[$key][$subkey] = sanitize_text_field($subvalue);
					}
				}
			}
		}
		// if the request is from a block-based checkout, omit the validation
		if (! empty($body['payment_data'])) {
			return true;
		}

		//CBOPAGA_Log::debug( 'POST Data: ' . print_r( $_POST, true ) );
		$cardNumber = isset($_POST[$this->id . '-card-number']) ? sanitize_text_field(wp_unslash($_POST[$this->id . '-card-number'])) : '';
		$cardExpiry = isset($_POST[$this->id . '-card-expiry']) ? sanitize_text_field(wp_unslash($_POST[$this->id . '-card-expiry'])) : '';
		$cardCvv    = isset($_POST[$this->id . '-card-cvc']) ? sanitize_text_field(wp_unslash($_POST[$this->id . '-card-cvc'])) : '';
		$cardHolder = isset($_POST[$this->id . '-card-holder']) ? sanitize_text_field(wp_unslash($_POST[$this->id . '-card-holder'])) : '';

        //CBOPAGA_Log::debug("cardNumber=$cardNumber, cardExpiry=$cardExpiry, cardCvv=$cardCvv, cardHolder=$cardHolder");
        $valid = true;

        $cardNumber = str_replace(" ", "", $cardNumber);
        if (!cbopaga_is_valid_luhn($cardNumber)) {
            wc_add_notice( __('Invalid card number', 'cbo-payment-gateway'), 'error' );
            $valid = false;
        }

        $cardExpiry = str_replace(" ", "", $cardExpiry);
        if (!cbopaga_is_valid_expiry_date($cardExpiry)) {
            wc_add_notice( __('Invalid expiry date', 'cbo-payment-gateway'), 'error' );
            $valid = false;
        }

        if (!cbopaga_is_valid_card_holder($cardHolder)) {
            wc_add_notice( __('Invalid card holder', 'cbo-payment-gateway'), 'error' );
            $valid = false;
        }

        if (!cbopaga_is_valid_cvv($cardCvv)) {
            wc_add_notice( __('Invalid card code (CVV)', 'cbo-payment-gateway'), 'error' );
            $valid = false;
        }


		return $valid;

	}

	public function process_refund($order_id, $amount = 0, $reason = '')
	{
		if ( ! isset( $_REQUEST['security'] ) || ! check_ajax_referer( 'order-item', 'security', false ) ) {
			CBOPAGA_Log::debug("Refund rechazado: nonce inválido o ausente");
			return new WP_Error('invalid_nonce', __('Unauthorized action.', 'cbo-payment-gateway'));
		}

		//CBOPAGA_Log::debug("api_client_id={$this->api_client_id}, api_client_secret={$this->api_client_secret}");
		$cboClient = new CBOPAGA_Client(
			$this->api_url,
			$this->api_client_id,
			$this->api_client_secret
		);

		if (! $order_id || ! $amount) {
			return new WP_Error('invalid_order', 'Invalid order ID or amount');
		}
		CBOPAGA_Log::debug("process_refund: order_id={$order_id}, amount={$amount}, reason={$reason}");
		$order = wc_get_order($order_id);
		
		if (! $order) {
			return new WP_Error('invalid_order', 'Invalid order ID');
		}
		$txn = $order->get_meta('cbopaga_transaction_id');
		if (! $txn) {
			return new WP_Error('no_transaction_id', 'No transaction ID found for this order');
		}

		try {
			$data = $cboClient->refund($txn, intval($amount * 100));
		} catch (CBOPAGA_Exception $e) {
			CBOPAGA_Log::debug("Error processing refund: " . $e->getMessage());
			return new WP_Error('cbopaga_refund_error', $e->getMessage());
		}

		$order->add_order_note(sprintf(
			'Reembolso de %s realizado vía CBO (refund_id %s). Motivo: %s',
			wc_price($amount),
			$data['identifier'] ?? $data['id'] ?? '',
			$reason
		));

		return true;
	}

	/*
	 * We're processing the payments here, everything about it is in Step 5
	 */
	public function process_payment( $order_id ) {

		// check if the nonce is set and valid
		if ( isset( $_POST[$this->id . '_nonce'] ) ) {
			if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[$this->id . '_nonce'] ) ), $this->id . '_process_payment' ) ) {
				wc_add_notice( __( 'Security check failed. Please try again.', 'cbo-payment-gateway' ), 'error' );
				return;
			}
		}
		// we need it to get any order details
		CBOPAGA_Log::debug("process_payment: " . $order_id);
		$order = wc_get_order($order_id);
		 
        //CBOPAGA_Log::debug(" api_client_id=$this->api_client_id, api_client_secret=$this->api_client_secret");
		$cboClient = new CBOPAGA_Client($this->api_url, $this->api_client_id, $this->api_client_secret);
		try {
            // detect if the request is from a block-based checkout or classic checkout
			$raw_input = file_get_contents('php://input');
			$body = json_decode($raw_input, true) ?: [];

			foreach ($body as $key => $value) {
				if (is_string($value)) {
					$body[$key] = sanitize_text_field($value);
				} elseif (is_array($value)) {
					foreach ($value as $subkey => $subvalue) {
						if (is_string($subvalue)) {
							$body[$key][$subkey] = sanitize_text_field($subvalue);
						}
					}
				}
			}
			$cbopaga_is_block = ! empty($body['payment_data']);

			// if the request is from a block-based checkout, we need to handle it differently
			if ($cbopaga_is_block) {
				CBOPAGA_Log::debug('Origin: Checkout Based Blocks');
				$pdata    = $body['payment_data'];
				$billing  = $body['billing_address']  ?? [];
				$shipping = $body['shipping_address'] ?? [];

				$data = [];
				foreach ($pdata as $index => $field) {
					if (isset($field['key'], $field['value'])) {
						$data[$field['key']] = wc_clean($field['value']);
					}
				}

				// card details
				$cardNumber = $data['cardNumber']  ?? '';
				$cardExpiry = $data['cardExpiry']  ?? '';
				$cardCvc    = $data['cardCvc']     ?? '';
				$cardHolder = $data['cardHolder']  ?? '';

				$threeDSParams = [
					'transType'                 => 'goods',
					'deviceChannel'             => 'browser',
					'browserJavaEnabled'        => $data['browserJavaEnabled']       ?? null,
					'browserJavascriptEnabled'  => $data['browserJavascriptEnabled'] ?? null,
					'browserLanguage'           => $data['browserLanguage']          ?? null,
					'browserColorDepth'         => $data['browserColorDepth']        ?? null,
					'browserScreenWidth'        => $data['browserScreenWidth']       ?? null,
					'browserScreenHeight'       => $data['browserScreenHeight']      ?? null,
					'browserTZ'                 => $data['browserTZ']                ?? null,
					'browserUserAgent'          => $data['browserUserAgent']         ?? null,
					'challengeWindowSize'       => $data['challengeWindowSize']      ?? null,

					'browserIP'                 => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
					'email'                     => $billing['email']                ?? $order->get_billing_email(),
					'billAddrCountry'           => $this->get_iso_alpha3_cc($billing['country'] ?? $order->get_billing_country()),
					'billAddrCity'              => $billing['city']                 ?? $order->get_billing_city(),
					'billAddrState'             => parse_state($billing['state']  ?? $order->get_billing_state()),
					'billAddrLine1'             => $billing['address_1']            ?? $order->get_billing_address_1(),
					'billAddrLine2'             => "none",
					'billAddrPostCode'          => $billing['postcode']             ?? $order->get_billing_postcode(),
					'shipAddrCountry'           => $this->get_iso_alpha3_cc($shipping['country'] ?? $order->get_shipping_country()),
					'shipAddrCity'              => $shipping['city']                ?? $order->get_shipping_city(),
					'shipAddrState'             => parse_state($shipping['state'] ?? $order->get_shipping_state()),
					'shipAddrLine1'             => $shipping['address_1']           ?? $order->get_shipping_address_1(),
					'shipAddrLine2'             => "none",
					'shipAddrPostCode'          => $shipping['postcode']            ?? $order->get_shipping_postcode(),
				];

			} else {
				CBOPAGA_Log::debug('Origin: Classic Checkout');
				$cardNumber = sanitize_text_field(wp_unslash($_POST[$this->id . '-card-number'] ?? ''));
				$cardExpiry = sanitize_text_field(wp_unslash($_POST[$this->id . '-card-expiry'] ?? ''));
				$cardCvc    = sanitize_text_field(wp_unslash($_POST[$this->id . '-card-cvc'] ?? ''));
				$cardHolder = sanitize_text_field(wp_unslash($_POST[$this->id . '-card-holder'] ?? ''));


				$cardNumber = str_replace(" ", "", $cardNumber);
				$cardExpiry = str_replace(" ", "", $cardExpiry);

				$threeDSParams = $this->get3DSParams();
			}
            CBOPAGA_Log::debug("threeDSParams=" . json_encode($threeDSParams));

			$transaction = $cboClient->sale($order, $cardNumber, $cardExpiry, $cardCvc, $cardHolder, $threeDSParams);
			CBOPAGA_Log::debug("Checkout data: " . json_encode($transaction));

         if ($transaction['status'] === 'authenticating') {
			return [
					'result'            => 'success',
					'requires_challenge'=> true,
					'challenge_url'     => $transaction['metadatas']['3ds_authentication_form'],
					'redirect'          => '',
					'callback_url'      => $callback,
				];
			} else if ($this->validate_payment($transaction)) {
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_order_received_url(),
					'additional_data' => [],
                );

            } else if ($transaction['status'] === 'refused') {
                wc_add_notice(  __('We were unable to complete the payment. Please contact with commerce.', 'cbo-payment-gateway'), 'error' );
            } else {
                wc_add_notice(  __('We were unable to complete the payment. Please check your card details or contact your bank.', 'cbo-payment-gateway'), 'error' );

            }
		} catch (\CBOPAGA_Exception $e) {
			if (!$e->isSuccessResponse()) {
				CBOPAGA_Log::debug($e->getMessage() . " - " . json_encode($e->getResponse()));
				wc_add_notice(  __('Cannot generate the payment. Please, contact with commerce.', 'cbo-payment-gateway'), 'error' );
			} else {
				wc_add_notice(  __('Cannot process the payment. Please, contact with commerce.', 'cbo-payment-gateway'), 'error' );
			}
		}
	}

    /**
     * @return array
     */
    private function get3DSParams()
    {
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-process_checkout' ) ) {
			$threeDSParams['email'] = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';
		}

        $threeDSAttrs = ['browserJavaEnabled', 'browserJavascriptEnabled', 'browserLanguage', 'browserColorDepth',
            'browserScreenWidth', 'browserScreenHeight', 'browserTZ', 'browserUserAgent', 'challengeWindowSize'];

        $threeDSParams = [];

        foreach ($threeDSAttrs as $attr) {
           $threeDSParams[$attr] = isset($_POST[$attr]) ? sanitize_text_field(wp_unslash($_POST[$attr])) : '';
        }

        //Order additional data
		$threeDSParams['transType']     = 'goods';
		$threeDSParams['deviceChannel'] = 'browser';
		$threeDSParams['browserIP']     = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    	$threeDSParams['email']         = isset($_POST['billing_email']) ? sanitize_email(wp_unslash($_POST['billing_email'])) : '';

		$threeDSParams['billAddrCountry']  = $this->get_iso_alpha3_cc(sanitize_text_field(wp_unslash($_POST['billing_country'] ?? ''))) ?: 'DIG';
		$threeDSParams['billAddrCity']     = sanitize_text_field(wp_unslash($_POST['billing_city'] ?? '')) ?: 'digital';
		$threeDSParams['billAddrState']    = parse_state(sanitize_text_field(wp_unslash($_POST['billing_state'] ?? ''))) ?: 'DIG';
		$threeDSParams['billAddrLine1']    = sanitize_text_field(wp_unslash($_POST['billing_address_1'] ?? '')) ?: 'digital';
		$threeDSParams['billAddrLine2']    = 'none';
		$threeDSParams['billAddrPostCode'] = sanitize_text_field(wp_unslash($_POST['billing_postcode'] ?? '')) ?: '0000';

		$billingCity  = $threeDSParams['billAddrCity'];
		$billingLine1 = $threeDSParams['billAddrLine1'];
		$billingPost  = $threeDSParams['billAddrPostCode'];

		$threeDSParams['shipAddrCountry']  = $this->get_iso_alpha3_cc(sanitize_text_field(wp_unslash($_POST['shipping_country'] ?? ''))) ?: 'DIG';
		$threeDSParams['shipAddrCity']     = sanitize_text_field(wp_unslash($_POST['shipping_city'] ?? '')) ?: $billingCity ?: 'digital';
		$threeDSParams['shipAddrState']    = parse_state(sanitize_text_field(wp_unslash($_POST['shipping_state'] ?? ''))) ?: 'DIG';
		$threeDSParams['shipAddrLine1']    = sanitize_text_field(wp_unslash($_POST['shipping_address_1'] ?? '')) ?: $billingLine1 ?: 'digital';
		$threeDSParams['shipAddrLine2']    = 'none';
		$threeDSParams['shipAddrPostCode'] = sanitize_text_field(wp_unslash($_POST['shipping_postcode'] ?? '')) ?: $billingPost ?: '0000';

        return $threeDSParams;
    }

    private function validate_payment($transaction)
    {

        $metas = $transaction['metadatas'];
        $order_id = $metas['order_id'];
        $order = wc_get_order( $order_id );

        $status = $transaction['status'];
        $successStatus = ['authorized', 'notified'];
        $order->add_meta_data('cbopaga_bank_code', $transaction['response_code']);
        $order->add_meta_data('cbopaga_transaction_id', $transaction['identifier']);
        $order->add_meta_data('cbopaga_bank_authorization', $transaction['authorization_number']);

        if (in_array($status, $successStatus)) {
            $order->update_status('completed', __( 'Payment completed', 'cbo-payment-gateway' ));
            $order->payment_complete($transaction['identifier']);
			if ( function_exists( 'WC' ) && WC()->cart ) {
				WC()->cart->empty_cart();
			}
            return true;
        } else {
            $order->update_status('failed', __( 'Failed payment', 'cbo-payment-gateway' ));
        }

        return false;
    }

	public function callback_url() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id = isset($_GET['oid']) ? absint($_GET['oid']) : 0;
		$order    = wc_get_order($order_id);

		$target = ($order && $order->is_paid())
			? $order->get_checkout_order_received_url()
			: $order->get_checkout_payment_url();
		CBOPAGA_Log::debug("callback_url: order_id=$order_id, target=$target");
		?>
		<!DOCTYPE html>
		<html lang="es">
		<head><meta charset="utf-8"><title>Procesando 3DS…</title></head>
		<body>
			<script>
		(function(){
			var target = <?php echo wp_json_encode($target); ?>;
			var success = <?php echo $order && $order->is_paid() ? 'true' : 'false'; ?>;

			if (window.opener && !window.opener.closed) {
			window.opener.postMessage({
				cbo3ds: success ? 'success' : 'fail',
				redirect_to: target
			}, '*');
			window.close();
			} else {
			window.location.href = target;
			}
		})();
		</script>
		</body>
		</html>
		<?php
		exit;
	}



	/*
	 * In case you need a webhook, like PayPal IPN etc
	 */
	public function webhook() {
		$raw_input = file_get_contents('php://input');
		$data = json_decode($raw_input, true);

		if (!is_array($data)) {
			CBOPAGA_Log::debug("Webhook error: input no es array válido");
			header('HTTP/1.1 400 Bad Request');
			exit;
		}

		foreach ($data as $key => $value) {
			if (is_string($value)) {
				$data[$key] = sanitize_text_field($value);
			}
		}

		CBOPAGA_Log::debug("Webhook recibido: " . json_encode($data));

		try {
			$validTransaction = $this->validate_payment($data);
			header('HTTP/1.1 204 OK');
		} catch (\CBOPAGA_Exception $e) {
			$tid = isset($data['tid']) ? $data['tid'] : 'N/A';
			CBOPAGA_Log::debug("Error en webhook. TID: $tid - " . $e->getMessage());
			header('HTTP/1.1 400 Bad Request');
		}
		exit;
	}

    function get_iso_alpha3_cc($country) {
        return isset(CBOPAGA_Constants::COUNTRIES[$country]) ? CBOPAGA_Constants::COUNTRIES[$country] : $country;
    }

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

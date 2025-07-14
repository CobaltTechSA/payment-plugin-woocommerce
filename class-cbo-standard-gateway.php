<?php

include_once 'cbo-constants.php';
include_once 'cbo-helpers.php';
include_once 'class-cbo-payment-gateway-cc.php';

class WC_CBO_Standard_Gateway extends WC_Payment_Gateway {

	protected static $instance;

	/**
	 * Class constructor, more about it in Step 3
	 */
	public function __construct() {

		$this->id = CBOConstants::STANDARD_GATEWAY_ID; // payment gateway plugin ID
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
            ),
		);

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
		$ver  = CBOConstants::PLUGIN_VERSION;

		wp_enqueue_script(
			'sweetalert',
			'https://unpkg.com/sweetalert/dist/sweetalert.min.js',
			[],
			'2.1.2',
			true
		);

		// Script for the standard payment method
		wp_enqueue_script(
			'cbo-standard-payment',
			$base . 'cbo-payment-script.js',
			[ 'jquery' ],
			$ver,
			true
		);

		// Script for the 3DS popup + classic checkout
		wp_enqueue_script(
			'cbo-3ds-popup',
			$base . 'cbo-3ds-popup.js',
			[ 'jquery', 'wc-checkout', 'sweetalert' ],
			$ver,
			true
		);

		// This will be used to handle the 3DS challenge response
		$callback = esc_url_raw( home_url( "/wc-api/{$this->id}_status" ) );

		wp_localize_script(
			'cbo-3ds-popup',
			'CBO3DS',
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
        $cc_form           = new CBO_Payment_Gateway_CC();
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

		// detect if the request is from a block-based checkout or classic checkout
		$raw_input = file_get_contents('php://input');
		$body      = json_decode($raw_input, true) ?: [];

		// if the request is from a block-based checkout, omit the validation
		if (! empty($body['payment_data'])) {
			return true;
		}

		//CBOLog::debug( 'POST Data: ' . print_r( $_POST, true ) );
        $cardNumber = $_POST[$this->id . '-card-number'];
        $cardExpiry = $_POST[$this->id . '-card-expiry'];
        $cardCvv = $_POST[$this->id . '-card-cvc'];
        $cardHolder = $_POST[$this->id . '-card-holder'];

        //CBOLog::debug("cardNumber=$cardNumber, cardExpiry=$cardExpiry, cardCvv=$cardCvv, cardHolder=$cardHolder");
        $valid = true;

        $cardNumber = str_replace(" ", "", $cardNumber);
        if (!is_valid_luhn($cardNumber)) {
            wc_add_notice( __('Invalid card number', 'cbo-payment-gateway'), 'error' );
            $valid = false;
        }

        $cardExpiry = str_replace(" ", "", $cardExpiry);
        if (!is_valid_expiry_date($cardExpiry)) {
            wc_add_notice( __('Invalid expiry date', 'cbo-payment-gateway'), 'error' );
            $valid = false;
        }

        if (!is_valid_card_holder($cardHolder)) {
            wc_add_notice( __('Invalid card holder', 'cbo-payment-gateway'), 'error' );
            $valid = false;
        }

        if (!is_valid_cvv($cardCvv)) {
            wc_add_notice( __('Invalid card code (CVV)', 'cbo-payment-gateway'), 'error' );
            $valid = false;
        }


		return $valid;

	}

	public function process_refund($order_id, $amount = 0, $reason = '')
	{
		CBOLog::debug("api_client_id={$this->api_client_id}, api_client_secret={$this->api_client_secret}");
		$cboClient = new CBOClient(
			$this->api_url,
			$this->api_client_id,
			$this->api_client_secret
		);

		if (! $order_id || ! $amount) {
			return new WP_Error('invalid_order', 'Invalid order ID or amount');
		}
		CBOLog::debug("process_refund: order_id={$order_id}, amount={$amount}, reason={$reason}");
		$order = wc_get_order($order_id);
		
		if (! $order) {
			return new WP_Error('invalid_order', 'Invalid order ID');
		}
		$txn = $order->get_meta('cbo_transaction_id');
		if (! $txn) {
			return new WP_Error('no_transaction_id', 'No transaction ID found for this order');
		}

		try {
			$data = $cboClient->refund($txn, intval($amount * 100));
		} catch (CBOException $e) {
			CBOLog::debug("Error processing refund: " . $e->getMessage());
			return new WP_Error('cbo_refund_error', $e->getMessage());
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

		// we need it to get any order details
		CBOLog::debug("process_payment: " . $order_id);
		$order = wc_get_order($order_id);
		 
        CBOLog::debug(" api_client_id=$this->api_client_id, api_client_secret=$this->api_client_secret");
		$cboClient = new CBOClient($this->api_url, $this->api_client_id, $this->api_client_secret);
		try {
            // detect if the request is from a block-based checkout or classic checkout
			$raw_input = file_get_contents('php://input');
			$body = json_decode($raw_input, true) ?: [];
			$is_block = ! empty($body['payment_data']);

			// if the request is from a block-based checkout, we need to handle it differently
			if ($is_block) {
				CBOLog::debug('Origin: Checkout Based Blocks');
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

					'browserIP'                 => $_SERVER['REMOTE_ADDR'],
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
				CBOLog::debug('Origin: Classic Checkout');
				$cardNumber = $_POST[$this->id . '-card-number'];
				$cardExpiry = $_POST[$this->id . '-card-expiry'];
				$cardCvc = $_POST[$this->id . '-card-cvc'];
				$cardHolder = $_POST[$this->id . '-card-holder'];

				$cardNumber = str_replace(" ", "", $cardNumber);
				$cardExpiry = str_replace(" ", "", $cardExpiry);

				$threeDSParams = $this->get3DSParams();
			}
            CBOLog::debug("threeDSParams=" . json_encode($threeDSParams));

			$transaction = $cboClient->sale($order, $cardNumber, $cardExpiry, $cardCvc, $cardHolder, $threeDSParams);
			CBOLog::debug("Checkout data: " . json_encode($transaction));

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
		} catch (\CBOException $e) {
			if (!$e->isSuccessResponse()) {
				CBOLog::debug($e->getMessage() . " - " . json_encode($e->getResponse()));
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
        $threeDSAttrs = ['browserJavaEnabled', 'browserJavascriptEnabled', 'browserLanguage', 'browserColorDepth',
            'browserScreenWidth', 'browserScreenHeight', 'browserTZ', 'browserUserAgent', 'challengeWindowSize'];

        $threeDSParams = [];

        foreach ($threeDSAttrs as $attr) {
            $threeDSParams[$attr] = $_POST[$attr];
        }

        //Order additional data
		$threeDSParams['transType']     = 'goods';
		$threeDSParams['deviceChannel'] = 'browser';
		$threeDSParams['browserIP']     = $_SERVER['REMOTE_ADDR'];
		$threeDSParams['email']         = $_POST['billing_email'] ?? '';

		$threeDSParams['billAddrCountry']  = $this->get_iso_alpha3_cc( $_POST['billing_country'] ?? '' ) ?: 'DIG';
		$threeDSParams['billAddrCity']     = trim( $_POST['billing_city'] ?? '' )        ?: 'digital';
		$threeDSParams['billAddrState']    = parse_state( $_POST['billing_state'] ?? '' ) ?: 'DIG';
		$threeDSParams['billAddrLine1']    = trim( $_POST['billing_address_1'] ?? '' )   ?: 'digital';
		$threeDSParams['billAddrLine2']    = 'none';
		$threeDSParams['billAddrPostCode'] = trim( $_POST['billing_postcode'] ?? '' )   ?: '0000';

		$billingCity  = $threeDSParams['billAddrCity'];
		$billingLine1 = $threeDSParams['billAddrLine1'];
		$billingPost  = $threeDSParams['billAddrPostCode'];

		$threeDSParams['shipAddrCountry']  = $this->get_iso_alpha3_cc( $_POST['shipping_country'] ?? '' ) ?: 'DIG';
		$threeDSParams['shipAddrCity']     = trim( $_POST['shipping_city'] ?? '' )          ?: $billingCity  ?: 'digital';
		$threeDSParams['shipAddrState']    = parse_state( $_POST['shipping_state'] ?? '' ) ?: 'DIG';
		$threeDSParams['shipAddrLine1']    = trim( $_POST['shipping_address_1'] ?? '' )     ?: $billingLine1 ?: 'digital';
		$threeDSParams['shipAddrLine2']    = 'none';
		$threeDSParams['shipAddrPostCode'] = trim( $_POST['shipping_postcode'] ?? '' )     ?: $billingPost  ?: '0000';


        return $threeDSParams;
    }

    private function validate_payment($transaction)
    {
        global $woocommerce;

        $metas = $transaction['metadatas'];
        $order_id = $metas['order_id'];
        $order = wc_get_order( $order_id );

        $status = $transaction['status'];
        $successStatus = ['authorized', 'notified'];
        $order->add_meta_data('cbo_bank_code', $transaction['response_code']);
        $order->add_meta_data('cbo_transaction_id', $transaction['identifier']);
        $order->add_meta_data('cbo_bank_authorization', $transaction['authorization_number']);

        if (in_array($status, $successStatus)) {
            $order->update_status('completed', __( 'Payment completed', 'cbo-payment-gateway' ));
            $order->payment_complete($transaction['identifier']);
            $woocommerce->cart->empty_cart();
            return true;
        } else {
            $order->update_status('failed', __( 'Failed payment', 'cbo-payment-gateway' ));
        }

        return false;
    }

	public function callback_url() {
		$order_id = absint($_GET['oid']);
		$order    = wc_get_order($order_id);

		$target = ($order && $order->is_paid())
		? $order->get_checkout_order_received_url()
		: $order->get_checkout_payment_url();


		?>
		<!DOCTYPE html>
		<html lang="es">
		<head><meta charset="utf-8"><title>Procesando 3DS…</title></head>
		<body>
		<script>
			(function(){
			var target = <?php echo wp_json_encode($target); ?>;
			// if the opener is still open, we can notify it
			// and redirect it to the target URL
			if (window.opener && !window.opener.closed) {
				window.opener.postMessage({ cbo3ds: 'success' }, '*' );
				window.opener.location.href = target;
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

		$data = json_decode(file_get_contents('php://input'), true);
		CBOLog::debug("Webhook: Tx #" . $data['identifier'] /*. ": " . json_encode($data)*/);

		try {
			//$transaction = $client->transaction($data['tid']);
			$validTransaction = $this->validate_payment($data);

			header( 'HTTP/1.1 204 OK' );

		} catch (\CBOException $e) {
			CBOLog::debug("Error getting transaction " . $data['tid'] . ' - ' .$e->getMessage());
			header( 'HTTP/1.1 400 Bad Request' );

		}
	}

    function get_iso_alpha3_cc($country) {
        return isset(CBOConstants::COUNTRIES[$country]) ? CBOConstants::COUNTRIES[$country] : $country;
    }

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

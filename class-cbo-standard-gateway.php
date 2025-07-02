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
			'products'
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
		$this->api_key = $this->testmode ? $this->get_option( 'test_api_key' ) : $this->get_option( 'api_key' );
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
        add_action( 'wp_enqueue_scripts', array($this, 'register_plugin_scripts'));
	}

	public function get_icon() {
		$path = plugin_dir_url( __FILE__ );
		$icons = array(
			'<img src="' . WC_HTTPS::force_https_url( $path. 'assets/images/visa.svg' ) . '" alt="Visa" />',
			'<img src="' . WC_HTTPS::force_https_url( $path . 'assets/images/mastercard.svg' ) . '" alt="Mastercard" />',
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
			'test_api_key' => array(
				'title'       => __('Test API Key', 'cbo-payment-gateway'),
				'type'        => 'password',
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
			'api_key' => array(
				'title'       => __('Production API Key', 'cbo-payment-gateway'),
				'type'        => 'password'
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

    public function register_plugin_scripts()
    {
        CBOLog::debug("plugin path: " . plugin_dir_url(__FILE__));
        wp_enqueue_script( 'cbo-standard-payment', plugin_dir_url(__FILE__) . 'assets/js/cbo-payment-script.js', array('jquery'), null, true);
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
			echo wpautop( wp_kses_post( $this->description ) );
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

	/*
	 * We're processing the payments here, everything about it is in Step 5
	 */
	public function process_payment( $order_id ) {

		// we need it to get any order details
		CBOLog::debug("process_payment: " . $order_id);
		$order = wc_get_order($order_id);
		 
        CBOLog::debug("api_key=$this->api_key, api_client_id=$this->api_client_id, api_client_secret=$this->api_client_secret");
		$cboClient = new CBOClient($this->api_url, $this->api_key, $this->api_client_id, $this->api_client_secret);
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
				$cardCvv = $_POST[$this->id . '-card-cvc'];
				$cardHolder = $_POST[$this->id . '-card-holder'];

				$cardNumber = str_replace(" ", "", $cardNumber);
				$cardExpiry = str_replace(" ", "", $cardExpiry);

				$threeDSParams = $this->get3DSParams();
			}
            CBOLog::debug("threeDSParams=" . json_encode($threeDSParams));

			$transaction = $cboClient->sale($order, $cardNumber, $cardExpiry, $cardCvv, $cardHolder, $threeDSParams);
			CBOLog::debug("Checkout data: " . json_encode($transaction));

            if ($transaction['status'] === 'authenticating') {
                return array(
                    'result' => 'success',
                    'redirect' => $transaction['metadatas']['3ds_authentication_form'],
					'additional_data' => [],
                );
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
        $threeDSParams['transType'] = 'goods';
		$threeDSParams['deviceChannel'] = 'browser';
		$threeDSParams['browserIP'] = $_SERVER['REMOTE_ADDR'];
		$threeDSParams['email'] = $_POST['billing_email'];
		$threeDSParams['billAddrCountry'] = $this->get_iso_alpha3_cc($_POST['billing_country']);
		$threeDSParams['billAddrCity'] = $_POST['billing_city'];
		$threeDSParams['billAddrState'] = parse_state($_POST['billing_state']);
		$threeDSParams['billAddrLine1'] = $_POST['billing_address_1'];
		$threeDSParams['billAddrLine2'] = "none";
		$threeDSParams['billAddrPostCode'] = $_POST['billing_postcode'];

		$threeDSParams['shipAddrCountry'] = $this->get_iso_alpha3_cc($_POST['shipping_country']) ?? "dig";
		$threeDSParams['shipAddrCity'] = $_POST['shipping_city'] ?? "digital";
		$threeDSParams['shipAddrState'] = parse_state($_POST['shipping_state']) ?? "dig";
		$threeDSParams['shipAddrLine1'] = $_POST['shipping_address_1'] ?? "digital";
		$threeDSParams['shipAddrLine2'] = "none";
		$threeDSParams['shipAddrPostCode'] = $_POST['shipping_postcode'] ?? "digital";

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
		$order_id = $_GET['oid'];

		CBOLog::debug("callback_url: " . $order_id);

		$order = wc_get_order( $order_id );
		if ($order->is_paid()) {
			header("Location: " . $order->get_checkout_order_received_url());
			return;
		}

		header("Location: " . $order->get_checkout_payment_url());

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

<?php

include_once 'cbo-constants.php';
class WC_CBO_Telered_Gateway extends WC_Payment_Gateway {

	protected static $instance;

	/**
	 * Class constructor, more about it in Step 3
	 */
	public function __construct() {

		$this->id = CBOConstants::TELERED_GATEWAY_ID; // payment gateway plugin ID
		$this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
		$this->has_fields = false; // in case you need a custom credit card form
		$this->method_title = 'CBO Clave Gateway';
		$this->method_description = __("Acceptance of payments with Clave", "cbo-payment-gateway"); // will be displayed on the options page

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


	}

	public function get_icon() {
		$path = plugin_dir_url( __FILE__ );
		$icons = array(
			'<img style="height: 40px; max-height: 3em; float: left !important; margin: 5px" src="' . WC_HTTPS::force_https_url( $path . 'assets/images/clave.svg' ) . '" alt="Telered" />',
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

		/*// I will echo() the form, but you can close PHP tags and print it directly in HTML
		echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">';

		// Add this action hook if you want your custom payment gateway to support it
		do_action( 'woocommerce_credit_card_form_start', $this->id );

		// I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
		echo '<div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
		<input id="misha_ccNo" type="text" autocomplete="off">
		</div>
		<div class="form-row form-row-first">
			<label>Expiry Date <span class="required">*</span></label>
			<input id="misha_expdate" type="text" autocomplete="off" placeholder="MM / YY">
		</div>
		<div class="form-row form-row-last">
			<label>Card Code (CVC) <span class="required">*</span></label>
			<input id="misha_cvv" type="password" autocomplete="off" placeholder="CVC">
		</div>
		<div class="clear"></div>';

		do_action( 'woocommerce_credit_card_form_end', $this->id );

		echo '<div class="clear"></div></fieldset>';*/

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
		return true;

	}

	/*
	 * We're processing the payments here, everything about it is in Step 5
	 */
	public function process_payment( $order_id ) {

		// we need it to get any order detailes
		$order = wc_get_order( $order_id );

        CBOLog::debug("api_key=$this->api_key, api_client_id=$this->api_client_id, api_client_secret=$this->api_client_secret");
        $cboClient = new CBOClient($this->api_url, $this->api_key, $this->api_client_id, $this->api_client_secret);
		try {
			$checkout = $cboClient->checkout($order, CBOConstants::PAYMENT_TYPE_TELERED);
			CBOLog::debug("Checkout data: " . json_encode($checkout));

			// Mark as on-hold (we're awaiting the cheque)
			//$order->update_status('on-hold', __( 'Awaiting cheque payment', 'woocommerce' ));

			return array(
				'result' => 'success',
				'redirect' => $this->api_url . '/checkout/' . $checkout['slug']
			);
		} catch (\CBOException $e) {
			if (!$e->isSuccessResponse()) {
				CBOLog::debug($e->getMessage() . " - " . json_encode($e->getResponse()));
				wc_add_notice(  'No se ha podido generar el pago. Por favor contacte con el comercio.', 'error' );
			} else {
				wc_add_notice(  'No se ha podido procesar el pago. Por favor contacte con el comercio.', 'error' );
			}
		}
	}

	public function callback_url() {
		$order_id = $_GET['oid'];

		CBOLog::debug("callback_url: " . $order_id);

		$order = wc_get_order( $order_id );
		
		$start = time();
		while (! $order->is_paid() && (time() - $start) < 30) {
			sleep(1);
			$order = wc_get_order($order_id);
		}

		if ($order->is_paid()) {
			CBOLog::debug("callback_url: PAGADO, redirigiendo a order-received");
			wp_safe_redirect($order->get_checkout_order_received_url());
			exit;
		}

		wp_safe_redirect($order->get_checkout_payment_url());
		exit;

	}
	/*
	 * In case you need a webhook, like PayPal IPN etc
	 */
	public function webhook() {

		global $woocommerce;

		$data = json_decode(file_get_contents('php://input'), true);
		CBOLog::debug("Webhook: Tx #" . $data['identifier'] . ": " . json_encode($data));

		//$client = new CBOClient($this->api_url, $this->api_key);

		try {
			//$transaction = $client->transaction($data['tid']);
			$transaction = $data;


			$metas = $transaction['metadatas'];
			$order_id = $metas['order_id'];
			$order = wc_get_order( $order_id );

			$status = $transaction['status'];
			$successStatus = ['authorized', 'notified'];
			$order->add_meta_data('cbo_bank_code', $transaction['response_code']);
			$order->add_meta_data('cbo_transaction_id', $transaction['identifier']);
			$order->add_meta_data('cbo_bank_authorization', $transaction['authorization_number']);

			if (in_array($status, $successStatus)) {
				$order->update_status('completed', __( 'Pago completado', 'woocommerce' ));
				$order->payment_complete($transaction['identifier']);
				$woocommerce->cart->empty_cart();
			} else {
				$order->update_status('failed', __( 'Pago fallido', 'woocommerce' ));
			}

			header( 'HTTP/1.1 204 OK' );

		} catch (\CBOException $e) {
			CBOLog::debug("Error getting transaction " . $data['tid'] . ' - ' .$e->getMessage());
			header( 'HTTP/1.1 400 Bad Request' );

		}

		return;

	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

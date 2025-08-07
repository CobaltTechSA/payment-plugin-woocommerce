<?php
if ( ! defined( 'ABSPATH' ) ) exit;
include_once 'cbo-constants.php';
class CBOPAGA_Telered_Gateway extends WC_Payment_Gateway {

	protected static $instance;

	/**
	 * Class constructor, more about it in Step 3
	 */
	public function __construct() {

		$this->id = CBOPAGA_Constants::TELERED_GATEWAY_ID; // payment gateway plugin ID
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

		// Add nonce field for security
		add_action('woocommerce_admin_field_cbopaga_nonce', function () {
    	wp_nonce_field('cbopaga_telered_save_settings', 'cbopaga_telered_nonce');
		});

	}

	public function process_admin_options() {
		if ( ! isset( $_POST['cbopaga_telered_nonce'] ) || 
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cbopaga_telered_nonce'] ) ), 'cbopaga_telered_save_settings' ) ) {
			wp_die(esc_html__( 'Unauthorized action.', 'cbo-payment-gateway' ), esc_html__( 'Security Error', 'cbo-payment-gateway' ), 403);
		}
		parent::process_admin_options();
	}

	public function get_icon() {
		$path = plugin_dir_url( __FILE__ );
		$icons = array(   sprintf(
        '<img class="%s" src="%s" alt="%s" />',
        esc_attr( 'cbo-gateway-icon' ),
        esc_url( WC_HTTPS::force_https_url( $path . 'assets/images/clave.svg' ) ),
        esc_attr__( 'Telered', 'cbo-payment-gateway' )
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

	public function admin_options() {
		?>
		<h2><?php echo esc_html( $this->get_method_title() ); ?></h2>
		<p><?php echo esc_html( $this->get_method_description() ); ?></p>
		<table class="form-table">
			<?php
			// Nonce field for security
			wp_nonce_field( 'cbopaga_telered_save_settings', 'cbopaga_telered_nonce' );

			$this->generate_settings_html();
			?>
		</table>
		<?php
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

        CBOPAGA_Log::debug("api_client_id=$this->api_client_id, api_client_secret=$this->api_client_secret");
        $cboClient = new CBOPAGA_Client($this->api_url, $this->api_client_id, $this->api_client_secret);
		try {
			$checkout = $cboClient->checkout($order, CBOPAGA_Constants::PAYMENT_TYPE_TELERED);
			CBOPAGA_Log::debug("Checkout data: " . json_encode($checkout));

			// Mark as on-hold (we're awaiting the cheque)
			//$order->update_status('on-hold', __( 'Awaiting cheque payment', 'woocommerce' ));

			return array(
				'result' => 'success',
				'redirect' => $this->api_url . '/checkout/' . $checkout['slug']
			);
		} catch (\CBOPAGA_Exception $e) {
			if (!$e->isSuccessResponse()) {
				CBOPAGA_Log::debug($e->getMessage() . " - " . json_encode($e->getResponse()));
				wc_add_notice(  'No se ha podido generar el pago. Por favor contacte con el comercio.', 'error' );
			} else {
				wc_add_notice(  'No se ha podido procesar el pago. Por favor contacte con el comercio.', 'error' );
			}
		}
	}

	public function callback_url() {
		// Returning user from payment gateway, read-only param. Nonce not applicable.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id = isset($_GET['oid']) ? absint($_GET['oid']) : 0;

		// CBOPAGA_Log::debug("callback_url: " . $order_id);

		$order = wc_get_order( $order_id );
		
		$start = time();
		while (! $order->is_paid() && (time() - $start) < 30) {
			sleep(1);
			$order = wc_get_order($order_id);
		}

		if ($order->is_paid()) {
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

		$data_raw = json_decode( file_get_contents( 'php://input' ), true );
		if ( ! is_array( $data_raw ) ) {
			CBOPAGA_Log::debug( 'Webhook error: payload no es JSON' );
			header( 'HTTP/1.1 400 Bad Request' );
			exit;
		}

		$data = $this->cbopaga_recursive_sanitize( $data_raw );
		CBOPAGA_Log::debug( 'Webhook: Tx # ' . ( $data['identifier'] ?? 'N/A' ) . ' - ' . json_encode( $data ) );

		//$client = new CBOPAGA_Client($this->api_url);

		try {
        $transaction = $data;
        $metas = $transaction['metadatas'] ?? [];
        $order_id = $metas['order_id'] ?? null;
        $order = $order_id ? wc_get_order($order_id) : null;

        $status = $transaction['status'] ?? '';
        $successStatus = ['authorized', 'notified'];

        if ($order) {
            $order->add_meta_data('cbopaga_bank_code', $transaction['response_code'] ?? '');
            $order->add_meta_data('cbopaga_transaction_id', $transaction['identifier'] ?? '');
            $order->add_meta_data('cbopaga_bank_authorization', $transaction['authorization_number'] ?? '');

            if (in_array($status, $successStatus)) {
                $order->update_status('completed', __('Pago completado', 'cbo-payment-gateway'));
                $order->payment_complete($transaction['identifier'] ?? '');
                if ( function_exists( 'WC' ) && WC()->cart ) {
					WC()->cart->empty_cart();
				}
            } else {
                $order->update_status('failed', __('Pago fallido', 'cbo-payment-gateway'));
            }
        }

        header('HTTP/1.1 204 OK');

    } catch (\CBOPAGA_Exception $e) {
        CBOPAGA_Log::debug("Error getting transaction " . ($data['tid'] ?? '') . ' - ' . $e->getMessage());
        header('HTTP/1.1 400 Bad Request');
    }

		return;

	}

	private function cbopaga_recursive_sanitize( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = $this->cbopaga_recursive_sanitize( $v );
			}
			return $value;
		}
		if ( is_string( $value ) ) {
			return sanitize_text_field( $value );
		}
		return $value; 
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

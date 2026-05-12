<?php

/**
 * Standard class for Neopayment plugin.
 *
 * @package NEOPAYMENT
 */

if (! defined('ABSPATH')) {
	exit;
}
require_once 'class-neopayment-constants.php';
require_once 'neopayment-helpers.php';
require_once 'class-neopayment-cc.php';

/**
 * Handles WooCommerce Standard integration for the payment gateway.
 */
class NEOPAYMENT_Standard_Gateway extends WC_Payment_Gateway
{

	/**
	 * Instance for the NEOPAYMENT Standard gateway.
	 *
	 * @var string
	 */
	protected static $instance;

	/**
	 * Class constructor, more about it in Step 3.
	 */
	public function __construct()
	{

		$this->id                 = NEOPAYMENT_Constants::NEOPAYMENT_STANDARD_GATEWAY_ID; // payment gateway plugin ID.
		$this->icon               = ''; // URL of the icon that will be displayed on checkout page near your gateway name.
		$this->has_fields         = true; // in case you need a custom credit card form.
		$this->method_title       = 'Neopayment Standard Gateway';
		$this->method_description = __('Acceptance of payments with Visa / Mastercard', 'neopayment'); // will be displayed on the options page.

		// gateways can support subscriptions, refunds, saved payment methods, but in this tutorial we begin with simple payments.
		$this->supports = array(
			'products',
			'refunds',
		);

		// Method with all the options fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();
		$this->title             = $this->get_option('title');
		$this->description       = $this->get_option('description');
		$this->enabled           = $this->get_option('enabled');
		$this->testmode          = 'yes' === $this->get_option('testmode');
		$this->api_url           = $this->testmode ? $this->get_option('test_api_url') : $this->get_option('api_url');
		$this->api_client_id     = $this->testmode ? $this->get_option('test_api_client_id') : $this->get_option('api_client_id');
		$this->api_client_secret = $this->testmode ? $this->get_option('test_api_client_secret') : $this->get_option('api_client_secret');

		// This action hook saves the settings.
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));

		// We need custom JavaScript to obtain a token.
		add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));

		// You can also register a webhook here.
		add_action('woocommerce_api_' . $this->id, array($this, 'neopayment_webhook'));

		// URL OK y KO.
		add_action('woocommerce_api_' . $this->id . '_status', array($this, 'neopayment_callback_url'));

		// JS Scripts.
		add_action('wp_enqueue_scripts', array($this, 'register_plugin_scripts'), 20);

		// Add nonce field for security.
		add_action(
			'woocommerce_admin_field_neopayment_nonce',
			function () {
				wp_nonce_field('neopayment_standard_save_settings', 'neopayment_standard_nonce');
			}
		);
	}

	/**
	 * Process Admin Validate.
	 */
	public function process_admin_options()
	{
		if (! current_user_can('manage_woocommerce')) {
			wp_die(esc_html__('Insufficient permissions.', 'neopayment'), esc_html__('Security Error', 'neopayment'), 403);
		}

		if (
			! isset($_POST['neopayment_standard_nonce']) ||
			! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['neopayment_standard_nonce'])), 'neopayment_standard_save_settings')
		) {
			wp_die(esc_html__('Unauthorized action.', 'neopayment'), esc_html__('Security Error', 'neopayment'), 403);
		}
		parent::process_admin_options();

		NEOPAYMENT_Client::clear_cached_oauth_tokens();
	}


	/**
	 * Get Icon Payment Option.
	 */
	public function get_icon()
	{
		$path  = plugin_dir_url(__FILE__);
		$icons = array(
			sprintf(
				'<img class="%s" src="%s" alt="%s" />',
				esc_attr('neopayment-icon'),
				esc_url(WC_HTTPS::force_https_url($path . 'assets/images/visa.svg')),
				esc_attr__('Visa', 'neopayment')
			),
			sprintf(
				'<img class="%s" src="%s" alt="%s" />',
				esc_attr('neopayment-icon'),
				esc_url(WC_HTTPS::force_https_url($path . 'assets/images/mastercard.svg')),
				esc_attr__('Mastercard', 'neopayment')
			),
		);

		$pay_icons = '<div style="vertical-align: middle; display: inline-block; margin-left: 22px">';
		foreach ($icons as $icon) {
			$pay_icons .= $icon;
		}

		$pay_icons .= '</div>';

		// WooCommerce core filter name; cannot be prefixed.
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		return apply_filters('woocommerce_gateway_icon', $pay_icons, $this->id);
	}
	/**
	 * Plugin options, we deal with it in Step 3 too
	 */
	public function init_form_fields()
	{

		$this->form_fields = array(
			'enabled'                => array(
				'title'       => __('Enable/Disable', 'neopayment'),
				'label'       => __('Enable Neopayment', 'neopayment'),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			),
			'title'                  => array(
				'title'       => __('Title', 'neopayment'),
				'type'        => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'neopayment'),
				'default'     => 'VISA, Mastercard',
				'desc_tip'    => true,
			),
			'description'            => array(
				'title'       => __('Description', 'neopayment'),
				'type'        => 'textarea',
				'description' => __('This controls the description which the user sees during checkout.', 'neopayment'),
				'default'     => __('Pay with your VISA or Mastercard card', 'neopayment'),
			),
			'testmode'               => array(
				'title'       => __('Test mode', 'neopayment'),
				'label'       => __('Enable Test Mode', 'neopayment'),
				'type'        => 'checkbox',
				'description' => __('Place the payment gateway in test mode using test API keys.', 'neopayment'),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'test_api_url'           => array(
				'title' => __('Test API URL', 'neopayment'),
				'type'  => 'text',
			),

			'test_api_client_id'     => array(
				'title' => __('Test API Client Id', 'neopayment'),
				'type'  => 'text',
			),
			'test_api_client_secret' => array(
				'title' => __('Test API Client Secret', 'neopayment'),
				'type'  => 'password',
			),
			'api_url'                => array(
				'title' => __('Production API URL', 'neopayment'),
				'type'  => 'text',
			),

			'api_client_id'          => array(
				'title' => __('Production API Client Id', 'neopayment'),
				'type'  => 'text',
			),
			'api_client_secret'      => array(
				'title' => __('Production API Client Secret', 'neopayment'),
				'type'  => 'password',
			),
		);
	}

	/**
	 * Admin options
	 */
	public function admin_options()
	{
?>
		<h2><?php echo esc_html($this->get_method_title()); ?></h2>
		<p><?php echo esc_html($this->get_method_description()); ?></p>
		<table class="form-table">
			<?php
			// Nonce field for security.
			wp_nonce_field('neopayment_standard_save_settings', 'neopayment_standard_nonce');

			$this->generate_settings_html();
			?>
		</table>
	<?php
	}


	/**
	 * Enqueues front-end scripts on checkout and pay-for-order screens.
	 *
	 * Loads:
	 * - `neopayment-script.js` — browser fingerprint hidden fields for classic / order-pay flows.
	 * - `neopayment-3ds-popup.js` — 3DS challenge modal and Store API interception (depends on jQuery + SweetAlert).
	 *
	 * Scripts are skipped on non-checkout pages to avoid unnecessary HTTP weight.
	 * The `order-pay` endpoint is included because WooCommerce may not treat it as `is_checkout()` in all setups,
	 * yet the customer still needs the same assets when paying from a direct link.
	 *
	 * @hook wp_enqueue_scripts
	 *
	 * @return void
	 */
	public function register_plugin_scripts()
	{
		$is_order_pay = function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'order-pay' );
		if ( ! is_checkout() && ! $is_order_pay ) {
			return;
		}

		$base = plugin_dir_url(__FILE__) . 'assets/js/';
		$ver  = NEOPAYMENT_Constants::NEOPAYMENT_PLUGIN_VERSION;
		$standard_script_path = plugin_dir_path(__FILE__) . 'assets/js/neopayment-script.js';
		$standard_ver = file_exists($standard_script_path) ? (string) filemtime($standard_script_path) : $ver;
		$popup_script_path = plugin_dir_path(__FILE__) . 'assets/js/neopayment-3ds-popup.js';
		$popup_ver = file_exists($popup_script_path) ? (string) filemtime($popup_script_path) : $ver;

		wp_register_script(
			'neopayment-sweetalert',
			plugins_url('assets/js/sweetAlert/sweetalert.min.js', __FILE__),
			array(),
			'2.1.2',
			true
		);

		wp_enqueue_script('neopayment-sweetalert');

		// Script for the standard payment method.
		wp_enqueue_script(
			'neopayment-standard-payment',
			$base . 'neopayment-script.js',
			array('jquery'),
			$standard_ver,
			true
		);

		// Script for the 3DS popup + classic checkout.
		wp_enqueue_script(
			'neopayment-3ds-popup',
			$base . 'neopayment-3ds-popup.js',
			array( 'jquery', 'neopayment-sweetalert' ),
			$popup_ver,
			true
		);

		// This will be used to handle the 3DS challenge response.
		$callback = esc_url_raw(home_url("/wc-api/{$this->id}_status"));

		$localized = array(
			'url_ok' => $callback,
			'url_ko' => $callback,
		);

		// Pay-for-order uses a full document redirect (not `wc-ajax=checkout`), so the JSON challenge is lost.
		// When `process_payment` stored a pending URL and redirected with `neopayment_open_3ds=1`, expose it here.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only GET flags; access is gated by `hash_equals()` against the order key below (same trust model as core order-pay URLs).
		$open_3ds_flag   = isset( $_GET['neopayment_open_3ds'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['neopayment_open_3ds'] ) ) : '';
		$order_key_param = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['key'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( $is_order_pay && '1' === $open_3ds_flag && '' !== $order_key_param ) {
			$pay_order_id = absint( get_query_var( 'order-pay' ) );
			$pay_order    = $pay_order_id ? wc_get_order( $pay_order_id ) : false;
			$key          = $order_key_param;
			if ( $pay_order instanceof WC_Order && hash_equals( $pay_order->get_order_key(), $key ) ) {
				$pending_url = $pay_order->get_meta( '_neopayment_pending_3ds_url' );
				$pending_at  = (int) $pay_order->get_meta( '_neopayment_pending_3ds_at' );
				if ( is_string( $pending_url ) && '' !== $pending_url && ( time() - $pending_at ) <= 900 ) {
					$localized['pending_challenge_url'] = esc_url_raw( $pending_url );
				}
			}
		}

		wp_localize_script(
			'neopayment-3ds-popup',
			'neopayment_3DS',
			$localized
		);
	}

	/**
	 * Delegates rendering of the hosted credit card inputs to `NEOPAYMENT_CC`.
	 *
	 * @param array $args   Optional arguments (reserved for WooCommerce compatibility).
	 * @param array $fields Optional field definitions (reserved).
	 *
	 * @return void
	 */
	public function credit_card_form($args = array(), $fields = array())
	{
		$cc_form           = new NEOPAYMENT_CC();
		$cc_form->id       = $this->id;
		$cc_form->supports = $this->supports;
		$cc_form->form();
	}

	/**
	 * Outputs gateway fields shown on the checkout / order-pay payment step (classic UI).
	 *
	 * Renders description (with test-mode notice), the payment nonce, **hidden browser fields for 3DS2**
	 * (`neopayment_echo_classic_checkout_browser_hidden_fields`), then the card form markup.
	 *
	 * Hidden fields start empty and are populated by `neopayment-script.js` before submit; PHP applies
	 * `neopayment_apply_3ds_browser_fallback()` if any value is still missing server-side.
	 *
	 * @return void
	 */
	public function payment_fields()
	{

		// ok, let's display some description before the payment form.
		if ($this->description) {
			// you can instructions for test mode, I mean test card numbers etc.
			if ($this->testmode) {
				$this->description .= ' ' . __('TEST MODE ENABLED', 'neopayment') . '.';
				$this->description  = trim($this->description);
			}
			// display the description with <p> tags etc.
			echo wp_kses_post(wpautop($this->description));
		}
		// nonce field for security.
		wp_nonce_field($this->id . '_process_payment', $this->id . '_nonce');

		$this->neopayment_echo_classic_checkout_browser_hidden_fields();

		$this->credit_card_form();
	}

	/**
	 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form.
	 *
	 * @return void
	 */
	public function payment_scripts() {}

	/**
	 * Validates posted card fields for classic / order-pay checkout (Blocks requests are skipped).
	 *
	 * Verifies nonce when present, detects Blocks via `payment_data` in `php://input`, then runs Luhn,
	 * expiry, cardholder, and CVV checks using `NEOPAYMENT_Helpers`.
	 *
	 * @return bool True when validation passes; false after adding `wc_add_notice()` errors.
	 */
	public function validate_fields()
	{

		// check if the nonce is set and valid.
		if (
			isset($_POST[$this->id . '_nonce']) &&
			! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$this->id . '_nonce'])), $this->id . '_process_payment')
		) {
			wc_add_notice(__('Security check failed. Please try again.', 'neopayment'), 'error');
			return false;
		}

		// detect if the request is from a block-based checkout or classic checkout.
		$raw_input = file_get_contents('php://input');
		$body      = json_decode($raw_input, true);
		$body      = is_array($body) ? $body : array();

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
		// if the request is from a block-based checkout, omit the validation.
		if (! empty($body['payment_data'])) {
			return true;
		}

		$card_number = isset($_POST[$this->id . '-card-number']) ? sanitize_text_field(wp_unslash($_POST[$this->id . '-card-number'])) : '';
		$card_expiry = isset($_POST[$this->id . '-card-expiry']) ? sanitize_text_field(wp_unslash($_POST[$this->id . '-card-expiry'])) : '';
		$card_cvv    = isset($_POST[$this->id . '-card-cvc']) ? sanitize_text_field(wp_unslash($_POST[$this->id . '-card-cvc'])) : '';
		$card_holder = isset($_POST[$this->id . '-card-holder']) ? sanitize_text_field(wp_unslash($_POST[$this->id . '-card-holder'])) : '';

		$valid = true;

		$card_number = str_replace(' ', '', $card_number);
		if (! NEOPAYMENT_Helpers::is_valid_luhn($card_number)) {
			wc_add_notice(__('Invalid card number', 'neopayment'), 'error');
			$valid = false;
		}

		$card_expiry = str_replace(' ', '', $card_expiry);
		if (! NEOPAYMENT_Helpers::is_valid_expiry_date($card_expiry)) {
			wc_add_notice(__('Invalid expiry date', 'neopayment'), 'error');
			$valid = false;
		}

		if (! NEOPAYMENT_Helpers::is_valid_card_holder($card_holder)) {
			wc_add_notice(__('Invalid card holder', 'neopayment'), 'error');
			$valid = false;
		}

		if (! NEOPAYMENT_Helpers::is_valid_cvv($card_cvv)) {
			wc_add_notice(__('Invalid card code (CVV)', 'neopayment'), 'error');
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
	public function process_refund($order_id, $amount = 0, $reason = '')
	{
		if (! check_ajax_referer('order-item', 'security', false)) {
			NEOPAYMENT_Log::debug('Refund rechazado: nonce inválido o ausente');
			return new WP_Error('invalid_nonce', __('Unauthorized action.', 'neopayment'));
		}
		if (! current_user_can('edit_shop_orders')) {
			NEOPAYMENT_Log::debug('Refund rechazado: usuario sin permisos');
			return new WP_Error('insufficient_permissions', __('Insufficient permissions.', 'neopayment'));
		}

		$neopayment_client = new NEOPAYMENT_Client(
			$this->api_url,
			$this->api_client_id,
			$this->api_client_secret,
			$this->testmode
		);

		if (! $order_id || ! $amount) {
			return new WP_Error('invalid_order', 'Invalid order ID or amount');
		}
		NEOPAYMENT_Log::debug("process_refund: order_id={$order_id}, amount={$amount}, reason={$reason}");
		$order = wc_get_order($order_id);

		if (! $order) {
			return new WP_Error('invalid_order', 'Invalid order ID');
		}
		$txn = $order->get_meta('neopayment_transaction_id');
		if (! $txn) {
			return new WP_Error('no_transaction_id', 'No transaction ID found for this order');
		}

		try {
			$amount_in_cents = (int) round(((float) $amount) * 100);
			$data            = $neopayment_client->refund($txn, $amount_in_cents);
		} catch (NEOPAYMENT_Exception $e) {
			$response          = $e->getResponse();
			$api_message       = $response['body']['message'] ?? $e->getMessage();
			$api_error_code    = $response['body']['error'] ?? '';
			$api_response_code = (int) ($response['code'] ?? 0);

			// Some environments expect the refund amount in major units instead of cents.
			if (409 === $api_response_code && 'invalid_state' === $api_error_code) {
				$fallback_amount = (int) round((float) $amount);
				if ($fallback_amount > 0) {
					NEOPAYMENT_Log::debug("Retry refund with major units: txn={$txn}, amount={$fallback_amount}");
					try {
						$data = $neopayment_client->refund($txn, $fallback_amount);
					} catch (NEOPAYMENT_Exception $retry_exception) {
						$retry_response = $retry_exception->getResponse();
						$retry_message  = $retry_response['body']['message'] ?? $retry_exception->getMessage();
						NEOPAYMENT_Log::debug('Error processing refund (retry): ' . $retry_message);
						return new WP_Error('neopayment_refund_error', $retry_message);
					}
				} else {
					NEOPAYMENT_Log::debug('Error processing refund: ' . $api_message);
					return new WP_Error('neopayment_refund_error', $api_message);
				}
			} else {
				NEOPAYMENT_Log::debug('Error processing refund: ' . $api_message);
				return new WP_Error('neopayment_refund_error', $api_message);
			}
		} catch (\Throwable $e) {
			NEOPAYMENT_Log::debug('Unexpected refund error: ' . $e->getMessage());
			return new WP_Error('neopayment_refund_error', __('Unexpected refund error. Please check logs.', 'neopayment'));
		}

		$status = strtolower($data['status'] ?? 'unknown');
		$success_status = array( 'authorized', 'approved', 'completed' );

		if ( in_array( $status, $success_status, true ) ) {
			$order->add_order_note(
				sprintf(
					'Reembolso de %s aprobado vía NEOPAYMENT (refund_id %s). Motivo: %s',
					wc_price($amount),
					$data['identifier'] ?? $data['id'] ?? '',
					$reason
				)
			);
			if (! empty($data['identifier'])) {
				$order->update_meta_data('neopayment_refund_id', $data['identifier']);
				$order->save();
			}
			return true;
		} else {
			$order->add_order_note(
				sprintf(
					'Intento de reembolso fallido. Estado devuelto por NEOPAYMENT: %s (código %s)',
					$status,
					$data['response_code'] ?? 'N/A'
				)
			);
			return new WP_Error(
				'refund_denied',
				sprintf(
					/* translators: %s is the refund status returned by the API */
					__('Reembolso denegado (estado: %s).', 'neopayment'),
					$status
				)
			);
		}
	}

	/**
	 * Executes the remote sale and maps the API response to WooCommerce checkout outcomes.
	 *
	 * **Checkout origin detection**
	 * - **Blocks / Store API:** JSON body contains `payment_data`; card + browser fields are read from that structure.
	 * - **Classic checkout & order-pay:** Reads card fields from `$_POST` and builds 3DS browser data via
	 *   `neopayment_get_3ds_params()` → validates with `neopayment_has_required_3ds_browser_params()`.
	 *
	 * **Common pipeline (both origins)**
	 * `neopayment_resolve_3ds_email()` fills a valid `email` from the order when POST/Blocks omitted it (API requirement).
	 * `neopayment_normalize_3ds_params()` adjusts types (e.g. `challengeWindowSize`), then `NEOPAYMENT_Client::sale()`
	 * is called. Returns success + challenge URL for 3DS step-up, success + redirect when authorized, or failure notices.
	 *
	 * @param int $order_id WooCommerce order ID being paid.
	 *
	 * @return array{
	 *     result: 'success'|'failure',
	 *     redirect?: string,
	 *     requires_challenge?: bool,
	 *     challenge_url?: string,
	 *     neopayment_callback_url?: string,
	 *     additional_data?: array
	 * } Structured result for WooCommerce core / Blocks.
	 */
	public function process_payment($order_id)
	{
		$callback = esc_url_raw(home_url("/wc-api/{$this->id}_status"));

		// check if the nonce is set and valid.
		if (isset($_POST[$this->id . '_nonce'])) {
			if (! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[$this->id . '_nonce'])), $this->id . '_process_payment')) {
				wc_add_notice(__('Security check failed. Please try again.', 'neopayment'), 'error');
				return array( 'result' => 'failure' );
			}
		}
		// we need it to get any order details.
		NEOPAYMENT_Log::debug('process_payment: ' . $order_id);
		$order = wc_get_order($order_id);
		if ( $order instanceof WC_Order ) {
			$this->neopayment_clear_pending_3ds_challenge( $order );
		}

		$neopayment_client = new NEOPAYMENT_Client( $this->api_url, $this->api_client_id, $this->api_client_secret, $this->testmode );
		try {
			// detect if the request is from a block-based checkout or classic checkout.
			$raw_input = file_get_contents('php://input');
			$body      = json_decode($raw_input, true);
			if (! is_array($body)) {
				$body = array();
			}

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
			$neopayment_is_block = ! empty($body['payment_data']);

			// if the request is from a block-based checkout, we need to handle it differently.
			if ($neopayment_is_block) {
				NEOPAYMENT_Log::debug('Origin: Checkout Based Blocks');
				$pdata    = $body['payment_data'];
				$billing  = $body['billing_address'] ?? array();
				$shipping = $body['shipping_address'] ?? array();

				$data = array();
				foreach ($pdata as $index => $field) {
					if (isset($field['key'], $field['value'])) {
						$data[$field['key']] = wc_clean($field['value']);
					}
				}
				NEOPAYMENT_Log::debug(' data: ' . wp_json_encode($data));

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

					'browserIP'                => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '',
					'email'                    => isset( $billing['email'] ) ? sanitize_email( (string) $billing['email'] ) : '',
					'billAddrCountry'          => $this->neopayment_get_iso_alpha3_cc($billing['country'] ?? $order->get_billing_country()),
					'billAddrCity'             => $billing['city'] ?? $order->get_billing_city(),
					'billAddrState'            => NEOPAYMENT_Helpers::parse_state($billing['state'] ?? $order->get_billing_state()),
					'billAddrLine1'            => $billing['address_1'] ?? $order->get_billing_address_1(),
					'billAddrLine2'            => 'none',
					'billAddrPostCode'         => $billing['postcode'] ?? $order->get_billing_postcode(),
					'shipAddrCountry'          => $this->neopayment_get_iso_alpha3_cc($shipping['country'] ?? $order->get_shipping_country()),
					'shipAddrCity'             => $shipping['city'] ?? $order->get_shipping_city(),
					'shipAddrState'            => NEOPAYMENT_Helpers::parse_state($shipping['state'] ?? $order->get_shipping_state()),
					'shipAddrLine1'            => $shipping['address_1'] ?? $order->get_shipping_address_1(),
					'shipAddrLine2'            => 'none',
					'shipAddrPostCode'         => $shipping['postcode'] ?? $order->get_shipping_postcode(),
				);
			} else {
				NEOPAYMENT_Log::debug('Origin: Classic Checkout');
				$card_number = sanitize_text_field(wp_unslash($_POST[$this->id . '-card-number'] ?? ''));
				$card_expiry = sanitize_text_field(wp_unslash($_POST[$this->id . '-card-expiry'] ?? ''));
				$card_cvc    = sanitize_text_field(wp_unslash($_POST[$this->id . '-card-cvc'] ?? ''));
				$card_holder = sanitize_text_field(wp_unslash($_POST[$this->id . '-card-holder'] ?? ''));

				$card_number = str_replace(' ', '', $card_number);
				$card_expiry = str_replace(' ', '', $card_expiry);

				$three_ds_params = $this->neopayment_get_3ds_params();
				if (! $this->neopayment_has_required_3ds_browser_params($three_ds_params)) {
					wc_add_notice(__('No se pudo iniciar la autenticación 3DS. Recarga la página e inténtalo nuevamente.', 'neopayment'), 'error');
					return array( 'result' => 'failure' );
				}
			}
			$three_ds_params['email'] = $this->neopayment_resolve_3ds_email( $three_ds_params, $order );
			if ( ! is_email( $three_ds_params['email'] ) ) {
				wc_add_notice(
					__( 'Se requiere un correo de facturación válido para la autenticación 3DS. Comprueba los datos del pedido.', 'neopayment' ),
					'error'
				);
				return array( 'result' => 'failure' );
			}
			$three_ds_params = $this->neopayment_normalize_3ds_params($three_ds_params);
			NEOPAYMENT_Log::debug('three_ds_params=' . wp_json_encode($three_ds_params));

			$transaction = $neopayment_client->sale($order, $card_number, $card_expiry, $card_cvc, $card_holder, $three_ds_params);
			NEOPAYMENT_Log::debug('Checkout data: ' . wp_json_encode($transaction));

			if ('authenticating' === ($transaction['status'] ?? '')) {
				$challenge_url = isset( $transaction['metadatas']['3ds_authentication_form'] )
					? esc_url_raw( (string) $transaction['metadatas']['3ds_authentication_form'] )
					: '';

				// Classic AJAX checkout keeps the JSON response and can open the iframe from JS using the hash.
				// Pay-for-order performs a real HTTP redirect, so the client never sees `challenge_url` unless we persist it.
				$redirect = '#neopayment-3ds-pending';
				if ( $order instanceof WC_Order && $this->neopayment_is_pay_for_order_request() && '' !== $challenge_url ) {
					$order->update_meta_data( '_neopayment_pending_3ds_url', $challenge_url );
					$order->update_meta_data( '_neopayment_pending_3ds_at', time() );
					$order->save();
					$redirect = add_query_arg( 'neopayment_open_3ds', '1', $order->get_checkout_payment_url( true ) );
				}

				return array(
					'result'               => 'success',
					'requires_challenge'   => true,
					'challenge_url'        => $challenge_url,
					'redirect'             => $redirect,
					'neopayment_callback_url' => $callback,
				);
			} elseif ($this->validate_payment($transaction)) {
				return array(
					'result'          => 'success',
					'redirect'        => $order->get_checkout_order_received_url(),
					'additional_data' => array(),
				);
			} elseif ('refused' === ($transaction['status'] ?? '')) {
				wc_add_notice(__('We were unable to complete the payment. Please contact with commerce.', 'neopayment'), 'error');
				return array( 'result' => 'failure' );
			} else {
				wc_add_notice(__('We were unable to complete the payment. Please check your card details or contact your bank.', 'neopayment'), 'error');
				return array( 'result' => 'failure' );
			}
		} catch (\NEOPAYMENT_Exception $e) {
			if (! $e->isSuccessResponse()) {
				NEOPAYMENT_Log::debug($e->getMessage() . ' - ' . wp_json_encode($e->getResponse()));
				wc_add_notice(__('Cannot generate the payment. Please, contact with commerce.', 'neopayment'), 'error');
				return array( 'result' => 'failure' );
			} else {
				wc_add_notice(__('Cannot process the payment. Please, contact with commerce.', 'neopayment'), 'error');
				return array( 'result' => 'failure' );
			}
		}

		return array( 'result' => 'failure' );
	}

	/**
	 * Normalizes a PHP `$_POST` value when duplicate HTML fields produced an array of scalars.
	 *
	 * WooCommerce may submit multiple inputs with the same `name` (theme + plugin duplicates). WordPress
	 * then exposes `$_POST['field']` as an array; passing that array to `sanitize_text_field()` yields an
	 * empty string. This helper picks the **last non-empty** scalar, then falls back to the last scalar value.
	 *
	 * @param array $parts Raw fragment from `$_POST` or parsed `post_data` (list of submitted values).
	 *
	 * @return string Coalesced scalar as string; empty string if nothing usable was found.
	 */
	private function neopayment_coalesce_post_array_to_string( array $parts ) {
		$candidates = array_reverse( $parts, true );
		foreach ( $candidates as $c ) {
			if ( is_scalar( $c ) && '' !== trim( (string) $c ) ) {
				return (string) $c;
			}
		}
		foreach ( $candidates as $c ) {
			if ( is_scalar( $c ) ) {
				return (string) $c;
			}
		}
		return '';
	}

	/**
	 * Prints empty hidden inputs for EMV 3DS2 browser channel attributes on classic / order-pay checkout.
	 *
	 * Field names must stay in sync with `neopayment-script.js` and `neopayment_get_3ds_params()`.
	 * Class `neopayment-standard-gateway-browser` allows the script to locate and update values before POST.
	 *
	 * @see neopayment_get_3ds_params()
	 * @see neopayment_apply_3ds_browser_fallback()
	 *
	 * @return void
	 */
	private function neopayment_echo_classic_checkout_browser_hidden_fields() {
		$names = array(
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
		foreach ( $names as $name ) {
			printf(
				'<input type="hidden" class="neopayment-standard-gateway-browser" name="%1$s" value="" autocomplete="off" />',
				esc_attr( $name )
			);
		}
	}

	/**
	 * Supplies conservative defaults for any 3DS2 browser-channel keys still empty after reading the request.
	 *
	 * Used when JavaScript did not run (performance plugins, CSP), on **order-pay** edge cases, or when
	 * duplicate inputs collapsed to blank strings. Prefer real browser data from the client when available;
	 * these defaults satisfy `neopayment_has_required_3ds_browser_params()` and keep the sale request valid.
	 *
	 * Does **not** overwrite non-empty values (after `trim`).
	 *
	 * @param array $three_ds_params Partial 3DS payload (typically output of `neopayment_get_3ds_params()` before fallback).
	 *
	 * @return array Same structure with missing browser keys filled.
	 */
	private function neopayment_apply_3ds_browser_fallback( array $three_ds_params ) {
		$keys = array(
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
		foreach ( $keys as $key ) {
			if ( isset( $three_ds_params[ $key ] ) && '' !== trim( (string) $three_ds_params[ $key ] ) ) {
				continue;
			}
			switch ( $key ) {
				case 'browserUserAgent':
					$three_ds_params[ $key ] = isset( $_SERVER['HTTP_USER_AGENT'] )
						? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
						: 'WooCommerce';
					break;
				case 'browserLanguage':
					$lang = '';
					if ( ! empty( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
						$al    = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) );
						$parts = explode( ',', $al );
						$piece = isset( $parts[0] ) ? $parts[0] : '';
						$seg   = explode( ';', $piece );
						$lang  = strtolower( trim( (string) ( $seg[0] ?? '' ) ) );
					}
					$three_ds_params[ $key ] = '' !== $lang ? $lang : 'en';
					break;
				case 'browserJavaEnabled':
					$three_ds_params[ $key ] = '0';
					break;
				case 'browserJavascriptEnabled':
					$three_ds_params[ $key ] = '1';
					break;
				case 'browserColorDepth':
					$three_ds_params[ $key ] = '24';
					break;
				case 'browserScreenWidth':
					$three_ds_params[ $key ] = '1920';
					break;
				case 'browserScreenHeight':
					$three_ds_params[ $key ] = '1080';
					break;
				case 'browserTZ':
					$three_ds_params[ $key ] = '0';
					break;
				case 'challengeWindowSize':
					$three_ds_params[ $key ] = '3';
					break;
				default:
					$three_ds_params[ $key ] = '';
			}
		}
		return $three_ds_params;
	}

	/**
	 * Ensures `email` in the 3DS payload is acceptable to the remote API (non-empty, valid format).
	 *
	 * The gateway returns HTTP 400 when `3ds_params.email` is empty. Classic AJAX checkout often omits
	 * top-level `billing_email` (it lives in `post_data` — handled in `neopayment_get_3ds_params()`), and
	 * pay-for-order flows may not repost billing fields; this method falls back to `WC_Order::get_billing_email()`.
	 *
	 * @param array         $three_ds_params Current 3DS payload.
	 * @param WC_Order|bool $order           Order being paid.
	 *
	 * @return string Sanitized email (empty string only when neither payload nor order provides a valid address).
	 */
	private function neopayment_resolve_3ds_email( array $three_ds_params, $order ) {
		$from_payload = '';
		if ( isset( $three_ds_params['email'] ) ) {
			$candidate = trim( (string) $three_ds_params['email'] );
			if ( '' !== $candidate ) {
				$from_payload = sanitize_email( $candidate );
			}
		}
		if ( $from_payload && is_email( $from_payload ) ) {
			return $from_payload;
		}
		if ( $order instanceof WC_Order ) {
			$from_order = sanitize_email( $order->get_billing_email() );
			if ( $from_order && is_email( $from_order ) ) {
				return $from_order;
			}
		}
		return '';
	}

	/**
	 * Builds the 3DS2 `three_ds_params` array for **classic checkout** and **pay-for-order** submissions.
	 *
	 * Reads browser attributes from top-level `$_POST` and from the serialized `post_data` string WooCommerce
	 * sends on AJAX checkout. Merges billing/shipping fallbacks for address-related 3DS fields. Applies
	 * `neopayment_coalesce_post_array_to_string()` when a browser field arrives as an array (duplicate inputs).
	 *
	 * Always ends by merging `neopayment_apply_3ds_browser_fallback()` so the payload is complete before validation.
	 *
	 * **Not used** by the Blocks checkout branch (that path receives browser data from the REST payload).
	 *
	 * @return array Associative 3DS payload ready for `neopayment_has_required_3ds_browser_params()`.
	 */
	private function neopayment_get_3ds_params()
	{
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// WooCommerce verifies `woocommerce-process_checkout` / `woocommerce-pay` nonces before calling gateway `process_payment()`.
		$posted_data = array();
		$post_data_raw = filter_input(INPUT_POST, 'post_data', FILTER_UNSAFE_RAW);
		$post_data_raw = is_string($post_data_raw) ? sanitize_textarea_field(wp_unslash($post_data_raw)) : '';
		if ('' !== $post_data_raw) {
			parse_str($post_data_raw, $posted_data);
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
			$value = '';
			if ( isset( $_POST[ $attr ] ) ) {
				$raw = wp_unslash( $_POST[ $attr ] );
				if ( is_array( $raw ) ) {
					$raw = $this->neopayment_coalesce_post_array_to_string( $raw );
				}
				$value = is_scalar( $raw ) ? sanitize_text_field( (string) $raw ) : '';
			} elseif ( isset( $posted_data[ $attr ] ) ) {
				$pd = $posted_data[ $attr ];
				if ( is_array( $pd ) ) {
					$pd = $this->neopayment_coalesce_post_array_to_string( $pd );
				}
				$value = is_scalar( $pd ) ? sanitize_text_field( (string) $pd ) : '';
			}
			$three_ds_params[ $attr ] = $value;
		}

		// Order additional data.
		$three_ds_params['transType']     = 'goods';
		$three_ds_params['deviceChannel'] = 'browser';
		$three_ds_params['browserIP']     = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

		// Shopper email: on AJAX checkout Woo often puts `billing_email` only inside serialized `post_data`, not top-level `$_POST`.
		$email_raw = '';
		if ( isset( $_POST['billing_email'] ) ) {
			$raw = wp_unslash( $_POST['billing_email'] );
			if ( is_array( $raw ) ) {
				$raw = $this->neopayment_coalesce_post_array_to_string( $raw );
			}
			$email_raw = is_scalar( $raw ) ? trim( (string) $raw ) : '';
		}
		if ( '' === $email_raw && isset( $posted_data['billing_email'] ) ) {
			$pd = $posted_data['billing_email'];
			if ( is_array( $pd ) ) {
				$pd = $this->neopayment_coalesce_post_array_to_string( $pd );
			}
			$email_raw = is_scalar( $pd ) ? trim( (string) $pd ) : '';
		}
		$three_ds_params['email'] = sanitize_email( $email_raw );

		$billing_country_raw                = isset($_POST['billing_country']) ? sanitize_text_field(wp_unslash($_POST['billing_country'])) : '';
		$billing_iso3                       = ('' !== $billing_country_raw) ? $this->neopayment_get_iso_alpha3_cc($billing_country_raw) : '';
		$three_ds_params['billAddrCountry'] = ('' !== $billing_iso3) ? $billing_iso3 : 'DIG';

		$billing_city                    = isset($_POST['billing_city']) ? sanitize_text_field(wp_unslash($_POST['billing_city'])) : '';
		$three_ds_params['billAddrCity'] = ('' !== $billing_city) ? $billing_city : 'digital';

		$billing_state                    = isset($_POST['billing_state']) ? sanitize_text_field(wp_unslash($_POST['billing_state'])) : '';
		$three_ds_params['billAddrState'] = ('' !== $billing_state) ? NEOPAYMENT_Helpers::parse_state($billing_state) : 'DIG';

		$billing_line1                    = isset($_POST['billing_address_1']) ? sanitize_text_field(wp_unslash($_POST['billing_address_1'])) : '';
		$three_ds_params['billAddrLine1'] = ('' !== $billing_line1) ? $billing_line1 : 'digital';

		$billing_line2                    = isset($_POST['billing_address_2']) ? sanitize_text_field(wp_unslash($_POST['billing_address_2'])) : '';
		$three_ds_params['billAddrLine2'] = ('' !== $billing_line2) ? $billing_line2 : 'none';

		$billing_post                        = isset($_POST['billing_postcode']) ? sanitize_text_field(wp_unslash($_POST['billing_postcode'])) : '';
		$three_ds_params['billAddrPostCode'] = ('' !== $billing_post) ? $billing_post : '0000';

		$billing_city  = $three_ds_params['billAddrCity'];
		$billing_line1 = $three_ds_params['billAddrLine1'];
		$billing_post  = $three_ds_params['billAddrPostCode'];

		$shipping_country_raw               = isset($_POST['shipping_country']) ? sanitize_text_field(wp_unslash($_POST['shipping_country'])) : '';
		$shipping_iso3                      = ('' !== $shipping_country_raw) ? $this->neopayment_get_iso_alpha3_cc($shipping_country_raw) : '';
		$three_ds_params['shipAddrCountry'] = ('' !== $shipping_iso3) ? $shipping_iso3 : 'DIG';

		$shipping_city = isset($_POST['shipping_city']) ? sanitize_text_field(wp_unslash($_POST['shipping_city'])) : '';
		if ('' === $shipping_city) {
			$shipping_city = ('' !== $billing_city) ? $billing_city : 'digital';
		}
		$three_ds_params['shipAddrCity'] = $shipping_city;

		$shipping_state                   = isset($_POST['shipping_state']) ? sanitize_text_field(wp_unslash($_POST['shipping_state'])) : '';
		$three_ds_params['shipAddrState'] = ('' !== $shipping_state) ? NEOPAYMENT_Helpers::parse_state($shipping_state) : 'DIG';

		$shipping_line1 = isset($_POST['shipping_address_1']) ? sanitize_text_field(wp_unslash($_POST['shipping_address_1'])) : '';
		if ('' === $shipping_line1) {
			$shipping_line1 = ('' !== $billing_line1) ? $billing_line1 : 'digital';
		}
		$three_ds_params['shipAddrLine1'] = $shipping_line1;

		$shipping_line2                   = isset($_POST['shipping_address_2']) ? sanitize_text_field(wp_unslash($_POST['shipping_address_2'])) : '';
		$three_ds_params['shipAddrLine2'] = ('' !== $shipping_line2) ? $shipping_line2 : 'none';

		$shipping_post = isset($_POST['shipping_postcode']) ? sanitize_text_field(wp_unslash($_POST['shipping_postcode'])) : '';
		if ('' === $shipping_post) {
			$shipping_post = ('' !== $billing_post) ? $billing_post : '0000';
		}
		$three_ds_params['shipAddrPostCode'] = $shipping_post;

		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return $this->neopayment_apply_3ds_browser_fallback( $three_ds_params );
	}

	/**
	 * Validates that all mandatory browser-channel keys are present and non-empty (classic / order-pay only).
	 *
	 * EMV 3DS2 expects these fields for risk analysis; missing values abort payment with a customer-facing notice.
	 * Debug logs include every array key currently set plus a hint when `$_POST[$key]` is an array (duplicate fields).
	 *
	 * @param array $three_ds_params Payload produced by `neopayment_get_3ds_params()` (after fallback).
	 *
	 * @return bool True if every required key has a non-empty trimmed string/number representation.
	 */
	private function neopayment_has_required_3ds_browser_params($three_ds_params)
	{
		$required = array(
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

		foreach ( $required as $key ) {
			$val = isset( $three_ds_params[ $key ] ) ? $three_ds_params[ $key ] : null;
			if ( null === $val || '' === trim( (string) $val ) ) {
				$present_keys = implode( ',', array_keys( $three_ds_params ) );
				// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Debug-only; POST was already consumed under WC nonces in `neopayment_get_3ds_params()`.
				$post_shape   = isset( $_POST[ $key ] ) && is_array( $_POST[ $key ] ) ? ' (POST is array — duplicate fields)' : '';
				NEOPAYMENT_Log::debug( "Missing required 3DS browser param: {$key}. Received keys: {$present_keys}{$post_shape}" );
				return false;
			}
		}

		NEOPAYMENT_Log::debug('Classic checkout 3DS browser params OK.');

		return true;
	}

	/**
	 * Whether the current HTTP request is the WooCommerce pay-for-order flow (order-pay template).
	 *
	 * Used to choose a full redirect with query args so PHP can restore the 3DS challenge URL after reload
	 * (WooCommerce does not use `wc-ajax=checkout` here, so hash-only redirects lose the JSON payload).
	 *
	 * @return bool
	 */
	private function neopayment_is_pay_for_order_request() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended -- WooCommerce verifies `woocommerce-pay` / checkout nonces before calling gateway `process_payment()`.
		$pay_nonce = isset( $_POST['woocommerce-pay-nonce'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['woocommerce-pay-nonce'] ) ) : '';
		if ( '' !== $pay_nonce ) {
			return true;
		}
		$pfo = '';
		if ( isset( $_GET['pay_for_order'] ) ) {
			$pfo = sanitize_text_field( wp_unslash( (string) $_GET['pay_for_order'] ) );
		} elseif ( isset( $_POST['pay_for_order'] ) ) {
			$pfo = sanitize_text_field( wp_unslash( (string) $_POST['pay_for_order'] ) );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing,WordPress.Security.NonceVerification.Recommended
		if ( '' !== $pfo && wc_string_to_bool( $pfo ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Removes stored 3DS challenge URL used only for pay-for-order resume-after-redirect.
	 *
	 * @param WC_Order|bool $order Order instance.
	 *
	 * @return void
	 */
	private function neopayment_clear_pending_3ds_challenge( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$order->delete_meta_data( '_neopayment_pending_3ds_url' );
		$order->delete_meta_data( '_neopayment_pending_3ds_at' );
		$order->save();
	}

	/**
	 * Normalizes 3DS payload types before calling `NEOPAYMENT_Client::sale()`.
	 *
	 * Currently enforces `challengeWindowSize` as an integer in the **1–5** EMV range (codes `01`–`05`).
	 * Values outside that range (legacy bugs such as sending pixel width `400`) are coerced to `3`.
	 *
	 * @param array $three_ds_params Combined classic or Blocks 3DS payload.
	 *
	 * @return array Mutated copy safe for JSON encoding to the Neopayment API.
	 */
	private function neopayment_normalize_3ds_params($three_ds_params)
	{
		if ( isset( $three_ds_params['challengeWindowSize'] ) && '' !== trim( (string) $three_ds_params['challengeWindowSize'] ) ) {
			$raw = (int) $three_ds_params['challengeWindowSize'];
			if ( $raw < 1 || $raw > 5 ) {
				$raw = 3;
			}
			$three_ds_params['challengeWindowSize'] = $raw;
		}

		return $three_ds_params;
	}

	/**
	 * Validate Payment
	 *
	 * @param array $transaction for all data.
	 * @return false return 'false'.
	 */
	private function validate_payment($transaction)
	{

		$metas    = $transaction['metadatas'];
		$order_id = $metas['order_id'];
		$order    = wc_get_order($order_id);
		if ( ! $order instanceof WC_Order ) {
			return false;
		}

		$status         = $transaction['status'];
		$success_status = array('authorized', 'notified');
		$order->update_meta_data('neopayment_bank_code', $transaction['response_code']);
		$order->update_meta_data('neopayment_transaction_id', $transaction['identifier']);
		$order->update_meta_data('neopayment_bank_authorization', $transaction['authorization_number']);

		if (in_array($status, $success_status, true)) {
			$this->neopayment_clear_pending_3ds_challenge( $order );
			$order->update_status('completed', __('Payment completed', 'neopayment'));
			$order->payment_complete($transaction['identifier']);
			if (function_exists('WC') && WC()->cart) {
				WC()->cart->empty_cart();
			}
			return true;
		} else {
			$this->neopayment_clear_pending_3ds_challenge( $order );
			$order->update_status('failed', __('Failed payment', 'neopayment'));
		}

		return false;
	}

	/**
	 * Callback function for show payment status
	 */
	public function neopayment_callback_url()
	{
		// Callback received from payment provider; validate order id and order key instead of nonce.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_id  = isset($_GET['oid']) ? absint($_GET['oid']) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_key = isset($_GET['key']) ? sanitize_text_field(wp_unslash($_GET['key'])) : '';
		$order     = wc_get_order($order_id);

		if (! $order) {
			status_header(400);
			exit;
		}

		if ('' !== $order_key && ! hash_equals($order->get_order_key(), $order_key)) {
			status_header(403);
			exit;
		}

		$target = ($order && $order->is_paid())
			? $order->get_checkout_order_received_url()
			: $order->get_checkout_payment_url();

		$success = (bool) ($order && $order->is_paid());

		NEOPAYMENT_Log::debug("neopayment_callback_url: order_id=$order_id, target=$target");

		wp_register_script('neopayment-3ds-handler', '', array(), '1.0', true);
		wp_enqueue_script('neopayment-3ds-handler');

		$script_data = sprintf(
			'var neopayment3dsData = { target: %s, success: %s };',
			wp_json_encode($target),
			$success ? 'true' : 'false'
		);
		wp_add_inline_script('neopayment-3ds-handler', $script_data, 'before');

		$main_script = '
			document.addEventListener("DOMContentLoaded", function() {
				var targetWindow = null;
				if (window.opener && !window.opener.closed) {
					targetWindow = window.opener;
				} else if (window.parent && window.parent !== window) {
					targetWindow = window.parent;
				}

				if (targetWindow) {
					targetWindow.postMessage({
						neopayment3ds: neopayment3dsData.success ? "success" : "fail",
						redirect_to: neopayment3dsData.target,
						source: "neopayment_3ds_handler"
					}, "' . esc_url(home_url('/')) . '");
				} else {
					window.location.href = neopayment3dsData.target;
				}
			});
		';
		wp_add_inline_script('neopayment-3ds-handler', $main_script);

	?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>

		<head>
			<meta charset="<?php bloginfo('charset'); ?>">
			<title><?php esc_html_e('Processing 3DS…', 'neopayment'); ?></title>
			<style>
				html, body {
					height: 100%;
					margin: 0;
					background: #f4f7fb;
					font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
				}
				.neopayment-3ds-loading {
					height: 100%;
					display: flex;
					align-items: center;
					justify-content: center;
					flex-direction: column;
					gap: 12px;
					color: #22324a;
				}
				.neopayment-3ds-spinner {
					width: 42px;
					height: 42px;
					border: 4px solid #d7deea;
					border-top-color: #2f6fb3;
					border-radius: 50%;
					animation: neopayment3dsspin 0.9s linear infinite;
				}
				.neopayment-3ds-text {
					font-size: 14px;
					text-align: center;
					max-width: 320px;
					line-height: 1.4;
				}
				@keyframes neopayment3dsspin {
					to { transform: rotate(360deg); }
				}
			</style>
			<?php wp_head(); ?>
		</head>

		<body>
			<div class="neopayment-3ds-loading">
				<div class="neopayment-3ds-spinner"></div>
				<p class="neopayment-3ds-text"><?php esc_html_e('Estamos finalizando la autenticación 3DS. Por favor espere...', 'neopayment'); ?></p>
				<noscript>
					<p><?php esc_html_e('Please enable JavaScript to complete your payment. You will be automatically redirected...', 'neopayment'); ?></p>
					<meta http-equiv="refresh" content="3;url=<?php echo esc_url($target); ?>">
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
	public function neopayment_webhook()
	{
		$raw_input = file_get_contents('php://input');
		$data      = json_decode($raw_input, true);

		if (! is_array($data)) {
			NEOPAYMENT_Log::debug('Webhook error: input no es array válido');
			status_header(400);
			exit;
		}

		foreach ($data as $key => $value) {
			if (is_string($value)) {
				$data[$key] = sanitize_text_field($value);
			}
		}

		NEOPAYMENT_Log::debug('Webhook recibido: ' . wp_json_encode($data));

		try {
			$valid_transaction = $this->validate_payment($data);
			status_header(204);
		} catch (\NEOPAYMENT_Exception $e) {
			$tid = isset($data['tid']) ? $data['tid'] : 'N/A';
			NEOPAYMENT_Log::debug("Error en webhook. TID: $tid - " . $e->getMessage());
			status_header(400);
		}
		exit;
	}

	/**
	 * Get ISO alpha-3 country code for 3DS.
	 *
	 * @param string $country ISO 3166-1 alpha-2 code (e.g. 'US').
	 * @return string ISO 3166-1 alpha-3 code (e.g. 'USA'). Returns the input if not mapped.
	 */
	public function neopayment_get_iso_alpha3_cc($country)
	{
		return NEOPAYMENT_Constants::NEOPAYMENT_PAYMENT_COUNTRIES[$country] ?? $country;
	}

	/**
	 * Instance function hooks.
	 */
	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}
}

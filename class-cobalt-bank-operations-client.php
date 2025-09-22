<?php
/**
 * Client for CBO Payment Gateway plugin.
 *
 * @package COBALT_BANK_OPERATIONS_Payment_Gateway
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
require_once 'class-cobalt-bank-operations-constants.php';
require_once 'class-cobalt-bank-operations-exception.php';

/**
 * Handles WooCommerce client for the payment gateway.
 */
class COBALT_BANK_OPERATIONS_Client {

	const API_V2_ROUTES = array(
		'sale'        => '/api/v2/transactions/sale',
		'transaction' => '/api/v2/transactions/',
		'checkout'    => '/api/v2/checkout',
		'refund'      => '/api/v2/transactions/refund',
	);

	/**
	 * Base API URL.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * API client ID.
	 *
	 * @var string|null
	 */
	private $client_id;

	/**
	 * API client secret.
	 *
	 * @var string|null
	 */
	private $client_secret;

	/**
	 * Authorization header/token.
	 *
	 * @var string|null
	 */
	private $authorization;

	/**
	 * Class constructor.
	 *
	 * @param string      $base_url      Base URL for API connection.
	 * @param string|null $client_id     Client identifier from the merchant.
	 * @param string|null $client_secret Client secret from the merchant.
	 * @throws COBALT_BANK_OPERATIONS_Exception When API credentials are not configured.
	 */
	public function __construct( string $base_url, ?string $client_id, ?string $client_secret ) {
		if ( empty( $base_url ) || empty( $client_id ) || empty( $client_secret ) ) {
			throw new COBALT_BANK_OPERATIONS_Exception( 'Credenciales API no configuradas.' );
		}

		$this->base_url      = $base_url;
		$this->client_id     = $client_id;
		$this->client_secret = $client_secret;
	}

	/**
	 * Sends a POST request to the CBO API.
	 *
	 * @param string $endpoint Endpoint path relative to base URL.
	 * @param array  $data     Request payload.
	 * @param bool   $login    Whether to include auth header (default true).
	 * @return array Decoded response as associative array.
	 * @throws COBALT_BANK_OPERATIONS_Exception When the HTTP request fails or API returns an error.
	 */
	private function post( string $endpoint, array $data = array(), $login = true ): array {
		if ( $login ) {
			if ( ! $this->login() ) {
				throw new COBALT_BANK_OPERATIONS_Exception( 'Could not authenticate.' );
			}
		}

		$headers = array(
			'Accept: application/json',
			'User-Agent: Cobalt-WC-Plugin ' . COBALT_BANK_OPERATIONS_Constants::PLUGIN_VERSION,
		);

		// When requesting an OAuth token, the content type must be.
		// 'application/x-www-form-urlencoded' as required by OAuth2 specifications.
		// All other endpoints use 'application/json'.
		// This prevents authentication failures and cURL errors during checkout request.
		if ( '/oauth/token' === $endpoint ) {
			$headers['Content-Type'] = 'application/x-www-form-urlencoded';
			$body                    = http_build_query( $data );
		} else {
			$headers['Content-Type'] = 'application/json';
			$body                    = wp_json_encode( $data );
		}

		if ( '/oauth/token' !== $endpoint && ! empty( $this->authorization ) ) {
			$headers['Authorization'] = str_replace( 'Authorization: ', '', $this->authorization );
		}

		$response = wp_remote_post(
			$this->base_url . $endpoint,
			array(
				'headers'     => $headers,
				'body'        => $body,
				'timeout'     => 60,
				'data_format' => 'body',
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'body'  => null,
				'code'  => 500,
				'error' => $response->get_error_message(),
			);
		}

		return array(
			'body' => json_decode( wp_remote_retrieve_body( $response ), true ),
			'code' => wp_remote_retrieve_response_code( $response ),
		);
	}

	/**
	 * Function get for endopoints.
	 *
	 * @param string $endpoint to use.
	 * @param bool   $login if is authenticate.
	 * @return array
	 * @throws COBALT_BANK_OPERATIONS_Exception If an authentication error occurs.
	 */
	private function get( string $endpoint, bool $login = true ): array {
		if ( $login ) {
			if ( ! $this->login() ) {
				throw new COBALT_BANK_OPERATIONS_Exception( 'Could not authenticate.' );
			}
		}

		$headers = array(
			'Accept: application/json',
			'User-Agent: Cobalt-WC-Plugin ' . COBALT_BANK_OPERATIONS_Constants::PLUGIN_VERSION,
		);

		if ( $this->authorization ) {
			$headers['Authorization'] = str_replace( 'Authorization: ', '', $this->authorization );
		}

		$response = wp_remote_get(
			$this->base_url . $endpoint,
			array(
				'headers' => $headers,
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'body'  => null,
				'code'  => 500,
				'error' => $response->get_error_message(),
			);
		}

		return array(
			'body' => json_decode( wp_remote_retrieve_body( $response ), true ),
			'code' => wp_remote_retrieve_response_code( $response ),
		);
	}

	/**
	 * Get route only for available
	 *
	 * @param string $action to do.
	 * @return string
	 */
	public function get_route( string $action ) {
		return self::API_V2_ROUTES[ $action ];
	}

	/**
	 * Verify if access token is expired.
	 *
	 * @return bool
	 */
	public function is_access_token_expired(): bool {
		$expires_at = intval( get_option( 'cobalt_bank_operations_expires_at', 0 ) );
		$now        = time();

		return $expires_at <= $now;
	}

	/**
	 * Client login function.
	 *
	 * @return bool
	 * @throws COBALT_BANK_OPERATIONS_Exception If an authentication error occurs.
	 */
	public function login() {
		$this->authorization = null;
		$access_token        = $this->get_access_token();
		if ( ! $access_token ) {
			throw new COBALT_BANK_OPERATIONS_Exception( 'Could not authenticate via OAuth2' );
		}

		$this->authorization = 'Authorization: Bearer ' . $access_token;
		return true;
	}

	/**
	 * Get client access token.
	 *
	 * @return false|mixed|null
	 * @throws COBALT_BANK_OPERATIONS_Exception If an authentication error occurs.
	 */
	public function get_access_token() {
		$access_token = get_option( 'cobalt_bank_operations_access_token' );
		if ( $this->is_access_token_expired() ) {
			$response = $this->post(
				'/oauth/token',
				array(
					'grant_type'    => 'client_credentials',
					'client_id'     => $this->client_id,
					'client_secret' => $this->client_secret,
				),
				false
			);

			if ( 200 === $response['code'] ) {
				$access_token = $response['body']['access_token'];
				$expires_in   = intval( $response['body']['expires_in'] );

				// access_token expiration time.
				$expires_in -= ( 60 * 5 ); // For prevention, subtract 5 minutes.
				$expires_at  = time() + $expires_in;

				update_option( 'cobalt_bank_operations_access_token', $access_token );
				update_option( 'cobalt_bank_operations_expires_at', $expires_at );
				COBALT_BANK_OPERATIONS_Log::debug( "Authentication completed: expires_in=$expires_in, access_token=$access_token" );
			} else {
				// Failed.
				COBALT_BANK_OPERATIONS_Log::error( 'Error getting access token: ' . wp_json_encode( $response ) );
				return null;
			}
		}

		return $access_token;
	}

	/**
	 * Refund function for partial or complete refunds.
	 *
	 * @param string $transaction_id to find.
	 * @param int    $amount on cents.
	 * @return array
	 * @throws COBALT_BANK_OPERATIONS_Exception If an authentication error occurs.
	 */
	public function refund( string $transaction_id, int $amount = 0 ): array {
		$parse_id = (int) $transaction_id;
		$id       = $parse_id - 130000000;
		if ( $id <= 0 ) {
			throw new COBALT_BANK_OPERATIONS_Exception( esc_html__( 'Invalid transaction ID', 'cobalt-bank-operations-payment-gateway' ) );
		}

		$route = $this->get_route( 'refund' );
		if ( empty( $route ) ) {
			throw new COBALT_BANK_OPERATIONS_Exception( esc_html__( 'Refund route not defined', 'cobalt-bank-operations-payment-gateway' ) );
		}

		$endpoint = sprintf(
			'%s/%d?amount=%d',
			$route,
			$id,
			$amount
		);

		$response = $this->get( $endpoint );
		\COBALT_BANK_OPERATIONS_Log::debug( 'Respuesta de reembolso: ' . esc_html( wp_json_encode( $response ) ) );

		if ( 200 !== $response['code'] ) {
			\COBALT_BANK_OPERATIONS_Log::error( 'Error al solicitar reembolso: ' . esc_html( wp_json_encode( $response ) ) );
			throw new COBALT_BANK_OPERATIONS_Exception( esc_html__( 'Error requesting refund', 'cobalt-bank-operations-payment-gateway' ), esc_html( wp_json_encode( $response ) ) );
		}

		$body = $response['body'];

		if ( empty( $body['status'] ) || 'ok' !== $body['status'] ) {
			$msg = ! empty( $body['message'] ) ? $body['message'] : __( 'Refund failed', 'cobalt-bank-operations-payment-gateway' );
			throw new COBALT_BANK_OPERATIONS_Exception( esc_html( $msg ), esc_html( wp_json_encode( $response ) ) );
		}

		return $body['data'];
	}

	/**
	 * Transaction fuction.
	 *
	 * @param string $id parameter.
	 * @return array
	 * @throws COBALT_BANK_OPERATIONS_Exception If an authentication error occurs.
	 */
	public function transaction( string $id ): array {
		$response = $this->get( $this->get_route( 'transaction' ) . $id );

		if ( 200 === $response['code'] ) {
			return $response['body']['data'];
		} else {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new COBALT_BANK_OPERATIONS_Exception( 'Error processing payment', wp_json_encode( $response ) );
		}
	}


	/**
	 * Checkout function.
	 *
	 * @param WC_Order $order parameter.
	 * @param string   $payment_type parameter.
	 * @return mixed
	 * @throws COBALT_BANK_OPERATIONS_Exception If an authentication error occurs.
	 */
	public function checkout( WC_Order $order, $payment_type ) {

		\COBALT_BANK_OPERATIONS_Log::debug( 'Order ID: ' . $order->get_id() );

		$tax               = $order->get_total_tax() * 100;
		$total             = $order->get_total() * 100;
		$total_without_tax = $total - $tax;
		$body              = array(
			'metadatas'     => array(
				'entry'        => get_bloginfo( 'name' ) . ' - Plugin Woocommerce v' . COBALT_BANK_OPERATIONS_Constants::PLUGIN_VERSION,
				'platform'     => 'Woocommerce',
				'version'      => COBALT_BANK_OPERATIONS_Constants::PLUGIN_VERSION,
				'order_id'     => $order->get_id(),
				'payment_type' => $payment_type,
			),
			'tip'           => 0,
			'tax'           => $tax,
			'amount'        => $total_without_tax,
			'currency_code' => $order->get_currency(),
			'webhook'       => get_bloginfo( 'url' ) . '/wc-api/' . COBALT_BANK_OPERATIONS_Constants::TELERED_GATEWAY_ID,
			'source'        => get_bloginfo( 'url' ),
			'return_url'    => wc_get_cart_url(),
			'url_ok'        => get_bloginfo( 'url' ) . '/wc-api/' . COBALT_BANK_OPERATIONS_Constants::TELERED_GATEWAY_ID . '_status?oid=' . $order->get_id(),
			'url_ko'        => get_bloginfo( 'url' ) . '/wc-api/' . COBALT_BANK_OPERATIONS_Constants::TELERED_GATEWAY_ID . '_status?oid=' . $order->get_id(),

		);

		$response = $this->post( $this->get_route( 'checkout' ), $body );
		\COBALT_BANK_OPERATIONS_Log::debug( 'Response: ' . wp_json_encode( $response ) );
		if ( 200 === $response['code'] ) {
			return $response['body']['data'];
		} else {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new COBALT_BANK_OPERATIONS_Exception( 'Error processing payment', wp_json_encode( $response ) );
		}
	}

	/**
	 * Processes a payment transaction with provided card details.
	 *
	 * @param WC_Order $order         WooCommerce order object.
	 * @param string   $card_number   Credit card number.
	 * @param string   $expiry_date   Card expiration date (MM/YY or MM/YYYY).
	 * @param string   $cvv           Card CVV code.
	 * @param string   $card_holder   Name of the card holder.
	 * @param array    $three_ds_params Optional. Parameters related to 3D Secure authentication.
	 * @param array    $metadatas     Optional. Additional metadata to include in the transaction.
	 *
	 * @return array $response        Response data from the transaction.
	 *
	 * @throws COBALT_BANK_OPERATIONS_Exception If the transaction fails or is invalid.
	 */
	public function sale( WC_Order $order, $card_number, $expiry_date, $cvv, $card_holder, $three_ds_params = array(), $metadatas = array() ) {

		\COBALT_BANK_OPERATIONS_Log::debug( 'Order ID: ' . $order->get_id() );

		$tax               = $order->get_total_tax() * 100;
		$total             = $order->get_total() * 100;
		$total_without_tax = $total - $tax;

		$final_metadatas = array(
			'entry'             => get_bloginfo( 'name' ) . ' - Plugin Woocommerce v' . COBALT_BANK_OPERATIONS_Constants::PLUGIN_VERSION,
			'platform'          => 'Woocommerce',
			'version'           => COBALT_BANK_OPERATIONS_Constants::PLUGIN_VERSION,
			'order_id'          => $order->get_id(),
			'payment_reference' => $order->get_id(),
			'source'            => get_bloginfo( 'url' ),
		);

		$final_metadatas = array_merge( $final_metadatas, $metadatas );

		$callback = home_url(
			'/wc-api/'
			. COBALT_BANK_OPERATIONS_Constants::STANDARD_GATEWAY_ID
			. '_status?oid='
			. $order->get_id()
		);

		$body = array(
			'metas'         => $final_metadatas,
			'tip'           => 0,
			'tax'           => $tax,
			'amount'        => $total_without_tax,
			'currency_code' => $order->get_currency(),
			'pan'           => $card_number,
			'exp_date'      => $expiry_date,
			'cvv2'          => $cvv,
			'card_holder'   => $card_holder,
			'3ds_params'    => $three_ds_params,
			'url_ok'        => $callback,
			'url_ko'        => $callback,
			'webhook'       => get_bloginfo( 'url' ) . '/wc-api/' . COBALT_BANK_OPERATIONS_Constants::STANDARD_GATEWAY_ID,
		);

		$response = $this->post( $this->get_route( 'sale' ), $body );
		\COBALT_BANK_OPERATIONS_Log::debug( 'Response: ' . wp_json_encode( $response ) );
		if ( 200 === $response['code'] ) {
			return $response['body']['data'];
		} else {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new COBALT_BANK_OPERATIONS_Exception( 'Error processing payment', wp_json_encode( $response ) );
		}
	}

	/**
	 * Commerce function.
	 *
	 * @return mixed
	 * @throws COBALT_BANK_OPERATIONS_Exception If an authentication error occurs.
	 */
	public function commerce() {
		$response = $this->get( '/checkout' );

		if ( 200 === $response['code'] ) {
			return $response['body']['data'];
		} else {
			throw new COBALT_BANK_OPERATIONS_Exception( esc_html__( 'Error getting commerce', 'cobalt-bank-operations-payment-gateway' ), esc_html( wp_json_encode( $response ) ) );
		}
	}
}

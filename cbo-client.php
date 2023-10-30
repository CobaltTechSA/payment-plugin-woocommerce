<?php

include_once 'cbo-constants.php';
class CBOClient {

	/** @var string */
	private $baseUrl;

	/** @var string */
	private $apiKey;

	/**
	 * @param string $baseUrl
	 * @param string $apiKey
	 */
	public function __construct( string $baseUrl, string $apiKey ) {
		$this->baseUrl = $baseUrl;
		$this->apiKey  = $apiKey;
	}

	/**
	 * @param string $endpoint
	 * @param array $data
	 *
	 * @return array
	 */
	private function post( string $endpoint, array $data = [] ): array {
		$ch = curl_init( $this->baseUrl . $endpoint );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Authorization: Bearer ' . $this->apiKey,
			'Accept: application/json'
		] );

		$response = curl_exec( $ch );

		$data = [
			'body' => json_decode($response, true),
			'code' => curl_getinfo( $ch, CURLINFO_HTTP_CODE ),
		];

		curl_close( $ch );
		return $data;
	}

	/**
	 * @param string $endpoint
	 *
	 * @return array
	 */
	private function get(string $endpoint): array {
		$ch = curl_init( $this->baseUrl . $endpoint );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $this->apiKey,
			'Accept: application/json'
			]);

		$response = curl_exec( $ch );

		$data = [
			'body' => json_decode($response, true),
			'code' => curl_getinfo( $ch, CURLINFO_HTTP_CODE ),
		];

		curl_close( $ch );
		return $data;
	}

	/**
	 * @param string $id
	 *
	 * @return array
	 * @throws CBOException
	 */
	public function transaction(string $id): array {
		$response = $this->get('/api/transactions/' . $id);

		if ($response['code'] == 200) {
			return $response['body']['data'];
		} else {
			throw new CBOException('Error processing payment', $response);
		}
	}


	/**
	 * @param WC_Order $order
	 *
	 * @return mixed
	 * @throws CBOException
	 */
	public function checkout(WC_Order $order) {

		\CBOLog::debug("Order ID: " . $order->get_id());

		/*$commerceService = $this->commerce();
		if ($commerceService['status'] !== 'active') {
			throw new CBOException('Commerce service is not active', $commerceService);
		}
		$hasVisaAndMC = $commerceService['merchant_id'];
		$hasTelered = $commerceService['telered_id'];

		$onlyTelered = $hasTelered && !$hasVisaAndMC;*/

		$tax = $order->get_total_tax() * 100;
		$total = $order->get_total() * 100;
		$totalWithoutTax = $total - $tax;
		$body = [
			'metadatas' => [
				'entry' => 'e-Commerce',
				'platform' => 'Woocommerce',
				'version' => CBOConstants::PLUGIN_VERSION,
				'order_id' => $order->get_id(),
				'payment_type' => 'telered'
			],
			'tip' => 0,
			'tax' => $tax,
			'amount' => $totalWithoutTax,
			'currency_code' => $order->get_currency(),
			'webhook' => get_bloginfo('url') . "/wc-api/" . CBOConstants::TELERED_GATEWAY_ID,
			'source' => get_bloginfo('url'),
			'return_url' => wc_get_cart_url(),
			'url_ok' => get_bloginfo('url') . "/wc-api/" . CBOConstants::TELERED_GATEWAY_ID . '_status?oid=' . $order->get_id(),
			'url_ko' => get_bloginfo('url') . "/wc-api/" . CBOConstants::TELERED_GATEWAY_ID . '_status?oid=' . $order->get_id(),

		];

		/*if ($onlyTelered) {
			$body['metadatas']['card_brand'] = 'TELERED';
		}*/

		$response = $this->post('/api/checkout', $body);
		\CBOLog::debug("Response: " . json_encode($response));
		if ($response['code'] == 200) {
			return $response['body']['data'];
		} else {
			throw new CBOException('Error processing payment', $response);
		}
	}

	/**
	 * @return mixed
	 * @throws CBOException
	 */
	public function commerce() {
		$response = $this->get('/checkout');

		if ($response['code'] == 200) {
			return $response['body']['data'];
		} else {
			throw new CBOException('Error getting commerce', $response);
		}
	}

}

class CBOException extends Exception {

	/** @var array */
	private array $response;

	public function __construct(string $message, array $response) {
		parent::__construct($message);
		$this->response = $response;
	}

	/**
	 * @return array
	 */
	public function getResponse(): array {
		return $this->response;
	}

	/**
	 * @return bool
	 */
	public function isSuccessResponse(): bool {
		return $this->response['code'] == 200;
	}

}
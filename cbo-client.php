<?php

include_once 'cbo-constants.php';
class CBOClient {

    const API_V2_ROUTES = [
        'sale' => '/api/v2/transactions/sale',
        'transaction' => '/api/v2/transactions/',
        'checkout' => '/api/v2/checkout',
        'refund' => '/api/v2/transactions/refund',
    ];

	/** @var string */
	private $baseUrl;

	/** @var string */
	private $apiKey;

    private $clientId;
    private $clientSecret;

    private $authorization;


    /**
     * @param string $baseUrl
     * @param string $apiKey
     * @param string|null $clientId
     * @param string|null $clientSecret
     */
	public function __construct( string $baseUrl, string $clientId = null, string $clientSecret = null ) {
		$this->baseUrl = $baseUrl;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
	}

    /**
     * @param string $endpoint
     * @param array $data
     * @param bool $login
     * @return array
     * @throws CBOException
     */
	private function post( string $endpoint, array $data = [], $login = true): array {
        if ($login) {
            if (!$this->login()) {
                throw new CBOException("Could not authenticate.");
            }
        }

		$ch = curl_init( $this->baseUrl . $endpoint );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Cobalt-WC-Plugin ' . CBOConstants::PLUGIN_VERSION
        ];

        if ($this->authorization) {
            $headers[] = $this->authorization;
        }

		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

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
     * @param bool $login
     * @return array
     * @throws CBOException
     */
	private function get(string $endpoint, bool $login = true): array {
        if ($login) {
            if (!$this->login()) {
                throw new CBOException("Could not authenticate.");
            }
        }

		$ch = curl_init( $this->baseUrl . $endpoint );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        $headers = [
            'Accept: application/json',
            'User-Agent: Cobalt-WC-Plugin ' . CBOConstants::PLUGIN_VERSION
        ];

        if ($this->authorization) {
            $headers[] = $this->authorization;
        }

        curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

		$response = curl_exec( $ch );

		$data = [
			'body' => json_decode($response, true),
			'code' => curl_getinfo( $ch, CURLINFO_HTTP_CODE ),
		];

		curl_close( $ch );
		return $data;
	}

    /**
     * @param string $action
     * @return string
     */
    public function getRoute(string $action)
    {
        return self::API_V2_ROUTES[$action];
    }

    /**
     * @return bool
     */
    public function isAccessTokenExpired(): bool {
        $expiresAt = intval(get_option('expires_at', 0));
        $now = time();

        return $expiresAt <= $now;
    }

    /**
     * @return bool
     * @throws CBOException
     */
    public function login()
    {
        $this->authorization = null;
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            throw new CBOException("Could not authenticate via OAuth2");
        }

        $this->authorization = 'Authorization: Bearer ' . $accessToken;
        return true;
    }

    /**
     * @return false|mixed|null
     * @throws CBOException
     */
    public function getAccessToken()
    {
        $accessToken = get_option('access_token');
        if ($this->isAccessTokenExpired()) {
            $response = $this->post('/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ], false);

            //CBOLog::debug("response=" . json_encode($response));
            if ($response['code'] == 200) {
                $accessToken = $response['body']['access_token'];
                $expiresIn = intval($response['body']['expires_in']);

                //AccessToken expiration time
                $expiresIn -= (60 * 5); //For prevention, subtract 5 minutes
                $expiresAt = time() + $expiresIn;
                CBOLog::debug("Authentication completed: expiresIn=$expiresIn, accessToken=$accessToken");
            } else {
                //Failed
                CBOLog::error("Error getting access token: " . json_encode($response));
                return null;
            }

            if (!$accessToken) {
                //New Option
                add_option('access_token', $accessToken);
                add_option('expires_at', $expiresAt);
            } else {
                //Update option
                update_option('access_token', $accessToken);
                update_option('expires_at', $expiresAt);
            }
        }

        //CBOLog::debug("API Access Token received: $accessToken");
        return $accessToken;

    }

     /**
     * @param string $transactionId
     * @param int    $amount        // en centavos
     * @return array               
     * @throws CBOException
     */
    public function refund(string $transactionId, int $amount = 0): array
    { 
        $parseId = (int) $transactionId;
        $id = $parseId - 130000000;
        if ($id <= 0) {
            throw new CBOException('ID de transacción inválido');
        }

        $route = $this->getRoute('refund'); 
        if (empty($route)) {
            throw new CBOException('Ruta de reembolso no definida');
        }
      
        $endpoint = sprintf(
            '%s/%d?amount=%d',
            $route,
            $id,
            $amount
        );

        $response = $this->get($endpoint);
        \CBOLog::debug("Respuesta de reembolso: " . json_encode($response));


        if ($response['code'] !== 200) {
            \CBOLog::error("Error al solicitar reembolso: " . json_encode($response));
            throw new CBOException('Error al solicitar reembolso', $response);
        }

        $body = $response['body'];

        if (empty($body['status']) || $body['status'] !== 'ok') {
            $msg = $body['message'] ?? 'Reembolso fallido';
            throw new CBOException($msg, $response);
        }

        return $body['data'];
    }

	/**
	 * @param string $id
	 *
	 * @return array
	 * @throws CBOException
	 */
	public function transaction(string $id): array {
		$response = $this->get($this->getRoute('transaction') . $id);

		if ($response['code'] == 200) {
			return $response['body']['data'];
		} else {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new CBOException('Error processing payment', $response);
		}
	}


    /**
     * @param WC_Order $order
     * @param $paymentType
     * @return mixed
     * @throws CBOException
     */
	public function checkout(WC_Order $order, $paymentType) {

		\CBOLog::debug("Order ID: " . $order->get_id());

		$tax = $order->get_total_tax() * 100;
		$total = $order->get_total() * 100;
		$totalWithoutTax = $total - $tax;
		$body = [
			'metadatas' => [
				'entry' => get_bloginfo('name') . ' - Plugin Woocommerce v' . CBOConstants::PLUGIN_VERSION,
				'platform' => 'Woocommerce',
				'version' => CBOConstants::PLUGIN_VERSION,
				'order_id' => $order->get_id(),
				'payment_type' => $paymentType
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

		$response = $this->post($this->getRoute('checkout'), $body);
		\CBOLog::debug("Response: " . json_encode($response));
		if ($response['code'] == 200) {
			return $response['body']['data'];
		} else {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new CBOException('Error processing payment', $response);
		}
	}

	public function sale(WC_Order $order, $cardNumber, $expiryDate, $cvv, $cardHolder, $threeDSParams = array(), $metadatas = array()) {

		\CBOLog::debug("Order ID: " . $order->get_id());

		$tax = $order->get_total_tax() * 100;
		$total = $order->get_total() * 100;
		$totalWithoutTax = $total - $tax;

        $finalMetadatas = [
            'entry' => get_bloginfo('name') . ' - Plugin Woocommerce v' . CBOConstants::PLUGIN_VERSION,
            'platform' => 'Woocommerce',
            'version' => CBOConstants::PLUGIN_VERSION,
            'order_id' => $order->get_id(),
            'payment_reference' => $order->get_id(),
            'source' => get_bloginfo('url')
        ];

        $finalMetadatas = array_merge($finalMetadatas, $metadatas);

		$callback = home_url( "/wc-api/" 
        . CBOConstants::STANDARD_GATEWAY_ID 
        . "_status?oid=" 
        . $order->get_id()
        );

		$body = [
			'metas' => $finalMetadatas,
			'tip' => 0,
			'tax' => $tax,
			'amount' => $totalWithoutTax,
			'currency_code' => $order->get_currency(),
            'pan' => $cardNumber,
            'exp_date' => $expiryDate,
            'cvv2' => $cvv,
            'card_holder' => $cardHolder,
            '3ds_params' => $threeDSParams,
            //'return_url' => wc_get_cart_url(),
            'url_ok' => $callback,
            'url_ko' => $callback,
            'webhook' => get_bloginfo('url') . "/wc-api/" . CBOConstants::STANDARD_GATEWAY_ID,
		];

		$response = $this->post($this->getRoute('sale'), $body);
		\CBOLog::debug("Response: " . json_encode($response));
		if ($response['code'] == 200) {
			return $response['body']['data'];
		} else {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
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

	public function __construct(string $message, array $response = []) {
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

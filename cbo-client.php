<?php
if ( ! defined( 'ABSPATH' ) ) exit;
include_once 'cbo-constants.php';
class CBOPAGA_Client {

    const API_V2_ROUTES = [
        'sale' => '/api/v2/transactions/sale',
        'transaction' => '/api/v2/transactions/',
        'checkout' => '/api/v2/checkout',
        'refund' => '/api/v2/transactions/refund',
    ];

	/** @var string */
	private $baseUrl;

    private $clientId;
    private $clientSecret;

    private $authorization;


    /**
     * @param string $baseUrl
     * @param string|null $clientId
     * @param string|null $clientSecret
     */
	public function __construct( string $baseUrl, string $clientId = null, string $clientSecret = null ) {
        if (empty($baseUrl) || empty($clientId) || empty($clientSecret)) {
            throw new CBOPAGA_Exception("Credenciales API no configuradas.");
        }

		$this->baseUrl = $baseUrl;
        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
	}

    /**
     * @param string $endpoint
     * @param array $data
     * @param bool $login
     * @return array
     * @throws CBOPAGA_Exception
     */
	private function post( string $endpoint, array $data = [], $login = true): array {
        if ($login) {
            if (!$this->login()) {
                throw new CBOPAGA_Exception("Could not authenticate.");
            }
        }

        $headers = [
            'Accept: application/json',
            'User-Agent: Cobalt-WC-Plugin ' . CBOPAGA_Constants::PLUGIN_VERSION
        ];

        // When requesting an OAuth token, the content type must be 
        // 'application/x-www-form-urlencoded' as required by OAuth2 specifications.
        // All other endpoints use 'application/json'.
        // This prevents authentication failures and cURL errors during checkout request
        if ($endpoint === '/oauth/token') {
            $headers['Content-Type'] = 'application/x-www-form-urlencoded';
            $body = http_build_query($data);
        } else {
            $headers['Content-Type'] = 'application/json';
            $body = wp_json_encode($data);
        }

        if ($this->authorization && $endpoint !== '/oauth/token') {
            $headers['Authorization'] = str_replace('Authorization: ', '', $this->authorization);
        }

		$response = wp_remote_post(
            $this->baseUrl . $endpoint,
            [
                'headers'     => $headers,
                'body'        => $body,
                'timeout'     => 60,
                'data_format' => 'body',
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'body'  => null,
                'code'  => 500,
                'error' => $response->get_error_message(),
            ];
        }

        return [
            'body' => json_decode( wp_remote_retrieve_body( $response ), true ),
            'code' => wp_remote_retrieve_response_code( $response ),
        ];
	}

    /**
     * @param string $endpoint
     * @param bool $login
     * @return array
     * @throws CBOPAGA_Exception
     */
	private function get(string $endpoint, bool $login = true): array {
        if ($login) {
            if (!$this->login()) {
                throw new CBOPAGA_Exception("Could not authenticate.");
            }
        }

        $headers = [
            'Accept: application/json',
            'User-Agent: Cobalt-WC-Plugin ' . CBOPAGA_Constants::PLUGIN_VERSION
        ];

        if ($this->authorization) {
            $headers['Authorization'] = str_replace('Authorization: ', '', $this->authorization);
        }


        $response = wp_remote_get(
            $this->baseUrl . $endpoint,
            [
                'headers' => $headers,
                'timeout' => 60,
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [
                'body'  => null,
                'code'  => 500,
                'error' => $response->get_error_message(),
            ];
        }

        return [
            'body' => json_decode( wp_remote_retrieve_body( $response ), true ),
            'code' => wp_remote_retrieve_response_code( $response ),
        ];
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
        $expiresAt = intval(get_option('cbopaga_expires_at', 0));
        $now = time();

        return $expiresAt <= $now;
    }

    /**
     * @return bool
     * @throws CBOPAGA_Exception
     */
    public function login()
    {
        $this->authorization = null;
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            throw new CBOPAGA_Exception("Could not authenticate via OAuth2");
        }

        $this->authorization = 'Authorization: Bearer ' . $accessToken;
        return true;
    }

    /**
     * @return false|mixed|null
     * @throws CBOPAGA_Exception
     */
    public function getAccessToken()
    {
        $accessToken = get_option('cbopaga_access_token');
        if ($this->isAccessTokenExpired()) {
            $response = $this->post('/oauth/token', [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
            ], false);

            //CBOPAGA_Log::debug("response=" . json_encode($response));
            if ($response['code'] == 200) {
                $accessToken = $response['body']['access_token'];
                $expiresIn = intval($response['body']['expires_in']);

                //AccessToken expiration time
                $expiresIn -= (60 * 5); //For prevention, subtract 5 minutes
                $expiresAt = time() + $expiresIn;

                update_option('cbopaga_access_token', $accessToken);
                update_option('cbopaga_expires_at', $expiresAt);
                CBOPAGA_Log::debug("Authentication completed: expiresIn=$expiresIn, accessToken=$accessToken");
            } else {
                //Failed
                CBOPAGA_Log::error("Error getting access token: " . json_encode($response));
                return null;
            }
        }

        //CBOPAGA_Log::debug("API Access Token received: $accessToken");
        return $accessToken;

    }

     /**
     * @param string $transactionId
     * @param int    $amount        // en centavos
     * @return array               
     * @throws CBOPAGA_Exception
     */
    public function refund(string $transactionId, int $amount = 0): array
    { 
        $parseId = (int) $transactionId;
        $id = $parseId - 130000000;
        if ($id <= 0) {
             throw new CBOPAGA_Exception( esc_html__( 'Invalid transaction ID', 'cbo-payment-gateway' ) );
        }

        $route = $this->getRoute('refund'); 
        if (empty($route)) {
            throw new CBOPAGA_Exception( esc_html__( 'Refund route not defined', 'cbo-payment-gateway' ) );
        }
      
        $endpoint = sprintf(
            '%s/%d?amount=%d',
            $route,
            $id,
            $amount
        );

        $response = $this->get($endpoint);
         \CBOPAGA_Log::debug( 'Respuesta de reembolso: ' . esc_html( wp_json_encode( $response ) ) );


        if ($response['code'] !== 200) {
            \CBOPAGA_Log::error( 'Error al solicitar reembolso: ' . esc_html( wp_json_encode( $response ) ) );
            throw new CBOPAGA_Exception(esc_html__( 'Error requesting refund', 'cbo-payment-gateway' ), esc_html( wp_json_encode( $response ) ) );
        }

        $body = $response['body'];

        if (empty($body['status']) || $body['status'] !== 'ok') {
             $msg = ! empty($body['message']) ? $body['message'] : __( 'Refund failed', 'cbo-payment-gateway' );
            throw new CBOPAGA_Exception(esc_html($msg), esc_html( wp_json_encode( $response ) ));
        }

        return $body['data'];
    }

	/**
	 * @param string $id
	 *
	 * @return array
	 * @throws CBOPAGA_Exception
	 */
	public function transaction(string $id): array {
		$response = $this->get($this->getRoute('transaction') . $id);

		if ($response['code'] == 200) {
			return $response['body']['data'];
		} else {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new CBOPAGA_Exception('Error processing payment', $response);
		}
	}


    /**
     * @param WC_Order $order
     * @param $paymentType
     * @return mixed
     * @throws CBOPAGA_Exception
     */
	public function checkout(WC_Order $order, $paymentType) {

		\CBOPAGA_Log::debug("Order ID: " . $order->get_id());

		$tax = $order->get_total_tax() * 100;
		$total = $order->get_total() * 100;
		$totalWithoutTax = $total - $tax;
		$body = [
			'metadatas' => [
				'entry' => get_bloginfo('name') . ' - Plugin Woocommerce v' . CBOPAGA_Constants::PLUGIN_VERSION,
				'platform' => 'Woocommerce',
				'version' => CBOPAGA_Constants::PLUGIN_VERSION,
				'order_id' => $order->get_id(),
				'payment_type' => $paymentType
			],
			'tip' => 0,
			'tax' => $tax,
			'amount' => $totalWithoutTax,
			'currency_code' => $order->get_currency(),
			'webhook' => get_bloginfo('url') . "/wc-api/" . CBOPAGA_Constants::TELERED_GATEWAY_ID,
			'source' => get_bloginfo('url'),
			'return_url' => wc_get_cart_url(),
			'url_ok' => get_bloginfo('url') . "/wc-api/" . CBOPAGA_Constants::TELERED_GATEWAY_ID . '_status?oid=' . $order->get_id(),
			'url_ko' => get_bloginfo('url') . "/wc-api/" . CBOPAGA_Constants::TELERED_GATEWAY_ID . '_status?oid=' . $order->get_id(),

		];

		/*if ($onlyTelered) {
			$body['metadatas']['card_brand'] = 'TELERED';
		}*/

		$response = $this->post($this->getRoute('checkout'), $body);
		\CBOPAGA_Log::debug("Response: " . json_encode($response));
		if ($response['code'] == 200) {
			return $response['body']['data'];
		} else {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new CBOPAGA_Exception('Error processing payment', $response);
		}
	}

	public function sale(WC_Order $order, $cardNumber, $expiryDate, $cvv, $cardHolder, $threeDSParams = array(), $metadatas = array()) {

		\CBOPAGA_Log::debug("Order ID: " . $order->get_id());

		$tax = $order->get_total_tax() * 100;
		$total = $order->get_total() * 100;
		$totalWithoutTax = $total - $tax;

        $finalMetadatas = [
            'entry' => get_bloginfo('name') . ' - Plugin Woocommerce v' . CBOPAGA_Constants::PLUGIN_VERSION,
            'platform' => 'Woocommerce',
            'version' => CBOPAGA_Constants::PLUGIN_VERSION,
            'order_id' => $order->get_id(),
            'payment_reference' => $order->get_id(),
            'source' => get_bloginfo('url')
        ];

        $finalMetadatas = array_merge($finalMetadatas, $metadatas);

		$callback = home_url( "/wc-api/" 
        . CBOPAGA_Constants::STANDARD_GATEWAY_ID 
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
            'webhook' => get_bloginfo('url') . "/wc-api/" . CBOPAGA_Constants::STANDARD_GATEWAY_ID,
		];

		$response = $this->post($this->getRoute('sale'), $body);
		\CBOPAGA_Log::debug("Response: " . json_encode($response));
		if ($response['code'] == 200) {
			return $response['body']['data'];
		} else {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new CBOPAGA_Exception('Error processing payment', $response);
		}
	}

	/**
	 * @return mixed
	 * @throws CBOPAGA_Exception
	 */
	public function commerce() {
		$response = $this->get('/checkout');

		if ($response['code'] == 200) {
			return $response['body']['data'];
		} else {
            throw new CBOPAGA_Exception(esc_html__( 'Error getting commerce', 'cbo-payment-gateway' ), esc_html( wp_json_encode( $response ) ));
		}
	}

}

class CBOPAGA_Exception extends Exception {

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

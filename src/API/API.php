<?php

namespace Click2pay_Payments\API;

use Click2pay_Payments;
use Exception;

defined( 'ABSPATH' ) || exit;

abstract class API {
	/**
	 * Gateway class.
	 *
	 * @var WC_Payment_Gateway
	 */
	protected $gateway;

	/**
	 * API URL.
	 *
	 * @var string
	 */
	protected $api_url = 'https://api.click2pay.com.br/v1/';

	/**
	 * Sandbox API URL.
	 *
	 * @var string
	 */
	protected $sandbox_api_url = 'https://apisandbox.click2pay.com.br/v1/';

	/**
	 * JS Library URL.
	 *
	 * @var string
	 */
	protected $js_url = 'https://api.click2pay.com.br/c2p/integrations/public/cardc2p.js';

	/**
	 * JS Library URL.
	 *
	 * @var string
	 */
	protected $sandbox_js_url = 'https://apisandbox.click2pay.com.br/c2p/integrations/public/cardc2p.js';


	/**
	 * Constructor.
	 *
	 * @param WC_Payment_Gateway $gateway Gateway instance.
	 */
	public function __construct( $gateway = null ) {
		$this->gateway = $gateway;
	}


	/**
	 * Using the gateway in test mode?
	 *
	 * @return bool
	 */
  public function is_sandbox() {
    return 'yes' === $this->gateway->sandbox;
  }


	/**
	 * Get API URL.
	 *
	 * @return string
	 */
	public function get_api_url() {
		return $this->is_sandbox() ? $this->sandbox_api_url : $this->api_url;
	}

	/**
	 * Get JS Library URL.
	 *
	 * @return string
	 */
	public function get_js_url() {
    return $this->is_sandbox() ? $this->sandbox_js_url : $this->js_url;
	}

	/**
	 * Returns a bool that indicates if currency is amongst the supported ones.
	 *
	 * @return bool
	 */
	public function using_supported_currency() {
		return 'BRL' === get_woocommerce_currency();
	}


  /**
	 * Process API Requests.
	 *
	 * @param  string $url      URL.
   * @param  array  $data     Request data.
	 * @param  string $method   Request method.
	 * @param  array  $headers  Request headers.
	 *
	 * @return object|WP_Error            Request response.
	 */
	protected function do_request( $endpoint, $data = [], $method = 'POST', $headers = [] ) {
		$url = sanitize_url( $this->get_api_url() . $endpoint );

		// Pagar.me user-agent and api version.
		$useragent = 'click2pay-for-woocommerce/' . Click2pay_Payments::VERSION;

		if ( defined( 'WC_VERSION' ) ) {
			$useragent .= ' woocommerce/' . WC_VERSION;
		}

		$useragent .= ' wordpress/' . get_bloginfo( 'version' );
		$useragent .= ' php/' . phpversion();

		$params = [
			'method'  => $method,
			'timeout' => 60,
			'headers' => [
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
        'User-Agent'   => $useragent,
        'Authorization' => 'Basic ' . base64_encode( sanitize_text_field( $this->gateway->client_id ) . ':' . sanitize_text_field( $this->gateway->client_secret ) ),
			],
		];

		if ( 'POST' === $method && ! empty( $data ) ) {
			$params['body'] = wp_json_encode( $data );
		}

		if ( ! empty( $headers ) ) {
			$params['headers'] = wp_parse_args( $headers, $params['headers'] );
		}

    $this->gateway->log( $url . ' (' . $method . '):' . print_r( $params['body'], true ) );

		$response = wp_safe_remote_post( $url, $params );

    if ( is_wp_error( $response ) ) {
      $this->gateway->log( 'WP_Error: ' . $response->get_error_message() );

      throw new Exception( sprintf( __( 'Ocorreu um erro interno: %s', 'click2pay-pagamentos' ) ) );
    }

    if ( ! $response ) {
      $this->gateway->log( 'Erro no requisição: resposta em branco.' );

      throw new Exception( __( 'Ocorreu um erro interno. Entre em contato para obter assistência.', 'click2pay-pagamentos' ) );
    }

    $response_code = wp_remote_retrieve_response_code( $response );

    if ( 401 === $response_code ) {
      $this->gateway->log( 'Erro no requisição: credenciais inválidas' );

      throw new Exception( __( 'Ocorreu um erro de autenticação. Entre em contato para obter assitência', 'click2pay-pagamentos' ) );
    }

    return $response;
	}




	/**
	 * Notification handler.
	 */
	public function notification_handler() {
		@ob_clean();

    try {
      $this->validate_request();

      $data = json_decode( file_get_contents( 'php://input' ) );

      $this->gateway->log( 'Webhook recebido:' . print_r( $data, true ) );

      if ( $this->gateway->transaction_type !== $data->transaction_type ) {
        $this->gateway->log( 'Tipo de transação inválida para este gateway:' . $this->gateway->transaction_type );

        throw new Exception( __( 'Tipo de transação inválida', 'click2pay-pagamentos' ) );
      }

      if ( ! in_array( $data->type, [ 'PAYMENT_RECEIVED', 'PAYMENT_REFUNDED' ] ) ) {
        $this->gateway->log( 'Tipo de evento inválido:' . $data->type );

        throw new Exception( __( 'Tipo de evento inválido', 'click2pay-pagamentos' ) );
      }

      $order_id = $this->get_order_id_by_transaction( $data->tid );

      if ( ! $order_id ) {
        $this->gateway->log( 'Transação não encontrada:' . $data->tid );

        throw new Exception( __( 'Transação não encontrada', 'click2pay-pagamentos' ) );
      }

      $order = wc_get_order( $order_id );

      if ( ! $order ) {
        $this->gateway->log( 'Pedido inválido:' . $order_id );

        throw new Exception( __( 'Pedido inválido', 'click2pay-pagamentos' ) );
      }

      if ( 'PAYMENT_RECEIVED' === $data->type && 'paid' === $data->status ) {
        $this->gateway->log( 'Pedido Pago! #' . $order_id );

        if ( ! $order->is_paid() ) {
          $order->add_order_note( __( 'Notificação de pagamento recebida', 'click2pay-pagamentos' ) );
          $order->update_meta_data( '_click2pay_payment_webhook_data', $data );
          $order->payment_complete();
        } else {
          $order->add_order_note( __( 'Nova notificação de pagamento recebida', 'click2pay-pagamentos' ) );
        }

      } else if ( 'PAYMENT_REFUNDED' === $data->type ) {
        $this->gateway->log( 'Pedido reembolsado! #' . $order_id );
        // handle it!
      } else {
        $this->gateway->log( 'No handler found:' . print_r( $data, true ) );
      }

      wp_die(
        __( 'Webhook received', 'click2pay-pagamentos' ),
        __( 'Webhook response', 'click2pay-pagamentos' ),
        array( 'response' => 200, 'code' => 'success' )
      );

    } catch ( Exception $e ) {
      wp_die(
        $e->getMessage(),
        __( 'Webhook response', 'click2pay-pagamentos' ),
        array( 'response' => $e->getCode() ? $e->getCode() : 400, 'code' => 'error' )
      );
    }
	}


  private function validate_request() {
		// Validate user secret.
		if ( ! hash_equals( base64_encode( $this->gateway->client_id ), $this->get_authorization_header() ) ) { // @codingStandardsIgnoreLine
      throw new Exception( __( 'Não autorizado.', 'click2pay-pagamentos' ), 401 );
    }

    return true;
  }



	/**
	 * Get the authorization header.
	 *
	 * On certain systems and configurations, the Authorization header will be
	 * stripped out by the server or PHP. Typically this is then used to
	 * generate `PHP_AUTH_USER`/`PHP_AUTH_PASS` but not passed on. We use
	 * `getallheaders` here to try and grab it out instead.
	 *
	 * @since 3.0.0
	 *
	 * @return string Authorization header if set.
	 */
	public function get_authorization_header() {
		if ( ! empty( $_SERVER['HTTP_C2P_HASH'] ) ) {
			return wp_unslash( $_SERVER['HTTP_C2P_HASH'] ); // WPCS: sanitization ok.
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
      $k = '';
			// Check for the authoization header case-insensitively.
			foreach ( $headers as $key => $value ) {
				if ( 'c2p-hash' === strtolower( $key ) ) {
					return wp_unslash( $value );
				}
			}
		}

		return '';
  }


  public function get_order_id_by_transaction( $transaction_id ) {
    $orders = wc_get_orders([
      'transaction_id' => sanitize_text_field( $transaction_id ),
      'return' => 'ids',
    ]);

    if ( ! $orders ) {
      return 0;
    }

    return $orders[0];
  }
}

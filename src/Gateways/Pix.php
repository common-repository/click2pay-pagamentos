<?php

namespace Click2pay_Payments\Gateways;

use Click2pay_Payments;
use Click2pay_Payments\API\Pix_API;
use Click2pay_Payments\Traits\Helpers;
use Click2pay_Payments\Traits\Logger;
use Exception;
use WC_Payment_Gateway;
use WC_AJAX;

defined( 'ABSPATH' ) || exit;

class Pix extends WC_Payment_Gateway {
  use Logger, Helpers;

  public $thankyou_page_loaded = false;

  public $transaction_type;


  public $client_id;
  public $client_secret;
  public $prefix;
  public $debug;


  public $expires_in;

  public $sandbox;
  public $payment_interval;
  public $instructions;

  public $api;


  public $supports = [
    'products',
    'refunds',
  ];


	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                   = 'click2pay-pix';
		$this->transaction_type     = 'InstantPayment';
		$this->has_fields           = true;
		$this->method_title         = __( 'Click2pay - Pix', 'click2pay-pagamentos' );
		$this->method_description   = __( 'Receba pagamentos instantâneos via Pix.', 'click2pay-pagamentos' );
		// $this->view_transaction_url = 'https://beta.dashboard.pagar.me/#/transactions/%s';

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->title            = $this->get_option( 'title' );
		$this->description      = $this->get_option( 'description' );
		$this->client_id        = $this->get_option( 'client_id' );
		$this->client_secret    = $this->get_option( 'client_secret' );
    	$this->prefix           = $this->get_option( 'prefix', 'wc-' );
		$this->expires_in       = $this->get_option( 'expires_in' );
		$this->debug            = $this->get_option( 'debug' );
   		$this->sandbox          = $this->get_option( 'sandbox' );
    	$this->payment_interval = $this->get_option( 'payment_interval', 10 );

  		$this->instructions  = '';

		// Set the API.
		$this->api = new Pix_API( $this );

		// Active logs.
		if ( 'yes' === $this->debug ) {
			$this->set_logger_source( $this->id );
		}

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_before_thankyou', array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_receipt_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

		add_action( 'woocommerce_api_' . $this->id, array( $this, 'notification_handler' ) );
		add_action( 'woocommerce_api_' . $this->id . '_details', array( $this, 'pix_details_page' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );

		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'orders_actions' ), 10, 2 );

		add_filter( 'woocommerce_valid_order_statuses_for_payment', function( $needs_payment, $order ) {
			if ( $order->get_payment_method() === $this->id ) {
				$needs_payment[] = 'on-hold';
			}

			return $needs_payment;
		}, 500, 2 );
	}

	/**
	 * Check if the gateway is available to take payments.
	 *
	 * @return bool
	 */
	public function is_available() {
		return parent::is_available() && ! empty( $this->client_id ) && ! empty( $this->client_secret ) && $this->api->using_supported_currency() && ! is_add_payment_method_page();
	}

	/**
	 * Settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Ativar método', 'click2pay-pagamentos' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar recebimentos via Pix', 'click2pay-pagamentos' ),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __( 'Título', 'click2pay-pagamentos' ),
				'type'        => 'text',
				'description' => __( 'Isto mostra o título que o usuário vai ver no checkout.', 'click2pay-pagamentos' ),
				'desc_tip'    => true,
				'default'     => __( 'Pagamento via Pix', 'click2pay-pagamentos' ),
			),
			'description' => array(
				'title'       => __( 'Descrição', 'click2pay-pagamentos' ),
				'type'        => 'textarea',
				'description' => __( 'A descrição do pagamento que é exibida no checkout.', 'click2pay-pagamentos' ),
				'desc_tip'    => true,
				'default'     => __( 'Pagamento instantâneo via Pix', 'click2pay-pagamentos' ),
			),
			'expires_in' => array(
				'title'             => __( 'Validade do Pix, em minutos', 'click2pay-pagamentos' ),
				'type'              => 'number',
				'description'       => __( 'Após esse período não será mais possível realizar o pagamento. Padrão 1440 (24 horas).', 'click2pay-pagamentos' ),
				'default'           => 1440,
				'custom_attributes' => array(
					'required' => 'required',
          'min'      => 20,
          'step'     => 10,
				),
			),
			'integration' => array(
				'title'       => __( 'Configurações de Integração', 'click2pay-pagamentos' ),
				'type'        => 'title',
				'description' => '',
			),
			'client_id' => array(
				'title'             => __( 'Client ID', 'click2pay-pagamentos' ),
				'type'              => 'text',
				'description'       => __( 'Chave fornecida pela Click2pay', 'click2pay-pagamentos' ),
				'default'           => '',
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			'client_secret' => array(
				'title'             => __( 'Client Secret', 'click2pay-pagamentos' ),
				'type'              => 'text',
				'description'       => __( 'Chave fornecida pela Click2pay', 'click2pay-pagamentos' ),
				'default'           => '',
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			'prefix' => array(
				'title'             => __( 'Prefixo do pedido', 'click2pay-pagamentos' ),
				'type'              => 'text',
				'description'       => __( 'Adicione um prefixo único ao ID do pedido enviado à Click2Pay.', 'click2pay-pagamentos' ),
				'default'           => 'wc-',
				'custom_attributes' => array(
					'required' => 'required',
				),
			),
			'payment_interval' => array(
				'title'             => __( 'Intervalo para verificação automática de pagamento (segundos)', 'click2pay-pagamentos' ),
				'type'              => 'number',
				'description'       => __( 'Identificar automaticamente o pagamento via Pix, redirecionando o usuário para a página de obrigado. Uma pequena requisição é feita ao seu servidor nesse intervalo. Deixe em branco para desativar', 'click2pay-pagamentos' ),
				'default'           => 10,
				'custom_attributes' => array(
          'min'      => 5,
          'step'     => 1,
				),
			),
			'testing' => array(
				'title'       => __( 'Teste do Gateway', 'click2pay-pagamentos' ),
				'type'        => 'title',
				'description' => '',
			),
			'debug' => array(
				'title'       => __( 'Log de depuração', 'click2pay-pagamentos' ),
				'type'        => 'checkbox',
				'label'       => __( 'Habilitar logs', 'click2pay-pagamentos' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Registra eventos deste método de pagamento, como requisições na API. Você pode verificar o log em %s', 'click2pay-pagamentos' ), '<a target="_blank" href="' . esc_url( admin_url( 'admin.php?page=wc-status&tab=logs&log_file=' . esc_attr( $this->id ) . '-' . sanitize_file_name( wp_hash( $this->id ) ) . '.log' ) ) . '">' . __( 'Status do Sistema &gt; Logs', 'click2pay-pagamentos' ) . '</a>' ),
			),
			'sandbox' => array(
				'title'       => __( 'Sandbox', 'click2pay-pagamentos' ),
				'type'        => 'checkbox',
				'label'       => __( 'Usar plugin em modo de testes', 'click2pay-pagamentos' ),
				'default'     => 'no',
				'description' => __( 'Neste caso, as transações não serão realmente processadas. Utilize dados de teste.', 'click2pay-pagamentos' ),
			),
		);
	}

	/**
	 * Payment fields.
	 */
	public function payment_fields() {
		if ( $description = $this->get_description() ) {
			echo wp_kses_post( wpautop( wptexturize( $description ) ) );
		}
	}

	/**
	 * Process the payment.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array Redirect data.
	 */
	public function process_payment( $order_id ) {
    $order = wc_get_order( $order_id );

		$response_data = $this->api->create_transaction( $order );

    if ( isset( $response_data->error, $response_data->errorDescription ) ) {
      throw new Exception( sprintf( __( 'Ocorreu um erro ao processar sua solicitação: %s', 'click2pay-pagamentos' ), $response_data->errorDescription ) );
    }

    $data = $response_data->data;

    $order->set_transaction_id( $data->tid );
    $order->update_meta_data( '_click2pay_external_identifier', $data->externalIdentifier );
    $order->update_meta_data( '_click2pay_data', $data );
    $order->update_meta_data( '_click2pay_pix_copy_paste', $data->pix->textPayment );
    $order->update_meta_data( '_click2pay_pix_image', $data->pix->qrCodeImage->base64 );
    $order->update_meta_data( '_click2pay_pix_minutes_to_expire', $this->expires_in );

    $order->set_status( 'on-hold', sprintf( __( 'Pagamento iniciado com Pix. Link de pagamento do cliente: <code>%s</code>', 'click2pay-pagamentos' ), '<a target="_blank" href="' . $this->get_pix_details_page( $order ) . '">' . __( 'Acessar página', 'click2pay-pagamentos' ) . '</a>' ) );

    $order->save();

    return [
      'result'   => 'success',
      'redirect' => $this->get_return_url( $order ),
    ];
	}




	/**
	 * Process refund.
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund.
	 * a passed in amount.
	 *
	 * @param  int        $order_id Order ID.
	 * @param  float|null $amount Refund amount.
	 * @param  string     $reason Refund reason.
	 * @return boolean True or false based on success, or a WP_Error object.
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
    $order = wc_get_order( $order_id );
		$response_data = $this->api->refund_transaction( $order, $amount );

    if ( isset( $response_data->error, $response_data->errorDescription ) ) {
      throw new Exception( sprintf( __( 'Ocorreu um erro ao processar o reembolso: %s', 'click2pay-pagamentos' ), $response_data->errorDescription ) );
    }

    $order->add_order_note( sprintf( __( 'Processado reembolso automático de %s. %s', 'click2pay-pagamentos' ), wc_price( $amount ), $reason ) );

    $order->update_meta_data( '_click2pay_refund_data', $response_data );
    $order->save();

		return true;
	}


	/**
	 * Thank You page message.
	 *
	 * @param int $order_id Order ID.
	 */
	public function thankyou_page( $order_id ) {
		if ($this->thankyou_page_loaded) {
			return;
		}

		$this->thankyou_page_loaded = true;

		$order = wc_get_order( $order_id );

    if ( ! $order ) {
      return;
    }

    if ( $this->id !== $order->get_payment_method() ) {
      return;
    }

    if ( $order->is_paid() ) {
      return;
    }

    wp_enqueue_script( 'click2pay-pix' );

		wc_get_template(
			'checkout/click2pay/pix.php',
			[
        'id' => $this->id,
        'instructions' => $this->instructions,
        'is_email' => false,
        'order' => $order,
        'payload' => $order->get_meta( '_click2pay_pix_copy_paste' ),
        'pix_image' => $order->get_meta( '_click2pay_pix_image' ),
        'pix_minutes_to_expire' => $order->get_meta( '_click2pay_pix_minutes_to_expire' ),
        'pix_details_page' => $this->get_pix_details_page( $order ),
      ],
			'',
			Click2pay_Payments::get_templates_path()
		);
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param  object $order         Order object.
	 * @param  bool   $sent_to_admin Send to admin.
	 * @param  bool   $plain_text    Plain text or HTML.
	 *
	 * @return string                Payment instructions.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $sent_to_admin || ! $order->has_status( [ 'on-hold' ] ) || $this->id !== $order->get_payment_method() ) {
			return;
		}

		wc_get_template(
			'checkout/click2pay/pix.php',
			[
        'id' => $this->id,
        'instructions' => $this->instructions,
        'is_email' => true,
        'order' => $order,
        'payload' => $order->get_meta( '_click2pay_pix_copy_paste' ),
        'pix_image' => $order->get_meta( '_click2pay_pix_image' ),
        'pix_minutes_to_expire' => $order->get_meta( '_click2pay_pix_minutes_to_expire' ),
        'pix_details_page' => $this->get_pix_details_page( $order ),
      ],
			'',
			Click2pay_Payments::get_templates_path()
		);
	}

	/**
	 * Notification handler.
	 */
	public function notification_handler() {
		$this->api->notification_handler();
	}


  public function wp_enqueue_scripts() {
    wp_register_script(
      'click2pay-clipboard',
      Click2pay_Payments::plugin_url() . '/assets/vendor/clipboard.min.js',
      [ 'jquery' ],
      '2.0.10',
      true
    );

    wp_register_script(
      $this->id,
      Click2pay_Payments::plugin_url() . '/assets/js/gateways/pix.js',
      [ 'click2pay-clipboard' ],
      Click2pay_Payments::VERSION,
      true
    );

    $params = [
      'method_id'  => $this->id,
      'notices' => [
        'qr_code_copied' => __( 'Copiado!', 'click2pay-pagamentos' ),
      ],
    ];

    global $wp;

    if ( isset( $wp->query_vars['order-pay'] ) && ! empty( $_GET['key'] ) && is_numeric( $this->payment_interval ) ) {
      $params['interval'] = $this->payment_interval;
      $params['wc_ajax_url'] = WC_AJAX::get_endpoint( '%%endpoint%%' );
      $params['orderId'] = intval( $wp->query_vars['order-pay'] );
      $params['orderKey'] = sanitize_text_field( wp_unslash( $_GET['key'] ) );
    }

		wp_localize_script(
			$this->id,
			'Click2PayPixParams',
			$params
		);
  }


  public function pix_details_page() {
		try {

			if ( ! isset( $_GET['id'], $_GET['key'] ) ) {
				throw new \Exception( __( 'URL inválida' ) );
			}

			$order_id = sanitize_text_field( wp_unslash( $_GET['id'] ) );
			$order_key = sanitize_text_field( wp_unslash( $_GET['key'] ) );

			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				throw new \Exception( __( 'Pedido não encontrado' ) );
			}

			if ( $order->get_order_key() !== $order_key ) {
				throw new \Exception( __( 'Sem permissões para visualizar esta página' ) );
			}

      ob_start();

			get_header();

			echo '<div class="woocommerce-thankyou-order-received"></div>';

      echo '<style>.wd-prefooter, .footer-container {
        display: none !important;
      }</style>';

			$this->thankyou_page( $order->get_id() );

			get_footer();

      $template = ob_get_clean();

      echo do_shortcode( $template );

			exit;

		} catch (\Exception $e) {
			wp_die( $e->getMessage() );
		}
  }



  public function get_pix_details_page( $order ) {
    return apply_filters(
      'click2pay_pix_url',
      add_query_arg(
        array(
          'id'  => $order->get_id(),
          'key' => $order->get_order_key(),
        ),
        WC()->api_request_url( $this->id . '_details' )
      ),
      $order
    );
  }


  public function orders_actions( $actions, $order ) {
    if ( $this->id !== $order->get_payment_method() ) {
      return $actions;
    }

    if ( ! $order->has_status( [ 'on-hold' ] ) ) {
      return $actions;
    }

    $expires_in = $order->get_meta( '_click2pay_pix_minutes_to_expire' );

    if ( ! $expires_in ) {
      return $actions;
    }

    $created_at = $order->get_date_created();

    $now = new \WC_DateTime();

    $created_at->modify( '+' . $expires_in . ' minutes' );

    if ( '+' === $created_at->diff( $now )->format( '%R' ) ) {
      return $actions;
    }

    $actions[ $this->id ] = array(
			'url'  => $this->get_pix_details_page( $order ),
			'name' => __( 'Acessar Pix', 'click2pay-pagamentos' ),
		);

    return $actions;
  }
}

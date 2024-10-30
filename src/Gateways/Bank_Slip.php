<?php

namespace Click2pay_Payments\Gateways;

use Click2pay_Payments;
use Click2pay_Payments\API\Bank_Slip_API;
use Click2pay_Payments\Traits\Helpers;
use Click2pay_Payments\Traits\Logger;
use Exception;
use WC_Payment_Gateway;

defined( 'ABSPATH' ) || exit;

class Bank_Slip extends WC_Payment_Gateway {
  use Logger, Helpers;

  public $supports = [
    'products',
  ];

  public $transaction_type;

  public $client_id;

  public $client_secret;

  public $prefix;

  public $expires_in;

  public $logo;

  public $instructions_1;

  public $instructions_2;

  public $debug;

  public $sandbox;

  public $instructions;

  public $api;


  public $thankyou_page_loaded = false;


	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                   = 'click2pay-bank-slip';
    $this->transaction_type     = 'BankSlip';
		$this->has_fields           = true;
		$this->method_title         = __( 'Click2pay - Boleto', 'click2pay-pagamentos' );
		$this->method_description   = __( 'Receba pagamentos via Boleto.', 'click2pay-pagamentos' );

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->client_id      = $this->get_option( 'client_id' );
		$this->client_secret  = $this->get_option( 'client_secret' );
		$this->prefix         = $this->get_option( 'prefix', 'wc-' );
		$this->expires_in     = $this->get_option( 'expires_in' );
		$this->logo           = $this->get_option( 'logo' );
		$this->instructions_1 = $this->get_option( 'instructions_1' );
		$this->instructions_2 = $this->get_option( 'instructions_2' );
		$this->debug          = $this->get_option( 'debug' );
		$this->sandbox        = $this->get_option( 'sandbox' );

	    $this->instructions   = '';

		// Set the API.
		$this->api = new Bank_Slip_API( $this );

		// Active logs.
		if ( 'yes' === $this->debug ) {
			$this->set_logger_source( $this->id );
		}

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_before_thankyou', array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
		add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );

		add_action( 'woocommerce_api_' . $this->id, array( $this, 'notification_handler' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );

		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'orders_actions' ), 10, 2 );
	}

	/**
	 * Check if the gateway is available to take payments.
	 *
	 * @return bool
	 */
	public function is_available() {
		return parent::is_available() && ! empty( $this->client_id ) && ! empty( $this->client_secret ) && $this->api->using_supported_currency();
	}

	/**
	 * Settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Ativar método', 'click2pay-pagamentos' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar recebimentos via Boleto', 'click2pay-pagamentos' ),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __( 'Título', 'click2pay-pagamentos' ),
				'type'        => 'text',
				'description' => __( 'Isto mostra o título que o usuário vai ver no checkout.', 'click2pay-pagamentos' ),
				'desc_tip'    => true,
				'default'     => __( 'Pagamento com boleto', 'click2pay-pagamentos' ),
			),
			'description' => array(
				'title'       => __( 'Descrição', 'click2pay-pagamentos' ),
				'type'        => 'textarea',
				'description' => __( 'A descrição do pagamento que é exibida no checkout.', 'click2pay-pagamentos' ),
				'desc_tip'    => true,
				'default'     => __( 'Pague com boleto pelo internet banking ou lotéricas. O processamento da compra pode demorar até 2 dias úteis.', 'click2pay-pagamentos' ),
			),
      'expires_in' => array(
				'title'             => __( 'Dias até o vencimento', 'click2pay-pagamentos' ),
				'type'              => 'number',
				'description'       => __( 'Prazo para vencimento do boleto.', 'click2pay-pagamentos' ),
				'default'           => 3,
				'custom_attributes' => array(
					'required' => 'required',
          'min'      => 1,
				),
			),
      'logo' => array(
				'title'             => __( 'URL do logotipo',  'click2pay-pagamentos' ),
				'type'              => 'text',
				'description'       => __( 'Exibido no boleto. Formatos aceitos: (png, svg, jpg, gif). Altura máxima: 100px. Largura máxima: 200px.', 'click2pay-pagamentos' ),
				'default'           => '',
			),
      'instructions_1' => array(
				'title'             => __( 'Instruções - Linha 1',  'click2pay-pagamentos' ),
				'type'              => 'text',
				'description'       => __( 'Instruções para o pagamento exibidas no boleto.', 'click2pay-pagamentos' ),
				'default'           => __( 'Não receber após vencimento.', 'click2pay-pagamentos' ),
			),
      'instructions_2' => array(
				'title'             => __( 'Instruções - Linha 2', 'click2pay-pagamentos' ),
				'type'              => 'text',
				'description'       => __( 'Instruções para o pagamento exibidas no boleto.', 'click2pay-pagamentos' ),
				'default'           => '',
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

    $order->set_transaction_id( sanitize_text_field( $data->tid ) );
    $order->update_meta_data( '_click2pay_external_identifier', sanitize_text_field( $data->externalIdentifier ) );
    $order->update_meta_data( '_click2pay_data', $data );
    $order->update_meta_data( '_click2pay_boleto_barcode', sanitize_text_field( $data->boleto->barcode ) );
    $order->update_meta_data( '_click2pay_boleto_url', sanitize_url( $data->boleto->url ) );
    $order->update_meta_data( '_click2pay_boleto_due_date', sanitize_text_field( $data->boleto->due_date ) );

    $order->set_status( 'on-hold', sprintf( __( 'Pagamento iniciado com Boleto. Link de pagamento do cliente: <code>%s</code>', 'click2pay-pagamentos' ), '<a target="_blank" href="' . esc_url( $data->boleto->url ) . '">' . __( 'Acessar página', 'click2pay-pagamentos' ) . '</a>' ) );

    $order->save();

    return [
      'result'   => 'success',
      'redirect' => $this->get_return_url( $order ),
    ];
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

    wp_enqueue_script( 'click2pay-bank-slip' );

		wc_get_template(
			'checkout/click2pay/bank-slip.php',
			[
        'id' => $this->id,
        'is_email' => false,
        'order' => $order,
        'bank_slip_barcode' => $order->get_meta( '_click2pay_boleto_barcode' ),
        'bank_slip_url' => $order->get_meta( '_click2pay_boleto_url' ),
        'instructions' => $this->instructions,
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
			'checkout/click2pay/bank-slip.php',
			[
        'id' => $this->id,
        'is_email' => true,
        'order' => $order,
        'bank_slip_barcode' => $order->get_meta( '_click2pay_boleto_barcode' ),
        'bank_slip_url' => $order->get_meta( '_click2pay_boleto_url' ),
        'instructions' => $this->instructions,
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
      'click2pay-bank-slip',
      Click2pay_Payments::plugin_url() . '/assets/js/gateways/bank-slip.js',
      [ 'click2pay-clipboard' ],
      Click2pay_Payments::VERSION,
      true
    );
  }


  public function orders_actions( $actions, $order ) {
    if ( $this->id !== $order->get_payment_method() ) {
      return $actions;
    }

    if ( ! $order->has_status( [ 'on-hold' ] ) ) {
      return $actions;
    }

    $url = $order->get_meta( '_click2pay_boleto_url' );

    if ( ! $url ) {
      return $actions;
    }

    $actions[ $this->id ] = array(
			'url'  => esc_url( $url ),
			'name' => __( 'Ver boleto', 'click2pay-pagamentos' ),
		);

    return $actions;
  }
}

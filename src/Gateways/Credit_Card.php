<?php

namespace Click2pay_Payments\Gateways;

use Click2pay_Payments;
use Click2pay_Payments\API\Credit_Card_API;
use Click2pay_Payments\Credit_Card\WC_Payment_Token_CC_Click2pay;
use Click2pay_Payments\Traits\Helpers;
use Click2pay_Payments\Traits\Logger;
use Click2pay_Payments\Traits\Subscriptions;
use Exception;
use WC_Payment_Gateway_CC;
use WC_Order_Item_Fee;

defined( 'ABSPATH' ) || exit;

class Credit_Card extends WC_Payment_Gateway_CC {
  use Logger, Helpers, Subscriptions;

  public $supports = [
    'products',
    'refunds',
    'tokenization',
  // 'add_payment_method',
  ];


  public $transaction_type;

  public $client_id;
  public $client_secret;
  public $public_key;
  public $prefix;
  public $debug;
  public $sandbox;
  public $soft_descriptor;

  public $max_installment;
  public $smallest_installment;
  public $interest_rate;
  public $free_installments;

  public $fee_name;
  public $instructions;

  public $api;


	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                   = 'click2pay-credit-card';
    $this->transaction_type     = 'CreditCard';
		$this->has_fields           = true;
		$this->method_title         = __( 'Click2pay - Cartão de crédito', 'click2pay-pagamentos' );
		$this->method_description   = __( 'Receba pagamentos em até 12x via cartão de crédito.', 'click2pay-pagamentos' );
		// $this->view_transaction_url = 'https://beta.dashboard.pagar.me/#/transactions/%s';

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->title         = $this->get_option( 'title' );
		$this->description   = $this->get_option( 'description' );
		$this->client_id     = $this->get_option( 'client_id' );
		$this->client_secret = $this->get_option( 'client_secret' );
		$this->public_key    = $this->get_option( 'public_key' );
    $this->prefix        = $this->get_option( 'prefix', 'wc-' );
		$this->debug         = $this->get_option( 'debug' );
    $this->sandbox       = $this->get_option( 'sandbox' );

    $this->soft_descriptor      = $this->get_option( 'soft_descriptor' );
    $this->max_installment      = $this->get_option( 'max_installment', 3 );
		$this->smallest_installment = $this->get_option( 'smallest_installment', 5 );
		$this->interest_rate        = $this->get_option( 'interest_rate', '0' );
		$this->free_installments    = $this->get_option( 'free_installments', '1' );
		$this->fee_name             = __( 'Taxa do parcelmento com juros', 'click2pay-pagamentos' );

    $this->instructions  = '';

		// Set the API.
		$this->api = new Credit_Card_API( $this );

		// Active logs.
		if ( 'yes' === $this->debug ) {
			$this->set_logger_source( $this->id );
		}

		// Actions.
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

    add_filter( 'woocommerce_credit_card_form_fields', array( $this, 'add_brazilian_fields' ), 10, 2 );

		add_action( 'woocommerce_before_thankyou', array( $this, 'thankyou_page' ) );

    add_action( 'woocommerce_api_' . $this->id, array( $this, 'notification_handler' ) );

    add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );

    // hide card fees from customer
    add_filter( 'woocommerce_get_order_item_totals', array( $this, 'adjust_card_fees_display' ), 10, 2 );

    add_filter( 'option_wcbcf_settings', [ $this, 'maybe_make_birthdate_required' ] );
	}

	/**
	 * Check if the gateway is available to take payments.
	 *
	 * @return bool
	 */
	public function is_available() {
		return parent::is_available() && ! empty( $this->client_id ) && ! empty( $this->client_secret ) && ! empty( $this->public_key ) && $this->api->using_supported_currency() && ! is_add_payment_method_page();
	}

	/**
	 * Settings fields.
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Ativar método', 'click2pay-pagamentos' ),
				'type'    => 'checkbox',
				'label'   => __( 'Habilitar recebimentos via Cartão de crédito', 'click2pay-pagamentos' ),
				'default' => 'no',
			),
			'title' => array(
				'title'       => __( 'Título', 'click2pay-pagamentos' ),
				'type'        => 'text',
				'description' => __( 'Isto mostra o título que o usuário vai ver no checkout.', 'click2pay-pagamentos' ),
				'desc_tip'    => true,
				'default'     => __( 'Cartão de Crédito - Click2pay', 'click2pay-pagamentos' ),
			),
			'description' => array(
        'title'       => __( 'Descrição', 'click2pay-pagamentos' ),
				'type'        => 'textarea',
				'description' => __( 'A descrição do pagamento que é exibida no checkout.', 'click2pay-pagamentos' ),
				'desc_tip'    => true,
				'default'     => __( 'Pagamento via cartão de crédito', 'click2pay-pagamentos' ),
			),
      'soft_descriptor' => array(
        'title'       => __( 'Descrição na fatura', 'click2pay-pagamentos' ),
        'type'        => 'text',
        'description' => __( 'O nome da cobrança que aparecerá na fatura do cartão do cliente. Máximo de 50 caracteres.', 'click2pay-pagamentos' ),
        'desc_tip'    => true,

        'default'     => esc_attr( get_bloginfo( 'name', 'display' ) ),
				'custom_attributes' => array(
					'required' => 'required',
          'maxlength' => 50,
				),
      ),
      'max_installment' => array(
				'title'       => __( 'Número de parcelas', 'click2pay-pagamentos' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'default'     => '12',
				'description' => __( 'Número máximo de parcelas que é possível com pagamentos por cartão de crédito.', 'click2pay-pagamentos' ),
				'desc_tip'    => true,
				'options'     => array(
					'1'  => '1',
					'2'  => '2',
					'3'  => '3',
					'4'  => '4',
					'5'  => '5',
					'6'  => '6',
					'7'  => '7',
					'8'  => '8',
					'9'  => '9',
					'10' => '10',
					'11' => '11',
					'12' => '12',
				),
			),
			'smallest_installment' => array(
				'title'       => __( 'Parcela mínima', 'click2pay-pagamentos' ),
				'type'        => 'text',
				'description' => __( 'Informe o valor mínimo da parcela. Nota: não pode ser menor do que 5.', 'click2pay-pagamentos' ),
				'desc_tip'    => true,
				'default'     => '5',
			),
			'interest_rate' => array(
				'title'       => __( 'Taxa de juros', 'click2pay-pagamentos' ),
				'type'        => 'text',
				'description' => __( 'Informe a taxa de juros. Nota: utilize 0 para não cobrar juros.', 'click2pay-pagamentos' ),
				'desc_tip'    => true,
				'default'     => '0',
			),
			'free_installments' => array(
				'title'       => __( 'Parcelas sem juros', 'click2pay-pagamentos' ),
				'type'        => 'select',
				'class'       => 'wc-enhanced-select',
				'default'     => '1',
				'description' => __( 'Número máximo de parcelas sem juros.', 'click2pay-pagamentos' ),
				'desc_tip'    => true,
				'options'     => array(
					'0'  => _x( 'Nenhuma', 'no free installments', 'click2pay-pagamentos' ),
					'1'  => '1',
					'2'  => '2',
					'3'  => '3',
					'4'  => '4',
					'5'  => '5',
					'6'  => '6',
					'7'  => '7',
					'8'  => '8',
					'9'  => '9',
					'10' => '10',
					'11' => '11',
					'12' => '12',
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
			'public_key' => array(
				'title'             => __( 'Chave Pública', 'click2pay-pagamentos' ),
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
			'birthdate_required' => array(
				'title'       => __( 'Data de nascimento', 'click2pay-pagamentos' ),
				'type'        => 'checkbox',
				'label'       => __( 'Manter sempre ativo o campo de nascimento', 'click2pay-pagamentos' ),
				'default'     => 'yes',
				'description' => __( 'A data de nascimento é obrigatória no antifraude. Se esta opção não estiver ativa, suas vendas podem ser rejeitadas. Essa opção força a ativação deste campo no plugin Brazilian Market.', 'click2pay-pagamentos' ),
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



  public function should_save_credit_card() {
    return isset( $_POST['wc-' . $this->id . '-new-payment-method'] ) && $_POST['wc-' . $this->id . '-new-payment-method'];
  }



	/**
	 * Process the payment.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return array Redirect data.
	 */
	public function process_payment( $order_id ) {
    try {
      $save_card = $this->should_save_credit_card();

      $card_hash  = isset( $_POST[ $this->id . '_hash'] ) ? wc_clean( wp_unslash( $_POST[ $this->id . '_hash'] ) ) : '';
      $card_brand = isset( $_POST[ $this->id . '_brand'] ) ? wc_clean( wp_unslash( $_POST[ $this->id . '_brand'] ) ) : '';
      $installments = isset( $_POST[ $this->id . '_installments'] ) ? wc_clean( wp_unslash( $_POST[ $this->id . '_installments'] ) ) : '1';

      $card_token = isset( $_POST[ 'wc-' . $this->id . '-payment-token'] ) ? wc_clean( wp_unslash( $_POST[ 'wc-' . $this->id . '-payment-token'] ) ) : '';

      $order = wc_get_order( $order_id );
      $this->remove_credit_card_fee( $order );

      // using saved card!
      if ( is_numeric( $card_token ) ) {
        $card_hash = null;
        $save_card = false;

        try {
          $token = new WC_Payment_Token_CC_Click2pay( $card_token );

          if ( ! $token->get_token() ) {
            throw new Exception( __( 'Token não disponível.', 'click2pay-pagamentos' ) );
          }

          $card_token = $token->get_token();

          $order->add_payment_token( $token );

        } catch (Exception $e) {
          $order->add_order_note( sprintf( __( 'Não foi possível processar o pagamento com cartão salvo: %s', 'click2pay-pagamentos' ), $e->getMessage() ) );

          throw new Exception( __( 'Não foi possível processar o pagamento com cartão salvo. Por favor, adicione um novo cartão.', 'click2pay-pagamentos' ) );
        }
      }

      if ( ! isset( $card_brand ) ) {
        throw new Exception( __( 'Não foi possível verificar o tipo do seu cartão. Por favor, tente novamente.', 'click2pay-pagamentos' ) );
      }

      if ( ! $card_hash && ! $card_token ) {
        throw new Exception( __( 'Não foi possível processar seu cartão. Por favor, tente novamente.', 'click2pay-pagamentos' ) );
      }

      $available_installments = $this->get_available_installments();

      if ( intval( $installments ) > 1 && ! isset( $available_installments[ $installments ] ) ) {
        throw new Exception( __( 'O número de parcelas é inválido. Por favor, selecione outra opção', 'click2pay-pagamentos' ) );
      }

      $installment_data = isset( $available_installments[ $installments ] ) ? $available_installments[ $installments ] : [];

      if ( $installment_data && $installment_data['has_fee'] ) {
        $fee_amount = $installment_data['total'] - $order->get_total();
        $fee = new WC_Order_Item_Fee();
        $fee->set_amount( $fee_amount );
        $fee->set_total( $fee_amount );
        $fee->set_name( $this->fee_name );
        $order->add_item( $fee );
        // $order->calculate_taxes();
        $order->calculate_totals();
        $order->save();
      }

      $response_data = $this->api->create_transaction( $order, $installments, $card_hash, $card_token, $save_card );

      $data = $response_data->data;

      if ( isset( $response_data->error, $response_data->errorDescription ) ) {
        throw new Exception( sprintf( __( 'Ocorreu um erro ao processar sua solicitação: %s', 'click2pay-pagamentos' ), $response_data->errorDescription ) );
      }

      $order->set_transaction_id( $data->tid );
      $order->update_meta_data( '_click2pay_external_identifier',
      $data->externalIdentifier );
      $order->update_meta_data( '_click2pay_data', $data );
      $order->update_meta_data( '_click2pay_installment_data', $installment_data );
      $order->update_meta_data( '_click2pay_total_amount', $data->totalAmount );

      if ( $save_card && isset( $data->card->card_token ) ) {
        $token = $this->save_card_token( $data->card );
        $order->add_payment_token( $token );
      }

      if ( 'paid' === $data->status ) {
        $order->add_order_note( __( 'Pagamento já confirmado pela Click2Pay.', 'click2pay-pagamentos' ) );

        $order->payment_complete();
      } else {
        $order->set_status( 'on-hold', __( 'Pagamento iniciado. Aguardando confirmação.', 'click2pay-pagamentos' ) );
      }

      $order->save();

    } catch (Exception $e) {
      $order->update_status( 'failed', sprintf( __( 'Ocorreu um erro ao processar o pedido: %s', 'click2pay-pagamentos' ), $e->getMessage() ) );

      throw new Exception( $e->getMessage(), $e->getCode() );
    }

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
		$order = wc_get_order( $order_id );

    if ( ! $order ) {
      return;
    }

    if ( $this->id !== $order->get_payment_method() ) {
      return;
    }

    $data = $order->get_meta( '_click2pay_data' );

    if ( isset( $data->installments ) && in_array( $order->get_status(), array( 'processing', 'on-hold' ) ) ) {
      wc_get_template(
        'checkout/click2pay/credit-card.php',
        [
          'id'           => $this->id,
          'instructions' => $this->instructions,
          'order'        => $order,
          'installments' => $data->installments,
          'card'         => isset( $data->card->brand ) ? $data->card : null,
        ],
        '',
        Click2pay_Payments::get_templates_path()
      );
    }
	}

	/**
	 * Notification handler.
	 */
	public function notification_handler() {
		$this->api->notification_handler();
	}


  public function wp_enqueue_scripts() {
    wp_register_script(
      $this->id . '-tokenization',
      $this->api->get_js_url(),
      [ 'jquery' ],
      Click2pay_Payments::VERSION,
      true
    );

    wp_register_script(
      $this->id,
      Click2pay_Payments::plugin_url() . '/assets/js/gateways/credit-card.js',
      [ $this->id . '-tokenization' ],
      Click2pay_Payments::VERSION,
      true
    );

		wp_localize_script(
			$this->id,
			'click2PayCreditCardParams',
			[
				'method_id'  => $this->id,
				'public_key' => $this->public_key,
        'errors'     => [
          'expire_date' => __( 'A data de validade é inválida.', 'click2pay-pagamentos' ),
          'card_number' => __( 'O número do cartão é inválido.', 'click2pay-pagamentos' ),
          'generic' => __( 'Ocorreu um erro ao validar seu cartão. Tente novamente ou entre em contato para obter assistência.', 'click2pay-pagamentos' ),
        ],
			]
		);
  }


  public function add_brazilian_fields( $fields ) {
    $extra_fields = [
      'card-holder-field' => '<p class="form-row form-row-wide">
        <label for="' . esc_attr( $this->id ) . '-card-holder">' . esc_html__( 'Nome completo', 'click2pay-pagamentos' ) . '&nbsp;<span class="required">*</span></label>
        <input id="' . esc_attr( $this->id ) . '-card-holder" class="input-text wc-credit-card-form-card-holder" inputmode="text" autocomplete="cc-name" autocorrect="no" autocapitalize="no" spellcheck="no" type="text" placeholder="' . esc_html__( 'Como no cartão', 'click2pay-pagamentos' ) . '" ' . $this->field_name( 'card-holder' ) . ' />
      </p>',
    ];

    return array_merge( $extra_fields, $fields );
  }
	/**
	 * Builds our payment fields area - including tokenization fields for logged
	 * in users, and the actual payment fields.
	 *
	 * @since 2.6.0
	 */
	public function payment_fields() {
    wp_enqueue_script( $this->id );
    parent::payment_fields();
  }



  /**
   * get_available_installments
   *
   * @return array
   */
  public function get_available_installments() {
    $installments_info = [];
    $order_total = $this->get_order_total();

    // get all installments options till the limit
    for ( $i = 1; $i <= $this->max_installment; $i++ ) {
      $fee = $this->free_installments >= $i ? 0 : floatval( $this->interest_rate );

      // Se o juros for zero, utilza uma só fórmula para tudo
      if ( floatval( 0 ) === floatval( $fee ) ) {
        $has_fee = false;
        $amount = $order_total / $i;
        $total = $order_total;
        $description = sprintf( '%sx de %s sem juros', $i, wc_price( $amount ) );
      } else {
        $has_fee = true;
        $amount  = $this->calculate_installment_with_fee( $order_total, $i );
        $total   = round( $amount * $i, 2 );
        $description = sprintf( '%sx de %s (Total: %s)', $i, wc_price( $amount ), wc_price( $total ) );
      }

      if ( $amount < $this->smallest_installment ) {
        break;
      }

      $installments_info[ $i ] = [
        'installment' => $i,
        'has_fee'     => $has_fee,
        'amount'      => wc_format_decimal( $amount, 2 ),
        'total'       => $total,
        'fee'         => $fee,
        'description' => strip_tags( $description ),
      ];
    }

    return $installments_info;
  }

  /**
    * Calcular o valor total de uma parcela com juros.
    *
    * @since 2.0
    * @param float $value Valor base para o cálculo
    * @param float $fee Taxa de juros
    * @param int $installments Total de parcelas
    * @return float valor da parcela
  */
  public function calculate_installment_with_fee( $value, $installments ) {
   $percentage = wc_format_decimal( $this->interest_rate ) / 100.00;

   return $value * $percentage * ( ( 1 + $percentage ) ** $installments ) / ( ( ( 1 + $percentage ) ** $installments ) - 1 );
 }



 public function remove_credit_card_fee( $order ) {
  $fees = $order->get_fees();

  foreach ( $fees as $fee ) {
    if ( $this->fee_name === $fee->get_name() ) {
      $order->remove_item( $fee->get_id() );
    }
  }

  return $order;
 }





  public function save_card_token( $credit_card, $user_id = false ) {
    if ( ! $user_id ) {
      $user_id = get_current_user_id();
    }

    $token = new WC_Payment_Token_CC_Click2pay();
    $token->set_token( $credit_card->card_token );
    $token->set_gateway_id( $this->id );
    $token->set_last4( $credit_card->last4_digits );
    $token->set_expiry_month( 12 );
    $token->set_expiry_year( 2040 );
    $token->set_card_type( strtolower( $credit_card->brand ) );
    $token->set_user_id( $user_id );
    $token->save();

    return $token;
  }




	/**
	 * Outputs fields for entering credit card information.
	 *
	 * @since 2.6.0
	 */
	public function form() {
    parent::form();
    $this->inline_style();

    $installments = $this->get_available_installments();

    if ( $installments && count( $installments ) === 1 ) {
      echo '<input type="hidden" name="' . esc_attr( $this->id . '_installments' ) . '" value="1" />';
    } elseif ( $installments ) {
      $installments_field = '<p class="form-row form-row-wide">
      <label for="' . esc_attr( $this->id ) . '-card-installments">' . esc_html__( 'Parcelamento', 'click2pay-pagamentos' ) . '&nbsp;<span class="required">*</span></label>
      <select id="' . esc_attr( $this->id ) . '-card-installments" class="input-select wc-credit-card-form-card-installments" name="' . esc_attr( $this->id . '_installments' ) . '">';

      foreach ( $installments as $installment ) {
        $installments_field .= '<option value="' . $installment['installment'] . '">' .  $installment['description'] . '</option>';
      }

      $installments_field .= '</select></p>';

      echo '<fieldset id="' . esc_attr( $this->id ) . '-installments-fieldset">' . $installments_field . '</fieldset>'; // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
    }
  }


  public function adjust_card_fees_display( $total_rows, $order ) {
    if ( current_user_can( 'manage_woocommerce' ) ) {
      // return $total_rows;
    }

    $fee_row = false;

    foreach ( $total_rows as $row_id => $total_row ) {
      if ( false !== strpos( $total_row['label'], $this->fee_name ) ) {
        $fee_row = $total_row;
        unset( $total_rows[ $row_id ] );
        break;
      }
    }

    if ( $fee_row && isset( $total_rows['order_total'] ) ) {
      $total_rows['order_total']['value'] .= ' <small>' . __( '(com juros)', 'click2pay-pagamentos' ) . '</small>';
    }

    return $total_rows;
  }


  public function inline_style() {
    if ( apply_filters( $this->id . '_inline_style', true ) ) { ?>
      <style type="text/css">
        #click2pay-credit-card-card-holder,
        #click2pay-credit-card-card-installments {
          font-size: 1.41575em;
        }


        #click2pay-credit-card-installments-fieldset {
          border: 0 !important;
          padding: 0 !important;
          margin: 1em 0 0 !important;
        }
        #click2pay-credit-card-installments-fieldset select {
          width: 100% !important;
        }
      </style>
    <?php }
  }



  public function maybe_make_birthdate_required( $option ) {
    if ( 'yes' === $this->get_option( 'birthdate_required', 'yes' ) ) {
      $option['birthdate_sex'] = true;
    }
    // print_r( $option ); exit;
    return $option;
  }
}

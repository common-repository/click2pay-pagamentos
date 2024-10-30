<?php

namespace Click2pay_Payments\Yith_Subscriptions;

defined( 'ABSPATH' ) || exit;

use Exception;
use WC_Payment_Tokens;

class Hooks {
  function __construct() {
    if ( function_exists( 'ywsbs_get_subscription' ) ) {
      add_action( 'ywsbs_customer_subscription_payment_done_mail', [ $this, 'save_subscription_card_token' ] );
      add_action( 'ywsbs_subscription_status_active', [ $this, 'save_subscription_card_token_on_active' ] );

      add_action( 'ywsbs_renew_subscription', [ $this, 'scheduled_subscription_payment' ], 500, 2 );

      add_action( 'ywsbs_subscription_loaded', [ $this, 'maybe_schedule_charge' ] );
    }
  }

  /**
   * Charge recurring subscription
   *
   * @param float $amount_to_charge
   * @param WC_Order $renewal_order
   * @return void
   */
  public function scheduled_subscription_payment( $order_id, $subscription_id ) {
    // using saved card!
    try {
      $order = wc_get_order( $order_id );
      $subscription = ywsbs_get_subscription( $subscription_id );

      // not our order!
      if ( 'click2pay-credit-card' !== $subscription->get_payment_method() ) {
        return;
      }

      $parent_order = $subscription->get_order();

      $extra_fields_to_copy = [
        '_billing_cellphone',
        '_billing_persontype',
        '_billing_cpf',
        '_billing_cnpj',
        '_billing_birthdate',
      ];

      foreach ( $extra_fields_to_copy as $field ) {
        $order->add_meta_data( $field, $parent_order->get_meta( $field ), true );
      }

      $order->save();

      $card_token = $subscription->get( '_click2pay_card_id' );

      if ( ! $card_token ) {
        throw new Exception( __( 'Token não encontrado no pedido.', 'click2pay-pagamentos' ) );
      }

      $gateway = wc_get_payment_gateway_by_order( $order );

      if ( ! $gateway ) {
        throw new Exception( __( 'Método de pagamento indisponível.', 'click2pay-pagamentos' ) );
      }

      $response_data = $gateway->api->create_transaction( $order, 1, false, $card_token, false );

      $data = $response_data->data;

      if ( isset( $response_data->error, $response_data->errorDescription ) ) {
        throw new Exception( sprintf( __( 'Ocorreu um erro ao processar sua solicitação: %s', 'click2pay-pagamentos' ), $response_data->errorDescription ) );
      }

      $order->set_transaction_id( $data->tid );
      $order->update_meta_data( '_click2pay_external_identifier',
      $data->externalIdentifier );
      $order->update_meta_data( '_click2pay_data', $data );
      $order->update_meta_data( '_click2pay_installment_data', [] );
      $order->update_meta_data( '_click2pay_total_amount', $data->totalAmount );

      if ( 'paid' === $data->status ) {
        $order->add_order_note( __( 'Pagamento já confirmado pela Click2Pay.', 'click2pay-pagamentos' ) );
        $order->payment_complete();

        // making sure subscription is active!
        $subscription->update_status( 'active', 'click2pay-credit-card' );
      } else {
        $order->set_status( 'on-hold', __( 'Pagamento iniciado. Aguardando confirmação. Assinatura permanecerá ativa até a confirmação ou cancelamento da transação.', 'click2pay-pagamentos' ) );
      }

      $order->save();

    } catch (Exception $e) {
      $order->update_status( 'failed', sprintf( __( 'Não foi possível processar o pagamento com cartão salvo: %s', 'click2pay-pagamentos' ), $e->getMessage() ) );

      $subscription->update_status( 'cancelled', 'click2pay-credit-card' );
    }
  }




  /**
   * New Subscription handler
   *
   * @return void
   */
  public function save_subscription_card_token( $subscription ) {
    // $subscription = ywsbs_get_subscription( $subscription_id );
    $order_id = $subscription->get( 'order_id' );
    $order = wc_get_order( $order_id );

    if ( ! $order ) {
      return;
    }

    // not our order!
    if ( 'click2pay-credit-card' !== $subscription->get_payment_method() ) {
      return;
    }

    $tokens = WC_Payment_Tokens::get_order_tokens( $order->get_id() );
    foreach ( $tokens as $token ) {
      $subscription->set( '_click2pay_card_id', $token->get_token() );
      break;
    }
  }


  /**
   * Manually change!
   *
   * @param mixed $subscription_id
   * @return void
   */
  public function save_subscription_card_token_on_active( $subscription_id ) {
    $subscription = ywsbs_get_subscription( $subscription_id );
    return $this->save_subscription_card_token( $subscription );
  }



  public function maybe_schedule_charge( $subscription ) {
    global $wp_current_filter;

    $cron_limit = time() + 86400;

    // we need just this first execution, the next hooks should be ignored
    if ( ! is_array( $wp_current_filter ) ||  [ 'ywsbs_renew_orders', 'ywsbs_subscription_loaded' ] !== $wp_current_filter ) {
      return;
    }

    // not our order!
    if ( 'click2pay-credit-card' !== $subscription->get_payment_method() ) {
      return;
    }

    // ignore not acitve subscriptions
    if ( 'active' !== $subscription->get_status() ) {
      return;
    }

    if ( $cron_limit < $subscription->get( 'payment_due_date' ) ) {
      return;
    }

    // make the renewal order available!
    $subscription->set( 'click2pay_set_subscription_payment_as_pending', time() );
    $subscription->set( 'renew_order', 0 );
  }
}

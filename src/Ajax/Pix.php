<?php

namespace Click2pay_Payments\Ajax;

defined( 'ABSPATH' ) || exit;

use Exception;

class Pix {
  function __construct() {
    add_action( 'wc_ajax_click2pay_order_is_paid', [ $this, 'order_is_paid' ], 10 );
  }


  public function order_is_paid() {
    try {
      $message = __( 'Pagamento pendente', 'click2pay-pagamentos' );

      if ( empty( $_POST['order_id'] ) || empty( $_POST['order_key'] ) ) {
        throw new Exception( __( 'Requisição inválida', 'click2pay-pagamentos' ) );
      }

      $order = wc_get_order( intval( wc_clean( wp_unslash( $_POST['order_id'] ) ) ) );

      if ( ! $order ) {
        throw new Exception( __( 'Pedido não encontrado', 'click2pay-pagamentos' ) );
      }

      if ( $order->get_order_key() !== wc_clean( wp_unslash( $_POST['order_key'] ) ) ) {
        throw new Exception( __( 'Chave inválida!', 'click2pay-pagamentos' ) );
      }

      wp_send_json_success( [
        'message'   => __( 'Pedido consultado!', 'click2pay-pagamentos' ),
        'result'    => wc_bool_to_string( $order->is_paid() ),
        'cancelled' => wc_bool_to_string( $order->has_status( ['cancelled', 'failed'] ) ),
        'redirect'  => $order->is_paid() ? $order->get_checkout_order_received_url() : false,
      ] );

    } catch (Exception $th) {
      $message = $th->getMessage();
    }

    wp_send_json_error( [
      'message'   => $message,
      'result'    => 'no',
      'cancelled' => 'no',
    ] );
  }
}

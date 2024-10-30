<?php

namespace Click2pay_Payments\API;

defined( 'ABSPATH' ) || exit;

class Pix_API extends API {
  public function create_transaction( $order ) {
    $args = [
      'id' => $this->gateway->prefix . $order->get_id() . uniqid('|'),
      'totalAmount' => $order->get_total(),
      'payerInfo' => [
        'name'        => $order->get_formatted_billing_full_name(),
        'taxid'       => $this->gateway->get_order_document( $order ),
        'phonenumber' => $this->gateway->get_order_phone( $order ),
        'email'       => $order->get_billing_email(),
      ],
      'expiration'     => intval( $this->gateway->expires_in ) * 60, // minutes to seconds
      'callbackAddress' => WC()->api_request_url( $this->gateway->id ),
      'returnQRCode' => true,
    ];

    $args = apply_filters( $this->gateway->id . '_transaction_args', $args, $this );

    $response = $this->do_request( 'transactions/pix', $args );

    $body = json_decode( $response['body'] );

    $this->gateway->log( 'response:' . print_r( $body, true ) );

    return $body;
  }


  public function refund_transaction( $order, $amount = null ) {
    $amount = $amount ? $amount : $order->get_total();

    $args = [
      'totalAmount' => sanitize_text_field( $amount ),
    ];

    $response = $this->do_request( sprintf( 'transactions/pix/%s/refund', $order->get_transaction_id() ), $args );

    $this->gateway->log( 'refund response:' . print_r( $response, true ) );

    return $response;
  }
}

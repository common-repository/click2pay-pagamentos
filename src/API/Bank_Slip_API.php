<?php

namespace Click2pay_Payments\API;

use Exception;

defined( 'ABSPATH' ) || exit;

class Bank_Slip_API extends API {
  public function create_transaction( $order ) {
    $birthdate = $this->gateway->get_birthdate( $order );

    if ( $order->get_shipping_address_1() && $order->get_meta( '_shipping_number' ) ) {
      $address = [
        'place' => $order->get_shipping_address_1(),
        'number' => $order->get_meta( '_shipping_number' ),
        'complement' => $order->get_shipping_address_2(),
        'neighborhood' => $order->get_meta( '_shipping_neighborhood' ),
        'city' => $order->get_shipping_city(),
        'state' => $order->get_shipping_state(),
        'zipcode' => $this->gateway->only_numbers( $order->get_shipping_postcode() ),
      ];
    } else {
      $address = [
        'place' => $order->get_billing_address_1(),
        'number' => $order->get_meta( '_billing_number' ),
        'complement' => $order->get_billing_address_2(),
        'neighborhood' => $order->get_meta( '_billing_neighborhood' ),
        'city' => $order->get_billing_city(),
        'state' => $order->get_billing_state(),
        'zipcode' => $this->gateway->only_numbers( $order->get_billing_postcode() ),
      ];
    }

    $args = [
      'id' => $this->gateway->prefix . $order->get_id() . uniqid('|'),
      'totalAmount' => $order->get_total(),
      'payerInfo' => [
        'name'        => $order->get_formatted_billing_full_name(),
        'taxid'       => $this->gateway->get_order_document( $order ),
        'phonenumber' => $this->gateway->get_order_phone( $order ),
        'email'       => $order->get_billing_email(),
        'birth_date'  => $birthdate,
        'address'     => $address,
      ],
      'due_date' => date( 'Y-m-d', strtotime( $this->gateway->expires_in . ' weekdays' ) ),
      'callbackAddress' => WC()->api_request_url( $this->gateway->id ),
      'instructions' => array_filter( [
        $this->gateway->instructions_1,
        $this->gateway->instructions_2,
      ] ),
      'description' => sprintf( __( 'Pagamento do pedido #%s', 'click2pay-pagamentos' ), $order->get_id() ),
      'logo' => $this->gateway->logo,
    ];

    $args = apply_filters( $this->gateway->id . '_transaction_args', $args, $this );

    $response = $this->do_request( 'transactions/boleto', $args );

    $body = json_decode( $response['body'] );

    $this->gateway->log( 'response:' . print_r( $body, true ) );

    return $body;
  }
}

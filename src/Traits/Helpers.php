<?php

namespace Click2pay_Payments\Traits;

use WC_Logger;

defined( 'ABSPATH' ) || exit;

trait Helpers {
  /**
   * Only numbers.
   *
   * @param  string|int $string String to convert.
   *
   * @return string|int
   */
  public function only_numbers( $string ) {
    return preg_replace( '([^0-9])', '', $string );
  }


  public function get_order_phone( $order ) {
    $cellphone = $order->get_meta( '_billing_cellphone' );
    $phone     = '' === $cellphone ? $order->get_billing_phone() : $cellphone;
    $phone     = $this->only_numbers( $phone );

    return apply_filters( 'click2pay_for_woocommerce_customer_phone', $phone, $order );
  }


  public function get_order_document( $order ) {
    $persontype = intval( $order->get_meta( '_billing_persontype' ) );
    $cpf        = $this->only_numbers( $order->get_meta( '_billing_cpf' ) );
    $cnpj       = $this->only_numbers( $order->get_meta( '_billing_cnpj' ) );

    $document = '';

    if ( 1 === $persontype ) {
      $document = $cpf;
    } else if ( 2 === $persontype ) {
      $document = $cnpj;
    } else if ( ! empty( $cnpj ) ) {
      $document = $cnpj;
    } else if ( ! empty( $cpf ) ) {
      $document = $cpf;
    }

    return apply_filters( 'click2pay_for_woocommerce_customer_document', $document, $order );
  }


  public function get_birthdate( $order ) {
    $birthdate = $order->get_meta( '_billing_birthdate' );
    $birthdate = $birthdate ? $birthdate : '31/12/1969';
    $parts     = explode( '/', $birthdate );
    $birthdate = sprintf( '%s-%s-%s', $parts[2], $parts[1], $parts[0] );

    return $birthdate;
  }
}

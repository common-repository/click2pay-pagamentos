<?php

namespace Click2pay_Payments\Gateways;

defined( 'ABSPATH' ) || exit;

use Exception;
use WC_Payment_Tokens;

class Credit_Card_Yith_Subscriptions extends Credit_Card {
  function __construct() {
    parent::__construct();
  }


  public function get_available_installments() {
    if ( $this->cart_has_yith_subscription() ) {
      return [];
    }

    return parent::get_available_installments();
  }


  public function should_save_credit_card() {
    if ( $this->cart_has_yith_subscription() ) {
      return true;
    }

    return parent::should_save_credit_card();
  }


	/**
	 * Outputs a checkbox for saving a new payment method to the database.
	 *
	 * @since 2.6.0
	 */
  public function save_payment_method_checkbox() {
    if ( $this->cart_has_yith_subscription() ) {
      return '';
    }

    return parent::save_payment_method_checkbox();
  }
}

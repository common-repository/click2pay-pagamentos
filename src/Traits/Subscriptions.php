<?php

namespace Click2pay_Payments\Traits;

defined( 'ABSPATH' ) || exit;

use WC_Subscriptions_Cart;

trait Subscriptions {

  /**
   * Check if current cart is a subscription
   *
   * @return void
   */
  public function is_subscription() {
    return isset( $_POST['woocommerce_change_payment'] ) || class_exists( '\WC_Subscriptions_Cart' ) && WC_Subscriptions_Cart::cart_contains_subscription();
  }

	/**
	 * Document is not required on order-pay endpoint
	 *
	 * @return bool
	 */
	public function needs_document() {
		return ! isset( $_POST['woocommerce_change_payment'] ) && ! is_checkout_pay_page();
	}



  public function cart_has_yith_subscription() {
    if ( ! function_exists( 'YWSBS_Subscription_Cart' ) ) {
      return false;
    }

    $yith_cart = YWSBS_Subscription_Cart();

    return $yith_cart::cart_has_subscriptions();
  }
}

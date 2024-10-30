<?php

namespace Click2pay_Payments\Credit_Card;

use WC_Payment_Token_CC;

defined( 'ABSPATH' ) || exit;

class WC_Payment_Token_CC_Click2pay extends WC_Payment_Token_CC {
	/**
	 * Token Type String.
	 *
	 * @var string
	 */
	protected $type = 'CC_Click2pay';


	/**
	 * Get type to display to user.
	 *
	 * @since  2.6.0
	 * @param  string $deprecated Deprecated since WooCommerce 3.0.
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ) {
		$display = sprintf(
			/* translators: 1: credit card type 2: last 4 digits 3: expiry month 4: expiry year */
			__( '%1$s ending in %2$s', 'woocommerce' ),
			wc_get_credit_card_type_label( $this->get_card_type() ),
			$this->get_last4()
		);

		return $display;
	}
}

<?php

namespace Click2pay_Payments\Integrations;

defined( 'ABSPATH' ) || exit;


class Woocommerce_Checkout_Field_Editor {
  function __construct() {
    add_filter( 'thwcfe_disabled_fields', [ $this, 'add_shipping_fields_to_validation' ], 500 );
  }


  /**
   * Plugin Name: 	Checkout Field Editor for WooCommerce (Pro)
   * Plugin URI:  	https://www.themehigh.com/product/woocommerce-checkout-field-editor-pro/
   *
   * They remove the Brazilian Market fields from address
   * However, we need it as "address" fields, not just as regular fields
   *
   * @param array $fields
   * @return array
   */
  public function add_shipping_fields_to_validation( $fields ) {
    $shipping_neighborhood = array_search( 'shipping_neighborhood', $fields );
    $shipping_number = array_search( 'shipping_number', $fields );

    if ( false !== $shipping_neighborhood ) {
      unset( $fields[ $shipping_neighborhood ] );
    }

    if ( false !== $shipping_number ) {
      unset( $fields[ $shipping_number ] );
    }

    return $fields;
  }
}

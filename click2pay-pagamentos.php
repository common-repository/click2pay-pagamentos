<?php
/**
 * Plugin Name:          Click2pay Pagamentos
 * Plugin URI:           https://click2pay.com.br/woocommerce
 * Description:          Receba pagamentos via Pix, Cartão de crédito e boleto
 * Author:               Click2Pay
 * Author URI:           https://click2pay.com.br
 * Version:              1.3.0
 * License:              GPLv2 or later
 * WC requires at least: 6.0.0
 * WC tested up to:      9.1.2
 *
 * This plugin is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * This plugin is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this plugin. If not, see
 * <https://www.gnu.org/licenses/gpl-2.0.txt>.
 *
 * @package Click2Pay
 */

use Click2pay_Payments\Credit_Card\WC_Payment_Token_CC_Click2pay;
use Click2pay_Payments\Gateways;
use Click2pay_Payments\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

class Click2pay_Payments {
  /**
   * Version.
   *
   * @var float
   */
  const VERSION = '1.3.0';

  /**
   * Instance of this class.
   *
   * @var object
   */
  protected static $instance = null;
  /**
   * Initialize the plugin public actions.
   */
  function __construct() {
    $this->init();
  }

  public function init() {
    if ( class_exists( 'Click2pay_For_WooCommerce' ) ) {

      add_action( 'admin_notices', function() {
        if ( in_array( 'click2pay-for-woocommerce/click2pay-for-woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

          deactivate_plugins( 'click2pay-for-woocommerce/click2pay-for-woocommerce.php' );

          ?>

          <div class="notice notice-error" style="border: none; background: #d63638; color: #fff; font-weight: bold;">
            <p><?php _e( 'Você estava usando uma versão legada do plugin Click2Pay. O plugin agora está no repositório oficial e você terá acesso a atualizações diretamente no seu painel. Desativamos a versão antiga para você. Sinta-se livre para excluir a versão chamada "Click2Pay para WooCommerce".', 'click2pay-pagamentos' ); ?></p>
          </div>

        <?php } else { ?>

          <div class="notice notice-error" style="border: none; background: #d63638; color: #fff; font-weight: bold;">
            <p><?php _e( 'Você está usando uma versão do plugin Click2Pay que será descontinuada. Desative a versão chamada "Click2Pay para WooCommerce" e deixe apenas esta nova, com atualizações automáticas e melhorias contínuas. Todas as configurações serão transferidas automaticamente.', 'click2pay-pagamentos' ); ?></p>
          </div>

        <?php }

      });

      return false;
    }


    $file = __DIR__ . '/vendor/autoload.php';

    if ( file_exists( $file ) ) {
      require_once $file;
    } else {
      // display message
      return;
    }

    add_action( 'before_woocommerce_init', array( $this, 'setup_hpos_compatibility' ) );

    new Ajax\Pix();

    new Click2pay_Payments\Integrations\Woocommerce_Checkout_Field_Editor();

    add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateway' ) );

    add_filter( 'woocommerce_payment_methods_types', array( $this, 'register_custom_payment_method_type' ) );
    add_filter( 'woocommerce_payment_token_class', array( $this, 'register_token_classname' ), 10, 2 );

    new Click2pay_Payments\Yith_Subscriptions\Hooks();
  }


	/**
	 * Setup WooCommerce HPOS compatibility.
	 */
	public function setup_hpos_compatibility() {
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '7.1', '<' ) ) {
			return;
		}

		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}


  /**
   * Return an instance of this class.
   *
   * @return object A single instance of this class.
   */
  public static function get_instance() {
    // If the single instance hasn't been set, set it now.
    if ( null == self::$instance ) {
      self::$instance = new self;
    }
    return self::$instance;
  }

  /**
   * Get main file.
   *
   * @return string
   */
  public static function get_main_file() {
    return __FILE__;
  }

  /**
   * Get plugin path.
   *
   * @return string
   */

  public static function get_plugin_path() {
    return plugin_dir_path( __FILE__ );
  }

  /**
   * Get the plugin url.
   * @return string
   */
  public static function plugin_url() {
    return untrailingslashit( plugins_url( '/', __FILE__ ) );
  }

  /**
   * Get the plugin dir url.
   * @return string
   */
  public static function plugin_dir_url() {
    return plugin_dir_url( __FILE__ );
  }

  /**
   * Get templates path.
   *
   * @return string
   */
  public static function get_templates_path() {
    return self::get_plugin_path() . 'templates/';
  }

  /**
   * Add the gateway to WooCommerce.
   *
   * @param  array $methods WooCommerce payment methods.
   *
   * @return array
   */
  public function add_gateway( $methods ) {
    if ( class_exists( 'WC_Subscriptions_Order' ) ) {
      $methods[] = Gateways\Credit_Card_Subscriptions::class;
    } elseif ( function_exists( 'YWSBS_Subscription_Cart' ) ) {
      $methods[] = Gateways\Credit_Card_Yith_Subscriptions::class;
    } else {
      $methods[] = Gateways\Credit_Card::class;
    }

    $methods[] = Gateways\Pix::class;
    $methods[] = Gateways\Bank_Slip::class;

    return $methods;
  }

  public function register_custom_payment_method_type( $types ) {
    $types['CC_Click2pay'] = __( 'Cartão de crédito - Click2pay', 'click2pay-pagamentos' );

    return $types;
  }

  public function register_token_classname( $classname, $type ) {
    if ( 'CC_Click2pay' === $type ) {
      return WC_Payment_Token_CC_Click2pay::class;
    }

    return $classname;
  }
}

add_action( 'plugins_loaded', array( 'Click2pay_Payments', 'get_instance' ), 15 );

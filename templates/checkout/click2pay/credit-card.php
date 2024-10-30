<?php

defined( 'ABSPATH' ) || exit;

?>

<section id="<?php echo esc_attr( $id ); ?>-thankyou">
  <div class="woocommerce-message">
    <?php if ( $card ) { ?>
      <span><?php printf( wp_kses( __( 'Pagamento realizado utilizando cartão de crédito %1$s em %2$s.', 'click2pay-pagamentos' ), array( 'strong' => array() ) ), '<strong>' . esc_html( $card->brand ) . '</strong>', '<strong>' . intval( $installments ) . 'x</strong>' ); ?></span>
    <?php } else { ?>
      <span><?php printf( wp_kses( __( 'Pagamento realizado utilizando cartão de crédito em %2$s.', 'click2pay-pagamentos' ), array( 'strong' => array() ) ), '<strong>' . intval( $installments ) . 'x</strong>' ); ?></span>
    <?php } ?>
  </div>
</section>

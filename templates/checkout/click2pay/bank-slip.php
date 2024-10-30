<?php

defined( 'ABSPATH' ) || exit;

$base      = get_option( 'woocommerce_email_base_color' );
$base_text = wc_light_or_dark( $base, '#202020', '#ffffff' );

?>

<style type="text/css">
  .click2pay-bankslip-open-browser-container {
    max-width: 300px;
    margin: 20px auto;
    text-align: center;
  }

  .click2pay-bankslip-open-browser {
    display: inline-block;
    text-decoration: none !important;
    font-size: 13px;
    min-height: 30px;
    padding: 5px 10px;
    font-weight: bold;
    border-radius: 3px !important;
	  color: <?php echo esc_attr( $base_text ); ?>;
    background: <?php echo esc_attr( $base ); ?>;
    border: 1px solid <?php echo esc_attr( $base ); ?>;
    vertical-align: middle;
    font-family: "Helvetica Neue", Helvetica, Roboto, Arial, sans-serif;
    text-decoration: none;
    cursor: pointer;
  }

  .click2pay-bankslip-copy-paste {
    max-width: 930px;
    padding: 0 0 20px 20px;
    box-sizing: border-box;
  }

  .click2pay-bankslip-copy-paste .click2pay-bankslip-copy-title {
    margin: 0 0 15px 0;
    padding: 0;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
  }

  .click2pay-bankslip-copy-paste .click2pay-bankslip-copy-title span {
    font-size: 13px;
    background: #1e262c;
    color: #fff;
    font-weight: normal;
    padding: 3px 6px;
    display: flex;
    margin-left: 15px;
    border-radius: 4px;
    cursor: pointer;
    transition: 0.2s;
  }

  .click2pay-bankslip-copy-paste .click2pay-bankslip-copy-title span:hover {
    background: #4e7f30;
  }

  .click2pay-bankslip-copy-paste .click2pay-bankslip-url {
    background: #efefef;
    text-align: center;
    padding: 10px 20px;
    font-size: 13px;
    cursor: pointer;
    resize: none;
    width: 100%;

    <?php if ( $is_email ) {
      echo 'min-height: 50px !important;';
    } else {
      echo 'height: 45px !important;';
    } ?>
  }

  @media only screen and (max-width: 650px){
    .click2pay-bankslip-copy-paste .click2pay-bankslip-url {
      height: 125px !important;
    }
  }

  .click2pay-bankslip-title {
    font-weight: 600;
    font-size: 21px;
    margin-bottom: 5px;
  }

  .click2pay-bankslip-container {
    display: flex;
    align-items: center;
    max-width: 930px;
  }

  .click2pay-bankslip-container img {
    margin: 0 auto;
    display: inline-block;
  }

  .click2pay-bankslip-container .click2pay-bankslip-instructions {
    flex: 1;
  }

  .click2pay-bankslip-container .click2pay-bankslip-instructions ul {
    padding: 0;
    margin: 0 0 0 10px;
    list-style: none;
  }

  .click2pay-bankslip-container .click2pay-bankslip-instructions ul li {
    margin: 15px 0 20px;
    display: flex;
    align-items: center;
    justify-content: flex-start;
  }

  .click2pay-bankslip-container .click2pay-bankslip-instructions svg {
    min-width: 45px;
    width: 45px;
    height: 45px;
    margin-right: 20px;
  }

  .click2pay-bankslip-container .click2pay-bankslip-instructions .click2pay-bankslip-mobile-only {
    display: none;
  }

  @media only screen and (max-width: 650px){
    .click2pay-bankslip-container {
      flex-direction: column-reverse;
    }

    .click2pay-bankslip-container .click2pay-bankslip-instructions .click2pay-bankslip-desktop-only {
      display: none;
    }
    .click2pay-bankslip-container .click2pay-bankslip-instructions .click2pay-bankslip-mobile-only {
      display: inline;
    }
  }
</style>

<section id="<?php echo esc_attr( $id ); ?>-thankyou">
	<h3 class="click2pay-bankslip-title"><?php echo __( 'Aguardando seu pagamento via boleto', 'click2pay-pagamentos' ); ?></h3>

  <?php if ( $instructions ) { ?>
		<div class="instruction"><?php echo wpautop( wptexturize( $instructions ) ); ?></div>
	<?php } ?>

  <?php if ( ! $is_email ) { ?>
    <div class="click2pay-bankslip-copy-paste">
      <textarea style="width: 100%;" id="click2pay-bankslip-payload" readonly data-clipboard-target="#click2pay-bankslip-payload" class="bankslip-copy click2pay-bankslip-url"><?php echo esc_html( $bank_slip_barcode ); ?></textarea>
    </div>
  <?php } ?>

  <div class="click2pay-bankslip-open-browser-container">
    <?php if ( ! $is_email ) {
      echo '<span class="bankslip-copy bankslip-copy-button click2pay-bankslip-open-browser" data-clipboard-target="#click2pay-bankslip-payload">' . __( 'Copiar linha digitável', 'click2pay-pagamentos' ) . '</span>';
    } ?>

    <a target="_blank" href="<?php echo esc_url( $bank_slip_url ); ?>" class="click2pay-bankslip-open-browser">
      <?php echo __( 'Imprimir boleto', 'click2pay-pagamentos' ); ?>
    </a>
  </div>
</section>

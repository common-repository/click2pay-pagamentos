<?php

defined( 'ABSPATH' ) || exit;

$base      = get_option( 'woocommerce_email_base_color' );
$base_text = wc_light_or_dark( $base, '#202020', '#ffffff' );

?>

<style type="text/css">
  .click2pay-pix-open-browser-container {
    max-width: 300px;
    margin: 20px auto;
    text-align: center;
  }

  .click2pay-pix-open-browser {
    display: inline-block;
    text-decoration: none;
    font-size: 13px;
    min-height: 30px;
    padding: 5px 10px;
    font-weight: bold;
    border-radius: 3px !important;
	  color: <?php echo esc_html( $base_text ); ?>;
    background: <?php echo esc_html( $base ); ?>;
    border: 1px solid <?php echo esc_html( $base ); ?>;
    vertical-align: middle;
    font-family: "Helvetica Neue", Helvetica, Roboto, Arial, sans-serif;
  }

  .click2pay-pix-copy-paste {
    max-width: 930px;
    padding: 0 0 20px 20px;
    box-sizing: border-box;
  }

  .click2pay-pix-copy-paste .click2pay-pix-copy-title {
    margin: 0 0 15px 0;
    padding: 0;
    font-size: 18px;
    font-weight: 600;
    display: flex;
    align-items: center;
  }

  .click2pay-pix-copy-paste .click2pay-pix-copy-title span {
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

  .click2pay-pix-copy-paste .click2pay-pix-copy-title span:hover {
    background: #4e7f30;
  }

  .click2pay-pix-copy-paste .click2pay-pix-url {
    background: #efefef;
    text-align: center;
    padding: 15px 20px;
    font-size: 13px;
    cursor: pointer;
    resize: none;
    width: 100%;

    <?php if ( $is_email ) {
      echo 'min-height: 100px !important;';
    } else {
      echo 'height: 75px !important;';
    } ?>
  }

  @media only screen and (max-width: 650px){
    .click2pay-pix-copy-paste .click2pay-pix-url {
      height: 125px !important;
    }
  }

  .click2pay-pix-title {
    font-weight: 600;
    font-size: 21px;
    margin-bottom: 5px;
  }

  .click2pay-pix-container {
    display: flex;
    align-items: center;
    max-width: 930px;
  }

  .click2pay-pix-container .qr-code,
  .click2pay-pix-container img {
    width: 350px;
    min-width: 350px;
  }

  .click2pay-pix-container img {
    margin: 0 auto;
    display: inline-block;
  }

  .click2pay-pix-container .click2pay-pix-instructions {
    flex: 1;
  }

  .click2pay-pix-container .click2pay-pix-instructions ul {
    padding: 0;
    margin: 0 0 0 10px;
    list-style: none;
  }

  .click2pay-pix-container .click2pay-pix-instructions ul li {
    margin: 15px 0 20px;
    display: flex;
    align-items: center;
    justify-content: flex-start;
  }

  .click2pay-pix-container .click2pay-pix-instructions svg {
    min-width: 45px;
    width: 45px;
    height: 45px;
    margin-right: 20px;
  }

  .click2pay-pix-container .click2pay-pix-instructions .click2pay-pix-mobile-only {
    display: none;
  }

  @media only screen and (max-width: 650px){
    .click2pay-pix-container {
      flex-direction: column-reverse;
    }

    .click2pay-pix-container .qr-code {
      width: 100%;
      text-align: center;
    }

    .click2pay-pix-container .click2pay-pix-instructions .click2pay-pix-desktop-only {
      display: none;
    }
    .click2pay-pix-container .click2pay-pix-instructions .click2pay-pix-mobile-only {
      display: inline;
    }
  }
</style>

<section id="<?php echo esc_attr( $id ); ?>-thankyou">
	<h3 class="click2pay-pix-title"><?php echo __( 'Aguardando sua transferência via Pix', 'click2pay-pagamentos' ); ?></h3>
	<?php if ( $instructions ) { ?>
		<div class="instruction"><?php echo wpautop( wptexturize( $instructions ) ); ?></div>
	<?php } ?>

  <?php if ( isset( $order ) && $is_email ) { ?>
    <div class="click2pay-pix-open-browser-container">
      <a href="<?php echo esc_url( $pix_details_page ); ?>" class="click2pay-pix-open-browser">
        <?php echo __( 'Ver QR Code no navegador', 'click2pay-pagamentos' ); ?>
      </a>
    </div>
  <?php } ?>

	<div class="click2pay-pix-copy-paste">
		<h3 class="click2pay-pix-copy-title"><?php echo __( 'Pix Copia e cola', 'click2pay-pagamentos' ); ?>
      <?php if ( ! $is_email ) {
        echo '<span class="pix-copy pix-copy-button" data-clipboard-target="#click2pay-pix-payload">' . __( 'Clique para copiar', 'click2pay-pagamentos' ) . '</span>';
      } ?>
    </h3>
		<textarea style="width: 100%;" id="click2pay-pix-payload" readonly data-clipboard-target="#click2pay-pix-payload" class="pix-copy click2pay-pix-url"><?php echo esc_html( $payload ); ?></textarea>
	</div>

	<div class="click2pay-pix-container">
    <?php if ( ! $is_email ) { ?>
      <div class="qr-code">
        <img
          src="<?php echo esc_attr( $pix_image ); // base64 image ?>"
          alt="<?php echo __( 'QR Code para pagamento', 'click2pay-pagamentos' ); ?>"
        />
      </div>
    <?php } ?>

		<div class="click2pay-pix-instructions">
			<ul>
				<li>
				<svg height="512" viewBox="0 0 60 60" width="512" xmlns="http://www.w3.org/2000/svg"><g id="Page-1" fill="none" fill-rule="evenodd"><g id="003---Mobile-Payment" fill="rgb(0,0,0)" fill-rule="nonzero"><path id="Shape" d="m55 13h-19v-9c0-2.209139-1.790861-4-4-4h-28c-2.209139 0-4 1.790861-4 4v52c0 2.209139 1.790861 4 4 4h28c2.209139 0 4-1.790861 4-4v-13h19c2.7600532-.0033061 4.9966939-2.2399468 5-5v-20c-.0033061-2.7600532-2.2399468-4.9966939-5-5zm3 5v12h-28c-.5522847 0-1 .4477153-1 1s.4477153 1 1 1h28v4h-40v-4h5c.5522847 0 1-.4477153 1-1s-.4477153-1-1-1h-5v-12c0-1.6568542 1.3431458-3 3-3h34c1.6568542 0 3 1.3431458 3 3zm-32.308-16-1.282 1.368c-.378896.40269492-.907076.63133176-1.46.632h-9.9c-.5527743.00000995-1.0809024-.22876112-1.459-.632l-1.282-1.368zm6.308 56h-28c-1.1045695 0-2-.8954305-2-2v-1.556c.60534654.3599241 1.29574677.5518554 2 .556h28c.7042532-.0041446 1.3946535-.1960759 2-.556v1.556c0 1.1045695-.8954305 2-2 2zm2-7c0 1.1045695-.8954305 2-2 2h-28c-1.1045695 0-2-.8954305-2-2v-47c0-1.1045695.8954305-2 2-2h3.567l2.565 2.735c.7567936.80574999 1.8125738 1.2634477 2.918 1.265h9.9c1.1054015-.00208587 2.1611681-.45925919 2.919-1.264l2.564-2.736h3.567c1.1045695 0 2 .8954305 2 2v9h-13c-2.7600532.0033061-4.9966939 2.2399468-5 5v2h-3c-.5522847 0-1 .4477153-1 1s.4477153 1 1 1h3v5h-5c-.5522847 0-1 .4477153-1 1s.4477153 1 1 1h5v5h-3c-.5522847 0-1 .4477153-1 1s.4477153 1 1 1h3v2c.0033061 2.7600532 2.2399468 4.9966939 5 5h13zm21-10h-34c-1.6568542 0-3-1.3431458-3-3h40c0 1.6568542-1.3431458 3-3 3z"/><path id="Shape" d="m8 22h1c.55228475 0 1-.4477153 1-1s-.44771525-1-1-1h-1c-.55228475 0-1 .4477153-1 1s.44771525 1 1 1z"/><path id="Shape" d="m9 34h-1c-.55228475 0-1 .4477153-1 1s.44771525 1 1 1h1c.55228475 0 1-.4477153 1-1s-.44771525-1-1-1z"/><path id="Shape" d="m8 28c0-.5522847-.44771525-1-1-1h-2c-.55228475 0-1 .4477153-1 1s.44771525 1 1 1h2c.55228475 0 1-.4477153 1-1z"/><path id="Shape" d="m49 23c.740399-.0026037 1.4533329-.2806479 2-.78 1.1986086 1.0894491 3.0455949 1.0307294 4.172581-.1326554s1.1269861-3.0113044 0-4.1746892-2.9739724-1.2221045-4.172581-.1326554c-.9933291-.9028176-2.4654891-1.0359148-3.6046342-.325893s-1.6680028 2.0903404-1.2949262 3.3797574c.3730765 1.289417 1.557279 2.1740833 2.8995604 2.1661356zm4-4c.5522847 0 1 .4477153 1 1s-.4477153 1-1 1-1-.4477153-1-1 .4477153-1 1-1zm-4 0c.5522847 0 1 .4477153 1 1s-.4477153 1-1 1-1-.4477153-1-1 .4477153-1 1-1z"/><path id="Shape" d="m22 23h1c.5522847 0 1-.4477153 1-1s-.4477153-1-1-1h-1c-.5522847 0-1 .4477153-1 1s.4477153 1 1 1z"/><path id="Shape" d="m27 23h3c.5522847 0 1-.4477153 1-1s-.4477153-1-1-1h-3c-.5522847 0-1 .4477153-1 1s.4477153 1 1 1z"/><path id="Shape" d="m33 25h-11c-.5522847 0-1 .4477153-1 1s.4477153 1 1 1h11c.5522847 0 1-.4477153 1-1s-.4477153-1-1-1z"/></g></g></svg>

				<div><?php echo __( 'Abra o app do seu banco ou instituição financeira e <strong>entre no ambiente Pix</strong>.', 'click2pay-pagamentos' ); ?></div></li>

				<li>
					<svg height="512" viewBox="0 0 64 64" width="512" xmlns="http://www.w3.org/2000/svg"><g id="Qr_Code-Smartphone-Technology-Commerce-Payment" data-name="Qr Code-Smartphone-Technology-Commerce-Payment"><path d="m30 18h-12a5 5 0 0 0 -5 5v12.08a7 7 0 0 0 -6 6.92v10.77a10.908 10.908 0 0 0 .33 2.67l.67 2.68v3.88h2v-4a.986.986 0 0 0 -.03-.24l-.7-2.81a8.916 8.916 0 0 1 -.27-2.18v-10.77a5.009 5.009 0 0 1 4-4.9v7.9a1 1 0 0 0 .14.51l2.43 4.05a2.956 2.956 0 0 0 2.53 1.45 3 3 0 0 0 2.92-2.21l1.15-4.3a1.006 1.006 0 0 1 1.23-.71.963.963 0 0 1 .6.46.974.974 0 0 1 .1.76l-1.86 6.97a6.922 6.922 0 0 0 -.24 1.81v1.88l-2.8 3.73a.984.984 0 0 0 -.2.6v2h2v-1.67l2.8-3.73a.984.984 0 0 0 .2-.6v-2.21a5.074 5.074 0 0 1 .17-1.29l.13-.5h5.7a5 5 0 0 0 5-5v-24a5 5 0 0 0 -5-5zm3 29a3.009 3.009 0 0 1 -3 3h-5.16l.53-2h1.63v-2h-1.09l.13-.47a3.032 3.032 0 0 0 -.3-2.28 3.007 3.007 0 0 0 -5.5.73l-1.15 4.3a.987.987 0 0 1 -1.8.25l-2.29-3.81v-21.72a3.009 3.009 0 0 1 3-3h12a3.009 3.009 0 0 1 3 3z"/><path d="m21 22h6v2h-6z"/><path d="m17 22h2v2h-2z"/><rect height="4" rx="1" width="4" x="17" y="27"/><rect height="4" rx="1" width="4" x="27" y="27"/><rect height="4" rx="1" width="4" x="17" y="37"/><path d="m29 39h-2v2h3a1 1 0 0 0 1-1v-3h-2z"/><path d="m23 27h2v4h-2z"/><path d="m27 33h4v2h-4z"/><path d="m21 33h4v2h-4z"/><path d="m23 37h2v4h-2z"/><path d="m17 33h2v2h-2z"/><path d="m54 2h-44a3.009 3.009 0 0 0 -3 3v30h2v-23h46v37a1 1 0 0 1 -1 1h-18v2h18a3.009 3.009 0 0 0 3-3v-44a3.009 3.009 0 0 0 -3-3zm1 8h-46v-5a1 1 0 0 1 1-1h44a1 1 0 0 1 1 1z"/><path d="m11 6h2v2h-2z"/><path d="m15 6h2v2h-2z"/><path d="m19 6h2v2h-2z"/><rect height="4" rx="1" width="4" x="37" y="17"/><rect height="4" rx="1" width="4" x="47" y="17"/><rect height="4" rx="1" width="4" x="37" y="27"/><path d="m47 31h3a1 1 0 0 0 1-1v-3h-2v2h-2z"/><path d="m43 17h2v4h-2z"/><path d="m47 23h4v2h-4z"/><path d="m41 23h4v2h-4z"/><path d="m43 27h2v4h-2z"/><path d="m37 23h2v2h-2z"/><path d="m41 38h10v2h-10z"/><path d="m41 42h10v2h-10z"/></g></svg>

					<div>
            <?php if ( $is_email ) {
              echo __( 'Escolha a opção <strong>Pix Copia e Cola</strong> e insira o texto acima<strong></strong>.', 'click2pay-pagamentos' );
            } else {
              echo __( 'Escolha a opção <strong> Pagar com QR Code</strong> e escaneie o código <span class="click2pay-pix-mobile-only"> abaixo</span><span class="click2pay-pix-desktop-only"> ao lado</span> ou utilize o recurso <strong>Pix Copia e Cola</strong>.', 'click2pay-pagamentos' );
            } ?>
          </div>
				</li>

				<li>
					<svg height="512" viewBox="0 0 512 512" width="512" xmlns="http://www.w3.org/2000/svg"><g><path d="m192 464h-64a8 8 0 0 0 0 16h64a8 8 0 0 0 0-16z"/><path d="m468 160h-164v-124a28.031 28.031 0 0 0 -28-28h-232a28.031 28.031 0 0 0 -28 28v440a28.031 28.031 0 0 0 28 28h232a28.031 28.031 0 0 0 28-28v-116h164a28.031 28.031 0 0 0 28-28v-144a28.031 28.031 0 0 0 -28-28zm-270.25-136-4 16h-67.5l-4-16zm90.25 452a12.01 12.01 0 0 1 -12 12h-232a12.01 12.01 0 0 1 -12-12v-440a12.01 12.01 0 0 1 12-12h61.75l6.49 25.94a8 8 0 0 0 7.76 6.06h80a8 8 0 0 0 7.76-6.06l6.49-25.94h61.75a12.01 12.01 0 0 1 12 12zm16-196h24v32h-24zm176 52a12.01 12.01 0 0 1 -12 12h-164v-16h32a8 8 0 0 0 8-8v-48a8 8 0 0 0 -8-8h-32v-24h176zm0-108h-176v-16h176zm0-32h-176v-16h164a12.01 12.01 0 0 1 12 12z"/><path d="m456 264h-72a8 8 0 0 0 0 16h72a8 8 0 0 0 0-16z"/><path d="m456 288h-72a8 8 0 0 0 0 16h72a8 8 0 0 0 0-16z"/><path d="m456 312h-32a8 8 0 0 0 0 16h32a8 8 0 0 0 0-16z"/><path d="m160 160a96 96 0 1 0 96 96 96.108 96.108 0 0 0 -96-96zm0 176a80 80 0 1 1 80-80 80.091 80.091 0 0 1 -80 80z"/><path d="m202.343 226.343-51.094 51.094-26.449-19.837a8 8 0 1 0 -9.6 12.8l32 24a8 8 0 0 0 10.457-.743l56-56a8 8 0 0 0 -11.314-11.314z"/></g></svg>
					<div>
            <?php echo __( 'Confirme as informações e <strong>finalize o pagamento</strong>. Pode demorar alguns minutos até que o pagamento seja confirmado. Iremos avisar você!', 'click2pay-pagamentos' ); ?>
          </div>
				</li>
			</ul>
		</div>
	</div>
</section>

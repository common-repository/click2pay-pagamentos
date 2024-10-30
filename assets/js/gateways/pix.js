
/* global Click2PayPixParams */
jQuery( function( $ ) {

	/**
	 * Pix.
	 *
	 * @type {Object}
	 */
	var Click2PayPix = {

		/**
		 * Initialize actions.
		 */
		init: function() {
      $( document.body ).find( '.order_details' ).insertAfter( '#click2pay-pix-thankyou' );

      this.copyQRCodeHandler();
      this.checkOrderStatus();
		},

		/**
		 * QR Code Handler.
		 *
		 * @return {String}
		 */
		copyQRCodeHandler: function() {
      // enable copy button
      var clipboard = new ClipboardJS( '.pix-copy' );

      clipboard.on( 'success', function() {
        const button = $( '.pix-copy-button' );
        const buttonText = button.text();

        button.text( Click2PayPixParams.notices.qr_code_copied );

        setTimeout(() => {
          button.text( buttonText );
        }, 1000);
      });
		},


    checkOrderStatus: function() {
      if ( ! Click2PayPixParams.wc_ajax_url ) {
        return;
      }

      var interval = setInterval(() => {
        $.ajax( {
          url:     Click2PayPixParams.wc_ajax_url.toString().replace( '%%endpoint%%', 'click2pay_order_is_paid' ),
          type:    'POST',
          data:    {
            order_id: Click2PayPixParams.orderId,
            order_key: Click2PayPixParams.orderKey,
          },
          beforeSend: function () {
          },
          success: function( response ) {
            if ( 'yes' === response?.data?.result ) {
              clearInterval(interval)

              $( document.body ).block({
                message: null,
                overlayCSS: {
                  background: '#fff',
                  opacity: 0.6
                }
              });

              if ( response.data.redirect ) {
                window.location.href = response.data.redirect
              }
            }

          },
          fail: function(error) {
            console.log( 'status check error', error, error.code )
          },
        } ).always(function(response) {
          // self.unblock();
        });

      }, parseInt( Click2PayPixParams.interval ) * 1000 );
    }
	};

	Click2PayPix.init();
});

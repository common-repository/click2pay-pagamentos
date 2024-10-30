/* global click2PayCreditCardParams, CardHash */
(function( $ ) {
	'use strict';
	$( function() {
		/**
		 * Process the credit card data when submit the checkout form.
		 */
		$( 'body' ).on( 'click', '#place_order', function() {
			if ( ! $( document.body ).find( `#payment_method_${click2PayCreditCardParams.method_id}` ).is( ':checked' ) ) {
				return true;
			}

      var cardToken = $( `input[name="wc-${click2PayCreditCardParams.method_id}-payment-token"]:checked` ).val();

      // using saved card!
      if ( typeof cardToken !== 'undefined' && 'new' !== cardToken ) {
        return true;
      }

      let card = new CardHash(click2PayCreditCardParams.public_key);

			var form           = $( 'form.checkout, form#order_review, #add_payment_method' ),
        method_id       = click2PayCreditCardParams.method_id,
        creditCardForm  = $( `#wc-${method_id}-cc-form` ),
        cardExpires     = $( `#${method_id}-card-expiry` ).val().trim(),
        expirationMonth = cardExpires.substr( 0, 2 ),
        expirationYear  = ( 4 === cardExpires.substr( -2 ).length ? cardExpires.substr( -2 ) : `20${cardExpires.substr( -2 )}` ),
        cardHolder      = $( `#${method_id}-card-holder` ).val(),
        cardNumber      = $( `#${method_id}-card-number` ).val().replace(/[^0-9]/g, ''),
        cardCVV         = $( `#${method_id}-card-cvc` ).val(),
        cardData        = {
          number: cardNumber,
          expires: `${expirationMonth}/${expirationYear}`,
          cvv: cardCVV,
          holder: cardHolder,
        },
				errors         = {},
				errorHtml      = '';

      // Lock the checkout form.
			form.addClass( 'processing' );

      card.getCardHash(cardData, function (hash) {
				form.removeClass( 'processing' );
				$( '.woocommerce-NoticeGroup', creditCardForm ).remove();

        // Remove any old hash input.
        $( `input[name=${method_id}_hash]`, form ).remove();
        $( `input[name=${method_id}_brand]`, form ).remove();

        // Add the hash input.
        form.append( $( `<input name="${method_id}_hash" type="hidden" />` ).val( hash ) );
        // Add the brand input
        form.append( $( `<input name="${method_id}_brand" type="hidden" />` ).val( card.getBrand(cardData.number) ) );

        // Submit the form.
        form.submit();

      }, function (err) {
        if ( ! card.isValidExpires( cardData.expires ) ) {
          errors['expires'] = click2PayCreditCardParams.errors.expire_date;
        }

        if ( ! card.isValidNumber( cardData.number ) ) {
          errors['number'] = click2PayCreditCardParams.errors.card_number;
        }

        if ( $.isEmptyObject( errors ) ) {
          errors['generic'] = click2PayCreditCardParams.errors.generic;
        }

        // Display the errors in credit card form.
        $( '.woocommerce-NoticeGroup', creditCardForm ).remove();

        errorHtml += '<ul class="woocommerce-error">';
        $.each( errors, function ( key, value ) {
          errorHtml += '<li>' + value + '</li>';
        });
        errorHtml += '</ul>';

        creditCardForm.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + errorHtml + '</div>' );

        $([document.documentElement, document.body]).animate({
          scrollTop: creditCardForm.offset().top - 40
        }, 700 );

        form.removeClass( 'processing' );
      });

			return false;
		});
	});

}( jQuery ));

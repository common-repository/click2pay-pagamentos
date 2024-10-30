jQuery(function ($) {
  // enable copy button
  var clipboard = new ClipboardJS( '.bankslip-copy' );

  clipboard.on('success', function() {
    const button = $( '.bankslip-copy-button' );
    const buttonText = button.text();

    button.text( 'Copiado!' );

    setTimeout(() => {
      button.text( buttonText );
    }, 1000);
  });
});

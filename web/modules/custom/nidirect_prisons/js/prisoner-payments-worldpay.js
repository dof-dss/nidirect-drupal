(function (Drupal) {
  Drupal.behaviors.prisonerPaymentsWorldpay = {
    attach: function (context, settings) {
      const options = {
        url: settings.worldpay.url,
        type: 'iframe',
        inject: 'onload',
        target: settings.worldpay.target,
        accessibility: true,
        debug: false,
        language: 'en',
        country: 'gb',
        resultCallback: Drupal.behaviors.prisonerPaymentsWorldpay.handleResult,
      };

      const libraryObject = new WPCL.Library();
      libraryObject.setup(options);
    },
    handleResult: function (responseData) {
      // Handle the response, potentially setting form values
      // and submitting the webform programmatically.
      console.log('Payment result:', responseData);

      //document.querySelector('[data-drupal-selector="edit-submit"]').click();
    },
  };
})(Drupal);

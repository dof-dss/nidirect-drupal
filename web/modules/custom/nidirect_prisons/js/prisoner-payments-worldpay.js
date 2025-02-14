(function (Drupal) {
  Drupal.behaviors.prisonerPaymentsWorldpay = {
    attach: function (context, settings) {

      // This runs after request to WP
      console.log(settings.worldpay);

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
    handleResult: function(responseData) {

      const wpResponse = document.querySelector('[data-drupal-selector="edit-wp-response"]');
      wpResponse.value = JSON.stringify(responseData);

      const status = responseData.order.status;
      switch (status) {
        case "success":
          document.getElementById('edit-wizard-next').click();
          break;
        case "failure":
          console.log(`Worldpay status failure: ${responseData}`);
          break;
        case "error":
          console.log(`Worldpay status error: ${responseData}`);
          break;
        default:
          console.log(`Worldpay status unknown: ${responseData}`);
      }
    },
  };
})(Drupal);

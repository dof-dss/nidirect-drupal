(function (Drupal) {
  Drupal.behaviors.prisonerPaymentsWorldpay = {
    attach: function (context, settings) {

      if (!settings.worldpay || !settings.worldpay.url) {
        console.error("Worldpay iframe URL missing.");
        return;
      }

      once('worldpay-init', '#worldpay-html', context).forEach(function (element) {
        const options = {
          url: settings.worldpay.url,
          type: 'iframe',
          inject: 'onload',
          target: 'worldpay-html',
          accessibility: true,
          debug: true,
          language: 'en',
          country: 'gb',
          resultCallback: Drupal.behaviors.prisonerPaymentsWorldpay.handleResult,
        };

        const libraryObject = new WPCL.Library();
        libraryObject.setup(options);
      });

    },
    handleResult: function(responseData) {
      const wpResponse = document.querySelector('[data-drupal-selector="edit-wp-response"]');
      if (!wpResponse) {
        console.error('Webform response field missing.');
        return;
      }

      wpResponse.value = JSON.stringify(responseData);

      const submitButton = document.querySelector('[data-drupal-selector="edit-actions-submit"]');
      if (!submitButton) {
        console.error('Submit button not found.');
        return;
      }

      const status = responseData.order?.status;
      switch (status) {
        case "success":
          console.log('Payment successful, submitting webform...');
          submitButton.click();
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

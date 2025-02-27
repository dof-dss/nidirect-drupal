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
        console.dir(libraryObject);

        //const prevButton = document.querySelector('[data-drupal-selector="edit-actions-wizard-prev"]');
        //prevButton.style.display = 'none';
      });

    },
    handleResult: function(responseData) {
      const iframeContainer = document.getElementById('worldpay-html');
      const overlay = document.createElement('div');
      overlay.className = 'payment-processing-overlay';
      overlay.ariaLive = 'assertive';
      iframeContainer.appendChild(overlay);

      overlay.innerHTML = `
        <div class="ajax-spinner ajax-spinner--fill-container">
          <span class="ajax-spinner__label visually-hidden">Please wait</span>
          <div class="rect1"></div>
          <div class="rect2"></div>
          <div class="rect3"></div>
          <div class="rect4"></div>
          <div class="rect5"></div>
        </div>`;

      const wpResponse = document.querySelector('[data-drupal-selector="edit-wp-response"]');
      if (!wpResponse) {
        console.error('Webform response field missing.');
        return;
      }

      wpResponse.value = JSON.stringify(responseData);

      const prevButton = document.querySelector('[data-drupal-selector="edit-actions-wizard-prev"]');

      const submitButton = document.querySelector('[data-drupal-selector="edit-actions-submit"]');
      if (!submitButton) {
        console.error('Submit button not found.');
        return;
      }

      const status = responseData.order?.status;

      if (status === 'success') {
        submitButton.click();
      } else {
        iframeContainer.removeChild(overlay);
        //prevButton.style.display = 'inline-block';
        console.log(`Worldpay payment did not succeed. Status: ${status}`);
        console.dir(responseData);
      }
    },
  };
})(Drupal);

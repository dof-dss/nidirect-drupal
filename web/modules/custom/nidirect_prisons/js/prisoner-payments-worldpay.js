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
          disableScrolling: true,
          debug: false,
          language: 'en',
          country: 'gb',
          resultCallback: Drupal.behaviors.prisonerPaymentsWorldpay.handleResult,
        };

        Drupal.worldpayLibrary = new WPCL.Library();
        Drupal.worldpayLibrary.setup(options);
      });

    },
    handleResult: function(responseData) {
      const iframeContainer = document.getElementById('worldpay-html');

      if (!iframeContainer) {
        console.warn('Worldpay callback received after session expiry.');
        return;
      }

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
        iframeContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        console.log(`Worldpay payment did not succeed. Status: ${status}`);
        console.dir(responseData);
      }
    },
  };
})(Drupal);

(function ($, Drupal, once) {
  Drupal.behaviors.cancelPaymentConfirm = {
    attach: function (context) {
      // 1. Target the button via its data-drupal-selector
      once('cancel-confirm', '[data-drupal-selector="edit-cancel-payment"]', context).forEach(function (el) {
        $(el).on('click', function (e) {
          // 2. Prevent the immediate server-side trigger
          e.preventDefault();
          const $originalButton = $(this);

          // 3. Open Drupal Modal
          const $dialogHtml = $('<div><p>' + Drupal.t('Are you sure you want to cancel this payment? Any details you’ve entered will be lost and you’ll be taken back to the start.') + '</p></div>');
          const confirmDialog = Drupal.dialog($dialogHtml, {
            title: Drupal.t('Confirm cancel payment'),
            classes: {
              "ui-dialog": "payment-cancel-modal",
              "ui-dialog-titlebar": "info-notice"
            },
            width: 'auto',
            height: 'auto',
            resizable: false,
            buttons: [
              {
                text: Drupal.t('Yes, cancel payment'),
                class: 'btn btn--primary',
                click: function () {
                  // 4. Close dialog and trigger the ACTUAL button click to run PHP handler
                  $(this).dialog('close');
                  $originalButton.off('click'); // Remove this listener to avoid infinite loop
                  $originalButton.mousedown().click();
                }
              },
              {
                text: Drupal.t('No, do not cancel'),
                class: 'btn btn--standard',
                click: function () {
                  $(this).dialog('close');
                }
              }
            ],
          });
          confirmDialog.showModal();
        });
      });
    }
  };
})(jQuery, Drupal, once);


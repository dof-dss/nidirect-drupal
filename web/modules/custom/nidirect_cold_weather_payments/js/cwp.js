(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.cwpPostcodeFormat = {
    attach(context) {
      const postcodeField = once('cwp-postcode-format', '#edit-postcode', context);

      if (!postcodeField.length) {
        return;
      }

      postcodeField[0].addEventListener('input', function (event) {
        let value = event.target.value.toUpperCase().replace(/[^A-Z0-9]/g, '');

        const postcode = value.match(/^(BT\d{1,2})(\d[A-Z]{2})$/);
        if (postcode) {
          value = `${postcode[1]} ${postcode[2]}`;
        }

        event.target.value = value;
      });
    }
  };
})(Drupal, once);

(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.cwpClearErrors = {
    attach(context) {
      const postcodeField = once('cwp-clear-errors', '#edit-postcode', context);

      if (!postcodeField.length) {
        return;
      }

      postcodeField[0].addEventListener('focus', function (event) {
        this.classList.remove('error');
        const errorMessages = document.querySelectorAll('.form-item--error-message');
        errorMessages.forEach(function (message) {
          message.remove();
        });
      });
    }
  };
})(Drupal, once);

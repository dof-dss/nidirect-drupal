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

        if (value.length === 5 && Number.isNaN(value.charAt(4)) || value.length > 5) {
          const outward = value.slice(0, -3);
          const inward = value.slice(-3);
          value = outward + ' ' + inward;
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

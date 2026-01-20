(function (Drupal) {
  Drupal.behaviors.prisonerPaymentsTimeout = {
    attach: function (context, settings) {

      if (!settings.prisonerPayments && !settings.prisonerPayments.expiresAt) {
        return;
      }

      const countdownEl = once('payment-countdown', '#payment-countdown', context)[0];
      if (!countdownEl) {
        return;
      }

      const expiresAtMs = settings.prisonerPayments.expiresAt * 1000;

      function updateCountdown() {
        const now = Date.now();
        const remaining = expiresAtMs - now;

        if (remaining <= 0) {
          countdownEl.textContent =
            Drupal.t('Your payment session has expired. Please restart the payment.');

          // Reload to trigger backend expiry handling
          setTimeout(() => {
            window.location.reload();
          }, 1500);

          return;
        }

        const minutes = Math.floor(remaining / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);

        countdownEl.textContent = Drupal.t(
          'For security reasons, this payment session will expire in @time.',
          {
            '@time': minutes + ':' + seconds.toString().padStart(2, '0'),
          }
        );
      }

      updateCountdown();
      setInterval(updateCountdown, 1000);
    }
  };
})(Drupal, drupalSettings);

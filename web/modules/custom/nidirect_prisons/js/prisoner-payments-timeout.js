(function (Drupal, once) {
  'use strict';

  let prisonerPaymentSessionExpired = false;

  Drupal.behaviors.prisonerPaymentCountdown = {
    attach(context, settings) {

      const container = once(
        'prisonerPaymentCountdown',
        '.webform-submission-prisoner-payments-form',
        context
      );

      if (!container.length) {
        return;
      }

      const cfg = settings.prisonerPayments || {};
      const softTimeoutMs = (cfg.softTimeout || 0) * 1000;
      const hardTimeoutMs = (cfg.hardTimeout || 0) * 1000;
      const startTimeMs = Number.isFinite(cfg.startTime) ? cfg.startTime * 1000 : Date.now();

      const restartUrl = cfg.restartUrl || '/forms/prisoner-payment';

      if (!softTimeoutMs) {
        return;
      }

      // Create a fixed countdown display.
      const countdownEl = document.createElement('div');
      countdownEl.id = 'prisoner-payment-countdown';
      countdownEl.className = 'payment-countdown';

      countdownEl.setAttribute('aria-live', 'polite');
      document.querySelector(
        '[data-webform-key="page_payment_amount"], ' +
        '[data-webform-key="page_payment_card_details"]'
      ).append(countdownEl);

      function formatTime(ms) {
        const totalSeconds = Math.max(0, Math.floor(ms / 1000));
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return `${minutes} minutes ${seconds.toString().padStart(2, '0')} seconds`;
      }

      function expireSession() {
        prisonerPaymentSessionExpired = true;

        // Tear down Worldpay iframe cleanly
        if (Drupal.worldpayLibrary && typeof Drupal.worldpayLibrary.destroy === 'function') {
          Drupal.worldpayLibrary.destroy();
          Drupal.worldpayLibrary = null;
        }

        // Replace webform with session timeout message.
        container[0].innerHTML = `
          <p class="info-notice info-notice--error">
             Your payment session has expired. Please start again.
          </p>
          <div class="launch-service">
            <a href="${restartUrl}" class="btn btn-large btn--primary" data-self-ref="true">Start again</a>
          </div>
        `;

        if (countdownEl && countdownEl.parentNode) {
          countdownEl.parentNode.removeChild(countdownEl);
        }

        clearInterval(timer);
        timer = null;
      }

      function tick() {
        const elapsed = Date.now() - startTimeMs;
        const remaining = hardTimeoutMs - elapsed;
        if (remaining < 2 * 60 * 1000) {
          countdownEl.classList.add('payment-countdown-warning');
          countdownEl.innerHTML = `<span class="visually-hidden">Warning!</span>You have ${formatTime(remaining)} left to make a payment`;
        }
        if (remaining <= 0) {
          expireSession();
          return;
        }
        else {
          countdownEl.innerHTML = `You have ${formatTime(remaining)} left to make a payment`;
        }
      }

      let timer = null;
      tick();
      timer = setInterval(tick, 1000);
    }
  };

  Drupal.behaviors.prisonerPaymentsHeartbeat = {
    attach(context, settings) {

      if (
        !settings.prisonerPayments ||
        !settings.prisonerPayments.orderCode
      ) {
        return;
      }

      const attachOnce = once(
        'prisoner-payments-heartbeat',
        'body',
        context
      );

      if (!attachOnce.length) {
        return;
      }

      const softTimeoutMs = (settings.prisonerPayments.softTimeout || 0) * 1000;

      // Fallback: 30s if misconfigured
      const heartbeatIntervalMs = softTimeoutMs
        ? Math.floor(softTimeoutMs / 2)
        : 30000;

      let heartbeatTimer = null;

      heartbeatTimer = setInterval(() => {

        if (prisonerPaymentSessionExpired) {
          clearInterval(heartbeatTimer);
          heartbeatTimer = null;
          return;
        }

        fetch(Drupal.url('prisoner-payments/heartbeat'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            order_code: settings.prisonerPayments.orderCode,
          }),
          credentials: 'same-origin',
        });

      }, heartbeatIntervalMs);
    }
  };
})(Drupal, once);



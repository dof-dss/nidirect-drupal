(function (Drupal, once) {
  'use strict';

  let prisonerPaymentSessionExpired = false;

  Drupal.behaviors.prisonerPaymentCountdown = {
    attach(context, settings) {

      const container = once(
        'prisoner-payment-countdown',
        '#worldpay-html',
        context
      );

      if (!container.length) {
        return;
      }

      const cfg = settings.prisonerPayments || {};
      const softTimeoutMs = (cfg.softTimeout || 0) * 1000; // Convert to milliseconds.
      const restartUrl = cfg.restartUrl || '/forms/prisoner-payment';

      if (!softTimeoutMs) {
        return;
      }

      // Create countdown UI
      const countdownEl = document.createElement('div');
      countdownEl.id = 'payment-countdown';
      countdownEl.className = 'payment-countdown';
      countdownEl.setAttribute('aria-live', 'polite');

      container[0].prepend(countdownEl);

      const startTime = Date.now();
      let warningShown = false;

      function formatTime(ms) {
        const totalSeconds = Math.max(0, Math.floor(ms / 1000));
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
      }

      function expireSession() {
        prisonerPaymentSessionExpired = true;

        // Replace Worldpay iframe/container
        container[0].innerHTML = `
          <div class="payment-expired-message info-notice info-notice--error">
            <p><strong>Your payment session has expired.</strong></p>
            <p>Please restart the payment process.</p>
            <p><a href="${restartUrl}">Start a new payment</a></p>
          </div>
        `;

        clearInterval(timer);
        timer = null;
      }

      function tick() {
        const elapsed = Date.now() - startTime;
        const remaining = softTimeoutMs - elapsed;

        console.log(`Elapsed: ${elapsed}, Remaining: ${remaining}`);

        if (remaining <= 0) {
          expireSession();
          return;
        }

        // 2-minute warning (once)
        if (!warningShown && remaining <= 2 * 60 * 1000) {
          countdownEl.classList.add('payment-countdown--warning');
          warningShown = true;
        }

        countdownEl.textContent =
          `Time remaining to complete payment: ${formatTime(remaining)}`;
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

      let heartbeatTimer = null;

      heartbeatTimer = setInterval(() => {

        // Stop heartbeats if session expired client-side
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

      }, 30000);
    }
  };
})(Drupal, once);



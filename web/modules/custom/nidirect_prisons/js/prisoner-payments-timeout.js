(function (Drupal, once) {
  'use strict';

  let state = {
    expired: false,
    heartbeatTimer: null,
    countdownTimer: null,
    expireUI: null,
    sendHeartbeat: null
  };

  function stopHeartbeat() {
    if (state.heartbeatTimer) {
      clearInterval(state.heartbeatTimer);
      state.heartbeatTimer = null;
    }
  }

  function stopCountdown() {
    if (state.countdownTimer) {
      clearInterval(state.countdownTimer);
      state.countdownTimer = null;
    }
  }

  function expireOnce() {
    if (state.expired) {
      return;
    }

    state.expired = true;
    stopHeartbeat();
    stopCountdown();

    if (typeof state.expireUI === 'function') {
      state.expireUI();
    }
  }

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
      const startTimeMs = Number.isFinite(cfg.startTime)
        ? cfg.startTime * 1000
        : Date.now();

      const restartUrl = cfg.restartUrl || '/forms/prisoner-payment';

      if (!softTimeoutMs || !hardTimeoutMs) {
        return;
      }

      const target = document.querySelector(
        '[data-webform-key="page_payment_amount"], ' +
        '[data-webform-key="page_payment_card_details"]'
      );

      if (!target) {
        return;
      }

      const countdownEl = document.createElement('div');
      countdownEl.id = 'prisoner-payment-countdown';
      countdownEl.className = 'payment-countdown';
      countdownEl.setAttribute('aria-live', 'polite');
      target.append(countdownEl);

      function formatTime(ms) {
        const totalSeconds = Math.max(0, Math.floor(ms / 1000));
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        return `${minutes} minutes ${seconds.toString().padStart(2, '0')} seconds`;
      }

      state.expireUI = function () {

        if (Drupal.worldpayLibrary && typeof Drupal.worldpayLibrary.destroy === 'function') {
          Drupal.worldpayLibrary.destroy();
          Drupal.worldpayLibrary = null;
        }

        container[0].innerHTML = `
          <p class="info-notice info-notice--error">
            Your payment session has expired. Please start again.
          </p>
          <div class="launch-service">
            <a href="${restartUrl}" class="btn btn-large btn--primary" data-self-ref="true">
              Start again
            </a>
          </div>
        `;

        if (countdownEl.parentNode) {
          countdownEl.parentNode.removeChild(countdownEl);
        }
      };

      function tick() {
        if (state.expired) {
          return;
        }

        const elapsed = Date.now() - startTimeMs;
        const remaining = hardTimeoutMs - elapsed;

        if (remaining <= 0) {
          stopCountdown();

          // Force immediate server expiry check
          if (typeof state.sendHeartbeat === 'function') {
            state.sendHeartbeat();
          }

          return;
        }

        if (remaining < 2 * 60 * 1000) {
          countdownEl.classList.add('payment-countdown-warning');
          countdownEl.innerHTML =
            `<span class="visually-hidden">Warning!</span>
             You have ${formatTime(remaining)} left to make a payment`;
        } else {
          countdownEl.innerHTML =
            `You have ${formatTime(remaining)} left to make a payment`;
        }
      }

      tick();
      state.countdownTimer = setInterval(tick, 1000);
    }
  };

  Drupal.behaviors.prisonerPaymentsHeartbeat = {
    attach(context, settings) {

      if (!settings.prisonerPayments || !settings.prisonerPayments.orderCode) {
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

      const heartbeatIntervalMs = softTimeoutMs
        ? Math.max(10000, Math.floor(softTimeoutMs / 2))
        : 30000;

      state.sendHeartbeat = function () {

        if (state.expired) {
          stopHeartbeat();
          return;
        }

        fetch(Drupal.url('prisoner-payments/heartbeat'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            order_code: settings.prisonerPayments.orderCode,
          }),
          credentials: 'same-origin',
        })
          .then(response => {
            if (response.status === 410) {
              expireOnce();
            }
          })
          .catch(() => {
            // Ignore transient network failures.
            // Heartbeat continues on next interval.
          });
      };

      state.sendHeartbeat(); // Initial check
      state.heartbeatTimer = setInterval(state.sendHeartbeat, heartbeatIntervalMs);
    }
  };

})(Drupal, once);

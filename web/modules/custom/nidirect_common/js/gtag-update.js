/**
 * @file
 * gtag-update.js
 *
 * EUCC consent handling for Google Consent Mode.
 */

(function (Drupal, drupalSettings) {

  Drupal.behaviors.gtagUpdateConsent = {
    attach: function (context, settings) {

      // If consent mode is enabled in Google Tag module settings, ensure
      // consent updates are triggered when EUCC statuses / preferences
      // change.

      const consentModeEnabled = (drupalSettings.gtag && drupalSettings.gtag.consentMode === true) ?? false;

      if (consentModeEnabled) {

        // Initialise the dataLayer Google Tag Manager and gtag.js uses to
        // pass information to tags.
        window.dataLayer = window.dataLayer || [];

        // Initialise Drupal.eu_cookie_compliance to add event
        // handlers to it (see eu_cookie_compliance/README.md).
        Drupal.eu_cookie_compliance = Drupal.eu_cookie_compliance || function() {
          (Drupal.eu_cookie_compliance.queue = Drupal.eu_cookie_compliance.queue || []).push(arguments)
        };

        // Functions to grant or deny consent for
        // Google analytics_storage.
        const gtagGrantAnalytics = function() {
          gtag('consent', 'update', {
            'analytics_storage': 'granted'
          });
          console.log('gtagGrantAnalytics');
        }

        const gtagDenyAnalytics = function() {
          gtag('consent', 'update', {
            'analytics_storage': 'denied'
          });
          console.log('gtagDenyAnalytics');
        }

        // Handler for EUCC status / preference change events. Check status
        // of EUCC consent and grant or deny Google Analytics consent
        // accordingly.
        const euccConsentHandler = function(response) {

          window.cookieResponse = response;
          const status = response.currentStatus ? parseInt(response.currentStatus) : null;

          if (status > 0) {
            gtagGrantAnalytics();
          } else {
            gtagDenyAnalytics();
          }

          // Push event to indicate cookie choices made.
          if (status !== null) {
            window.dataLayer.push({
              'event': 'eucc_preferences_completed'
            });
          }
        }

        // Add handler to relevant EUCC events.
        Drupal.eu_cookie_compliance('postPreferencesLoad', euccConsentHandler);
        Drupal.eu_cookie_compliance('postStatusSave', euccConsentHandler);
      }

    }
  };

})(Drupal, drupalSettings);

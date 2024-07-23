(function (Drupal, drupalSettings) {

  const gtagGrantAnalytics = function() {
    gtag('consent', 'update', {
      'analytics_storage': 'granted'
    });
    console.log('gtag consent granted');
  }

  const gtagDenyAnalytics = function() {
    gtag('consent', 'update', {
      'analytics_storage': 'denied'
    });
    console.log('gtag consent denied');
  }

  // Handle EUCC events.
  const euccConsentHandler = function(response) {
    console.log(response.currentStatus);
    window.cookieResponse = response;
    if (parseInt(response.currentStatus) === 2) {
      console.log(`EUCC - user has agreed`);
      gtagGrantAnalytics();
    } else {
      console.log(`EUCC - user has declined`);
      gtagDenyAnalytics();
    }
  }

  Drupal.eu_cookie_compliance('postPreferencesLoad', euccConsentHandler);
  Drupal.eu_cookie_compliance('postStatusSave', euccConsentHandler);

})(Drupal, drupalSettings);

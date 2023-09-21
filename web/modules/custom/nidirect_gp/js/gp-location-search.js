/**
 * @file
 * Defines Javascript behaviors for Google Maps Lazy Load Formatter.
 */

(function ($, Drupal) {
  Drupal.behaviors.gpLocationSearch = {
    attach: (context) => {

      let locationOptions = {
        enableHighAccuracy: false,
        timeout: 5000,
        maximumAge: 0
      };

      // Callback function to initiate the GP location search.
      function performLocationSearch(location) {
        let lat = location.coords.latitude;
        let lng = location.coords.longitude;
        // @codingStandardsIgnoreStart
        const distance = drupalSettings.nidirect.gpSearch.maxDistance ?? 10;
        // @codingStandardsIgnoreEnd
        let url = '/services/gp-practices?lat=' + lat + '&lng=' + lng + '&proximity=' + distance;
        window.location.href = url;
      }

      // Callback function for geolocation errors.
      function locationError(err) {

        console.warn(`ERROR(${err.code}): ${err.message}`);
        $('#find-by-location-status').html('');

        let errmsg = '<p>' + Drupal.t('There was a problem finding your location.') + '</p>' +
          '<p>' + Drupal.t('Try searching by entering a GP name, practice, town or postcode') + '</p>';

        const confirmationDialog = Drupal.dialog('<div>' + errmsg + '.</div>', {
          title: Drupal.t('Sorry'),
          dialogClass: 'editor-change-text-format-modal',
          resizable: false,
          closeOnEscape: true,
          buttons: [{
            text: Drupal.t('OK'),
            class: 'button button--primary',
            click() {
              confirmationDialog.close();
              $('#edit-search-api-views-fulltext').focus();
            },
          }]
        });

        confirmationDialog.showModal();
      }

      $(context).find('#views-exposed-form-gp-practices-find-a-gp').once('gp-location-search').each(function () {

        let $searchForm = $(this);
        let querystring = new URLSearchParams(window.location.search);
        let locationButtonTxt = 'Use my location';

        if (querystring.has('lat') || querystring.has('lng')) {
          locationButtonTxt = 'Reset my location';
        }

        // Display 'Use my location' if the browser supports the Geolocation API.
        if('geolocation' in navigator) {
          $searchForm.prepend('<div class="find-by-location">' +
            '<label for="use_location">'
            + '<span class="visually-hidden">' + Drupal.t('Use your location to') + '</span>'
            + Drupal.t('Search for a GP practice near you') +
            '</label>' +
            '<input type="button" class="button button--primary button--medium" id="use_location" name="use_location" value="' + Drupal.t(locationButtonTxt) + '" />' +
            '<div id="find-by-location-status" role="alert" aria-live="assertive"></div>' +
            '</div>');

          $searchForm
            .find('#use_location').on('click', function () {
              // Update status with a ajax progress indicator (even though it's not an ajax call).
              $('#find-by-location-status').html(Drupal.theme.ajaxProgressIndicatorFullscreen());

              // Do the location search
              navigator.geolocation.getCurrentPosition(performLocationSearch, locationError, locationOptions);
            });
          $searchForm
            .find('label[for="edit-search-api-views-fulltext"]')
            .text(Drupal.t('Or enter a GP name, practice, town or postcode'));
        }
      });
    }
  };

}(jQuery, Drupal));

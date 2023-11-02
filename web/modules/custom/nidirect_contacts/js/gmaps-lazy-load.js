/**
 * @file
 * Defines Javascript behaviors for Google Maps Lazy Load Formatter.
 */

(function ($, Drupal) {
  Drupal.behaviors.gmapslazyLoad = {
    attach: (context) => {

      // Define observer options.
      let options = {
        root: null,
        rootMargin: '0px',
        threshold: 0.66
      };

      // Callback for observer targets.
      let callback = (entries, observer) => {
          entries.forEach(entry => {
            if (entry.isIntersecting) {
              entry.target.classList.add('gmap-loaded');

              // Define map latitude and longitude coordinates.
              let mapLatLng = {
                lat: parseFloat(entry.target.dataset.lat),
                lng: parseFloat(entry.target.dataset.lng)
              };

              // Google map settings.
              let mapSettings = {
                mapTypeId: entry.target.dataset.maptype,
                zoom: parseInt(entry.target.dataset.zoom),
                center: mapLatLng,
              };

              // Create a new google map targeting the observed target element.
              let map = new google.maps.Map(document.getElementById(entry.target.id), mapSettings);

              // Add a marker to the map using the existing coordinates.
              let marker = new google.maps.Marker({
                position: mapLatLng,
                map: map,
              });

              // Unsubscribe this target from the observer.
              observer.unobserve(entry.target);
            }
          });
        };

      // Subscribe gmap elements to the observer.
      $(context).find('.gmap-lazy-load').each(function () {
        let observer = new IntersectionObserver(callback, options);
        let target = $(this)[0];
        observer.observe(target);
      });
    }
  }
}(jQuery, Drupal));

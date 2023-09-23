/**
 * @file
 * Provides a script to perform various tasks in relation to site wide search
 *
 */

/* eslint-disable */
(function ($, Drupal) {
  Drupal.behaviors.nicsdruAutoComplete = {
    attach: function attach (context) {

      var $autoComplete = $('.form-type-search-api-autocomplete');
      var topOffsetBuffer = 15;

      $autoComplete.focusin(function () {
        // Add a css class to the body to indicate it has focus.
        $('body').toggleClass('search-autocomplete-focussed', true);

        // If hamburger menu toggle is visible, we are on a narrow screen.
        // Scroll the search form to the top of the page.
        if ($('.hamburger').is(":visible")) {
          $('body,html').animate({
            scrollTop: $(this).offset().top - topOffsetBuffer,
          }, 300);
        }
      });

      // When search input loses focus, remove css class indicating it had focus.
      $autoComplete.focusout(function () {
        $('body').toggleClass('search-autocomplete-focussed', false);
      });
    }
  };
})(jQuery, Drupal);

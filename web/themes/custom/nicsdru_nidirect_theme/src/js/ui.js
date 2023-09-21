/**
 * @file
 * Javascript for User Interface functions.
 *
 */

/* eslint-disable */
/*
 * User Interface scripts.
 */
(function ($, Drupal) {
  Drupal.behaviors.nidirectui = {
    attach: function attach (context) {

      // Toggles the display of links within a card with multiple
      // field_link values.
      $('.card--multilink', context).once('nicsdruAccordions').each(function(index) {
        var links_wrapper = $(this).find('.card__links');

        // Show/hide event handler.
        $(this).on('click', function () {
          $(links_wrapper).toggle();
        })

        // Initially hide the links.
        $(links_wrapper).hide();

        // Prevent link click event from triggering parent show/hide handler.
        $(links_wrapper).find('a').click(function(e) {
          e.stopPropagation();
        });
      });

      // Hide duplicated status messages from layout builder.
      const lbMessages = '#layout-builder div[data-drupal-messages=""]';
      if ($(lbMessages)) {
        $(lbMessages).hide();
      }
    }
  };
})(jQuery, Drupal);

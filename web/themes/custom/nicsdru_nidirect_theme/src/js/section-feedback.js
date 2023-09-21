/**
 * @file
 * Provides a script to handle the page feedback form section at the bottom of all pages.
 *
 */

/* eslint-disable */
(function ($, Drupal) {
  Drupal.behaviors.nicsdruSectionFeedback = {
    attach: function attach (context) {

      $('#section-feedback', context).once('nicsdruSectionFeedback').each(function() {

        var $feedback_heading = $('#section-feedback__heading');
        var $feedback_form = $('#section-feedback__form');
        var $btn = $('<button></button>');

        $btn
          .attr('id', 'section-feedback__toggle')
          .addClass('section-feedback__toggle')
          .attr('aria-controls', 'section-feedback__form')
          .attr('aria-expanded', 'false')
          .click(function() {
            var $target = $feedback_form;
            var expanded = $(this).attr('aria-expanded') === 'true' || false;
            $(this).attr('aria-expanded', !expanded);
            $target.toggleClass('expanded', !$target.hasClass('expanded'));
          });

        $feedback_heading.wrapInner($btn);

      });
    }
  };

})(jQuery, Drupal);

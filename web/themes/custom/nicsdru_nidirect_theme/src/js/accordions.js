/**
 * @file
 * Provides a script to collapse accordion sections and expand them when section headings are clicked.
 *
 */

/* eslint-disable */
(function ($, Drupal) {
  Drupal.behaviors.nicsdruAccordions = {
    attach: function attach (context) {

      $(once('nicsdruAccordions', '.accordion', context)).each(function(index) {

        // Need to know if this accordion has a visible title (H2)
        var acc_title_visible = ($(this).find('.accordion-heading').length)? true : false;

        // If the accordion has no title (H2), H3 section headings should be H2s.
        if (!acc_title_visible) {
          $('h3.accordion-section-title').replaceWith(function(){
            return $('<h2 class="accordion-section-title" />').append($(this).contents());
          });
        }

        // Get the accordion headings and sections
        var $acc_headings = $(this).find('.accordion-section-title');
        var $acc_sections = $(this).find('.accordion-section');

        // Need a unique prefix for heading buttons and section attributes.
        var prefix = 'accordion-' + index;

        $acc_headings.each(function(index) {
          // Construct the button for this heading.
          // Include appropriate aria attributes for accessibility.
          var $btn = $('<button></button>');
          $btn.attr('id', prefix + '-heading-' + index)
            .attr('aria-controls', prefix + '-section-' + index)
            .attr('aria-expanded', 'false')
            .click(function() {
              var $target = $('#' + prefix + '-section-' + index);
              var expanded = $(this).attr('aria-expanded') === 'true' || false;
              $(this).attr('aria-expanded', !expanded);
              $target.toggleClass('expanded', !$target.hasClass('expanded'));
            });

          // Append button to heading or wrap the heading with the button.
          if ($(this).children('a').length) {
            // Appended button needs its own button text.
            $btn.html('<span class="visually-hidden">show/hide details</span>');
            $(this).append($btn);
          } else {
            $(this).wrapInner($btn);
          }
        });

        $acc_sections.each(function(index) {
          $(this)
            .attr('id', prefix + '-section-' + index)
            .attr('aria-labelledby', prefix + '-heading-' + index);
        });

      });
    }
  };

})(jQuery, Drupal);

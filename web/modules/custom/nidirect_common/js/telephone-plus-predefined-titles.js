/**
 * @file
 * Javascript behaviors for Telephone Plus field overrides.
 */
(function ($) {

  "use strict";

  /**
   * Attach behaviour to allow altering of Telephone Plus title field
   */
  Drupal.behaviors.telephonePlusPredefined = {
    attach: function (context, settings) {
      $('.telephone-predefined', context)
        .once('telephone-predefined-select')
        .change(function () {
          var title_field = $(this).parent().next().find('.telephone-title');
          if ($(this).val() === '' || $(this).val() === 'Other') {
            title_field.val('');
          } else {
            title_field.val($(this).find(':selected').text());
          }
        });
    },
  }

}(jQuery, Drupal));

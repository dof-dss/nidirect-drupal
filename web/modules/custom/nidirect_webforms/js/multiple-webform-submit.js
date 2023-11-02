/**
 * @file
 * JQuery to look for pages that contain the feedback webform and other wedforms.
 * If other webforms are found then the ids of the submit buttons are updated
 * to make sure that they are unique.
 */

(function ($, Drupal) {
  Drupal.behaviors.searchConditionsPrompt = {
    attach: function attach (context) {
      // Is there a feedback webform on this page ?
      if ($(context).find('form.webform-submission-your-comments-form').length) {
        // Look for any other webforms with submit buttons that
        // have the same id as the one in the feedback form.
        var counter = 2;
        $(context).find('#edit-actions-submit').each(function () {
          // We have found one, update the id so that it is unique.
          $(this).attr('id','edit-actions-submit-' + counter);
          counter++;
        });
      }
    }
  };
}(jQuery, Drupal));

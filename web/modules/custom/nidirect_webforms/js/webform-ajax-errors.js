/**
 * @file
 * JS to ensure webform ajax errors shown as Drupal messages are
 * visible to user.
 */
window.addEventListener("error", (event) => {
  if (event.error instanceof Drupal.AjaxError && Drupal.AjaxError.messages) {
    // Ensure the messages wrapper is visible to the user.
    Drupal.AjaxError.messages.messageWrapper.scrollIntoView({ behavior: 'smooth' });
  }
});

/**
 * JQuery to ensure webform ajax errors that use Drupal.Message()
 * are cleared when ajax replaces the form.
 */
(function($, Drupal, once) {

  Drupal.behaviors.clearDrupalAjaxErrorMessages = {
    attach: function (context, settings) {
      if (Drupal.AjaxError.messages) {
        Drupal.AjaxError.messages.clear();
      }
    }
  };

})(jQuery, Drupal, once);

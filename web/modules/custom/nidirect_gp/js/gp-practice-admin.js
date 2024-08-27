/**
 * @file
 * Defines Javascript behaviors for GP Practice node form.
 */

(function ($, Drupal) {
  Drupal.behaviors.gpPracticeAdmin = {
    attach: (context) => {

      // Update the title fields when the URI field is changed and trigger when the page is loaded.
      $('#edit-field-gp-appointments-0-uri').on('change', (function () {
        if ($(this).val().trim().length === 0) {
          $('input[name="field_gp_appointments[0][title]"]').val('');
        }
        else {
          $('input[name="field_gp_appointments[0][title]"]').val('Online appointments');
        }
      })).trigger('change');

      $('#edit-field-gp-prescriptions-0-uri').on('change', (function () {
        if ($(this).val().trim().length === 0) {
          $('input[name="field_gp_prescriptions[0][title]"]').val('');
        }
        else {
          $('input[name="field_gp_prescriptions[0][title]"]').val('Repeat prescriptions');
        }
      })).trigger('change');
    }
  };
}(jQuery, Drupal));

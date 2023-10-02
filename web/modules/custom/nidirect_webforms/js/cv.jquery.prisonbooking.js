/**
 * @file
 * JQuery to look for pages that contain the feedback webform and other wedforms.
 * If other webforms are found then the ids of the submit buttons are updated
 * to make sure that they are unique.
 */

(function($, Drupal, once) {

  Drupal.behaviors.prisonVisit = {
    attach: function (context, settings) {

      const prisonVisitForm = once('prisonVisitForm', 'form.webform-submission-prison-visit-online-booking-form', context);
      const $visitorRememberToggle = $('input[name="yes_remember_these_visitor_details"]', prisonVisitForm);

      let $prisonVisitOrderNumber = $(prisonVisitForm).find('input[name="visitor_order_number"]');
      let visitOrderVisitTypeKey = drupalSettings.prisonVisitBooking.booking_ref.visit_type_key;
      let visitPrisons = drupalSettings.prisonVisitBooking.prisons;
      let visitTypes = drupalSettings.prisonVisitBooking.visit_type;
      let visitAdvanceNotice = drupalSettings.prisonVisitBooking.visit_advance_notice;
      let visitBookingRefValidityPeriodDays = drupalSettings.prisonVisitBooking.booking_reference_validity_period_days;
      let visitSlotsAvailable = drupalSettings.prisonVisitBooking.visit_slots;
      let visitSequenceAffiliations = drupalSettings.prisonVisitBooking.visit_order_number_categories;
      let visitBookingRefMaxAdvancedIssue = drupalSettings.prisonVisitBooking.visit_order_number_max_advance_issue;

      $prisonVisitOrderNumber.rules( "add", {
        validPrisonVisitBookingRef: [
          true,
          visitPrisons,
          visitTypes,
          visitAdvanceNotice,
          visitBookingRefValidityPeriodDays,
          visitSlotsAvailable,
          visitSequenceAffiliations
        ],
        validVisitBookingRefDate: [
          true,
          visitPrisons,
          visitTypes,
          visitAdvanceNotice,
          visitBookingRefValidityPeriodDays,
          visitSlotsAvailable,
          visitSequenceAffiliations,
          visitBookingRefMaxAdvancedIssue
        ]
      });

      const $weekSlots = $('[data-webform-key^="slots_week"]', prisonVisitForm);
      if ($weekSlots.length === 1) {
        $weekSlots.prop("open", true);
        $('summary', $weekSlots)
          .prop('aria-expanded', true)
          .prop('aria-pressed', true);
      }

      const $timeSlots = $('input[type="checkbox"]', $weekSlots);
      const timeSlotLimit = (visitOrderVisitTypeKey === 'V') ? 5 : 3;

      disableCheckboxes($timeSlots, timeSlotLimit);

      $('[data-webform-key^="slots_week"] input[type="checkbox"]', prisonVisitForm).on('change', function(e) {
        disableCheckboxes($timeSlots, timeSlotLimit);
      });

      function disableCheckboxes($checkboxes, checked_limit = 3) {
        let checkboxesCheckedCount = $checkboxes.filter(':checked').length;
        if (checkboxesCheckedCount === checked_limit) {
          $checkboxes.filter(':not(:checked)').prop('disabled', true);
        }
        else if (checkboxesCheckedCount < checked_limit) {
          $checkboxes.filter(':not(:checked)').prop('disabled', false);
        }
      }

      function save_additional_visitors(ev){
        $("#edit-additional-visitor-details input[name^='visitor_']").each(function(index, $input){
          localStorage.setItem('pvb.' + $input.name, $input.value);
        });
        return true;
      }

      function load_additional_visitors(){
        let keys = Object.keys(localStorage);
        for (let i= 0; i < keys.length; i++){
          let key = keys[i];
          if (!key.startsWith('pvb.')){
            continue;
          }
          $("[name='" + key.split(".")[1] + "']").val(localStorage[key]);
        }
      }

      function reset_additional_visitors(ev){
        $("#edit-additional-visitor-details input[name^='visitor_']").each(function(index, $input){
          localStorage.removeItem('pvb.' + $input.name);
        });
        return true;
      }

      function visitor_settings(ev) {
        if ($visitorRememberToggle.length) {
          if ($visitorRememberToggle.is(':checked')) {
            save_additional_visitors();
          } else {
            reset_additional_visitors();
          }
        }

        return true;
      }

      if ($visitorRememberToggle.length) {
        $visitorRememberToggle.on('change', visitor_settings);
      }

      $(document).on('submit', visitor_settings);
      load_additional_visitors();
    }
  };


})(jQuery, Drupal, once);


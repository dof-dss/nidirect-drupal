/**
 * @file
 * JQuery for prison visit booking webform.
 */

(function($, Drupal, once) {

  Drupal.behaviors.prisonVisit = {
    attach: function (context, settings) {

      const prisonVisitForm = once('prisonVisitForm', 'form.webform-submission-prison-visit-online-booking-form', context);

      const $prisonVisitOrderNumber = $(prisonVisitForm).find('input[name="visitor_order_number"]');
      const visitTypeKey = drupalSettings.prisonVisitBooking.booking_ref.visit_type_key;
      const visitPrisons = drupalSettings.prisonVisitBooking.prisons;
      const visitTypes = drupalSettings.prisonVisitBooking.visit_type;
      const visitAdvanceNotice = drupalSettings.prisonVisitBooking.visit_advance_notice;
      const visitBookingRefValidityPeriodDays = drupalSettings.prisonVisitBooking.booking_reference_validity_period_days;
      const visitBookingRefMaxAdvancedIssue = drupalSettings.prisonVisitBooking.visit_order_number_max_advance_issue;

      $prisonVisitOrderNumber.rules( "add", {
        validPrisonVisitBookingRef: [
          true,
          visitPrisons,
          visitTypes,
          visitAdvanceNotice,
          visitBookingRefValidityPeriodDays,
        ],
        validVisitBookingRefDate: [
          true,
          visitPrisons,
          visitTypes,
          visitAdvanceNotice,
          visitBookingRefValidityPeriodDays,
          visitBookingRefMaxAdvancedIssue
        ]
      });

      let $weekSlots = $(once('prisonVisitSlots', '[data-webform-key^="slots_week"]', context));

      if ($weekSlots.length === 1) {
        $weekSlots.prop("open", true);
        $('summary', $weekSlots)
          .prop('aria-expanded', true)
          .prop('aria-pressed', true);
      }

      $weekSlots.each(function() {
        const $timeSlots = $('input[type="checkbox"]', $(this));
        const timeSlotLimit = (visitTypeKey === 'V') ? 5 : 3;

        disableCheckboxes($timeSlots, timeSlotLimit);

        $timeSlots.on('change', function(e) {
          disableCheckboxes($timeSlots, timeSlotLimit);
        });
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
    }
  };

})(jQuery, Drupal, once);


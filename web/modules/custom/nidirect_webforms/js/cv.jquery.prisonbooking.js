/**
 * @file
 * JQuery for prison visit booking webform.
 */

(function($, Drupal, once) {

  Drupal.behaviors.prisonVisit = {
    attach: function (context, settings) {

      const prisonVisitForm = once('prisonVisitForm', 'form.webform-submission-prison-visit-online-booking-form', context);

      const $prisonVisitOrderNumber = $(prisonVisitForm).find('input[name="visitor_order_number"]');
      const visitPrisons = settings.prisonVisitBooking.prisons;
      const visitTypes = settings.prisonVisitBooking.visit_type;
      const visitAdvanceNotice = settings.prisonVisitBooking.visit_advance_notice;
      const visitBookingRefValidityPeriodDays = settings.prisonVisitBooking.booking_reference_validity_period_days;
      const visitBookingRefMaxAdvancedIssue = settings.prisonVisitBooking.visit_order_number_max_advance_issue;

      let visitTypeId = 'F';
      if (settings.prisonVisitBooking['booking_ref'] !== null) {
        visitTypeId = settings.prisonVisitBooking.booking_ref.visit_type_id;
      }

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

      const $weekSlots = $(once('prisonVisitSlots', '[data-webform-key^="slots_week"]', context));
      const timeSlotLimit = (visitTypeId === 'V') ? 5 : 3;

      if ($weekSlots.length === 1) {
        $weekSlots.prop("open", true);
        $('summary', $weekSlots)
          .prop('aria-expanded', true)
          .prop('aria-pressed', true);
      }

      let $timeSlots = $('input[type="checkbox"]', $weekSlots);
      if ($timeSlots.length) {
        disableCheckboxes($timeSlots, timeSlotLimit);

        $timeSlots.on('change', function(e) {
          disableCheckboxes($timeSlots, timeSlotLimit);
        });
      }

      function disableCheckboxes($checkboxes, limit = 3) {
        let checkboxesCheckedCount = $checkboxes.filter(':checked').length;
        if (checkboxesCheckedCount === limit) {
          $checkboxes.filter(':not(:checked)').prop('disabled', true);
        }
        else if (checkboxesCheckedCount < limit) {
          $checkboxes.filter(':not(:checked)').prop('disabled', false);
        }
      }
    }
  };

})(jQuery, Drupal, once);


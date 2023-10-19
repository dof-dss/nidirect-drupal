/**
 * @file
 * JQuery for prison visit booking webform.
 */

(function($, Drupal, once) {

  Drupal.behaviors.prisonVisit = {
    attach: function (context, settings) {

      const pvForm = once('pvForm', 'form.webform-submission-prison-visit-online-booking-form', context);
      const $pvbRef = $(pvForm).find('input[name="visitor_order_number"]');

      const pvPrisons = settings.prisonVisitBooking.prisons;
      const pvTypes = settings.prisonVisitBooking.visit_type;
      const pvNotice = settings.prisonVisitBooking.visit_advance_notice;
      const pvbRefValidity = settings.prisonVisitBooking.booking_reference_validity_period_days;
      const pvbRefMaxAdvancedIssue = settings.prisonVisitBooking.visit_order_number_max_advance_issue;

      let pvTypeId = 'F';
      if (settings.prisonVisitBooking['booking_ref'] !== null) {
        pvTypeId = settings.prisonVisitBooking.booking_ref.visit_type_id;
      }

      $pvbRef.rules( "add", {
        validPrisonVisitBookingRef: [
          true,
          pvPrisons,
          pvTypes,
          pvNotice,
          pvbRefValidity,
        ],
        validVisitBookingRefDate: [
          true,
          pvPrisons,
          pvTypes,
          pvNotice,
          pvbRefValidity,
          pvbRefMaxAdvancedIssue
        ]
      });

      const $weekSlots = $(once('prisonVisitSlots', '[data-webform-key^="slots_week"]', context));
      const timeSlotLimit = (pvTypeId === 'V') ? 5 : 3;

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


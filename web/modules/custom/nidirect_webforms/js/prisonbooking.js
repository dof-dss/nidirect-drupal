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

      const $pvVisitorOneDob = $(once('pvVisitorOneDob', '[name="visitor_1_dob"]', context));
      if ($pvVisitorOneDob.length) {
        $pvVisitorOneDob.rules("add", {
          minAge: [true, 18],
          messages: {
            minAge: "You must be at least 18 years of age to book a prison visit"
          }
        });
      }

      let pvAdultDobSelectors = '[name="additional_visitor_adult_1_dob"], [name="additional_visitor_adult_2_dob"]';

      $(once('pvAdultDobs', pvAdultDobSelectors, context)).each(function() {
        $(this).rules("add", {
          minAge: [true, 18],
          messages: {
            minAge: "Adult visitor must be at least 18 years of age"
          }
        });
      });

      let pvChildDobSelectors = '';
      pvChildDobSelectors += '[name="additional_visitor_child_1_dob"], ';
      pvChildDobSelectors += '[name="additional_visitor_child_2_dob"], ';
      pvChildDobSelectors += '[name="additional_visitor_child_3_dob"], ';
      pvChildDobSelectors += '[name="additional_visitor_child_4_dob"], ';
      pvChildDobSelectors += '[name="additional_visitor_child_5_dob"]';

      $(once('pvChildDobs', pvChildDobSelectors, context)).each(function() {
        $(this).rules("add", {
          minAge: [true, 0],
          maxAge: [true, 18],
          messages: {
            minAge: "Date of birth cannot be greater than today's date",
            maxAge: "Child visitor must be under 18 years of age"
          }
        });
      });

      const $weekSlots = $(once('pvSlots', '[data-webform-key^="slots_week"]', context));
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

      const $additionalAdults = $(once('prisonVisitAdditionalAdults', '[name="additional_visitor_adult_number"]', context));
      const $additionalChildren = $(once('prisonVisitAdditionalChildren', '[name="additional_visitor_child_number"]', context));

      if ($additionalAdults.val() > 0) {
        $('option', $additionalChildren)
          .slice(($(this).length - 1) - parseInt($additionalAdults.val()))
          .attr('disabled', true);
      }

      $additionalAdults.on('change', function(e) {
        let numAdults = $(this).val() ?? 0;

        if (numAdults > 0) {
          $('option', $additionalChildren)
            .removeAttr('disabled')
            .slice(($(this).length - 1) - numAdults)
            .attr('disabled', true)
            .prop('selected', false);
        } else {
          $('option', $additionalChildren).removeAttr('disabled')
        }

        if ($('option:selected', $additionalChildren).is(':disabled')) {
          $('option').not(':disabled').last().prop('selected', true);
        }
      });


      $(once('pvTextAreas', 'textarea', context)).each(function() {
        $(this).rules("add", {
          noHtml: true
        });
      });

    }
  };

})(jQuery, Drupal, once);


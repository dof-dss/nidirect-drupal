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

      let $prisonVisitOrderNumber = $(prisonVisitForm).find('input[name="visitor_order_number"]');
      let visitTypes = Object.keys(drupalSettings.prisonVisitBooking.visit_type);
      let visitPrisonIDs = Object.keys(drupalSettings.prisonVisitBooking.prisons);
      let visitAdvanceNotice = drupalSettings.prisonVisitBooking.visit_advance_notice;
      let visitBookingRefValidityPeriodDays = drupalSettings.prisonVisitBooking.booking_reference_validity_period_days;


      $prisonVisitOrderNumber.rules( "add", {
        validPrisonVisitBookingRef: [true, visitPrisonIDs, visitTypes, visitAdvanceNotice, visitBookingRefValidityPeriodDays]
      });

      let visitSlotsAvailable = drupalSettings.prisonVisitBooking.visit_slots;

      let strJSON = JSON.stringify(visitSlotsAvailable);
      strJSON = JSON.stringify(visitSlotsAvailable, null, 4);
      
      console.log(strJSON);

    }
  };
})(jQuery, Drupal, once);

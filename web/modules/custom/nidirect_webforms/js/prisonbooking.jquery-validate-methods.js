/**
 * @file
 * Defines jquery-validation methods and rules for prison visit booking.
 */
(function ($, Drupal, once) {

  Drupal.behaviors.prisonVisitValidateMethods = {
    attach: function (context, settings) {

      // Add validation methods once globally for the page.
      once('prisonVisitValidateMethods', 'html').forEach(function (element) {

        // Validation methods and related functions

        $.validator.addMethod('validPrisonVisitBookingRef', function (value, element, params) {

          let bookRefIsValid = true;

          const pvbID = value.slice(0, 2),
            pvbTypeID = value.slice(2, 3),
            pvbWeek = parseInt(value.slice(3, 5)),
            pvbYear = parseInt(value.slice(5, 7));

          if (Object.keys(params[1]).includes(pvbID) !== true) {
            bookRefIsValid = false;
          }

          if (Object.keys(params[2]).includes(pvbTypeID) !== true) {
            bookRefIsValid = false;
          }

          // Validate order number week and year.
          if (pvbWeek < 1 || pvbWeek > 53) {
            bookRefIsValid = false;
          } else if (pvbYear < 1 || pvbYear > 99) {
            bookRefIsValid = false;
          }

          return bookRefIsValid;
        }, `Visit reference number is not recognised.`);

        $.validator.addMethod('validVisitBookingRefDate', function (value, element, params) {

          let bookRefIsValid = true;

          const pvbID = value.slice(0, 2),
            pvbTypeID = value.slice(2, 3),
            pvbWeek = parseInt(value.slice(3, 5)),
            pvbYear = parseInt(value.slice(5, 7)),
            pvbRefValidityDays = parseInt(params[4][pvbTypeID]),
            pvbAdvanceNoticeHours = parseInt(params[3][pvbTypeID]),
            pvbRefMaxAdvanceIssueWeeks = parseInt(params[5]),
            pvbPrisonName = params[1][pvbID],
            pvbType = params[2][pvbTypeID];

          // Validate booking reference number has not expired.
          const today = new Date();

          // Valid from.
          const bookRefValidFrom = new Date();
          const currentCentury = Math.floor((bookRefValidFrom.getFullYear())/100);
          const pvbYearFull = currentCentury * 100 + pvbYear;
          bookRefValidFrom.setDateFromISOWeekDate(pvbYearFull, pvbWeek);
          bookRefValidFrom.setHours(0, 0, 0); // Ensure valid from midnight.

          // Valid to.
          const bookRefValidTo = new Date();
          bookRefValidTo.setTime(bookRefValidFrom.getTime());
          bookRefValidTo.setDate(bookRefValidFrom.getDate() + (pvbRefValidityDays - 1));
          bookRefValidTo.setHours(23, 59, 59); // Ensure valid till midnight.

          // A booking ref can be issued a number of weeks in advance of
          // valid from date.
          const earliestBookingDate = new Date();
          earliestBookingDate.setTime(bookRefValidFrom.getTime());
          earliestBookingDate.setDate(earliestBookingDate.getDate() - (7 * pvbRefMaxAdvanceIssueWeeks));

          // Latest date and time for booking the last time slot within the
          // booking reference's validity period.
          const latestBookingDate = new Date();
          latestBookingDate.setTime(bookRefValidTo.getTime());
          latestBookingDate.setDate(latestBookingDate.getDate() - (pvbAdvanceNoticeHours / 24));

          if (today.getTime() < earliestBookingDate.getTime()) {
            bookRefIsValid = false;
          }

          if (today.getTime() > latestBookingDate.getTime()) {
            bookRefIsValid = false;
          }

          if (today.getTime() > bookRefValidTo.getTime()) {
            bookRefIsValid = false;
          }

          return bookRefIsValid;
        }, `Visit reference number is not recognised or has expired.`);

        $.validator.addMethod("uniqueVisitorId", function(value, element, params) {
          return this.optional(element) || isUniqueVisitorId(params[1], value, element) === true;
        }, $.validator.format("Visitor ID has already been entered"));

        $.validator.addMethod("minAge", function(value, element, param) {
          return this.optional(element) || getAge(value) >= param[1];
        }, $.validator.format("Age must be {1} years old or over"));

        $.validator.addMethod("maxAge", function(value, element, param) {
          return this.optional(element) || getAge(value) <= param[1];
        }, $.validator.format("Age must be no more than {1} years old"));

        $.validator.addMethod("noHtml", function(value, element) {
          return this.optional(element) || value === value.replace(/(<([^>]+)>)/gi, "");
        }, "Text must be plain text only");


        function isUniqueVisitorId(existingIds = {}, visitorId, visitorElement) {

          let isUnique = true;

          // Check against adult visitor ids.
          for (const [key, value] of Object.entries(existingIds)) {
            const visitorElementName = $(visitorElement).attr('name');
            if (value === visitorId && key !== visitorElementName) {
              isUnique = false;
            }
          }

          // Check against child visitor ids within the present step.
          $('[name*="visitor_"][name$="_id"]').each(function() {
            let key = $(this).attr('name');
            let value = $(this).val();
            if (value === visitorId && key !== $(visitorElement).attr('name')) {
              isUnique = false;
            }
          });

          return isUnique;
        }

        function getAge(dateString) {
          // Convert dd/mm/yyyy to yyyy-mm-dd.
          if (dateString.includes('/')) {
            const dateParts = dateString.split("/");
            dateString = dateParts[2] + '-' + dateParts[1] + '-' + dateParts[0];
          }
          const today = new Date();
          const birthDate = new Date(dateString);
          let age = today.getFullYear() - birthDate.getFullYear();
          let m = today.getMonth() - birthDate.getMonth();
          if (m < 0 || (m === 0 && today.getDate() < birthDate.getDate())) {
            age--;
          }
          return age;
        }

      });

      // Apply jquery validation methods once to individual elements
      // which might be loaded in via ajax.

      // Visit reference number validation rules.
      const $pvRef = $(once('pvRef', 'form input[name="visitor_order_number"]', context));

      if ($pvRef.length) {
        const pvPrisons = settings.prisonVisitBooking.prisons;
        const pvTypes = settings.prisonVisitBooking.visit_type;
        const pvNotice = settings.prisonVisitBooking.visit_advance_notice;
        const pvRefValidity = settings.prisonVisitBooking.booking_reference_validity_period_days;
        const pvRefMaxAdvancedIssue = settings.prisonVisitBooking.visit_order_number_max_advance_issue;

        $pvRef.rules( "add", {
          validPrisonVisitBookingRef: [
            true,
            pvPrisons,
            pvTypes,
            pvNotice,
            pvRefValidity,
          ],
          validVisitBookingRefDate: [
            true,
            pvPrisons,
            pvTypes,
            pvNotice,
            pvRefValidity,
            pvRefMaxAdvancedIssue
          ]
        });
      }

      // Visitor One date of birth validation rules.
      const $pvVisitorOneDob = $(once('pvVisitorOneDob', 'form [name="visitor_1_dob"]', context));

      if ($pvVisitorOneDob.length) {
        $pvVisitorOneDob.rules("add", {
          minAge: [true, 18],
          messages: {
            minAge: "You must be at least 18 years of age to book a prison visit"
          }
        });
      }

      // Adult Visitor ID validation rules.
      const pvVisitorOneId =  settings.prisonVisitBooking.visitorOneId ?? {};
      $(once('pvAdultIds', '[name*="additional_visitor_adult"][name$="_id"]', context)).each(function() {
        $(this).rules("add", {
          uniqueVisitorId: [true, pvVisitorOneId],
          messages: {
            uniqueVisitorId: "Visitor ID has already been entered"
          }
        });
      });

      // Child Visitor ID validation rules.
      const pvAdultVisitorIds =  settings.prisonVisitBooking.adultVisitorIds ?? {};
      $(once('pvChildIds', '[name*="additional_visitor_child"][name$="_id"]', context)).each(function() {
        $(this).rules("add", {
          uniqueVisitorId: [true, pvAdultVisitorIds],
          messages: {
            uniqueVisitorId: "Visitor ID has already been entered"
          }
        });
      });

      // Adult visitor dates of birth validation rules.
      const pvAdultDobSelectors = '[name="additional_visitor_adult_1_dob"], [name="additional_visitor_adult_2_dob"]';

      $(once('pvAdultDobs', pvAdultDobSelectors, context)).each(function() {
        $(this).rules("add", {
          minAge: [true, 18],
          messages: {
            minAge: "Adult visitor must be at least 18 years of age"
          }
        });
      });

      // Child visitor dates of birth validation rules.
      const pvChildDobSelectors = '' +
        '[name="additional_visitor_child_1_dob"], ' +
        '[name="additional_visitor_child_2_dob"], ' +
        '[name="additional_visitor_child_3_dob"], ' +
        '[name="additional_visitor_child_4_dob"], ' +
        '[name="additional_visitor_child_5_dob"]';

      $(once('pvChildDobs', pvChildDobSelectors, context)).each(function() {
        $(this).rules("add", {
          minAge: [true, 0],
          maxAge: [true, 17],
          messages: {
            minAge: "Date of birth cannot be greater than today's date",
            maxAge: "Child visitor must be under 18 years of age"
          }
        });
      });

      // Textarea validation rules.
      $(once('pvTextAreas', 'textarea', context)).each(function() {
        $(this).rules("add", {
          noHtml: true
        });
      });

    }
  }
})(jQuery, Drupal, once);

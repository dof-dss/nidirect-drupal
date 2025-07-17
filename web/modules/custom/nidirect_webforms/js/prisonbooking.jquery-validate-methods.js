/**
 * @file
 * Defines jquery-validation methods and rules for prison visit booking.
 */
(function ($, Drupal, once) {

  Drupal.pvGetAge = function (dateString) {
    // Validate the input format: dd/mm/yyyy.
    const dateRegex = /^([0-2][0-9]|3[0-1])\/(0[1-9]|1[0-2])\/\d{4}$/;
    if (!dateRegex.test(dateString)) {
      return null;
    }

    // Convert dd/mm/yyyy to ISO format (yyyy-mm-dd).
    const dateParts = dateString.split("/");
    const isoDateString = `${dateParts[2]}-${dateParts[1]}-${dateParts[0]}T00:00:00Z`;

    // Parse the birthdate.
    const birthDate = new Date(isoDateString);
    if (isNaN(birthDate)) {
      return null;
    }

    // Calculate the age.
    const today = new Date();
    let age = today.getUTCFullYear() - birthDate.getUTCFullYear();
    const monthDiff = today.getUTCMonth() - birthDate.getUTCMonth();
    const dayDiff = today.getUTCDate() - birthDate.getUTCDate();

    // Adjust age if the birth month/day hasn't occurred yet.
    if (monthDiff < 0 || (monthDiff === 0 && dayDiff < 0)) {
      age--;
    }

    return age;
  };


  Drupal.pvIsUniqueVisitorId = function(existingIds = {}, visitorId, visitorElement) {

    let isUnique = true;

    // Check against known existing ids.
    for (const [key, value] of Object.entries(existingIds)) {
      const visitorElementName = $(visitorElement).attr('name');
      if (value === visitorId && key !== visitorElementName) {
        isUnique = false;
      }
    }

    // Check against other additional visitor ids.
    $('[name^="additional_visitor_"][name$="_id"]').each(function() {
      let key = $(this).attr('name');
      let value = $(this).val();
      if (value === visitorId && key !== $(visitorElement).attr('name')) {
        isUnique = false;
      }
    });

    return isUnique;
  }

  Drupal.pvNumberOfAdultVisitors = function() {
    let numAdults = 0;
    $('[name^="additional_visitor_"][name$="_dob"]').each(function(index) {
      if ($(this).is(':visible') && Drupal.pvGetAge($(this).val()) >= 18) {
        numAdults++;
      }
    });
    return numAdults;
  }

  Drupal.pvDaysInMonth = function(m, y) {
    switch (m) {
      case 1 :
        return (y % 4 == 0 && y % 100) || y % 400 == 0 ? 29 : 28;
      case 8 : case 3 : case 5 : case 10 :
        return 30;
      default :
        return 31;
    }
  }

  Drupal.pvIsValidDate = function(d, m, y) {
    m = parseInt(m, 10) - 1;
    return m >= 0 && m < 12 && d > 0 && d <= Drupal.pvDaysInMonth(m, y);
  }

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
          return this.optional(element) || Drupal.pvIsUniqueVisitorId(params[1], value, element) === true;
        }, $.validator.format("Visitor ID has already been entered"));

        $.validator.addMethod("minAge", function(value, element, param) {
          return this.optional(element) || Drupal.pvGetAge(value) >= param[1];
        }, $.validator.format("Age must be {1} years old or over"));

        $.validator.addMethod("maxAge", function(value, element, param) {
          return this.optional(element) || Drupal.pvGetAge(value) <= param[1];
        }, $.validator.format("Age must be no more than {1} years old"));

        $.validator.addMethod("maxAdults", function(value, element, param) {
          let isValid = true;
          if (Drupal.pvNumberOfAdultVisitors() > param[1] && Drupal.pvGetAge(value) >= 18) {
            isValid = false;
          }
          return this.optional(element) || isValid;
        }, $.validator.format("Maximum number of adults is {1}"));

        $.validator.addMethod("validDate", function(value, element) {
          const dateParts = value.split("/");
          const year = parseInt(dateParts[2]);
          const month = parseInt(dateParts[1]);
          const day = parseInt(dateParts[0]);
          return this.optional(element) || Drupal.pvIsValidDate(day, month, year);
        }, $.validator.format("Date must be a valid date"));

        $.validator.addMethod("noHtml", function(value, element) {
          return this.optional(element) || value === value.replace(/(<([^>]+)>)/gi, "");
        }, "Text must be plain text only");

        $.validator.addMethod("timeSlotRequired", function(value, element, options) {
          // Count how many checkboxes in the group are checked.
          const checkboxes = $(options);
          const checked = checkboxes.filter(":checked").length;
          return checked > 0;
        }, "You must choose a time slot for your visit");

      });

      // The form's validator object.
      const validator = $('form.webform-submission-prison-visit-online-booking-form').validate();

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
          validDate: true,
          minAge: [true, 18],
          messages: {
            minAge: "You must be at least 18 years of age to book a prison visit"
          }
        });
      }

      // Additional Visitor ID validation rules.
      const pvVisitorOneId =  settings.prisonVisitBooking.visitorOneId ?? '';

      if (pvVisitorOneId !== '') {
        $(once('pvAdditionalVisitorIds', '[name^="additional_visitor_"][name$="_id"]', context)).each(function() {
          $(this).rules("add", {
            uniqueVisitorId: [true, pvVisitorOneId],
            messages: {
              uniqueVisitorId: "Visitor ID has already been entered"
            }
          });
        });
      }

      // Visitor dates of birth validation rules.
      // There is maximum of two adults.
      const $pvDobs = $(once('pvDobs', '[name^="additional_visitor_"][name$="_dob"]', context));

      $pvDobs.each(function() {
        $(this).rules("add", {
          validDate: true,
          minAge: [true, 0],
          maxAdults: [true, 2],
          messages: {
            validDate: "Enter a valid birthdate.",
            minAge: "Enter a valid birthdate.",
            maxAdults: "The maximum number of adults is two. Enter a child's visitor ID and date of birth."
          }
        });
      });

      // Textarea validation rules.
      $(once('pvTextAreas', 'textarea', context)).each(function() {
        $(this).rules("add", {
          noHtml: true
        });
      });

      // Visit time slot validation rules.
      const $weekSlots = $(once('pvTimeSlots', 'details[data-webform-key^="slots_week_"]', context));

      // Add container for time slot error messages.
      $weekSlots.append('<div class="time-slot-error-placement" aria-live="polite"></div>');

      // Add required rule to time slots
      // const $timeslots = $weekSlots.find("input.timeslot");
      const $timeslots = $(once('pvTimeSlotValidationRules', 'input.timeslot', context));
      $timeslots.rules("add", {
        timeSlotRequired: $timeslots
      });

      // Update the errorPlacement function on the validator instance.
      validator.settings.errorPlacement = function(error, element) {
        if (element.hasClass('timeslot')) {
          error.appendTo($(".time-slot-error-placement"));
        } else {
          // Default error placement for other elements.
          error.insertAfter(element);
        }
      };
    }
  }
})(jQuery, Drupal, once);

/**
 * @file
 * jquery-validation methods for prison booking reference number.
 *
 */
(function ($) {

  $.validator.addMethod('validPrisonVisitBookingRef', function (value, element, params) {

    let bookRefIsValid = true;

    const pvbID = value.slice(0, 2),
          pvbTypeID = value.slice(2, 3),
          pvbWeek = parseInt(value.slice(3, 5)),
          pvbYear = parseInt(value.slice(5, 7));

    if (Object.keys(params[1]).includes(pvbID) !== true) {
      bookRefIsValid = false;
      console.log(`pvbID ${pvbID} is not a valid identifier`);
    }

    if (Object.keys(params[2]).includes(pvbTypeID) !== true) {
      bookRefIsValid = false;
      console.log(`pvbType ${pvbTypeID} is not a valid type identifier`);
    }

    // Validate order number week and year.
    if (pvbWeek < 1 || pvbWeek > 53) {
      bookRefIsValid = false;
      console.log(`pvbWeek ${pvbWeek} is not in range 1-53`);
    } else if (pvbYear < 1 || pvbYear > 99) {
      bookRefIsValid = false;
      console.log(`pvbYear ${pvbYear} is not in range 01-99`);
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
      console.log(`Visit reference number cannot be used until ${earliestBookingDate.toString()}.`);
    }

    if (today.getTime() > latestBookingDate.getTime()) {
      bookRefIsValid = false;
      console.log(`Advanced notice of ${pvbAdvanceNoticeHours} is required and cannot be met.`);
    }

    if (today.getTime() > bookRefValidTo.getTime()) {
      bookRefIsValid = false;
      console.log(`Booking reference number expired ${bookRefValidTo.toString()}`);
    }

    console.log(`######################################################`);
    console.log(`Prison: ${pvbPrisonName}`);
    console.log(`Visit type: ${pvbType}`);
    console.log(`Booking date: ${today.toString()}`);
    console.log(`Booking ref validity period (days): ${pvbRefValidityDays}`);
    console.log(`Booking ref valid from: ${bookRefValidFrom.toString()}`);
    console.log(`Booking ref valid to: ${bookRefValidTo.toString()}`);
    console.log(`Advance notice required (hours): ${pvbAdvanceNoticeHours}`);
    console.log(`Earliest possible booking date: ${earliestBookingDate.toString()}`)
    console.log(`Latest possible booking date: ${latestBookingDate.toString()}`);

    return bookRefIsValid;
  }, `Visit reference number is not recognised or has expired.`);

})(jQuery);

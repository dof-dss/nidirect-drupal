/**
 * @file
 * jquery-validation method for prison booking reference number.
 *
 */
(function ($) {

  $.validator.addMethod('validPrisonVisitBookingRef', function (value, element, params) {

    let bookRefIsValid = true;

    const pvbID = value.slice(0, 2),
          pvbTypeID = value.slice(2, 3),
          pvbWeek = parseInt(value.slice(3, 5)),
          pvbYear = parseInt(value.slice(5, 7)),
          pvbPrisonName = params[1][pvbID],
          pvbType = params[2][pvbTypeID];

    if (Object.keys(params[1]).includes(pvbID) !== true) {
      bookRefIsValid = false;
      console.log(`pvbID ${pvbID} is not a valid identifier`);
    }

    if (Object.keys(params[2]).includes(pvbTypeID) !== true) {
      bookRefIsValid = false;
      console.log(`pvbType ${pvbTypeID} is not a valid type identifier`);
    }

    if (Object.keys(params[5][pvbPrisonName]).includes(pvbType) !== true) {
      console.log(Object.keys(params[5][pvbPrisonName]));
      bookRefIsValid = false;
      console.log(`pvbType ${pvbTypeID} is not a valid type identifier for ${pvbPrisonName}`);
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
          pvbSeq = parseInt(value.slice(8, 12)),
          pvbRefValidityDays = parseInt(params[4][pvbTypeID]),
          pvbAdvanceNoticeHours = parseInt(params[3][pvbTypeID]),
          pvbRefMaxAdvanceIssueWeeks = parseInt(params[7]),
          pvbPrisonName = params[1][pvbID],
          pvbType = params[2][pvbTypeID],
          pvbIsIntegrated = (
            pvbSeq >= params[6]['integrated'][0][0] && pvbSeq <= params[6]['integrated'][0][1]
          ),
          pvbIsAffiliationOne = (
            pvbSeq >= params[6]['separates'][0][0] && pvbSeq <= params[6]['separates'][0][1]
          ),
          pvbIsAffiliationTwo = (
            pvbSeq >= params[6]['separates'][1][0] && pvbSeq <= params[6]['separates'][1][1]
          );

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

    // Earliest and latest date and time a booking can be made.
    // Need to get available slots.
    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    let slotsAvailable = params[5][pvbPrisonName][pvbType];
    let timeSlotsAvailable = [];
    let firstTime = null;
    let lastTime = null;
    let lastDay = null;

    if (slotsAvailable && Object.keys(slotsAvailable).length) {
      // For each day's worth of available slots...
      Object.keys(slotsAvailable).forEach((day, index) => {

        if (slotsAvailable[day]) {

          // Assume this is the last day.
          lastDay = day;

          // Are there integrated time slots?
          if (pvbIsIntegrated && typeof slotsAvailable[day]['integrated'] !== 'undefined') {
            timeSlotsAvailable = Object.keys(slotsAvailable[day]['integrated']);
          }
          // Or are there separates time slots?
          else if ((pvbIsAffiliationOne || pvbIsAffiliationTwo) && typeof slotsAvailable[day]['separates'] !== 'undefined') {

            const filterAm = function(time) {
              let timeHours = parseInt(time.slice(0,2));
              return timeHours <= 12;
            }

            const filterPm = function(time) {
              let timeHours = parseInt(time.slice(0,2));
              return timeHours > 12;
            }

            // For even numbered weeks...
            if (today.getUTCWeekNumber() % 2 === 0) {
              // Separates affiliation 1 get AM slots and Separates affiliation 2 get PM slots.
              if (pvbIsAffiliationOne) {
                timeSlotsAvailable = Object.keys(slotsAvailable[day]['separates']).filter(filterAm);
              }
              else {
                timeSlotsAvailable = Object.keys(slotsAvailable[day]['separates']).filter(filterPm);
              }
            }
            else {
              // Separates 1 get PM slots and Separates 2 get AM slots.
              if (pvbIsAffiliationOne) {
                timeSlotsAvailable = Object.keys(slotsAvailable[day]['separates']).filter(filterPm);
              }
              else {
                timeSlotsAvailable = Object.keys(slotsAvailable[day]['separates']).filter(filterAm);
              }
            }
          }
          else {
            timeSlotsAvailable = Object.keys(slotsAvailable[day]);
          }
        }

      });

      // First and last timeslots
      firstTime = timeSlotsAvailable[0];
      lastTime = timeSlotsAvailable[timeSlotsAvailable.length - 1];

      // A booking ref can be issued a number of weeks in advance of its
      // valid from date.
      const earliestBookingDate = new Date();
      earliestBookingDate.setTime(bookRefValidFrom.getTime());
      earliestBookingDate.setDate(earliestBookingDate.getDate() - (7 * pvbRefMaxAdvanceIssueWeeks));
      earliestBookingDate.setHours(parseInt(firstTime.slice(0,2)), parseInt(firstTime.slice(3,5)), 0);

      // Latest date and time for booking the last time slot within the
      // booking reference's validity period.
      const latestBookingDate = new Date();
      latestBookingDate.setTime(bookRefValidTo.getTime());
      latestBookingDate.setDate(latestBookingDate.getDate() - 7 + (days.indexOf(lastDay) + 1) - (pvbAdvanceNoticeHours / 24));
      latestBookingDate.setHours(parseInt(lastTime.slice(0,2)), parseInt(lastTime.slice(3,5)), 0);

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
      console.log(`Week parity: ${today.getUTCWeekNumber() % 2 === 0 ? 'even' : 'odd'}`);
      console.log(`Booking ref validity period (days): ${pvbRefValidityDays}`);
      console.log(`Booking ref valid from: ${bookRefValidFrom.toString()}`);
      console.log(`Booking ref valid to: ${bookRefValidTo.toString()}`);
      console.log(`Seq integrated? ${pvbIsIntegrated}`);
      console.log(`Seq affil 1? ${pvbIsAffiliationOne}`);
      console.log(`Seq affil 2? ${pvbIsAffiliationTwo}`);
      console.log(`Last bookable time slot: ${lastDay} ${lastTime}`);
      console.log(`Advance notice required (hours): ${pvbAdvanceNoticeHours}`);
      console.log(`Earliest possible booking date: ${earliestBookingDate.toString()}`)
      console.log(`Latest possible booking date: ${latestBookingDate.toString()}`);

    } else {
      bookRefIsValid = false;
      console.log(`No slots available for ${pvbPrisonName} of type ${pvbType}`);
    }

    return bookRefIsValid;
  }, `Visit reference number is not recognised or has expired.`);

})(jQuery);

/**
 * @file
 * jquery-validation method for prison booking reference number.
 *
 */
(function ($) {

  $.validator.addMethod('validPrisonVisitBookingRef', function (value, element, params) {

    let bookRefIsValid = true;

    const pvbID = value.slice(0, 2),
          pvbType = value.slice(2, 3),
          pvbWeek = parseInt(value.slice(3, 5)),
          pvbYear = parseInt(value.slice(5, 7)),
          pvbSeq = parseInt(value.slice(9, 12));

    const pvbRefValidityDays = parseInt(params[4][pvbType]);


    if (params[1].includes(pvbID) !== true) {
      bookRefIsValid = false;
      console.log(`pvbID [${pvbID}] is not a valid identifier`);
    }

    if (params[2].includes(pvbType) !== true) {
      bookRefIsValid = false;
      console.log(`pvbType [${pvbType}] is not a valid type`);
    }

    // Validate order number week and year.
    if (pvbWeek < 1 || pvbWeek > 53) {
      bookRefIsValid = false;
      console.log(`pvbWeek [${pvbWeek}] is not in range 1-53`);
    } else if (pvbYear < 1 || pvbYear > 99) {
      bookRefIsValid = false;
      console.log(`pvbYear [${pvbYear}] is not in range 01-99`);
    } else {
      // Validate order number has not expired.
      const today = new Date();
      console.log(`######## PVB ${today.toString()}`);

      // Valid from.
      const bookRefValidFrom = new Date();
      const currentCentury = Math.floor((bookRefValidFrom.getFullYear())/100);
      const pvbYearFull = currentCentury * 100 + pvbYear;

      bookRefValidFrom.setDateFromISOWeekDate(pvbYearFull, pvbWeek);
      bookRefValidFrom.setHours(00, 00, 00); // Ensure valid from midnight.
      console.log(`valid from: [${bookRefValidFrom.toString()}]`);

      const bookRefValidTo = new Date();
      const bookRefValidityDays = parseInt(params[4][pvbType]);
      bookRefValidTo.setTime(bookRefValidFrom.getTime());
      bookRefValidTo.setDate(bookRefValidFrom.getDate() + (bookRefValidityDays - 1));
      bookRefValidTo.setHours(23, 59, 59); // Ensure valid till midnight.
      console.log(`valid until: [${bookRefValidTo.toString()}]`);

      if (today.getTime() > bookRefValidTo.getTime()) {
        bookRefIsValid = false;
        console.log(`Booking reference number has expired`);
      }
    }

    return bookRefIsValid;
  }, 'Visit reference number does not look correct or has expired.');

})(jQuery);

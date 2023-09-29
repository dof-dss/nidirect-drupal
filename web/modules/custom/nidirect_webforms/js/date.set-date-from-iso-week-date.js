/**
 * Get the date from an ISO 8601 week and year
 *
 * https://en.wikipedia.org/wiki/ISO_week_date
 *
 * @param {number} week ISO 8601 week number
 * @param {number} year ISO year
 *
 * Examples:
 *  getDateOfIsoWeek(53, 1976) -> Mon Dec 27 1976
 *  getDateOfIsoWeek( 1, 1978) -> Mon Jan 02 1978
 *  getDateOfIsoWeek( 1, 1980) -> Mon Dec 31 1979
 *  getDateOfIsoWeek(53, 2020) -> Mon Dec 28 2020
 *  getDateOfIsoWeek( 1, 2021) -> Mon Jan 04 2021
 *  getDateOfIsoWeek( 0, 2023) -> Invalid (no week 0)
 *  getDateOfIsoWeek(53, 2023) -> Invalid (no week 53 in 2023)
 */

Date.prototype.setDateFromISOWeekDate = function(year, week = 1, day = 1){

  if (!Number.isInteger(year) || !Number.isInteger(week) || !Number.isInteger(day)) {
    throw new TypeError("Year must be an integer");
  } else if (week < 1 || week > 53) {
    throw new RangeError("Week must be an integer from 1 to 53 in the ISO week date system.");
  } else if (day < 1 || day > 7) {
    throw new RangeError("Day must be an integer from 1 to 7");
  }

  const initialDate = new Date(Date.UTC(year, 0, 1 + (week - 1) * 7));
  const dayOfWeek = initialDate.getDay();
  const isoWeekStart = initialDate;

  //console.log(`Initial week start date is ${initialDate.toString()}`);

  // Get the Monday past, and add a week if the day was
  // Friday, Saturday or Sunday.

  isoWeekStart.setDate(initialDate.getDate() - dayOfWeek + 1);
  if (dayOfWeek > 4) {
    isoWeekStart.setDate(isoWeekStart.getDate() + 7);
  }

  //console.log(`Final week start date is ${initialDate.toString()}`);

  // The latest possible ISO week starts on December 28 of the current year.
  if (isoWeekStart.getFullYear() > year || (isoWeekStart.getFullYear() == year && isoWeekStart.getMonth() == 11 && isoWeekStart.getDate() > 28)) {
    throw new RangeError(`${year} has no ISO week ${week}`);
  }

  return this.setTime(isoWeekStart.getTime());
}

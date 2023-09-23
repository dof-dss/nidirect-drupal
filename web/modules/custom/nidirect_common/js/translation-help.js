/**
 * @file
 * Defines Javascript behaviors for translation help.
 *
 * Modify links to Google translate to add on the referring page path.
 * This ensures users get a translation of the page on which they hit the
 * translation help button.
 */
(function ($) {

  "use strict";

  Drupal.behaviors.translationHelp = {
    attach: function (context, settings) {

      const translateReferrer = document.referrer;

      // If the referrer is nidirect.
      if (translateReferrer.length && translateReferrer.indexOf(document.location.origin) !== -1) {
        // Replace the 'u' parameter in Google Translate links with the referrer.
        // TODO: use URL and URLSearchParams objects for cleaner replacement (when IE11 support is discontinued).
        $('a[href^="https://translate.google.com"]', context).once('translation-help-links').each(function () {
          let thisHref = $(this).attr('href');
          // Replace existing 'u' param with one containing the referrer.
          thisHref = thisHref.replace(/&u=[^&#]*/, '&u=' + encodeURIComponent(translateReferrer));
          $(this).attr('href', thisHref);
        });
      }
    }
  };

}(jQuery, Drupal));

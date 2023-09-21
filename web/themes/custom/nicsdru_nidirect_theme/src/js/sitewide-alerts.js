/**
 * @file
 * Javascript to modify behaviour of sitewide alerts.
 *
 */

/* eslint-disable */
/*
 * User Interface scripts.
 */
(function ($, Drupal) {
  Drupal.behaviors.nidirectSitewideAlerts = {
    attach: function attach (context) {

      // Prevent sitewide alerts being announced by screen readers on page load
      // by removing their role="alert" attribute because they interrupt the
      // screen reader on each an every page load and are very intrusive.
      // TODO: submit feature request or patch for sitewide alert module to get this behaviour amended.
      $("body").on('DOMSubtreeModified', "#sitewide-alert", function() {
        $('#sitewide-alert').find('.sitewide-alert').removeAttr('role');
      });

      // For scheduled sitewide alerts that get added into the DOM after page
      // load, it seems reasonable to let screen readers know they have been added.
      // Make the sidewide alert container a polite aria-live region. When new alerts
      // get added, a screen reader will announce them when it is idle (and not when
      // it is reading something else).
      $('#sitewide-alert', context).attr({
        'role': 'region',
        'aria-roledescription': 'Site wide messages',
        'aria-live': 'polite',
        'aria-atomic': 'true'
      });
    }
  };
})(jQuery, Drupal);

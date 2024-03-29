/**
 * @file
 * Defines Javascript fix to prevent Google Translate altering application green button links.
 */
(function ($) {

  "use strict";

  Drupal.behaviors.appLinksNoTranslate = {
    attach: function (context, settings) {
      $(once('app-links-no-translate', 'a.call-to-action', context)).each(function() {
        let apphref = this.href;
        $(this)
          .attr("data-nid-app", apphref.substring(apphref.lastIndexOf("https://")))
          .click(function(e) {
            if ($(this).data("nid-app").length) {
              e.originalEvent.currentTarget.href = decodeURIComponent($(this).data("nid-app"));
            }
          });
      });

    }
  };

}(jQuery, Drupal));

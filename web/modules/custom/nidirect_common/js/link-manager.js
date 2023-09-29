/**
 * @file
 * Defines Javascript behaviors for Link handling.
 */
(function ($) {

  "use strict";

  /**
   * Changes any page links, within certain defined regions, from
   * <a> elements to <span> elements if the current path matches the
   * 'href' attribute.
   *
   * header.header
   * #block-mainnavigation
   * #main-content
   * #footer
   */
  Drupal.behaviors.rewriteSelfReferencingLinks = {
    attach: function (context, settings) {
      let pathname = $(location).attr('pathname');
      // Trim query parameters, if any, from pathname.
      pathname = pathname.substr(0, (pathname.indexOf('?') < 0) ? pathname.length : pathname.indexOf('?'));

      const elements = ['header.header', '#block-mainnavigation', '#main-content', '#footer'];

      $.each(elements, function (index, elementRef) {
        let classes = ['active', 'link__self'];

        if (elementRef === '#block-mainnavigation') {
          classes.push('nav-link');
        }

        $(elementRef + ' a[href*="' + pathname + '"]').each(function () {
          // Move to next item if we have a data attribute to indicate we should ignore this element.
          if ($(this).attr('data-self-ref')) {
            return true;
          }

          let href = $(this).attr('href');
          // Prune away any query parameters, if they exist.
          href = href.substr(0, (href.indexOf('?') < 0) ? href.length : href.indexOf('?'));

          // Need a regex object to inject the pathname variable as a matching value.
          let regex = new RegExp('^' + pathname + '$'.replace(/\//g, '\\/'), 'g');

          if (href.match(regex)) {
            $(this).replaceWith('<span class="' + classes.join(' ') + '">' + $(this).text() + '</span>');
          }
        });
      });
    }
  };

}(jQuery, Drupal));

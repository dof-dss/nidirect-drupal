/**
 * @file
 * Defines Javascript behaviors for search conditions prompt.  The prompt is a link which skips user down
 * to the health conditions search widget which appears at the bottom of health conditions on narrow screens.
 */

(function ($, Drupal) {
  Drupal.behaviors.searchConditionsPrompt = {
    attach: function attach (context) {

      const $hc_search_widget = $('#block-exposedformhealth-conditionssearch-sidebar-2');

      // Add scp (search conditions prompt) if health-conditions-az-widget is present.
      if ($hc_search_widget.length) {

        // First, add the jump link container
        $('h1').after('<div class="search-conditions-prompt"><a href="#edit-query-health-az">Search for health conditions</a></div>');

        // Browser window scroll (in pixels) after which the prompt is displayed.
        const scp_offset = $('h1').offset().top + 54,
          // Duration of the top scrolling animation (in ms).
          scp_scroll_top_duration = 700,
          // Grab the prompt link.
          $scp = $('.search-conditions-prompt > a'),
          // Browser window scroll (in pixels) after which the prompt is hidden (when search/az widget is in view).
          scp_offset_hide = $hc_search_widget.offset().top - $(window).height() + $scp.height();

        // Hide or show the scp.
        $(window).scroll(function () {
          if ($(this).scrollTop() > scp_offset && $(this).scrollTop() < scp_offset_hide) {
            $scp.addClass('scp-is-visible');
          } else {
            $scp.removeClass('scp-is-visible scp-fade-out');
          }
        });

        // Smooth scroll to scp.
        $scp.on('mousedown', function (event) {
          event.preventDefault();
          $('body,html').animate({
              scrollTop: $hc_search_widget.offset().top,
            }, scp_scroll_top_duration,
            function () {
              // animation complete
              $('#edit-query-health-az').focus();
            }
          );
        });
      }
    }
  };
}(jQuery, Drupal));

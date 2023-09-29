/**
 * @file
 * Provides a script to thwoop (expand/contract) floated responsive images and display full content width
 * images in a modal.
 *
 */

/* eslint-disable */
(function ($, Drupal) {
  Drupal.behaviors.nicsdruOriginsThwoopImages = {
    attach: function attach (context) {

      // Make expandable responsive image styles thwoopable.
      var thwoopImageSelector = '[data-picture-mapping*="_expandable"] > img, [data-picture-mapping*="_expandable"] > figure';
      var $thwoopImages = $(thwoopImageSelector, context);

      // Add a clickable/focusable wrapper and icon to indicate image is thwoopable.
      $thwoopImages.once('thwoop-toggle').each(function () {

        var $thwooper = $('<a class="thwooper" aria-label="expand image" href="#"></a>');

        // CSS animates the thwooper expansion and contraction.
        $thwooper.bind('oanimationstart animationstart webkitAnimationStart', function() {
          // When animation begins, clear away any text wrapping near the
          // image by making the parent contain the floated image.
          $(this).parent().addClass('clearfix');
        });

        $thwooper.bind('oanimationend animationend webkitAnimationEnd', function() {
          // When animation finishes, let text wrap around non-thwooped images by
          // removing float clearing from the parent.
          if (!$(this).hasClass('thwooped')) $(this).parent().removeClass('clearfix');
        });

        $thwooper.click(function (event) {
          event.preventDefault();
          var $thwoopimage = $(this).find('img, figure');
          var $thwoop_wrap = $(this).closest('.media-image');
          var is_thwooped = $(this).hasClass('thwooped');
          var thwooper_label = $(this).attr('aria-lable') === 'expand image'? 'shrink image' : 'expand image';

          // Open image in a modal if it does not have room to expand inside its
          // container (and its not already thwooped).
          var open_as_modal = (!is_thwooped && $thwoopimage.outerWidth() == $thwoop_wrap.outerWidth());

          $(this).toggleClass('thwooped', !is_thwooped);
          $(this).attr('aria-label', thwooper_label);
          if (open_as_modal) {
            $thwoop_wrap.addClass('thwooped-modal');
            $(this).attr('aria-label', 'close image');
          } else if ($thwoop_wrap.hasClass('thwooped-modal')) {
            $thwoop_wrap.removeClass('thwooped-modal');
            $thwoop_wrap[0].scrollIntoView({
              block: 'center',
            });
          }
        });

        $(this).wrap($thwooper);
      });
    }
  };
})(jQuery, Drupal);

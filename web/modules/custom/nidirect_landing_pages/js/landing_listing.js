/**
 * @file
 * Defines Javascript behaviors for landing pages.
 */

(function ($, Drupal) {
  Drupal.behaviors.landinglisting = {
    attach: (context) => {
      // Look through all <select>s.
      $(context).find('select').each(function () {
        var $thisclass = $(this).attr('class');
        // Find any whose class starts with 'shs-'.
        if ($thisclass.indexOf('shs-') == 0) {
          $(this).on("change", function () {
            if ($(context).find('#edit-field-manually-control-listing-value').prop('checked') == true) {
              // This is an edge case, user has already ordered the listings but then
              // changes the subtheme. Just un-check the 'manual control' button and
              // hide the fields until after the node is saved for now.
              $(context).find('#edit-field-manually-control-listing-value')
                .trigger('click');
              $(context).find('#edit-field-manually-control-listing-wrapper')
                .hide();
            }
            $(context).find('#edit-field-listing-wrapper')
              .hide();
          });
        }
      });
      // Check when the user clicks on the 'manual control' checkbox.
      $(context).find('#edit-field-manually-control-listing-value').on("change", function () {
        if ($(this).prop('checked') == true) {
          // If the user has chosen manual control but has not selected a
          // subtheme, show an error.
          var $subtheme = $(context).find('#edit-field-subtheme-shs-0-0').children("option:selected").val();
          if ($subtheme == '_none') {
            alert('Please select a subtheme first');
            $(this).prop('checked', false);
          }
          // If the title and teaser fields have not been completed then the
          // 'nidirect_landing_pages_node_prepare_form' function will not run
          // and the listing field will not be populated.
          var $title = $(context).find('#edit-title-0-value').val();
          var $teaser = $(context).find('#edit-field-teaser-0-value').val();
          if (($title.length == 0) && ($teaser.length == 0)) {
            alert('Please complete the title and teaser fields');
            $(this).prop('checked', false);
          }
        }
      });
    }
  };
}(jQuery, Drupal));

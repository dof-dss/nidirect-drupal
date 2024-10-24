/**
 * @file
 * JQuery for prison visit booking webform.
 */

(function($, Drupal, once) {

  Drupal.behaviors.prisonVisit = {
    attach: function (context, settings) {

      // The number of additional adult visitors determines
      // the number of additional child visitors.
      const $additionalAdults = $(once('prisonVisitAdditionalAdults', '[name="additional_visitor_adult_number"]', context));
      const $additionalChildren = $(once('prisonVisitAdditionalChildren', '[name="additional_visitor_child_number"]', context));

      if ($additionalAdults.val() > 0) {
        $('option', $additionalChildren)
          .slice(($(this).length - 1) - parseInt($additionalAdults.val()))
          .attr('disabled', true);
      }

      $additionalAdults.on('change', function(e) {
        let numAdults = $(this).val() ?? 0;

        if (numAdults > 0) {
          $('option', $additionalChildren)
            .removeAttr('disabled')
            .slice(($(this).length - 1) - numAdults)
            .attr('disabled', true)
            .prop('selected', false);
        } else {
          $('option', $additionalChildren).removeAttr('disabled')
        }

        if ($('option:selected', $additionalChildren).is(':disabled')) {
          $('option').not(':disabled').last().prop('selected', true);
        }
      });


      // Visit time slots grouped by week in details containers.
      const $weekSlots = $(once('pvSlots', '[data-webform-key^="slots_week"]', context));

      // One week of slots only? Force open the details container.
      if ($weekSlots.length === 1) {
        $weekSlots.prop("open", true);
        $('summary', $weekSlots)
          .prop('aria-expanded', true)
          .prop('aria-pressed', true);
      }

      // Visit time slots ...
      let $timeSlots = $('input[type="checkbox"]', $weekSlots);

      // Array for tracking slot IDs as they are checked or unchecked.
      let preferredSlotIds = [];

      // Get visit type from the booking reference and get
      // the timeslot limit for that visit type from settings.
      let pvTypeId =  'F';
      let pvTimeSlotLimit = 3;

      if (settings.prisonVisitBooking['booking_ref'] !== null) {
        pvTypeId = settings.prisonVisitBooking.booking_ref.visit_type_id;
        pvTimeSlotLimit = settings.prisonVisitBooking.visit_type_time_slot_limit[pvTypeId];
      }

      if ($timeSlots.length) {

        // Disable checkboxes if time slot limit reached.
        disableCheckboxes($timeSlots, pvTimeSlotLimit);

        // Add aria-live region for announcing slot preferences.
        const $preferredStatus = $('<div role="region" class="visually-hidden" aria-live="polite" />');
        $preferredStatus.append($('<h2 id="timeslots-announce-title" />'));
        $preferredStatus.append($('<div id="timeslots-announce-description" />'));

        $(once('pvPreferredStatus', '[data-webform-key="visit_preferred_day_and_time"]', context)).prepend($preferredStatus);

        // Set aria-controls on timeslots to control the
        // aria-live region.
        $timeSlots.attr('aria-controls', 'timeslots-announce');

        let hiddenTimeSlotSelectors = '';
        hiddenTimeSlotSelectors += '[name="slot1_datetime"], ';
        hiddenTimeSlotSelectors += '[name="slot2_datetime"], ';
        hiddenTimeSlotSelectors += '[name="slot3_datetime"], ';
        hiddenTimeSlotSelectors += '[name="slot4_datetime"], ';
        hiddenTimeSlotSelectors += '[name="slot5_datetime"]';

        const $hiddenTimeSlots = $(hiddenTimeSlotSelectors);

        // D8NID-1661.
        if (settings.prisonVisitBooking.resetTimeslots && settings.prisonVisitBooking.resetTimeslots === true) {
          // Reset all timeslots user has selected.
          $timeSlots.prop('checked', false);
          $hiddenTimeSlots.val('').attr('value', '');
        }

        // Prep timeslots to show preference rank.
        $timeSlots.each(function() {
          let $span = $('<span />');
          $span.addClass('rank').attr('aria-hidden', 'true');
          $(this).next('label').append($span);
        });

        // Update preference rank shown on timeslots.
        $hiddenTimeSlots.each(function(index) {
          let value = $(this).val();
          let $timeSlot = $('input[value="' + value + '"]');
          if (value.length && $timeSlot.length) {
            $timeSlot.next('label').find('span.rank').text(index + 1);
            preferredSlotIds.push($timeSlot.prop('id'));
          }
        });

        // Trigger announcement of preferred timeslots.
        updatePreferredStatus();

        // When a timeslot is checked or unchecked, update preference
        // ranks and hidden slot values.
        $timeSlots.on('change', function(e) {

          // Disable checkboxes if time slot limit reached.
          disableCheckboxes($timeSlots, pvTimeSlotLimit);

          // Update slot rank.
          let slotId = $(this).prop('id');
          let slotValue = $(this).val();
          let $slotRank = $(this).next('label').find('span.rank');
          let rank = preferredSlotIds.indexOf(slotId);

          // By default a slot has no rank.
          $slotRank.text('');
          $hiddenTimeSlots.eq(rank).val('').attr('value', '');

          if ($(this).is(':checked')) {
            preferredSlotIds.push(slotId);
            rank = preferredSlotIds.indexOf(slotId);
            $slotRank.text(rank + 1);
            $hiddenTimeSlots.eq(rank).val(slotValue).attr('value', slotValue);
          } else {
            preferredSlotIds.splice(preferredSlotIds.indexOf(slotId), 1);

            // Update ranks shown on remaining preferred slots.
            preferredSlotIds.forEach(function(value, index) {
              let selector = '#' + value;
              $(selector).next('label').find('span.rank').text(index + 1);
            });

            // Update hidden timeslots.
            $hiddenTimeSlots.each(function(index) {
              const $slotVal = $('#' + preferredSlotIds[index]).val();
              if (index in preferredSlotIds) {
                $(this).val($slotVal).attr('value', $slotVal);
              } else {
                $(this).val('').attr('value', '');
              }
            });
          }

          // Announce preferred slots.
          updatePreferredStatus();
        });
      }

      function disableCheckboxes($checkboxes, limit = 3) {
        let checkboxesCheckedCount = $checkboxes.filter(':checked').length;
        if (checkboxesCheckedCount === limit) {
          $checkboxes.filter(':not(:checked)').prop('disabled', true);
        }
        else if (checkboxesCheckedCount < limit) {
          $checkboxes.filter(':not(:checked)').prop('disabled', false);
        }
      }

      function updatePreferredStatus() {

        if (preferredSlotIds.length < pvTimeSlotLimit) {
          $('#timeslots-announce-title').html('You have selected ' + preferredSlotIds.length + ' time slots');
        } else {
          $('#timeslots-announce-title').html('You have selected a maximum of ' + preferredSlotIds.length + ' time slots');
        }

        let items = '';
        preferredSlotIds.forEach(function(value, index) {
          const options = {
            weekday: "long",
            year: "numeric",
            month: "long",
            day: "numeric",
          };
          if (value.length) {
            const date = new Date($('#' + value).val());
            const prettyDate = date.toLocaleDateString('en-GB', options);
            const prettyTime =  date.toLocaleTimeString([], { hour12: true, hour: "numeric", minute: "2-digit" });

            items += `<li>${prettyTime} on ${prettyDate}</li>`;
          }
        });

        $('#timeslots-announce-description').html('<ol class="list--ordered-bullet">' + items + '</ol>');
      }

    }
  };

  Drupal.behaviors.prisonVisitAdditionalVisitors = {
    attach: function (context, settings) {

      let selectors = '';
      selectors += 'fieldset[data-drupal-selector^="edit-additional-visitor-1-"],';
      selectors += 'fieldset[data-drupal-selector^="edit-additional-visitor-2-"],';
      selectors += 'fieldset[data-drupal-selector^="edit-additional-visitor-3-"],';
      selectors += 'fieldset[data-drupal-selector^="edit-additional-visitor-4-"]';

      const $additionalVisitors = $(once('prisonVisitAdditionalVisitors', selectors, context));

      const removeVisitor = function(element) {
        // When a visitor's ID and DOB is removed, we shift the details for all
        // the next visitors "up" to close the gap.

        let $visitor = $(element).closest('fieldset');
        //console.log('Removing', $visitor);

        let $visitor_id = $('input[name$="_id"]', $visitor);
        let $visitor_dob = $('input[name$="_dob"]', $visitor);

        const $visitorNextAll = $visitor.nextAll();

        $visitorNextAll.each(function(index) {
          let $visitor_sibling_id = $('input[name$="_id"]', $(this));
          let $visitor_sibling_dob = $('input[name$="_dob"]', $(this));

          $visitor_id.val($visitor_sibling_id.val());
          $visitor_dob.val($visitor_sibling_dob.val());

          $visitor_id = $visitor_sibling_id;
          $visitor_dob = $visitor_sibling_dob;
        });

        $visitor_id.val('');
        $visitor_dob.val('');

        // const validator = $(element).closest('form').validate();
        // validator.resetForm();

        // Update the radios controlling the number of visitors.
        // Use a click event so it fires events to hide visitors
        // in the normal way.
        let visitor_number = $('input[id^="edit-additional-visitor-number"]:checked').val();
        $('input[id^="edit-additional-visitor-number"][value="' + (visitor_number - 1) + '"]').click();
      }

      const updateVisitorBadges = function(event) {
        $additionalVisitors.each(function(index) {
          let $visitor = $(this);
          let $dob = $visitor.find('input[name^="additional_visitor_' + (index+1) + '_dob"]');
          let $badge = $visitor.find('.visitor-badge');

          updateVisitorBadge($dob, $badge);
        });
      }

      const updateVisitorBadge = function($dob, $badge) {
        if (Drupal.pvGetAge($dob.val()) >= 18) {
          $badge.text('Adult').removeClass('visitor-badge--child');
        } else {
          $badge.text('Child').addClass('visitor-badge--child');
        }
      }

      $additionalVisitors.each(function(index) {

        let $visitor = $(this);

        // Remove link for each visitor.
        // Has to remove the visitor and update visitor badges.
        let $link_innerHTML = 'Remove <span class="visually-hidden">' + $('legend', $visitor).text() + '</span>';
        let $link = $('<a href="#">' + $link_innerHTML + '</a>').attr('data-index', (index + 1));
        $link.click(function() {
          removeVisitor(this);
          updateVisitorBadges();
          return false;
        });
        $visitor.find('.fieldset-wrapper').append($link);

        // Add a badge indicating whether visitor is an adult or a child.
        const $badge = $('<span />');
        $badge.addClass('visitor-badge');
        $visitor.find('legend').append($badge);

        // Update badge based on visitor DOB.
        let $dob = $visitor.find('input[name^="additional_visitor_' + (index+1) + '_dob"]');
        updateVisitorBadge($dob, $badge);

        // Update badge when DOB is altered.
        $dob.on('keyup blur', function() {
          updateVisitorBadge($dob, $badge);
        });

      });

    }
  };

})(jQuery, Drupal, once);

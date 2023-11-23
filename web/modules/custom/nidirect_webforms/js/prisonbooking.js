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

      // Visit time slots preferences...

      // Add aria-live region for announcing time slot preferences.
      const $preferredStatus = $('<div role="region" class="visually-hidden" aria-live="polite" />');
      $preferredStatus.append($('<h2 id="timeslots-announce-title" />'));
      $preferredStatus.append($('<div id="timeslots-announce-description" />'));

      $(once('pvPreferredStatus', '[data-webform-key="visit_preferred_day_and_time"]', context)).prepend($preferredStatus);

      // Limit timeslots that can be selected and
      // keep track of preferred time slots.
      let $timeSlots = $('input[type="checkbox"]', $weekSlots);
      $timeSlots.attr('aria-controls', 'timeslots-announce');

      let preferredSlots = [];
      let pvTypeId = 'F';
      let timeSlotLimit = 3;

      if ($timeSlots.length) {

        // Determine the time slot limit. By default it's 3,
        // but for virtual visits it's 5.
        if (settings.prisonVisitBooking['booking_ref'] !== null) {
          pvTypeId = settings.prisonVisitBooking.booking_ref.visit_type_id;
          timeSlotLimit = (pvTypeId === 'V') ? 5 : 3;
        }

        // Call function to disable checkboxes if time slot limit reached.
        disableCheckboxes($timeSlots, timeSlotLimit);

        // Prep timeslots to show preference rank.
        $timeSlots.each(function() {
          let $span = $('<span />');
          $span.addClass('rank').attr('aria-hidden', 'true');
          $(this).next('label').append($span);
        });

        // Preferred time slots may already exist (stored in hidden inputs).
        // Update ranks shown on time slot checkbox labels.
        let hiddenTimeSlotSelectors = '';
        hiddenTimeSlotSelectors += '[name="slot1_datetime"], ';
        hiddenTimeSlotSelectors += '[name="slot2_datetime"], ';
        hiddenTimeSlotSelectors += '[name="slot3_datetime"], ';
        hiddenTimeSlotSelectors += '[name="slot4_datetime"], ';
        hiddenTimeSlotSelectors += '[name="slot5_datetime"]';

        $(hiddenTimeSlotSelectors).each(function(index) {
          let value = $(this).val();
          if (value.length) {
            let $timeSlot = $('input[value="' + value + '"]');
            $timeSlot.next('label').find('span.rank').text(index + 1);
            preferredSlots.push($timeSlot.prop('id'));
          }
        });

        updatePreferredStatus();

        // When a timeslot is checked or unchecked, update preference
        // ranks and hidden slot values.

        $timeSlots.on('change', function(e) {

          // Disable checkboxes if time slot limit reached.
          disableCheckboxes($timeSlots, timeSlotLimit);

          // Update slot rank.
          let slotId = $(this).prop('id');
          let slotValue = $(this).val();
          let $slotRank = $(this).next('label').find('span.rank');
          let rank = preferredSlots.indexOf(slotId);

          // By default a slot has no rank.
          $slotRank.text('');
          $(hiddenTimeSlotSelectors).eq(rank).val('').attr('value', '');

          if ($(this).is(':checked')) {
            preferredSlots.push(slotId);
            rank = preferredSlots.indexOf(slotId);
            $slotRank.text(rank + 1);
            $(hiddenTimeSlotSelectors).eq(rank).val(slotValue).attr('value', slotValue);
          } else {
            preferredSlots.splice(preferredSlots.indexOf(slotId), 1);

            // Update ranks shown on remaining preferred slots.
            preferredSlots.forEach(function(value, index) {
              let selector = '#' + value;
              $(selector).next('label').find('span.rank').text(index + 1);
            });

            // Update hidden timeslots.
            $(hiddenTimeSlotSelectors).each(function(index) {
              const $slotVal = $('#' + preferredSlots[index]).val();
              if (index in preferredSlots) {
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

        if (preferredSlots.length < timeSlotLimit) {
          $('#timeslots-announce-title').html('You have selected ' + preferredSlots.length + ' time slots');
        } else {
          $('#timeslots-announce-title').html('You have selected a maximum of ' + preferredSlots.length + ' time slots');
        }

        let items = '';
        preferredSlots.forEach(function(value, index) {
          const options = {
            weekday: "long",
            year: "numeric",
            month: "long",
            day: "numeric",
          };
          const date = new Date($('#' + value).val());
          const prettyDate = date.toLocaleDateString('en-GB', options);
          const prettyTime =  date.toLocaleTimeString([], { hour12: true, hour: "numeric", minute: "2-digit" });

          items += `<li>${prettyTime} on ${prettyDate}</li>`;
        });

        $('#timeslots-announce-description').html('<ol class="list--ordered-bullet">' + items + '</ol>');
      }

    }
  };

})(jQuery, Drupal, once);


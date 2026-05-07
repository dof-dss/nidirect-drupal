/**
 * @file
 * JQuery for prison visit booking webform.
 */

(function($, Drupal, once) {

  Drupal.behaviors.prisonVisitTimeSlots = {
    attach: function (context, settings) {
      $(once('prisonVisitTimeSlotsInit', '[data-webform-key="visit_preferred_day_and_time"]', context)).each(function () {
        const $container = $(this);
        const $weekSlots = $container.find('[data-webform-key^="slots_week"]');
        const $timeSlots = $container.find('input[type="checkbox"].timeslot');

        // Hidden inputs store preferred timeslots in rank order. There
        // are five in total named slot1_datetime, slot2_datetime, etc.
        const $hiddenTimeSlots = $('[name^="slot"][name$="_datetime"]', context);

        // Track ids of timeslots that are preferred. The array
        // index + 1 will match its preference rank.
        let preferredSlotIds = [];

        // If there is only one week's worth of timeslots, always
        // ensure it is expanded.
        if ($weekSlots.length === 1) {
          $weekSlots.prop("open", true);
          $weekSlots.find('summary')
            .prop('aria-expanded', true)
            .prop('aria-pressed', true);
        }

        // Visit type determines the number of timeslots a user
        // can choose.
        let pvTypeId = 'F';
        let pvTimeSlotLimit = 3;

        if (settings.prisonVisitBooking['booking_ref'] !== null) {
          pvTypeId = settings.prisonVisitBooking.booking_ref.visit_type_id;
          pvTimeSlotLimit = settings.prisonVisitBooking.visit_type_time_slot_limit[pvTypeId];
        }

        if ($timeSlots.length) {

          // Important to disable checkboxes to prevent timeslot limit
          // being exceeded.
          disableCheckboxes($timeSlots, pvTimeSlotLimit);

          // Create an accessible hidden element that will announce
          // the user's selection of preferred timeslots.
          // TODO: consider using Drupal.announce() for this bit.
          const $preferredStatus = $('<div role="region" class="visually-hidden" aria-live="polite" />');
          $preferredStatus.append($('<h2 id="timeslots-announce-title" />'));
          $preferredStatus.append($('<div id="timeslots-announce-description" />'));
          $container.prepend($preferredStatus);

          $timeSlots.attr('aria-controls', 'timeslots-announce');

          // Force reset of timeslots if necessary. This will occur
          // if user changes the booking reference number which means
          // already selected timeslots are not valid.
          if (settings.prisonVisitBooking.resetTimeslots) {
            $timeSlots.prop('checked', false);
            $hiddenTimeSlots.val('');
            settings.prisonVisitBooking.resetTimeslots = false;
          }

          // Inject rank spans once.
          $timeSlots.each(function () {
            const id = $(this).prop('id');
            const $label = $container.find('label[for="' + id + '"]');

            $(once('pvInjectRank-' + id, $label)).each(function () {
              if (!$(this).find('span.rank').length) {
                $('<span />')
                  .addClass('rank')
                  .attr('aria-hidden', 'true')
                  .appendTo($(this));
              }
            });
          });

          // Restore ranks from hidden inputs used to track preferred
          // timeslots across form submissions.
          $hiddenTimeSlots.each(function (index) {
            const value = $(this).val();
            if (value) {
              const $checkbox = $timeSlots.filter('[value="' + value + '"]');
              if ($checkbox.length) {
                const $label = $container.find('label[for="' + $checkbox.prop('id') + '"]');
                $label.find('span.rank').text(index + 1);
                preferredSlotIds[index] = $checkbox.prop('id');
              }
            }
          });

          updatePreferredStatus();

          // When timeslots are clicked, update ranks and tracking
          // of preferred timeslots. Use of Drupal.debounce() is to
          // prevent race conditions caused by fast clicks.
          $timeSlots.on('click', Drupal.debounce(function () {

            disableCheckboxes($timeSlots, pvTimeSlotLimit);

            const slotId = $(this).prop('id');
            const slotValue = $(this).val();
            const $label = $container.find('label[for="' + slotId + '"]');
            const $slotRank = $label.find('span.rank');
            const currentRankIndex = preferredSlotIds.indexOf(slotId);

            $slotRank.text('');
            if (currentRankIndex !== -1) {
              $hiddenTimeSlots.eq(currentRankIndex).val('');
            }

            if ($(this).is(':checked')) {
              // If not currently ranked, add to preferredSlotIds[].
              if (currentRankIndex === -1) {
                preferredSlotIds.push(slotId);
              }
              // Update rank and corresponding hidden timeslot.
              const newRank = preferredSlotIds.indexOf(slotId);
              $slotRank.text(newRank + 1);
              $hiddenTimeSlots.eq(newRank).val(slotValue).attr('value', slotValue);
            } else {
              // Timeslot unchecked, so remove from preferredSlotIds[].
              if (currentRankIndex > -1) {
                preferredSlotIds.splice(currentRankIndex, 1);
              }

              // Adjust ranks for remaining preferred timeslots.
              preferredSlotIds.forEach(function (id, index) {
                $container.find('label[for="' + id + '"]').find('span.rank').text(index + 1);
              });

              // Update hidden slots with new preferred times.
              $hiddenTimeSlots.each(function (index) {
                const id = preferredSlotIds[index];
                const val = id ? $('#' + id).val() : '';
                $(this).val(val || '').attr('value', val || '');
              });
            }

            updatePreferredStatus();
          }, 150));
        }

        function disableCheckboxes($checkboxes, limit = 3) {
          let checked = $checkboxes.filter(':checked').length;
          $checkboxes.filter(':not(:checked)').prop('disabled', checked >= limit);
        }

        function updatePreferredStatus() {
          const count = preferredSlotIds.length;
          const max = pvTimeSlotLimit;

          $('#timeslots-announce-title').text(
            count < max
              ? `You have selected ${count} time slot${count === 1 ? '' : 's'}`
              : `You have selected a maximum of ${count} time slots`
          );

          let items = '';
          const options = { weekday: "long", year: "numeric", month: "long", day: "numeric" };

          preferredSlotIds.forEach(function (slotId) {
            const value = $('#' + slotId).val();
            if (value) {
              const date = new Date(value);
              const prettyDate = date.toLocaleDateString('en-GB', options);
              const prettyTime = date.toLocaleTimeString([], { hour12: true, hour: "numeric", minute: "2-digit" });
              items += `<li>${prettyTime} on ${prettyDate}</li>`;
            }
          });

          $('#timeslots-announce-description').html(`<ol class="list--ordered-bullet">${items}</ol>`);
        }
      });
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

        // Clear any errors.
        $('input', $additionalVisitors)
          .removeClass('error')
          .attr('aria-invalid', false)
          .next('.form-item--error-message')
          .remove();

        let $visitor = $(element).closest('fieldset');
        let $visitor_id = $('input[name$="_id"]', $visitor);
        let $visitor_dob = $('input[name$="_dob"]', $visitor);

        const $visitorNextAll = $visitor.nextAll();

        $visitorNextAll.each(function(index) {
          let $visitor_sibling_id = $('input[name$="_id"]', $(this));
          let $visitor_sibling_dob = $('input[name$="_dob"]', $(this));

          $visitor_id
            .val($visitor_sibling_id.val())
            .attr('value', $visitor_sibling_id.val());
          $visitor_dob
            .val($visitor_sibling_dob.val())
            .attr('value', $visitor_sibling_dob.val());

          $visitor_id = $visitor_sibling_id;
          $visitor_dob = $visitor_sibling_dob;
        });

        $visitor_id.val('').attr('value', '');
        $visitor_dob.val('').attr('value', '');

        // Update the radios controlling the number of visitors.
        // Use a click event to fire events to hide visitors
        // in the normal way and focus on the number of visitors.
        let visitor_number = $('input[id^="edit-additional-visitor-number"]:checked').val();
        $('input[id^="edit-additional-visitor-number"][value="' + (visitor_number - 1) + '"]').click();

        Drupal.announce(
          Drupal.t('Visitor removed. Number of additional visitors is ' + (visitor_number - 1))
        );
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
        const age = Drupal.pvGetAge($dob.val());
        if (age >= 18) {
          $badge.text('Adult').removeClass('visitor-badge--child visually-hidden');
        } else if (age !== null && age >= 0) {
          $badge.text('Child').removeClass('visually-hidden').addClass('visitor-badge--child');
        } else {
          $badge.addClass('visually-hidden');
        }
      }

      $additionalVisitors.each(function(index) {

        let $visitor = $(this);

        // Remove link for each visitor.
        // Has to remove the visitor and update visitor badges.
        let $link_innerHTML = 'Remove <span class="visually-hidden">' + $('legend', $visitor).text() + '</span>';
        let $link = $('<a href="#" class="visitor-remove">' + $link_innerHTML + '</a>').attr('data-index', (index + 1));
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

  Drupal.behaviors.prisonVisitMainVisitor = {
    attach: function (context, settings) {

      const $mainVisitorID = $(once('prisonVisitMainVisitorID', '[name="visitor_1_id"]', context));
      const additionalVisitorIDs =  settings.prisonVisitBooking.additionalVisitorIds ?? {};

      $mainVisitorID.on('keyup blur', function() {
        // Check against existing additional visitor IDs and show
        // a warning message if there is a match.
        let mainVisitorID = $(this).val();

        if (mainVisitorID.length >= 6 && Object.values(additionalVisitorIDs).includes($(this).val())) {
          $('#visitor-1-id-duplicate-warning').removeAttr('hidden');
        } else {
          $('#visitor-1-id-duplicate-warning').attr('hidden', 'hidden');
        }
      });

    }
  };

})(jQuery, Drupal, once);

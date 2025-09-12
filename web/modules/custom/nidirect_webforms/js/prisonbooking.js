/**
 * @file
 * JQuery for prison visit booking webform.
 */

(function($, Drupal, once) {

  Drupal.behaviors.prisonVisitTimeSlots = {
    attach: function (context, settings) {
      $(once('prisonVisitTimeSlotsInit', '[data-webform-key="visit_preferred_day_and_time"]', context)).each(function () {
        const $container = $(this);
        const $form = $container.closest('form');
        const $nextButton = $form.find('input.webform-button--next, input.webform-button--preview');
        const $weekSlots = $container.find('[data-webform-key^="slots_week"]');
        const $timeSlots = $container.find('input[type="checkbox"].timeslot');
        const $hiddenTimeSlots = $form.find('[name^="slot"][name$="_datetime"]');

        // If there is only one week's worth of timeslots, expand it by default.
        if ($weekSlots.length === 1) {
          $weekSlots.prop("open", true);
          $weekSlots.find('summary')
            .prop('aria-expanded', true)
            .prop('aria-pressed', true);
        }

        // Visit type determines the number of timeslots a user can choose.
        let pvTypeId = 'F';
        let pvTimeSlotLimit = 3;

        if (settings.prisonVisitBooking['booking_ref'] !== null) {
          pvTypeId = settings.prisonVisitBooking.booking_ref.visit_type_id;
          pvTimeSlotLimit = settings.prisonVisitBooking.visit_type_time_slot_limit[pvTypeId];
        }

        // Track click order of timeslots.
        let clickOrder = [];

        if ($timeSlots.length) {
          // Enforce selection limit straight away.
          disableTimeSlots($timeSlots, pvTimeSlotLimit);

          // Reset if required (e.g. booking reference changed).
          if (settings.prisonVisitBooking.resetTimeslots) {
            $timeSlots.prop('checked', false);
            $hiddenTimeSlots.val('');
            settings.prisonVisitBooking.resetTimeslots = false;
          }

          // Inject persistent rank spans once.
          $timeSlots.each(function () {
            const id = $(this).prop('id');
            const $label = $container.find('label[for="' + id + '"]');

            $(once('pvInjectRank-' + id, $label)).each(function () {
              if (!$(this).find('span.rank').length) {
                $('<span />')
                  .prop('id', id + '-rank')
                  .addClass('rank')
                  .appendTo($(this));
              }
            });
          });

          // Restore from hidden inputs (e.g. after AJAX rebuild).
          $hiddenTimeSlots.each(function (index) {
            const value = $(this).val();
            let now = Date.now();
            if (value) {
              const $checkbox = $timeSlots.filter('[value="' + value + '"]');
              if ($checkbox.length) {
                clickOrder.push({ value: value, time: now + index }); // ensure stable order
              }
            }
          });

          updateTimeSlotRankings(clickOrder.map(item => item.value));

          // On clicking a timeslot...
          $timeSlots.on('click', function () {

            // Prevent form submission.
            $nextButton.prop('disabled', true);

            // Immediately disable timeslots.
            disableTimeSlots($timeSlots, pvTimeSlotLimit);

            // Track click order of timeslot.
            const value = $(this).val();
            const now = Date.now();

            if ($(this).is(':checked')) {
              clickOrder = clickOrder.filter(item => item.value !== value);
              clickOrder.push({ value: value, time: now });
            } else {
              clickOrder = clickOrder.filter(item => item.value !== value);
            }

            // Further updates are debounced.
            debouncedUpdateSlots();
          });

          // Debounced updates.
          const debouncedUpdateSlots = Drupal.debounce(function () {
            clickOrder.sort((a, b) => a.time - b.time);
            const orderedSlots = clickOrder.map(item => item.value);

            updateHiddenSlots(orderedSlots, pvTimeSlotLimit);
            updateTimeSlotRankings(orderedSlots);

            $nextButton.prop('disabled', false);
          }, 150);
        }

        // --- Helpers ---

        /**
         * Disable time slots when the number checked has hit a limit.
         * @param $timeSlots
         * @param limit
         */
        function disableTimeSlots($timeSlots, limit = 3) {
          let checked = $timeSlots.filter(':checked').length;
          $timeSlots.filter(':not(:checked)').prop('disabled', checked >= limit);
        }

        /**
         * Update hidden inputs that store user's preferred timeslots.
         * @param orderedSlots
         * @param limit
         */
        function updateHiddenSlots(orderedSlots, limit) {
          for (let i = 1; i <= limit; i++) {
            let $hidden = $form.find('[name="slot' + i + '_datetime"]');
            if (orderedSlots[i - 1]) {
              $hidden.val(orderedSlots[i - 1]);
            } else {
              $hidden.val('');
            }
          }
        }

        /**
         * Update time slot rankings that are displayed/announced to
         * user.
         * @param orderedSlots
         */
        function updateTimeSlotRankings(orderedSlots) {
          // Clear all rank text first.
          $('label span.rank', $form).text('');

          // Update ranks in order.
          orderedSlots.forEach(function (value, index) {
            const $timeSlot = $timeSlots.filter('[value="' + value + '"]');
            rankId = $timeSlot.prop('id') + '-rank';
            $('#' + rankId).text(index + 1);
          });

          // Announce the most recent change.
          if (clickOrder.length) {
            const last = clickOrder[clickOrder.length - 1];
            const $checkbox = $timeSlots.filter('[value="' + last.value + '"]');
            const labelText = $checkbox.next('label').clone()
              .children('.rank').remove().end()
              .text().trim();

            if ($checkbox.is(':checked')) {
              // Added.
              const rank = orderedSlots.indexOf(last.value) + 1;
              Drupal.announce('Added ' + labelText + ' as choice ' + rank, 'assertive');
            } else {
              // Removed.
              Drupal.announce('Removed ' + labelText + ' from your choices', 'assertive');
            }
          } else {
            Drupal.announce('No timeslots selected', 'polite');
          }
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

<?php

namespace Drupal\nidirect_webforms\Plugin\WebformHandler;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Prison Visit Booking Webform Handler.
 *
 * @WebformHandler(
 *   id = "prison_visit_booking",
 *   label = @Translation("Prison Visit Booking"),
 *   category = @Translation("NIDirect"),
 *   description = @Translation("Does stuff with Prison Visit Booking."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_REQUIRED,
 * )
 */
class PrisonVisitBookingHandler extends WebformHandlerBase {

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return $this->configFactory->get('nidirect_webforms.prison_visit_booking.settings')->getRawData() ?? [];
  }

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $form['#attached']['drupalSettings']['prisonVisitBooking'] = $this->configuration;

    $booking_ref = $this->processBookingReference($form_state);

    if (!empty($booking_ref)) {

      $visit_type = $booking_ref['visit_type'];
      $visit_type_key = $booking_ref['visit_type_key'];
      $visit_prison = $booking_ref['prison_name'];
      $visit_prison_key = $booking_ref['prison_key'];
      $visit_sequence = $booking_ref['visit_sequence'];
      $visit_prisoner_category = $booking_ref['prisoner_category'];
      $visit_prisoner_subcategory = $booking_ref['prisoner_subcategory'];
      $visit_order_date = $booking_ref['visit_order_date'];
      $visit_booking_ref_valid_from = $booking_ref['visit_order_valid_from'];
      $visit_booking_ref_valid_to = $booking_ref['visit_order_valid_to'];
      $visit_earliest_booking_date = $booking_ref['visit_earliest_booking_date'];
      $visit_latest_booking_date = $booking_ref['visit_latest_booking_date'];
      $visit_booking_week_start = $booking_ref['visit_booking_week_start'];

      if ($visit_booking_ref_valid_from < $visit_booking_week_start) {
        $visit_booking_ref_valid_from = $visit_booking_week_start;
      }

      // Retrieve configured visit slots for a given prison and visit type.
      // For example, slots for Maghaberry face-to-face visits).
      $config_visit_slots = $this->configuration['visit_slots'][$visit_prison][$visit_type];

      if (!empty($config_visit_slots)) {

        // Loop through four weeks worth of slots and disable or remove slots
        // that fall outside the validity period of the booking reference, or
        // fall within the notice period required to book, etc.

        for ($i = 4; $i > 0; $i--) {

          // Slots for each week are grouped together in some kind of webform
          // grouping element (e.g. container, section or details). The keys
          // must be slots_week_1, slots_week_2, etc.
          $form_slots_week = &$form['elements']['visit_preferred_day_and_time']['slots_week_' . $i];

          // By default, disable access. Enable access if there are days
          // and times to show.
          $form_slots_week['#access'] = FALSE;

          // Add week commencing date to container titles for each week.
          $form_slots_week_date = clone $visit_booking_week_start;
          $form_slots_week_date->modify('+' . ($i - 1) . 'weeks');
          $form_slots_week['#title'] = str_replace('[DATE]', $form_slots_week_date->format('d F Y'), $form_slots_week['#title']);

          // Loop through each day of config slots.
          foreach ($config_visit_slots as $day => $config_slots) {
            $form_slots_day = &$form_slots_week[strtolower($day) . '_week_' . $i];

            // By default, disable access.
            $form_slots_day['#access'] = FALSE;

            // Get the configured time slots.
            $config_time_slots = $config_slots;

            // There may be specific time slots for prisoner categories.
            $time_slots_prisoner_category_specific = FALSE;
            if (!empty($config_slots[$visit_prisoner_category])) {
              $time_slots_prisoner_category_specific = TRUE;
              $config_time_slots = $config_slots[$visit_prisoner_category];
            }

            if (!empty($config_slots)) {
              // Work out date to prefix option keys with.
              $key_date = clone $visit_booking_ref_valid_from;
              $key_date->setTimezone(new \DateTimeZone('Europe/London'));
              $key_date->modify('+' . ($i - 1) . ' weeks');
              $key_date->modify($day . ' this week');

              // Loop through time slots for this day.
              $options = &$form_slots_day['#options'];

              foreach ($options as $key => $value) {

                $key_time = (date_parse($key));
                $key_date->setTime($key_time['hour'], $key_time['minute'], $key_time['second']);

                // Determine if this key_date is bookable.
                $key_date_is_bookable = TRUE;

                if (array_key_exists($key, $config_time_slots) === FALSE) {
                  $key_date_is_bookable = FALSE;
                }

                if ($key_date > $visit_booking_ref_valid_to) {
                  $key_date_is_bookable = FALSE;
                }

                if ($key_date <= $visit_earliest_booking_date) {
                  $key_date_is_bookable = FALSE;
                }

                if ($key_date > $visit_latest_booking_date && $visit_order_date > $visit_latest_booking_date) {
                  $key_date_is_bookable = FALSE;
                }

                if ($time_slots_prisoner_category_specific) {
                  // Prevent separates booking am or pm timeslots depending on
                  // week number parity.
                  $week_number = $key_date->format('W');
                  if ($week_number % 2 === 0) {
                    if ($visit_prisoner_subcategory === 0 && $key_time['hour'] <= 12) {
                      $key_date_is_bookable = FALSE;
                    }
                    elseif ($visit_prisoner_subcategory === 1 && $key_time['hour'] > 12) {
                      $key_date_is_bookable = FALSE;
                    }
                  }
                  else {
                    if ($visit_prisoner_subcategory === 0 && $key_time['hour'] > 12) {
                      $key_date_is_bookable = FALSE;
                    }
                    elseif ($visit_prisoner_subcategory === 1 && $key_time['hour'] <= 12) {
                      $key_date_is_bookable = FALSE;
                    }
                  }
                }

                if ($key_date_is_bookable) {
                  // Make a new key containing full datetime.
                  $new_key = $key_date->format('d/m/Y H:i');
                  $options[$new_key] = $value;
                }

                // Unset old options.
                unset($options[$key]);
              }

              // If we have any options left to show to user, enable access to
              // parent webform elements.
              if (!empty($options)) {
                $form_slots_day['#access'] = TRUE;
                $form_slots_week['#access'] = TRUE;
              }
            }
          }
        }
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $this->validateVisitBookingReference($form, $form_state);
    $this->validateVisitorOneDateOfBirth($form, $form_state);
  }

  /**
   * Validate visit booking reference.
   */
  private function validateVisitBookingReference(array &$form, FormStateInterface $form_state) {

    $booking_ref = !empty($form_state->getValue('visitor_order_number')) ? $form_state->getValue('visitor_order_number') : NULL;

    // Basic validation with early return.
    if (empty($booking_ref)) {
      $form_state->setErrorByName('visitor_order_number', $this->t('Visit reference number is required'));
      return;
    }
    elseif (strlen($booking_ref) !== $this->configuration['visit_order_number_length']) {
      $form_state->setErrorByName('visitor_order_number', $this->t('Visit reference number must contain 12 characters'));
      return;
    }

    // Get processed version of the booking reference.
    $booking_ref_processed = $form_state->getValue('booking_reference_processed');
    if (empty($booking_ref_processed)) {
      $booking_ref_processed = $this->processBookingReference($form_state);
    }

    $process_booking_ref_is_valid = TRUE;
    $error_message = $this->t('Visit reference number is not recognised.');

    // If any of the processed booking reference values are missing,
    // it's invalid.
    foreach ($booking_ref_processed as $key => $value) {
      if (!isset($value)) {
        $process_booking_ref_is_valid = FALSE;
      }
    }

    // Check prison name and visit type are a valid combination.
    $prison_name = $booking_ref_processed['prison_name'];
    $visit_type = $booking_ref_processed['visit_type'];
    if (empty($this->configuration['visit_slots'][$prison_name][$visit_type])) {
      $process_booking_ref_is_valid = FALSE;
    }

    // Check date and year portions.
    if ($booking_ref_processed['visit_order_date'] > $booking_ref_processed['visit_order_valid_to']) {
      $process_booking_ref_is_valid = FALSE;
      $error_message = $this->t('Visit reference number has expired.');
    }
    elseif ($booking_ref_processed['visit_order_date'] > $booking_ref_processed['visit_latest_booking_date']) {
      $process_booking_ref_is_valid = FALSE;
      $error_message = $this->t('Visit reference number has expired.');
    }

    if ($process_booking_ref_is_valid !== TRUE) {
      $form_state->setErrorByName('visitor_order_number', $error_message);
    }
    else {
      $form_state->setValue('visitor_order_number', $booking_ref);
    }

  }

  /**
   * Process booking reference.
   */
  private function processBookingReference(FormStateInterface $form_state) {

    $booking_ref = !empty($form_state->getValue('visitor_order_number')) ? $form_state->getValue('visitor_order_number') : NULL;

    if (empty($booking_ref)) {
      return [];
    }

    $booking_ref_processed = [];

    // Extract various bits of the booking reference.
    $booking_ref_prison_identifier = substr($booking_ref, 0, 2);
    $booking_ref_visit_type = substr($booking_ref, 2, 1);
    $booking_ref_week = (int) substr($booking_ref, 3, 2);
    $booking_ref_validity_period_days = $this->configuration['booking_reference_validity_period_days'][$booking_ref_visit_type];
    $booking_ref_year = (int) substr($booking_ref, 5, 2);
    $booking_ref_year_full = (int) DrupalDateTime::createFromFormat('y', $booking_ref_year)->format('Y');
    $booking_ref_sequence = (int) substr($booking_ref, 8);

    // Process prison identifier.
    if (array_key_exists($booking_ref_prison_identifier, $this->configuration['prisons'])) {
      $booking_ref_processed['prison_key'] = $booking_ref_prison_identifier;
      $booking_ref_processed['prison_name'] = $this->configuration['prisons'][$booking_ref_prison_identifier];
    }

    // Process visit type.
    if (array_key_exists($booking_ref_visit_type, $this->configuration['visit_type'])) {
      $booking_ref_processed['visit_type_key'] = $booking_ref_visit_type;
      $booking_ref_processed['visit_type'] = $this->configuration['visit_type'][$booking_ref_visit_type];
    }

    // Process the week number and year to set some dates in form state.
    $now = new \DateTime('now');
    $now->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));

    // Determine date for first day of this week (always a Monday).
    $now_week_commence = clone $now;
    $now_week_commence->setISODate($now->format('Y'), $now->format('W'), 1);
    $now_week_commence->setTime(0, 0, 0);

    // Determine valid from date for the booking reference.
    // The week number and year in the booking reference is an
    // ISO 8601 week date.
    $booking_ref_valid_from = clone $now;
    $booking_ref_valid_from->setISODate($booking_ref_year_full, $booking_ref_week, 1);
    $booking_ref_valid_from->setTime(0, 0, 0);

    // Determine the valid to date for booking reference. It is
    // calculated by adding the validity period to the valid from date.
    $booking_ref_valid_to = clone $booking_ref_valid_from;
    $booking_ref_valid_to->modify('+' . $booking_ref_validity_period_days . ' days');

    // Determine the advance notice required for a booking.
    // The advance notice required is dependent on the visit type.
    $visit_advance_notice = $this->configuration['visit_advance_notice'][$booking_ref_visit_type];

    // Determine the earliest date for booking
    // (current date plus the advance notice).
    $visit_earliest_booking_date = clone $now;
    $visit_earliest_booking_date->modify('+' . $visit_advance_notice);

    // Determine the latest date for booking
    // (booking reference valid to date minus the advance notice).
    $visit_latest_booking_date = clone $booking_ref_valid_to;
    $visit_latest_booking_date->modify('-' . $visit_advance_notice);

    $booking_ref_processed['visit_order_date'] = $now;
    $booking_ref_processed['visit_order_valid_from'] = $booking_ref_valid_from;
    $booking_ref_processed['visit_order_valid_to'] = $booking_ref_valid_to;
    $booking_ref_processed['visit_earliest_booking_date'] = $visit_earliest_booking_date;
    $booking_ref_processed['visit_latest_booking_date'] = $visit_latest_booking_date;

    if ($now < $booking_ref_valid_from) {
      // Determine whether week date for the booking is for a future week or
      // current week.
      $booking_ref_processed['visit_booking_week_start'] = $booking_ref_valid_from;
    }
    else {
      $booking_ref_processed['visit_booking_week_start'] = $now_week_commence;
    }

    // Determine prisoner category and subcategory from booking reference
    // sequence number.
    if ($booking_ref_sequence > 0 && $booking_ref_sequence < 9999) {

      $booking_ref_processed['visit_sequence'] = $booking_ref_sequence;
      $prisoner_categories = $this->configuration['visit_order_number_categories'];

      foreach ($prisoner_categories as $category_key => $category_value) {
        foreach ($category_value as $subcategory_key => $subcategory_value) {
          if ($booking_ref_sequence >= $subcategory_value[0] && $booking_ref_sequence <= $subcategory_value[1]) {
            $booking_ref_processed['prisoner_category'] = $category_key;
            $booking_ref_processed['prisoner_subcategory'] = $subcategory_key;
          }
        }
      }
    }

    $form_state->setValue('booking_reference_processed', $booking_ref_processed);

    return $booking_ref_processed;
  }

  /**
   * Validate visitor one DOB.
   */
  private function validateVisitorOneDateOfBirth(array &$form, FormStateInterface $form_state) {
    $visitor_1_dob = !empty($form_state->getValue('visitor_1_dob')) ? $form_state->getValue('visitor_1_dob') : NULL;

    if (!empty($visitor_1_dob)) {
      $today = new \DateTime('now', new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
      $today->setTime(0, 0, 0);

      $birthday = new \DateTime($visitor_1_dob);
      if ($today->diff($birthday)->y < 18) {
        $form_state->setErrorByName('visitor_1_dob', $this->t('You must be at least 18 years old to book a prison visit.'));
      }
    }
  }

}

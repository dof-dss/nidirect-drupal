<?php

namespace Drupal\nidirect_webforms\Plugin\WebformHandler;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\Utility\WebformFormHelper;
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
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class PrisonVisitBookingHandler extends WebformHandlerBase
{

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    $instance->request = $container->get('request_stack')->getCurrentRequest();
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration()
  {
    return $this->configFactory->get('nidirect_webforms.prison_visit_booking.settings')->getRawData() ?? [];
  }

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
  {

    $booking_ref = $this->processBookingReference($form_state);
    $available_slots = $this->getAvailableSlots($form_state);

    $form['#attached']['drupalSettings']['prisonVisitBooking'] = $this->configuration;
    $form['#attached']['drupalSettings']['prisonVisitBooking']['booking_ref'] = $booking_ref;


    if (!empty($booking_ref)) {
      $visit_type = $booking_ref['visit_type'];
      $visit_type_key = $booking_ref['visit_type_key'];
      $visit_prison = $booking_ref['prison_name'];
      $visit_prison_key = $booking_ref['prison_key'];
      $visit_sequence = $booking_ref['visit_sequence'];
      $visit_prisoner_category = $booking_ref['prisoner_category'];
      $visit_prisoner_subcategory = $booking_ref['prisoner_subcategory'];
      $visit_order_date = $booking_ref['visit_order_date'];
      $visit_earliest_date = $booking_ref['visit_earliest_date'];
      $visit_booking_ref_valid_from = $booking_ref['visit_order_valid_from'];
      $visit_booking_ref_valid_to = $booking_ref['visit_order_valid_to'];
      $visit_advance_booking_earliest_date = $booking_ref['visit_advance_booking_earliest_date'];
      $visit_latest_booking_date = $booking_ref['visit_latest_booking_date'];
      $visit_booking_week_start = $booking_ref['visit_booking_week_start'];

      if ($visit_booking_ref_valid_from < $visit_booking_week_start) {
        $visit_booking_ref_valid_from = $visit_booking_week_start;
      }

//      kint(
//        $visit_order_date,
//        $visit_earliest_date,
//        $visit_booking_ref_valid_from,
//        $visit_booking_ref_valid_to,
//        $visit_advance_booking_earliest_date,
//        $visit_latest_booking_date,
//        $visit_booking_week_start,
//        $available_slots
//      );


      if (!empty($available_slots)) {

        // Alter form slots to correspond with available slots.

        for ($i = 4; $i >= 1; $i--) {

          // Form slots for each week.
          $form_slots_week = &$form['elements']['visit_preferred_day_and_time']['slots_week_' . $i];

          $webform_submission_slots_week = $webform_submission->getWebform()->getElement('slots_week_' . $i, TRUE);

          if ($form_slots_week['#access'] = FALSE) {
            continue;
          }

          // By default, disable access. Enable access if there are days
          // and times to show.
          //$form_slots_week['#access'] = FALSE;

          // Add week commencing date to container titles for each week.
          $form_slots_week_date = clone $visit_booking_ref_valid_from;
          $form_slots_week_date->modify('+' . ($i - 1) . 'weeks');
          $form_slots_week_title = str_replace('[DATE]', $form_slots_week_date->format('j F Y'), $form_slots_week['#title']);

          $form_slots_week['#title'] = $form_slots_week_title;
          $webform_submission_slots_week['#title'] = $form_slots_week_title;
          $webform_submission->getWebform()->setElementProperties('slots_week_' . $i, $webform_submission_slots_week);

          // Loop through form slots for each day.
          $days_of_week = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

          for ($j = 1; $j <= 7; $j++) {

            $day = $days_of_week[$j-1];
            $form_slots_day_date = clone $form_slots_week_date;
            $form_slots_day = &$form_slots_week[$day . '_week_' . $i];

            $form_slots_day_title = $form_slots_day['#title'];
            $form_slots_day_date->modify('+' . ($j - 1) . ' day');
            $form_slots_day_title .= ', ' . $form_slots_day_date->format('j F Y');
            $form_slots_day['#title'] = $form_slots_day_title;

            // By default, disable access.
            $form_slots_day['#access'] = FALSE;

            $form_slots_day['#options'] = [];

            // Create the bookable options and add to the form.
            foreach ($available_slots as $available_slot) {

              // Determine if this slot is bookable.
              $slot_is_bookable = TRUE;

              if ($available_slot < $visit_booking_week_start) {
                $slot_is_bookable = FALSE;
              }

              if ($available_slot > $visit_booking_ref_valid_to) {
                $slot_is_bookable = FALSE;
              }

              if ($available_slot < $visit_order_date) {
                $slot_is_bookable = FALSE;
              }

              if ($available_slot < $visit_earliest_date ) {
                $slot_is_bookable = FALSE;
              }

              if ($visit_type === 'face-to-face' && $visit_prisoner_category === 'separates') {

                // Separates get am or pm timeslots depending on
                // week number parity.

                $week_number = $available_slot->format('W');
                $hour = $available_slot->format('H');

                if ($week_number % 2 === 0) {
                  if ($visit_prisoner_subcategory === 0 && $hour <= 12) {
                    $slot_is_bookable = FALSE;
                  }
                  elseif ($visit_prisoner_subcategory === 1 && $hour > 12) {
                    $slot_is_bookable = FALSE;
                  }
                }
                else {
                  if ($visit_prisoner_subcategory === 0 && $hour > 12) {
                    $slot_is_bookable = FALSE;
                  }
                  elseif ($visit_prisoner_subcategory === 1 && $hour <= 12) {
                    $slot_is_bookable = FALSE;
                  }
                }
              }

              if ($slot_is_bookable && $available_slot->format('Y-m-d') === $form_slots_day_date->format('Y-m-d')) {
                $form_slots_day['#options'][$available_slot->format(DATE_ATOM)] = $available_slot->format('H:i');
              }
            }

            if (!empty($form_slots_day['#options'])) {
              $form_slots_day['#access'] = TRUE;
              $form_slots_week['#access'] = TRUE;
            }

          }
        }
      }
    }


    // Set visitor form element values from session.
    $session = $this->request->getSession();
    $elements = WebformFormHelper::flattenElements($form);
    $visitor_data = [];

    foreach ($session->all() as $key => $value) {
      if (str_starts_with($key, 'prison_visit_booking.visitor_') && !str_contains($key, 'visitor_order_number')) {
        $form_key = explode(".", $key)[1];
        $visitor_data[$form_key] = $value;
        $elements[$form_key]['#default_value'] = $value;
      }
    }

    $form_state->setValues($visitor_data);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
  {
    $this->validateVisitBookingReference($form, $form_state);
    $this->validateVisitorOneDateOfBirth($form, $form_state);
  }

  /**
   * Validate visit booking reference.
   */
  private function validateVisitBookingReference(array &$form, FormStateInterface $form_state)
  {

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
    $booking_ref_processed = $form_state->getValue('booking_ref_processed');
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
    else {
      $form_state->setValue('prison_visit_prison_name', $prison_name);
      $form_state->setValue('prison_visit_type', $visit_type);
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
    elseif ($booking_ref_processed['visit_order_date'] < $booking_ref_processed['visit_advance_booking_earliest_date']) {
      $process_booking_ref_is_valid = FALSE;
      $error_message = $this->t('Visit reference number is not recognised.');
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
  private function processBookingReference(FormStateInterface $form_state)
  {

    $booking_ref = !empty($form_state->getValue('visitor_order_number')) ? $form_state->getValue('visitor_order_number') : NULL;

    if (empty($booking_ref)) {
      return [];
    }

    $booking_ref_processed = [];

    // Extract various bits of the booking reference.
    $booking_ref_prison_identifier = substr($booking_ref, 0, 2);
    $booking_ref_visit_type = substr($booking_ref, 2, 1);
    $booking_ref_week = (int)substr($booking_ref, 3, 2);
    $booking_ref_year = (int)substr($booking_ref, 5, 2);
    $booking_ref_year_full = (int)DrupalDateTime::createFromFormat('y', $booking_ref_year)->format('Y');
    $booking_ref_sequence = (int)substr($booking_ref, 8);

    // Process prison identifier.
    if (array_key_exists($booking_ref_prison_identifier, $this->configuration['prisons'])) {
      $booking_ref_processed['prison_key'] = $booking_ref_prison_identifier;
      $booking_ref_processed['prison_name'] = $this->configuration['prisons'][$booking_ref_prison_identifier];
    }

    // Process visit type.
    if (array_key_exists($booking_ref_visit_type, $this->configuration['visit_type'])) {
      $booking_ref_processed['visit_type_key'] = $booking_ref_visit_type;
      $booking_ref_processed['visit_type'] = $this->configuration['visit_type'][$booking_ref_visit_type];

      // The "E" visit type (enhanced) is synonymous with the 'F' type
      // and so is face-to-face and has same time slots.
      if ($booking_ref_visit_type === 'E') {
        $booking_ref_processed['visit_type'] = $this->configuration['visit_type']['F'];
      }
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

    if (!empty($booking_ref_processed['prison_name']) && !empty($booking_ref_processed['visit_type'])) {

      // Extract some bits from config.
      $booking_ref_validity_period_days = $this->configuration['booking_reference_validity_period_days'][$booking_ref_visit_type];
      $booking_ref_processed['booking_ref_validity_period_days'] = $booking_ref_validity_period_days;

      // Advance notice required for a booking.
      // The advance notice required is dependent on the visit type.
      $booking_advance_notice = $this->configuration['visit_advance_notice'][$booking_ref_visit_type];

      // Maximum period of advance issue for a booking reference number.
      // For example, a booking reference may be issued 4 weeks in
      // advance of the valid from date.
      $booking_ref_max_advance_issue = $this->configuration['visit_order_number_max_advance_issue'];

      // Work out some dates.
      $now = new \DateTime('now');
      $now->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));

      // Date from now when a slot can be booked.
      $visit_earliest_date = clone $now;
      $visit_earliest_date->modify('+' . $booking_advance_notice);

      // Date for first day of this week (always a Monday).
      $now_week_commence = clone $now;
      $now_week_commence->setISODate($now->format('Y'), $now->format('W'), 1);
      $now_week_commence->setTime(0, 0, 0);

      // Valid from date for the booking reference.
      // The week number and year in the booking reference is an
      // ISO 8601 week date.
      $booking_ref_valid_from = clone $now;
      $booking_ref_valid_from->setISODate($booking_ref_year_full, $booking_ref_week, 1);
      $booking_ref_valid_from->setTime(0, 0, 0);

      // Valid to date for booking reference.
      $booking_ref_valid_to = clone $booking_ref_valid_from;
      $booking_ref_valid_to->modify('+' . $booking_ref_validity_period_days . ' days');
      $booking_ref_valid_to->setTime(23, 59, 59);

      $booking_ref_max_advanced_issue_date = clone $booking_ref_valid_from;
      $booking_ref_max_advanced_issue_date->modify('-' . $booking_ref_max_advance_issue);

      // Determine the latest date for booking.
      // (booking reference valid to date minus the advance notice).
      $visit_latest_booking_date = clone $booking_ref_valid_to;
      $visit_latest_booking_date->modify('-' . $booking_advance_notice);

      $booking_ref_processed['visit_order_date'] = $now;
      $booking_ref_processed['visit_earliest_date'] = $visit_earliest_date;
      $booking_ref_processed['visit_order_valid_from'] = $booking_ref_valid_from;
      $booking_ref_processed['visit_order_valid_to'] = $booking_ref_valid_to;
      $booking_ref_processed['visit_advance_booking_earliest_date'] = $booking_ref_max_advanced_issue_date;
      $booking_ref_processed['visit_latest_booking_date'] = $visit_latest_booking_date;

      if ($now < $booking_ref_valid_from) {
        // Determine whether week date for the booking is for a future week or
        // current week.
        $booking_ref_processed['visit_booking_week_start'] = $booking_ref_valid_from;
      } else {
        $booking_ref_processed['visit_booking_week_start'] = $now_week_commence;
      }
    }

    $form_state->setValue('booking_ref_processed', $booking_ref_processed);

    return $booking_ref_processed;
  }

  /**
   * Get available slots for a given prison, visit type and prisoner category.
   */
  private function getAvailableSlots(FormStateInterface $form_state) {

    $booking_ref = $form_state->getValue('booking_ref_processed');

    $prison_name = $booking_ref['prison_name'];
    $visit_type = $booking_ref['visit_type'];
    $prisoner_category = $booking_ref['prisoner_category'];

    if (empty($prison_name) || empty($visit_type) || empty($prisoner_category)) {
      return [];
    }

    $available_slots = [];

    // TODO: get face-to-face time slots from json. For now, retrieve from config.
    $visit_slots = $this->configuration['visit_slots'][$prison_name][$visit_type];
    $valid_from = clone $booking_ref['visit_order_valid_from'];
    $valid_period = $booking_ref['booking_ref_validity_period_days'];
    $valid_period_weeks = $valid_period / 7;

    for ($i = 0; $i < $valid_period_weeks; $i++) {

      $slot_date = clone $valid_from;
      $slot_date->modify('+' . $i . ' weeks');

      foreach ($visit_slots as $day => $values) {

        if (!empty($values)) {

          $day_slots = $values;
          if (array_key_exists($prisoner_category, $values)) {
            $day_slots = $values[$prisoner_category];
          }

          $day = date('N', strtotime($day));
          $year = $slot_date->format('Y');
          $week = $slot_date->format('W');

          $slot_date->setISODate($year, $week, $day);

          foreach ($day_slots as $time => $pretty_time) {
            $time_parts = date_parse($time);
            $slot_date->setTime($time_parts['hour'], $time_parts['minute']);
            $available_slots[] = clone $slot_date;
          }
        }
      }
    }

    return $available_slots;
  }

  /**
   * Validate visitor one DOB.
   */
  private function validateVisitorOneDateOfBirth(array &$form, FormStateInterface $form_state)
  {
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

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
  {

    if ($form_state->isValidationComplete()) {

      // Save some stuff in session if user requested it.
      $session = $this->request->getSession();

      if ($form_state->getValue('yes_remember_these_visitor_details') === 1) {
        $session->set('prison_visit_booking.remember_settings', TRUE);
      }
      else {
        $session->set('prison_visit_booking.remember_settings', FALSE);
      }

      // Reset hidden elements keeping track of preferred time slots.
      for ($i = 1; $i <= 5; $i++) {
        $form_state->setValue('slot' . $i . '_datetime', '');
        $webform_submission->setElementData('slot' . $i . '_datetime', '');
        $form_state->setValue('slot' . $i . '_pretty_datetime', NULL);
        $webform_submission->setElementData('slot' . $i . '_pretty_datetime', NULL);
      }

      $form_values = $form_state->getValues();
      $count = 0;

      foreach ($form_values as $element_name => $element_values) {

        // Set hidden elements to keep track of selected time slots.
        // Time slots have keys like 'monday_week_1', 'tuesday_week_4', etc.

        if (str_contains($element_name, '_week_') && is_array($element_values)) {

          // Add up to 5 selected time slots to our hidden elements.
          foreach ($element_values as $slot) {
            $count++;
            if ($count <= 5) {
              $slot_date = new \DateTime($slot);
              $form_state->setValue('slot' . $count . '_datetime', $slot);
              $webform_submission->setElementData('slot' . $count . '_datetime', $slot);

              // "Pretty" slot dates and times for preview
              if (!empty($slot)) {
                $slot_datetime = new \DateTime($slot);
                $form_state->setValue('slot' . $count . '_pretty_datetime', $slot_datetime->format('l, j F Y \a\t g.i a'));
                $webform_submission->setElementData('slot' . $count . '_pretty_datetime', $slot_datetime->format('l, j F Y \a\t g.i a'));
              }
              else {
                $form_state->setValue('slot' . $count . '_pretty_datetime', '');
                $webform_submission->setElementData('slot' . $count . '_pretty_datetime', '');
              }
            }
          }
        }

        if (str_contains($element_name, 'special_requirements_details') && !empty($element_values)) {
          $special_requirements = Json::encode($element_values);
          $form_state->setValue('special_requirements_json', $special_requirements);
          $webform_submission->setElementData('special_requirements_json', $special_requirements);
        }

        // Use session to remember visitor information.
        if ($session->get('prison_visit_booking.remember_settings') === TRUE) {

          if (str_starts_with($element_name, 'visitor_')) {
            $session->set('prison_visit_booking.' . $element_name, $element_values);
          }
        }
        else {
          $session->remove('prison_visit_booking.' . $element_name);
        }
      }
    }
  }

}

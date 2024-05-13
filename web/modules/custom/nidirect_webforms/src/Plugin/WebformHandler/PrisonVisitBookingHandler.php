<?php

namespace Drupal\nidirect_webforms\Plugin\WebformHandler;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
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
 *   description = @Translation("Handles a Prison Visit Booking."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class PrisonVisitBookingHandler extends WebformHandlerBase {

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
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  private $tempStoreFactory;

  /**
   * Array for storing various values extrapolated
   * from the visit reference number supplied by the webform.
   *
   * @var array
   */
  protected $bookingReference = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    $instance->request = $container->get('request_stack')->getCurrentRequest();
    $instance->httpClient = $container->get('http_client');
    $instance->tempStoreFactory = $container->get('tempstore.private');
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
   *
   * If webform submissions are optionally enabled, we can prevent or
   * alter the submission before insertion into the database to
   * obfuscate sensitive data or remove it altogether.
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    // For now, just delete the submission entirely!
    $webform_submission->delete();
  }

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $page = $form_state->get('current_page');
    $elements = WebformFormHelper::flattenElements($form);

    $this->bookingReference = $form_state->get('booking_reference_processed');
    $form['#attached']['drupalSettings']['prisonVisitBooking'] = $this->configuration;
    $form['#attached']['drupalSettings']['prisonVisitBooking']['booking_ref'] = $this->bookingReference;

    // Visitor IDs are entered in separate wizard steps. To enable
    // clientside checking for duplicate IDs, pass any visitor IDs
    // added so far to clientside.

    if ($page === 'additional_visitor_adult_details' && $visitor_1_id = $form_state->getValue('visitor_1_id')) {
      // Pass the main visitor ID to clientside to enable additional
      // adult visitor ids to be checked against it.
      $form['#attached']['drupalSettings']['prisonVisitBooking']['visitorOneId'] = ['visitor_1_id' => $visitor_1_id];
    }
    elseif ($page === 'additional_visitor_child_details') {
      // Pass all adult visitor ids to clientside to validate
      // child visitor ids.
      $adultVisitorIds = array_filter($form_state->getValues(), function ($v, $k) {
        return ($k === 'visitor_1_id' || (str_contains($k, 'additional_visitor_adult_') && str_ends_with($k, '_id'))) && is_numeric($v);
      }, ARRAY_FILTER_USE_BOTH);

      if (!empty($adultVisitorIds)) {
        $form['#attached']['drupalSettings']['prisonVisitBooking']['adultVisitorIds'] = $adultVisitorIds;
      }
    }

    // Show available time slots in the form.
    if ($page === 'visit_preferred_day_and_time' && !empty($this->bookingReference)) {

      // Reset timeslots when user changes visit_order_number.
      // Some of the logic for setting timeslot preferences is handled
      // via clientside JS.  Flag to clientside when timeslots must be
      // reset.
      $form['#attached']['drupalSettings']['prisonVisitBooking']['resetTimeslots'] = FALSE;

      // Check last_visitor_order_number. If different from the present
      // visitor_order_number then timeslots need reset.
      $last_visitor_order_number = $form_state->get('last_visitor_order_number');
      if (!empty($last_visitor_order_number) && $last_visitor_order_number !== $form_state->getValue('visitor_order_number')) {
        $form['#attached']['drupalSettings']['prisonVisitBooking']['resetTimeslots'] = TRUE;

        $form_values = array_filter($form_state->getValues(), function ($key) {
          return str_contains($key, '_week_');
        }, ARRAY_FILTER_USE_KEY);

        foreach ($form_values as $element_name => $element_value) {
          $form_state->setValue($element_name, []);
          $elements[$element_name]['#default_value'] = [];
          $webform_submission->setElementData($element_name, []);
        }
      }

      // Update last_visitor_order_number.
      $form_state->set('last_visitor_order_number', $form_state->getValue('visitor_order_number'));

      // Get available slots and show only those slots on the form.
      $available_slots = $this->bookingReference['available_slots'];

      // Determine dates.
      $visit_booking_ref_valid_from = $this->bookingReference['date_valid_from'];
      $visit_booking_week_start = $this->bookingReference['date_visit_week_start'];

      if ($visit_booking_ref_valid_from < $visit_booking_week_start) {
        $visit_booking_ref_valid_from = clone $visit_booking_week_start;
      }

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
          $form_slots_week['#access'] = FALSE;

          // Add week commencing date to container titles for each week.
          $form_slots_week_date = clone $visit_booking_ref_valid_from;
          $form_slots_week_date->modify('+' . ($i - 1) . 'weeks');
          $form_slots_week_title = str_replace('[DATE]', $form_slots_week_date->format('j F Y'), $form_slots_week['#title']);

          $form_slots_week['#title'] = $form_slots_week_title;
          $webform_submission_slots_week['#title'] = $form_slots_week_title;
          $webform_submission->getWebform()->setElementProperties('slots_week_' . $i, $webform_submission_slots_week);

          // Loop through form slots for each day.
          $days_of_week = [
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
            'saturday',
            'sunday'
          ];

          for ($j = 1; $j <= 7; $j++) {
            $day = $days_of_week[$j - 1];
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
              if ($available_slot->format('Y-m-d') === $form_slots_day_date->format('Y-m-d')) {
                $form_slots_day['#options'][$available_slot->format(DATE_ATOM)] = $available_slot->format('g.i a');
              }
            }

            // Enable access to form slots if options to show.
            if (!empty($form_slots_day['#options'])) {
              $form_slots_day['#access'] = TRUE;
              $form_slots_week['#access'] = TRUE;
            }

          }
        }
      }
    }

    // Deal with remembered additional visitors.
    $elements['msg_existing_additional_visitors']['#access'] = FALSE;
    $elements['msg_additional_visitors']['#access'] = FALSE;

    $temp_store = $this->tempStoreFactory->get('nidirect_webforms.prison_visit_booking');
    $visitor_data = $temp_store->get('visitor_data');

    if (!empty($visitor_data) && $page === 'additional_visitors') {
      $visitor_data_is_valid = TRUE;

      if ($visitor_data['additional_visitors_remember'] === 'no') {
        $visitor_data_is_valid = FALSE;
      }
      elseif (empty($visitor_data['visitor_1_id']) || empty($visitor_data['visitor_1_dob'])) {
        $visitor_data_is_valid = FALSE;
      }
      elseif ($visitor_data['visitor_1_id'] !== $form_state->getValue('visitor_1_id') || $visitor_data['visitor_1_dob'] !== $form_state->getValue('visitor_1_dob')) {
        $visitor_data_is_valid = FALSE;
      }

      if ($visitor_data_is_valid) {
        $elements['msg_existing_additional_visitors']['#access'] = TRUE;

        // Retrieve existing visitor data.
        foreach ($visitor_data as $key => $value) {
          $form_state->setValue($key, $value);
          $elements[$key]['#default_value'] = $value;
        }
      }
      else {
        $elements['msg_additional_visitors']['#access'] = TRUE;

        // @TODO - do not remove all visitor data?
        // Remove all visitor data.
        $temp_store->delete('visitor_data');

        // Reset form state for additional visitors.
        foreach ($visitor_data as $key => $value) {
          if (str_starts_with($key, 'additional_visitor')) {
            $form_state->setValue($key, $value);
            $elements[$key]['#default_value'] = '';
          }
        }
      }
    }
    else {
      $elements['msg_additional_visitors']['#access'] = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $page = $form_state->get('current_page');

    if ($page === 'booking_reference_prisoner_reference') {
      $this->validateVisitBookingReference($form, $form_state);
    }

    if ($page === 'additional_visitor_adult_details' || $page === 'additional_visitor_child_details') {
      $this->validateUniqueVisitorIds($form, $form_state);
    }

    if ($page === 'visitor_special_requirements') {
      $error_msg = $this->t('Details of special requirements must be plain text.');
      $this->validatePlainText('visitor_special_requirements_details', $error_msg, $form, $form_state);
    }

    if ($page === 'visit_preferred_day_and_time') {
      $this->validateSlotPicked($form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    if ($form_state->isValidationComplete() && $form_state->get('current_page') === 'webform_preview') {

      $temp_store = $this->tempStoreFactory->get('nidirect_webforms.prison_visit_booking');
      $remember_visitors = $form_state->getValue('additional_visitors_remember') === 'yes' ?? FALSE;
      $visitor_data = [];
      $form_values = $form_state->getValues();

      foreach ($form_values as $element_name => $element_value) {
        // Special requirements textarea needs to be encoded for JSON.
        if (str_contains($element_name, 'special_requirements_details')) {
          $special_requirements = Json::encode($element_value);
          $form_state->setValue('special_requirements_json', $special_requirements);
          $webform_submission->setElementData('special_requirements_json', $special_requirements);
        }

        // Capture non-sensitive visitor data in session temp store.
        if ($remember_visitors) {
          if ($element_name === 'visitor_1_id' ||
            $element_name === 'visitor_1_dob' ||
            str_starts_with($element_name, 'additional_visitor') ||
            str_starts_with($element_name, 'visitor_special_requirements')) {
            $visitor_data[$element_name] = $element_value;
          }
        }
      }

      $temp_store->set('visitor_data', $visitor_data);

      // The form accommodates two additional adults and five
      // additional children.

      // Reset secure value elements (bit like hidden elements)
      // keeping track of additional visitors.

      for ($i = 1; $i <= 5; $i++) {
        $form_state->setValue('av' . $i . '_id', '');
        $form_state->setValue('av' . $i . '_dob', NULL);
        $webform_submission->setElementData('av' . $i . '_id', '');
        $webform_submission->setElementData('av' . $i . '_dob', NULL);
      }

      // Get additional visitors.
      $additional_visitors = [];
      $num_adults = $form_values['additional_visitor_adult_number'];
      $num_children = $form_values['additional_visitor_child_number'];

      for ($i = 1; $i <= $num_adults; $i++) {

        $visitor_id = $form_values['additional_visitor_adult_' . $i . '_id'] ?? NULL;
        $visitor_dob = $form_values['additional_visitor_adult_' . $i . '_dob'] ?? NULL;

        if ($visitor_dob) {
          $visitor_dob = new \DateTime(str_replace("/", "-",  $visitor_dob));
        }

        if (!empty($visitor_id) && !empty($visitor_dob)) {
          $additional_visitors[] = [
            'id' => $visitor_id,
            'dob' => $visitor_dob->format('d/m/Y H:i')
          ];
        }
      }

      for ($i = 1; $i <= $num_children; $i++) {

        $visitor_id = $form_values['additional_visitor_child_' . $i . '_id'] ?? NULL;
        $visitor_dob = $form_values['additional_visitor_child_' . $i . '_dob'] ?? NULL;

        if ($visitor_dob) {
          $visitor_dob = new \DateTime(str_replace("/", "-",  $visitor_dob));
        }

        if (!empty($visitor_id) && !empty($visitor_dob)) {
          $additional_visitors[] = [
            'id' => $visitor_id,
            'dob' => $visitor_dob->format('d/m/Y H:i')
          ];
        }
      }

      // Set secure value elements for submission.
      foreach ($additional_visitors as $key => $value) {
        $form_state->setValue('av' . ($key + 1) . '_id', $value['id']);
        $form_state->setValue('av' . ($key + 1) . '_dob', $value['dob']);
        $webform_submission->setElementData('av' . ($key + 1) . '_id', $value['id']);
        $webform_submission->setElementData('av' . ($key + 1) . '_dob', $value['dob']);
      }

      // Set preferred time slots in correct date format for submission.
      for ($i = 1; $i <= 5; $i++) {
        if ($slot_value = $form_state->getValue('slot' . $i . '_datetime')) {
          $slot_date = new \DateTime($slot_value);
          $slot_date_submission = $slot_date->format('d/m/Y H:i');
          $form_state->setValue('slot' . $i . '_datetime_submission', $slot_date_submission);
          $webform_submission->setElementData('slot' . $i . '_datetime_submission', $slot_date_submission);
        }
        else {
          $form_state->setValue('slot' . $i . '_datetime_submission', NULL);
          $webform_submission->setElementData('slot' . $i . '_datetime_submission', NULL);
        }
      }
    }
  }

  /**
   * Validate visit booking reference.
   */
  private function validateVisitBookingReference(array &$form, FormStateInterface $form_state) {

    $elements = WebformFormHelper::flattenElements($form);
    $booking_ref = !empty($form_state->getValue('visitor_order_number')) ? $form_state->getValue('visitor_order_number') : NULL;
    $booking_ref_element = $elements['visitor_order_number'];

    // Basic validation with early return.
    if (empty($booking_ref)) {
      $form_state->setErrorByName('visitor_order_number', $this->t('Visit reference number is required'));
      return;
    }
    elseif (strlen($booking_ref) !== $this->configuration['visit_order_number_length']) {
      $form_state->setErrorByName('visitor_order_number', $this->t('Visit reference number must contain 12 characters'));
      return;
    }

    // More detailed validation ...
    $booking_reference_valid = TRUE;
    $error_message = $this->t('Visit reference number is invalid.');

    // Extract various bits of the booking reference.
    $prison_id = substr($booking_ref, 0, 2);
    $visit_type_id = substr($booking_ref, 2, 1);
    $visit_week = (int) substr($booking_ref, 3, 2);
    $visit_year = (int) substr($booking_ref, 5, 2);
    $visit_year_full = (int) DrupalDateTime::createFromFormat('y', $visit_year)->format('Y');
    $visit_sequence = (int) substr($booking_ref, 8);

    // Valid prison id.
    if (array_key_exists($prison_id, $this->configuration['prisons'])) {
      $this->bookingReference['prison_id'] = $prison_id;
      $this->bookingReference['prison_name'] = $this->configuration['prisons'][$prison_id];
      $form_state->setValue('prison_name', $this->bookingReference['prison_name']);
    }
    else {
      $booking_reference_valid = FALSE;
    }

    // Valid visit type.
    if (array_key_exists($visit_type_id, $this->configuration['visit_type'])) {
      $this->bookingReference['visit_type_id'] = $visit_type_id;
      $this->bookingReference['visit_type'] = $this->configuration['visit_type'][$visit_type_id];

      // The "E" visit type (enhanced) is synonymous with the 'F' type
      // and so is face-to-face and has same time slots.
      if ($visit_type_id === 'E') {
        $this->bookingReference['visit_type'] = $this->configuration['visit_type']['F'];
      }

      $form_state->setValue('visit_type', $this->bookingReference['visit_type']);
    }
    else {
      $booking_reference_valid = FALSE;
    }

    // Valid sequence number.
    if ($visit_sequence > 0 && $visit_sequence < 9999) {
      $this->bookingReference['visit_sequence'] = $visit_sequence;
      $prisoner_categories = $this->configuration['visit_order_number_categories'];

      foreach ($prisoner_categories as $category_key => $category_value) {
        foreach ($category_value as $subcategory_key => $subcategory_value) {
          if ($visit_sequence >= $subcategory_value[0] && $visit_sequence <= $subcategory_value[1]) {
            $this->bookingReference['prisoner_category'] = $category_key;
            $this->bookingReference['prisoner_subcategory'] = $subcategory_key + 1;
          }
        }
      }
    }
    else {
      $booking_reference_valid = FALSE;
    }

    // Valid week and year.
    // Only proceed to this bit if things so far are valid ...
    if ($booking_reference_valid) {
      // Extract some bits from config.
      $booking_ref_validity_period_days = $this->configuration['booking_reference_validity_period_days'][$visit_type_id];
      $this->bookingReference['validity_period_days'] = $booking_ref_validity_period_days;

      // Advance notice required for a booking.
      // The advance notice required is dependent on the visit type.
      $booking_advance_notice = $this->configuration['visit_advance_notice'][$visit_type_id];

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

      // Date from now when slot cannot be booked.
      $visit_latest_date = clone $now;
      $visit_latest_date->modify('+' . $booking_ref_max_advance_issue);

      // Date for first day of this week (always a Monday).
      $now_week_commence = clone $now;
      $now_week_commence->setISODate($now->format('Y'), $now->format('W'), 1);
      $now_week_commence->setTime(0, 0, 0);

      // Valid from date for the booking reference.
      // The week number and year in the booking reference is an
      // ISO 8601 week date.
      $booking_ref_valid_from = clone $now;
      $booking_ref_valid_from->setISODate($visit_year_full, $visit_week, 1);
      $booking_ref_valid_from->setTime(0, 0, 0);

      // Valid to date for booking reference.
      $booking_ref_valid_to = clone $booking_ref_valid_from;
      $booking_ref_valid_to->modify('+' . $booking_ref_validity_period_days . ' days');

      // A booking reference can be issued no more than
      // four weeks (for example) in advance of the valid from date.
      $booking_ref_max_advanced_issue_date = clone $booking_ref_valid_from;
      $booking_ref_max_advanced_issue_date->modify('-' . $booking_ref_max_advance_issue);

      // Determine the latest date for using a booking reference.
      // (booking reference valid to date minus the advance notice).
      $visit_latest_booking_date = clone $booking_ref_valid_to;
      $visit_latest_booking_date->modify('-' . $booking_advance_notice);

      // Check date and year portions.
      if ($now > $booking_ref_valid_to) {
        $booking_reference_valid = FALSE;
        $error_message = $this->t('Visit reference number has expired.');
      }
      elseif ($now > $visit_latest_booking_date) {
        $booking_reference_valid = FALSE;
        $error_message = $this->t('Visit reference number has expired.');
      }
      elseif ($now < $booking_ref_max_advanced_issue_date) {
        $booking_reference_valid = FALSE;
        $error_message = $this->t('Visit reference number is not recognised.');
      }
      else {
        // Booking reference week and year is good.
        // Keep track of all the key dates.
        $this->bookingReference['date'] = $now;
        $this->bookingReference['date_valid_from'] = $booking_ref_valid_from;
        $this->bookingReference['date_valid_to'] = $booking_ref_valid_to;
        $this->bookingReference['date_visit_earliest'] = $visit_earliest_date;
        $this->bookingReference['date_visit_latest'] = $visit_latest_date;
        $this->bookingReference['date_advance_booking_earliest'] = $booking_ref_max_advanced_issue_date;
        $this->bookingReference['date_booking_latest'] = $visit_latest_booking_date;

        if ($now < $booking_ref_valid_from) {
          // Determine whether week date for the booking is for a future week or
          // current week.
          $this->bookingReference['date_visit_week_start'] = $booking_ref_valid_from;
        }
        else {
          $this->bookingReference['date_visit_week_start'] = $now_week_commence;
        }

        // Finally check for slots available.
        $available_slots = $this->getAvailableSlots();
        if ($available_slots && !empty($available_slots['error'])) {
          $form_state->setError($form, 'An error has occurred. Booking cannot proceed at this time. Try again later.');
        }
        elseif (!$available_slots) {
          $booking_reference_valid = FALSE;
          $error_message = 'There are no remaining time slots for visit reference number ';
          $error_message .= $this->t(
            '<a href="@visit-reference-number-url">@visit-reference-number</a>',
            [
              '@visit-reference-number-url' => '#' . $booking_ref_element['#id'],
              '@visit-reference-number' => $booking_ref_element['#value'],
            ]
          );
        }
        else {
          $this->bookingReference['available_slots'] = $available_slots;
        }
      }
    }

    if ($booking_reference_valid !== TRUE) {
      $form_state->setErrorByName('visitor_order_number', $error_message);
    }
    else {
      $form_state->setValue('visitor_order_number', $booking_ref);
      $form_state->set('booking_reference_processed', $this->bookingReference);
    }

  }

  /**
   * Validate visitor IDs and DOBs.
   */
  private function validateUniqueVisitorIds(array &$form, FormStateInterface $form_state) {

    if ($form_state->get('current_page') === 'additional_visitor_adult_details') {
      // Get all additional adult visitor ids.
      $visitorIds = array_filter($form_state->getValues(), function ($v, $k) {
        return ($k === 'visitor_1_id' || (str_contains($k, 'additional_visitor_adult_') && str_ends_with($k, '_id'))) && is_numeric($v);
      }, ARRAY_FILTER_USE_BOTH);
    }
    else {
      // Get all additional visitor IDs.
      $visitorIds = array_filter($form_state->getValues(), function ($v, $k) {
        return str_contains($k, 'visitor_') && str_ends_with($k, '_id') && is_numeric($v);
      }, ARRAY_FILTER_USE_BOTH);
    }

    $visitorIdCounts = array_count_values($visitorIds);

    foreach ($visitorIds as $key => $value) {
      if (!empty($value) && isset($visitorIdCounts[$value]) && $visitorIdCounts[$value] > 1) {
        $form_state->setErrorByName($key, $this->t('Visitor ID has already been entered.'));
      }
    }
  }

  /**
   * Validate textareas contain plain text (no html).
   */
  private function validatePlainText(string $element_name, string $error_msg, array &$form, FormStateInterface $form_state) {
    $elements = WebformFormHelper::flattenElements($form);
    $element = $elements[$element_name] ?? NULL;

    if (!empty($element)) {

      if (empty($error_msg)) {
        $error_msg = $this->t(
          '@title must contain plain text only.',
          ['@title' => $element['#title']]
        );
      }

      if ($element['#value'] != strip_tags($element['#value'])) {
        $form_state->setErrorByName($element['#name'], $error_msg);
      }
    }
  }

  /**
   * Validate visitor one DOB.
   */
  private function validateSlotPicked(array &$form, FormStateInterface $form_state) {

    if ($form_state->get('current_page') !== 'visit_preferred_day_and_time') {
      return;
    }

    $slotPicked = FALSE;

    $form_values = array_filter($form_state->getValues(), function ($key) {
      return str_contains($key, '_week_');
    }, ARRAY_FILTER_USE_KEY);

    foreach ($form_values as $element_name => $element_value) {
      if (is_array($element_value) && !empty($element_value)) {
        $slotPicked = TRUE;
        break;
      }
    }

    if ($slotPicked === FALSE) {
      $form_state->setErrorByName('slots_week_1', $this->t('You did not choose a time slot for your visit.'));
    }
  }

  /**
   * Get available slots for a given prison, visit type and prisoner category.
   */
  private function getAvailableSlots() {

    $available_slots = [];

    // Early return when there is no valid booking reference.
    if (empty($this->bookingReference)) {
      return ['error' => TRUE];
    }

    // Return an error if there is no slot data.
    $data = $this->getData();
    if (!$data || empty($data['SLOTS'])) {
      return ['error' => TRUE];
    }

    // Get slots based on visit type. Return an error if none exist.
    $visit_type = $this->bookingReference['visit_type'];

    if ($visit_type === 'face-to-face' && array_key_exists('FACE_TO_FACE', $data['SLOTS'])) {
      $slots = $data['SLOTS']['FACE_TO_FACE'];
    }
    elseif ($visit_type === 'virtual' && array_key_exists('VIRTUAL', $data['SLOTS'])) {
      $slots = $data['SLOTS']['VIRTUAL'];
    }
    else {
      return ['error' => TRUE];
    }

    // Get available slots for specific prison and prisoner category.
    $prison_id = $this->bookingReference['prison_id'];
    $prisoner_category = $this->bookingReference['prisoner_category'];
    $prisoner_subcategory = $this->bookingReference['prisoner_subcategory'];

    // Build a key to get slots for specific prison and prisoner category.
    $visit_slots_key = $prison_id;

    if ($prisoner_category === 'separates' && array_key_exists($visit_slots_key . '_AFILL_' . $prisoner_subcategory, $slots)) {
      $visit_slots_key .= '_AFILL_' . $prisoner_subcategory;
    }
    elseif ($prisoner_category === 'separates' && array_key_exists($visit_slots_key . '_AFILL', $slots)) {
      $visit_slots_key .= '_AFILL';
    }
    elseif ($prisoner_category === 'integrated' && array_key_exists($visit_slots_key . '_INT', $slots)) {
      $visit_slots_key .= '_INT';
    }

    // Slots from cached json have dates dd/mm/yyyy format. Alter to make it dd-mm-yyyy.
    foreach ($slots[$visit_slots_key] as $slot) {
      $slot_parsed = str_replace('/', '-', $slot);
      $slot_datetime = new \DateTime($slot_parsed);

      $available_slots[] = $slot_datetime;
    }

    // Discard slots that are not bookable. A slot is bookable if it
    // falls within certain dates. Weekend slots cannot be booked where
    // the visit type is enhanced (booking reference visit type
    // identifier is 'E').

    $visit_type_id = $this->bookingReference['visit_type_id'];

    $date = $this->bookingReference['date'];
    $date_visit_earliest = $this->bookingReference['date_visit_earliest'];
    $date_valid_to = $this->bookingReference['date_valid_to'];
    $date_visit_week_start = $this->bookingReference['date_visit_week_start'];

    foreach ($available_slots as $slot_key => $slot) {
      // Determine if this slot is bookable.
      $slot_is_bookable = TRUE;

      if ($slot < $date) {
        $slot_is_bookable = FALSE;
      }
      elseif ($slot < $date_visit_earliest) {
        $slot_is_bookable = FALSE;
      }
      elseif ($slot < $date_visit_week_start) {
        $slot_is_bookable = FALSE;
      }
      elseif ($slot > $date_valid_to) {
        $slot_is_bookable = FALSE;
      }
      // Enhanced visits cannot be booked on Saturday or Sunday.
      elseif ($visit_type_id === 'E' && ($slot->format('D') === 'Sat' || $slot->format('D') === 'Sun')) {
        $slot_is_bookable = FALSE;
      }

      if ($slot_is_bookable === FALSE) {
        unset($available_slots[$slot_key]);
      }
    }

    $this->bookingReference['available_slots'] = $available_slots;

    return $available_slots;
  }

  /**
   * Get data stored in cache or from file.
   */
  private function getData() {

    $data = [];

    // Face-to-face slots are retrieved from external data in cache
    // (see PrisonVisitBookingJsonApiController.php). If there is no
    // cached data, fallback to using slots from file.

    $cached_data = \Drupal::cache()->get('prison_visit_slots_data');

    if (!empty($cached_data)) {

      // There is cached data.
      $data = $cached_data->data;
      $cached_data_timestamp = (int) $cached_data->created;

      // Check when created.
      $now = new \DateTime('now');
      $data_last_updated = new \DateTime('now');
      $data_last_updated->setTimestamp($cached_data_timestamp);

      // Log a warning when cached data more than 24 hours old.
      if ($now->diff($data_last_updated)->h >= 24) {
        $this->getLogger('prison_visits')->warning('prison_visit_slots_data cache data has not been updated in last 24 hours.');
      }

    }
    else {

      // No data in cache, so try file instead. Every time the
      // external service posts data to prison visits api controller,
      // data is stored in cache and written to file.

      $file_uri = 'private://nidirect_webforms/prison_visit_slots_data.json';
      $file_contents = file_exists($file_uri) ? file_get_contents($file_uri) : NULL;

      if (!empty($file_contents)) {
        $data = json_decode($file_contents, TRUE);
        $this->getLogger('prison_visits')->info('prison_visit_slots_data cache is empty. Using @file instead.', ['@file' => $file_uri]);
      }
      else {
        $this->getLogger('prison_visits')->error('prison_visit_slots_data not found in cache or file @file.', ['@file' => $file_uri]);
      }
    }

    return $data;
  }

}

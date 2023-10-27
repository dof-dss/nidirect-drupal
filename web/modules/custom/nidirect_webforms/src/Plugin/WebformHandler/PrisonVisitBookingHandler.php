<?php

namespace Drupal\nidirect_webforms\Plugin\WebformHandler;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
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
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

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
   * @var ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var Request
   */
  protected $request;

  /**
   * @var RequestStack
   */
  protected $requestStack;

  /**
  * @var ClientInterface
  */
  protected $httpClient;

  /**
   * @var PrivateTempStoreFactory
   */
  private $tempStoreFactory;

  /**
   * Array for storing various values extrapolated
   * from the visit reference number supplied by the webform.
   *
   * @var array
   */
  protected $booking_reference = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
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
    $elements = WebformFormHelper::flattenElements($form);
    $this->booking_reference = $form_state->get('booking_reference_processed');
    $form['#attached']['drupalSettings']['prisonVisitBooking'] = $this->configuration;
    $form['#attached']['drupalSettings']['prisonVisitBooking']['booking_ref'] = $this->booking_reference;

    $page = $form_state->get('current_page');

    // Show available time slots in the form.
    if ($page === 'visit_preferred_day_and_time' && !empty($this->booking_reference))
    {
      $available_slots = $this->booking_reference['available_slots'];

      // Determine dates
      $visit_booking_ref_valid_from = $this->booking_reference['date_valid_from'];
      $visit_booking_week_start = $this->booking_reference['date_visit_week_start'];

      if ($visit_booking_ref_valid_from < $visit_booking_week_start)
      {
        $visit_booking_ref_valid_from = $visit_booking_week_start;
      }

      if (!empty($available_slots))
      {
        // Alter form slots to correspond with available slots.
        for ($i = 4; $i >= 1; $i--)
        {
          // Form slots for each week.
          $form_slots_week = &$form['elements']['visit_preferred_day_and_time']['slots_week_' . $i];

          $webform_submission_slots_week = $webform_submission->getWebform()->getElement('slots_week_' . $i, TRUE);

          if ($form_slots_week['#access'] = FALSE)
          {
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
          $days_of_week = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

          for ($j = 1; $j <= 7; $j++)
          {
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
            foreach ($available_slots as $available_slot)
            {
              if ($available_slot->format('Y-m-d') === $form_slots_day_date->format('Y-m-d')) {
                $form_slots_day['#options'][$available_slot->format(DATE_ATOM)] = $available_slot->format('g.i a');
              }
            }

            // Enable access to form slots if options to show.
            if (!empty($form_slots_day['#options']))
            {
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

    $tempstore = $this->tempStoreFactory->get('nidirect_webforms.prison_visit_booking');
    $visitor_data = $tempstore->get('visitor_data');

    if (!empty($visitor_data) && $page === 'additional_visitors')
    {
      $visitor_data_is_valid = TRUE;

      if (empty($visitor_data['visitor_1_id']) || $visitor_data['visitor_1_id'] !== $form_state->getValue('visitor_1_id'))
      {
        $visitor_data_is_valid = FALSE;
      }
      elseif ($visitor_data['additional_visitors_remember'] === 'no')
      {
        $visitor_data_is_valid = FALSE;
      }

      if ($visitor_data_is_valid) {
        $elements['msg_existing_additional_visitors']['#access'] = TRUE;

        // Retrieve existing visitor data.
        foreach ($visitor_data as $key => $value)
        {
          $form_state->setValue($key, $value);
          $elements[$key]['#default_value'] = $value;
        }
      }
      else
      {
        $elements['msg_additional_visitors']['#access'] = TRUE;

        // Remove all visitor data.
        $tempstore->delete('visitor_data');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
  {
    $this->validateVisitBookingReference($form, $form_state);
    $this->validateVisitorOneDateOfBirth($form, $form_state);
    $this->validateSlotPicked($form, $form_state);
  }

  /**
   * Validate visit booking reference.
   */
  private function validateVisitBookingReference(array &$form, FormStateInterface $form_state)
  {
    $elements = WebformFormHelper::flattenElements($form);
    $booking_ref = !empty($form_state->getValue('visitor_order_number')) ? $form_state->getValue('visitor_order_number') : NULL;
    $booking_ref_element = $elements['visitor_order_number'];

    // Basic validation with early return.
    if (empty($booking_ref))
    {
      $form_state->setErrorByName('visitor_order_number', $this->t('Visit reference number is required'));
      return;
    }
    elseif (strlen($booking_ref) !== $this->configuration['visit_order_number_length'])
    {
      $form_state->setErrorByName('visitor_order_number', $this->t('Visit reference number must contain 12 characters'));
      return;
    }

    // More detailed validation ...
    $booking_reference_valid = TRUE;
    $error_message = $this->t('Visit reference number is invalid.');

    // Extract various bits of the booking reference.
    $prison_id = substr($booking_ref, 0, 2);
    $visit_type_id = substr($booking_ref, 2, 1);
    $visit_week = (int)substr($booking_ref, 3, 2);
    $visit_year = (int)substr($booking_ref, 5, 2);
    $visit_year_full = (int)DrupalDateTime::createFromFormat('y', $visit_year)->format('Y');
    $visit_sequence = (int)substr($booking_ref, 8);

    // Valid prison id.
    if (array_key_exists($prison_id, $this->configuration['prisons']))
    {
      $this->booking_reference['prison_id'] = $prison_id;
      $this->booking_reference['prison_name'] = $this->configuration['prisons'][$prison_id];
      $form_state->setValue('prison_name', $this->booking_reference['prison_name']);
    }
    else
    {
      $booking_reference_valid = FALSE;
    }

    // Valid visit type.
    if (array_key_exists($visit_type_id, $this->configuration['visit_type']))
    {
      $this->booking_reference['visit_type_id'] = $visit_type_id;
      $this->booking_reference['visit_type'] = $this->configuration['visit_type'][$visit_type_id];

      // The "E" visit type (enhanced) is synonymous with the 'F' type
      // and so is face-to-face and has same time slots.
      if ($visit_type_id === 'E')
      {
        $this->booking_reference['visit_type'] = $this->configuration['visit_type']['F'];
      }

      $form_state->setValue('visit_type', $this->booking_reference['visit_type']);
    }
    else
    {
      $booking_reference_valid = FALSE;
    }

    // Valid visit type and prison id combo.
    // Only Maghaberry supports enhanced type (E).
    if ($visit_type_id === 'E' && $prison_id !== 'MY') {
      $booking_reference_valid = FALSE;
    }

    // Valid sequence number.
    if ($visit_sequence > 0 && $visit_sequence < 9999)
    {
      $this->booking_reference['visit_sequence'] = $visit_sequence;
      $prisoner_categories = $this->configuration['visit_order_number_categories'];

      foreach ($prisoner_categories as $category_key => $category_value)
      {
        foreach ($category_value as $subcategory_key => $subcategory_value)
        {
          if ($visit_sequence >= $subcategory_value[0] && $visit_sequence <= $subcategory_value[1])
          {
            $this->booking_reference['prisoner_category'] = $category_key;
            $this->booking_reference['prisoner_subcategory'] = $subcategory_key + 1;
          }
        }
      }
    }
    else
    {
      $booking_reference_valid = FALSE;
    }

    // Valid week and year.
    // Only proceed to this bit if things so far are valid ...
    if ($booking_reference_valid)
    {
      // Extract some bits from config.
      $booking_ref_validity_period_days = $this->configuration['booking_reference_validity_period_days'][$visit_type_id];
      $this->booking_reference['validity_period_days'] = $booking_ref_validity_period_days;

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
      if ($now > $booking_ref_valid_to)
      {
        $booking_reference_valid = FALSE;
        $error_message = $this->t('Visit reference number has expired.');
      }
      elseif ($now > $visit_latest_booking_date)
      {
        $booking_reference_valid = FALSE;
        $error_message = $this->t('Visit reference number has expired.');
      }
      elseif ($now < $booking_ref_max_advanced_issue_date)
      {
        $booking_reference_valid = FALSE;
        $error_message = $this->t('Visit reference number is not recognised.');
      }
      else
      // Booking reference
      {
        // Booking reference week and year is good.
        // Keep track of all the key dates.
        $this->booking_reference['date'] = $now;
        $this->booking_reference['date_valid_from'] = $booking_ref_valid_from;
        $this->booking_reference['date_valid_to'] = $booking_ref_valid_to;
        $this->booking_reference['date_visit_earliest'] = $visit_earliest_date;
        $this->booking_reference['date_visit_latest'] = $visit_latest_date;
        $this->booking_reference['date_advance_booking_earliest'] = $booking_ref_max_advanced_issue_date;
        $this->booking_reference['date_booking_latest'] = $visit_latest_booking_date;

        if ($now < $booking_ref_valid_from)
        {
          // Determine whether week date for the booking is for a future week or
          // current week.
          $this->booking_reference['date_visit_week_start'] = $booking_ref_valid_from;
        } else {
          $this->booking_reference['date_visit_week_start'] = $now_week_commence;
        }

        // Finally check for slots available.
        $available_slots = $this->getAvailableSlots();
        if (empty($available_slots)) {
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
        else
        {
          $this->booking_reference['available_slots'] = $available_slots;
        }
      }
    }

    if ($booking_reference_valid !== TRUE)
    {
      $form_state->setErrorByName('visitor_order_number', $error_message);
    }
    else {
      $form_state->setValue('visitor_order_number', $booking_ref);
      $form_state->set('booking_reference_processed', $this->booking_reference);
    }

  }

  /**
   * Get available slots for a given prison, visit type and prisoner category.
   */
  private function getAvailableSlots()
  {
    // Need a valid booking reference to get available slots.
    if (empty($this->booking_reference)) {
      return [];
    }

    $available_slots = [];

    $prison_id = $this->booking_reference['prison_id'];
    $visit_type = $this->booking_reference['visit_type'];
    $visit_type_id = $this->booking_reference['visit_type_id'];
    $prisoner_category = $this->booking_reference['prisoner_category'];
    $prisoner_subcategory = $this->booking_reference['prisoner_subcategory'];

    $date = $this->booking_reference['date'];
    $date_visit_earliest = $this->booking_reference['date_visit_earliest'];
    $date_visit_latest = $this->booking_reference['date_visit_latest'];
    $date_valid_from = $this->booking_reference['date_valid_from'];
    $date_valid_to = $this->booking_reference['date_valid_to'];
    $date_visit_week_start = $this->booking_reference['date_visit_week_start'];

    if ($date_valid_from < $date_visit_week_start)
    {
      $date_valid_from = $date_visit_week_start;
    }

    // Face-to-face visit slots are retrieved from external JSON file.
    if ($visit_type === 'face-to-face') {

      // Get data as associative array from the external webservice.
      $uri = $this->configuration['visit_slots_uri'];

      if ($json_data = $this->fetchExternalData($uri)) {
        // Cache in case external fetch fails in future.
        \Drupal::cache()->set('prison_visit_json_data', $json_data);
      }
      else
      {
        // Get data from cache.
        $cache = \Drupal::cache()->get('prison_visit_json_data');
        $json_data = $cache->data;
      }

      if (!empty($json_data)) {
        $visit_slots = json_decode($json_data, TRUE);
      }
      else
      {
        $visit_slots = $this->configuration['visit_slots']['face-to-face'];
      }

      // Build a key to get the right slots depending on prison and prisoner category.
      $visit_slots_key = $prison_id;

      if ($prison_id === 'MY' && $prisoner_category === 'integrated') {
        $visit_slots_key .= '_INT';
      }

      if ($prisoner_category === 'separates' && ($prison_id === 'MY' || $prison_id === 'HW')) {
        $visit_slots_key .= '_AFILL';

        if ($prison_id === 'MY') {
          $visit_slots_key .= '_' . $prisoner_subcategory;
        }
      }

      $visit_slots = $visit_slots[$visit_slots_key];

      foreach ($visit_slots as $slot) {
        // Slot date is in dd/mm/yyyy format. Alter to make it dd-mm-yyyy.
        $slot_parsed = str_replace('/', '-', $slot);
        $slot_datetime = new \DateTime($slot_parsed);

        $available_slots[] = $slot_datetime;
      }

    }
    // Virtual slots are retrieved from config.
    elseif ($visit_type === 'virtual')
    {
      $visit_slots = $this->configuration['visit_slots']['virtual'][$prison_id];

      foreach ($visit_slots as $slot)
      {
        $slot_p = date_parse($slot);
        $slot_date_adjusted = new \DateTime($slot);
        $slot_date_adjusted->setISODate($date_valid_from->format('Y'), $date_valid_from->format('W'), $slot_p['day']);
        $slot_date_adjusted->setTime($slot_p['hour'], $slot_p['minute'], $slot_p['second']);
        $available_slots[] = $slot_date_adjusted;
      }
    }

    // Discard slots that are not bookable.
    foreach ($available_slots as $slot_key => $slot)
    {
      // Determine if this slot is bookable.
      $slot_is_bookable = TRUE;

      if ($slot < $date)
      {
        $slot_is_bookable = FALSE;
      }
      elseif ($slot < $date_visit_earliest )
      {
        $slot_is_bookable = FALSE;
      }
      elseif ($slot < $date_visit_week_start)
      {
        $slot_is_bookable = FALSE;
      }
      elseif ($slot > $date_valid_to)
      {
        $slot_is_bookable = FALSE;
      }
      // Enhanced visits cannot be booked on Saturday or Sunday.
      elseif ($visit_type_id === 'E' && ($slot->format('D') === 'Sat' || $slot->format('D') === 'Sun'))
      {
        $slot_is_bookable = FALSE;
      }

      if ($slot_is_bookable === FALSE)
      {
        unset($available_slots[$slot_key]);
      }
    }

    $this->booking_reference['available_slots'] = $available_slots;

    return $available_slots;
  }


  /**
   * Get external data using http client.
   */
  private function fetchExternalData($uri) {
    $method = 'GET';
    try {
      $response = $this->httpClient->request($method, $uri, ['connect_timeout' => 6, 'timeout' => 10]);
      $code = $response->getStatusCode();
      if ($code == 200) {
        return $response->getBody()->getContents();
      }
    }
    catch(RequestException $e) {
      watchdog_exception('prison_visits', $e);
    }
  }

  /**
   * Validate visitor one DOB.
   */
  private function validateVisitorOneDateOfBirth(array &$form, FormStateInterface $form_state)
  {
    $visitor_1_dob = !empty($form_state->getValue('visitor_1_dob')) ? $form_state->getValue('visitor_1_dob') : NULL;

    if (!empty($visitor_1_dob))
    {
      $today = new \DateTime('now', new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
      $today->setTime(0, 0, 0);

      $birthday = new \DateTime($visitor_1_dob);
      if ($today->diff($birthday)->y < 18)
      {
        $form_state->setErrorByName('visitor_1_dob', $this->t('You must be at least 18 years old to book a prison visit.'));
      }
    }
  }

  /**
   * Validate visitor one DOB.
   */
  private function validateSlotPicked(array &$form, FormStateInterface $form_state)
  {
    if ($form_state->get('current_page') !== 'visit_preferred_day_and_time') {
      return;
    }

    $slotPicked = FALSE;

    $form_values = array_filter($form_state->getValues(), function($key) {
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
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission)
  {
    if ($form_state->isValidationComplete() && $form_state->get('current_page') === 'webform_preview')
    {

      $tempstore = $this->tempStoreFactory->get('nidirect_webforms.prison_visit_booking');
      $remember_visitors = $form_state->getValue('additional_visitors_remember') === 'yes' ?? FALSE;
      $visitor_data = [];

      // Reset hidden elements keeping track of preferred time slots.
      for ($i = 1; $i <= 5; $i++) {
        $form_state->setValue('slot' . $i . '_datetime', '');
        $webform_submission->setElementData('slot' . $i . '_datetime', '');
        $form_state->setValue('slot' . $i . '_pretty_datetime', NULL);
        $webform_submission->setElementData('slot' . $i . '_pretty_datetime', NULL);
      }

      $form_values = $form_state->getValues();
      $count = 0;

      foreach ($form_values as $element_name => $element_value) {

        // Set hidden elements to keep track of selected time slots.
        // Time slots have keys like 'monday_week_1', 'tuesday_week_4', etc.

        if (str_contains($element_name, '_week_') && is_array($element_value)) {

          // Add up to 5 selected time slots to our hidden elements.
          foreach ($element_value as $slot) {
            $count++;
            if ($count <= 5) {
              $slot_date = new \DateTime($slot);
              $form_state->setValue('slot' . $count . '_datetime', $slot_date->format('d/m/Y H:i'));
              $webform_submission->setElementData('slot' . $count . '_datetime', $slot_date->format('d/m/Y H:i'));

              // "Pretty" slot dates and times for preview
              if (!empty($slot)) {
                $form_state->setValue('slot' . $count . '_pretty_datetime', $slot_date->format('l, j F Y \a\t g.i a'));
                $webform_submission->setElementData('slot' . $count . '_pretty_datetime', $slot_date->format('l, j F Y \a\t g.i a'));
              } else {
                $form_state->setValue('slot' . $count . '_pretty_datetime', '');
                $webform_submission->setElementData('slot' . $count . '_pretty_datetime', '');
              }
            }
          }
        }

        // Special requirements textarea needs to be encoded for JSON.
        if (str_contains($element_name, 'special_requirements_details')) {
          $special_requirements = Json::encode($element_value);
          $form_state->setValue('special_requirements_json', $special_requirements);
          $webform_submission->setElementData('special_requirements_json', $special_requirements);
        }

        // Capture non-sensitive visitor data in session temp store.
        if ($remember_visitors) {
          if ($element_name === 'visitor_1_id' ||
              str_starts_with($element_name, 'additional_visitor') ||
              str_starts_with($element_name, 'visitor_special_requirements'))
          {
            $visitor_data[$element_name] = $element_value;
          }
        }
      }

      $tempstore->set('visitor_data', $visitor_data);

      // Set additional visitors for submission.
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

        $av_id = $form_values['additional_visitor_adult_' . $i .'_id'];
        $av_dob = new \DateTime($form_values['additional_visitor_adult_' . $i .'_dob']);

        if (!empty($av_id) && !empty($av_dob)) {
          $additional_visitors[] = [
            'id' => $av_id,
            'dob' => $av_dob->format('d/m/Y H:i')
          ];
        }
      }

      for ($i = 1; $i <= $num_children; $i++) {

        $av_id = $form_values['additional_visitor_child_' . $i .'_id'];
        $av_dob = new \DateTime($form_values['additional_visitor_child_' . $i .'_dob']);

        if (!empty($av_id) && !empty($av_dob)) {
          $additional_visitors[] = [
            'id' => $av_id,
            'dob' => $av_dob->format('d/m/Y H:i')
          ];
        }
      }

      // Set secure value elements for submission.
      foreach ($additional_visitors as $key => $value) {
        $form_state->setValue('av' . $key + 1 . '_id', $value['id']);
        $form_state->setValue('av' . $key + 1 . '_dob', $value['dob']);
        $webform_submission->setElementData('av' . $key + 1 . '_id', $value['id']);
        $webform_submission->setElementData('av' . $key + 1 . '_dob', $value['dob']);
      }
    }
  }

}

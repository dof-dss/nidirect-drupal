<?php

namespace Drupal\nidirect_prisons\Plugin\WebformHandler;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\webform\Entity\Webform;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\Utility\WebformFormHelper;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionForm;
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
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $flood;

  /**
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  private $tempStoreFactory;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * Array for storing various values extrapolated
   * from the visit reference number supplied by the webform.
   *
   * @var array
   */
  protected $bookingReference = [];

  /**
   * @var array
   */
  protected array $form = [];

  /**
   * @var \Drupal\Core\Form\FormStateInterface
   */
  protected FormStateInterface $formState;

  /**
   * @var \Drupal\webform\WebformSubmissionInterface
   */
  protected $webformSubmission;

  /**
   * @var array
   */
  protected array $elements = [];

  /**
   * Visit reference number validation statuses.
   */
  const VISIT_ORDER_REF_MISSING = 1;

  const VISIT_ORDER_REF_INVALID = 2;

  const VISIT_ORDER_REF_EXPIRED = 3;

  const VISIT_ORDER_REF_NOTICE_EXCEEDED = 4;

  const VISIT_ORDER_REF_AMENDMENT_NOTICE_EXCEEDED = 5;

  const VISIT_ORDER_REF_AMENDMENT_EXPIRED = 6;

  const VISIT_ORDER_REF_NO_SLOTS = 7;

  const VISIT_ORDER_REF_NO_DATA = 8;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->configFactory = $container->get('config.factory');
    $instance->request = $container->get('request_stack')->getCurrentRequest();
    $instance->httpClient = $container->get('http_client');
    $instance->tempStoreFactory = $container->get('tempstore.private');
    $instance->database = $container->get('database');
    $instance->time = $container->get('datetime.time');
    $instance->flood = $container->get('flood');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return $this->configFactory->get('nidirect_prisons.prison_visit_booking.settings')->getRawData() ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {

    // Store the amendment link unique identifier for this
    // submission so that it cannot be used again.
    $amend_booking_options = $webform_submission->getData()['amend_booking_options'] ?? NULL;
    $is_amended = $amend_booking_options === 'cancel' || $amend_booking_options === 'change' ?? FALSE;

    if ($is_amended && $booking_link_id = $webform_submission->getData()['bkg_link_uniqueid']) {

      // Store the unique identifier with the current timestamp.
      $this->database->insert('prison_visit_booking_link_ids')
        ->fields([
          'unique_identifier' => $booking_link_id,
          'created' => $this->time->getRequestTime(),
        ])
        ->execute();
    }

    // For security reasons, the submission is never stored.
    $webform_submission->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function prepareForm(WebformSubmissionInterface $webform_submission, $operation, FormStateInterface $form_state) {

    // Prepare form if a booking is being amended.
    $webform = $webform_submission->getWebform();

    // Early return if no booking data in the request.
    if ($this->request->query->has('booking') === FALSE) {
      $webform->setElementProperties('amend_booking_page', ['#access' => FALSE]);
      return;
    }

    $booking_data = $this->getRequestBookingData();

    if ($booking_data) {

      // Set cache contexts.
      $webform->addCacheableDependency([
        '#cache' => [
          'contexts' => ['url.query_args:booking'],
        ],
      ]);

      // Has the LINK_UNIQUEID in the booking data been used before?
      $link_unique_id = $booking_data['LINK_UNIQUEID'];

      if ($link_unique_id) {
        $query = $this->database->select('prison_visit_booking_link_ids', 'pvblink_ids')
          ->fields('pvblink_ids', ['unique_identifier'])
          ->condition('unique_identifier', $link_unique_id);

        $result = $query->execute()->fetchField();

        if ($result) {
          // Unique identifier exists, booking data already amended.
          // Flag this in the booking data.
          $booking_data['error'] = TRUE;
          $booking_data['error_msg'] = t('Booking link has already been be used and cannot be reused to amend your booking.');
        }
      }

      // Convert booking data slotdatetime to a more useable format.
      $booked_slotdatetime = new \DateTime(str_replace('/', '-', $booking_data['SLOTDATETIME']));
      $booking_data['SLOTDATETIME'] = $booked_slotdatetime->format(DATE_ATOM);

      // Store booking data in form_state.
      $form_state->set('amend_booking_data', $booking_data);

      // Disable the booking_reference_prisoner_reference wizard page
      // and set the current page to amend_booking_page.
      $webform->setElementProperties('booking_reference_prisoner_reference', ['#access' => FALSE]);
      $webform_submission->setCurrentPage('amend_booking_page');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $this->form = &$form;
    $this->formState = $form_state;
    $this->webformSubmission = $webform_submission;
    $this->elements = WebformFormHelper::flattenElements($form);

    $page = $form_state->get('current_page');
    $page_trigger = $form_state->getTriggeringElement()['#value'] ?? NULL;
    $page_trigger_is_next = $page_trigger === 'Next';
    $page_trigger_is_previous = $page_trigger === 'Previous' || $page_trigger === 'Back';

    $elements = $this->elements;
    $webform = $webform_submission->getWebform();

    /*
     * The visitor_order_number or booking reference determines:
     * - The prison being visited
     * - Whether it is a face-to-face or virtual visit (online meeting)
     * - The date when the visit can be booked
     * - The prisoner type and face-to-face timeslots available for
     *   the prisoner type.
     */

    $this->bookingReference = $form_state->get('booking_reference_processed');

    // Set form elements that display the prison name and visit type.
    $prison_name = $this->bookingReference['prison_name'] ?? NULL;
    $visit_type = $this->bookingReference['visit_type'] ?? NULL;

    if ($this->bookingReference) {
      $this->setFormElementValue('prison_name', $prison_name);
      $this->setFormElementValue('visit_type', $visit_type);
    }

    // Pass configuration and the booking reference to clientside.
    $form['#attached']['library'][] = 'nidirect_prisons/prison_visit_booking';
    $form['#attached']['drupalSettings']['prisonVisitBooking'] = $this->configuration;
    $form['#attached']['drupalSettings']['prisonVisitBooking']['booking_ref'] = $this->bookingReference;

    // Detect if the booking reference has changed.
    $last_ref = $form_state->get('last_visitor_order_number');
    $curr_ref = $form_state->getValue('visitor_order_number');

    // First-time set.
    if (empty($last_ref) && !empty($curr_ref)) {
      $form_state->set('last_visitor_order_number', $curr_ref);
    }
    // Changed.
    elseif (!empty($last_ref) && $curr_ref !== $last_ref) {
      $form_state->set('last_visitor_order_number', $curr_ref);

      // Reset time slots when the booking reference changes.
      $this->resetFormSlots($form, $form_state, $webform_submission);

      // Reset form elements not applicable to virtual visits.
      if ($visit_type === 'virtual') {
        $this->resetAdditionalVisitors();
        $this->setFormElementValue('visitor_special_requirements_details');
      }
    }

    // Virtual slots depend on the prisoner's house affiliation.
    // If the prisoner_id changes, reset the virtual slots.
    $last_prisoner_id = $form_state->get('last_prisoner_id');
    $curr_prisoner_id = $form_state->getValue('prisoner_id');
    if ($visit_type === 'virtual' && !empty($curr_prisoner_id)) {
      if (!empty($last_prisoner_id) && $curr_prisoner_id !== $last_prisoner_id) {
        $this->resetFormSlots($form, $form_state, $webform_submission);
      }
      $form_state->set('last_prisoner_id', $curr_prisoner_id);
    }

    // Booking amendment handling.
    // If the user is amending a booking, the form is altered to reflect the
    // existing booking data with checks performed to verify the booking can
    // be amended.
    $amend_booking_data = $form_state->get('amend_booking_data');
    if ($amend_booking_data && !$this->alterFormForBookingAmendment($page, $page_trigger_is_previous)) {
      return;
    }

    if ($page === 'main_visitor_details' && !$amend_booking_data && !empty($this->bookingReference)) {

      // This page immediately follows the booking reference page.
      // Perform slot availability checks here.

      $slots = $this->getAvailableSlots();
      $slots_error = $slots['error'] ?? FALSE;

      if ($slots_error) {
        // Unable to get slot data.
        $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_NO_DATA;
        $this->bookingReference['error_status_msg'] = $this->t('An error has occurred. Booking cannot proceed at this time. Try again later.');
      }
      elseif (!$slots) {
        // No slots available.
        $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_NO_SLOTS;
        $this->bookingReference['error_status_msg'] = $this->t('There are no time slots available for visit reference number @booking_ref.', ['@booking_ref' => $this->bookingReference['visitor_order_number']]);
      }
      else {
        $this->bookingReference['available_slots'] = $slots;
      }

      if ($slots_error || !$slots) {

        $elements['main_visitor_details']['#title_display'] = 'invisible';
        $elements['visitor_1_details']['#access'] = FALSE;
        $elements['wizard_next']['#access'] = FALSE;

        if ($slots_error) {
          $elements['msg_reference_no_data']['#access'] = TRUE;
        }

        if (!$slots) {
          $elements['msg_reference_no_slots']['#access'] = TRUE;
        }
      }
    }

    // Pass all visitor IDs to clientside for duplication checks.
    $visitor_1_id = $form_state->getValue('visitor_1_id');
    $additional_visitor_ids = array_filter($form_state->getValues(), function ($v, $k) {
      return preg_match("/additional_visitor_[1-5]_id/", $k);
    }, ARRAY_FILTER_USE_BOTH);

    $form['#attached']['drupalSettings']['prisonVisitBooking']['visitorOneId'] = ['visitor_1_id' => $visitor_1_id];
    $form['#attached']['drupalSettings']['prisonVisitBooking']['additionalVisitorIds'] = $additional_visitor_ids;

    // If the main visitor ID duplicates an existing additional
    // visitor ID, remove the additional visitor.

    if (!empty($visitor_1_id) && $duplicate_visitor_id_key = array_search($visitor_1_id, $additional_visitor_ids)) {

      // Determine the additional visitor number from the key for
      // the duplicate ID.
      $duplicate_visitor_number = (int) filter_var($duplicate_visitor_id_key, FILTER_SANITIZE_NUMBER_INT);

      // Erase the duplicate visitor values.
      $this->setFormElementValue('additional_visitor_' . $duplicate_visitor_number . '_id', '');
      $this->setFormElementValue('additional_visitor_' . $duplicate_visitor_number . '_dob', '');

      // Shift up the visitors that follow to fill the gap.
      for ($i = $duplicate_visitor_number; $i < count($additional_visitor_ids); $i++) {

        // Grab next visitor details before erasing them.
        $next_id = $this->formState->getValue('additional_visitor_' . ($i + 1) . '_id');
        $next_dob = $this->formState->getValue('additional_visitor_' . ($i + 1) . '_dob');

        // Erase next visitor details.
        $this->setFormElementValue('additional_visitor_' . ($i + 1) . '_id', '');
        $this->setFormElementValue('additional_visitor_' . ($i + 1) . '_dob', '');

        // Copy next visitor details to this visitor.
        $this->setFormElementValue('additional_visitor_' . $i . '_id', $next_id);
        $this->setFormElementValue('additional_visitor_' . $i . '_dob', $next_dob);
      }

      // Subtract 1 from the form element tracking the number of
      // additional visitors.
      $additional_visitor_number = (string) ($this->formState->getValue('additional_visitor_number') - 1);
      $this->setFormElementValue('additional_visitor_number', $additional_visitor_number);
    }

    // If user is amending a booking, timeslots are always reset and
    // the original timeslot restored if user chose not to amend it.
    $choose_changes = $form_state->getValue('choose_changes') ?? $form_state->getValue('choose_changes_virtual') ?? NULL;
    if ($amend_booking_data && is_array($choose_changes) && !in_array('time_slot', $choose_changes)) {
      $this->resetFormSlots($form, $form_state, $webform_submission);

      $this->setFormElementValue('slot1_datetime', $amend_booking_data['SLOTDATETIME']);
    }

    // Show available timeslots in the form.
    if ($page === 'visit_preferred_day_and_time' && !empty($this->bookingReference)) {

      // Reset timeslots when user is amending a booking.
      if ($amend_booking_data && $page_trigger_is_next) {
        $this->resetFormSlots($form, $form_state, $webform_submission);
      }

      // Keep track of timeslots that have been offered.
      $offered_slots = [];

      // Get available slots and show only those slots on the form.
      $available_slots = $this->getAvailableSlots();
      $slots_error = $available_slots['error'] ?? FALSE;

      // Determine dates.
      $visit_booking_ref_valid_from = $this->bookingReference['date_valid_from'];
      $visit_booking_week_start = $this->bookingReference['date_visit_week_start'];

      if ($visit_booking_ref_valid_from < $visit_booking_week_start) {
        $visit_booking_ref_valid_from = clone $visit_booking_week_start;
      }

      // Alter form slots to correspond with available slots.
      if (!empty($available_slots) && !$slots_error) {

        for ($i = 4; $i >= 1; $i--) {

          // Form slots for each week.
          $form_slots_week = &$form['elements']['visit_preferred_day_and_time']['slots']['slots_week_' . $i];
          $webform_submission_slots_week = $webform->getElement('slots_week_' . $i, TRUE);

          // By default, disable access. Enable access if there are days
          // and times to show.
          $form_slots_week['#access'] = FALSE;

          // Add week commencing date to container titles for each week.
          $form_slots_week_date = clone $visit_booking_ref_valid_from;
          $form_slots_week_date->modify('+' . ($i - 1) . 'weeks');
          $form_slots_week_title = str_replace('[DATE]', $form_slots_week_date->format('j F Y'), $form_slots_week['#title']);

          $form_slots_week['#title'] = $form_slots_week_title;
          $webform_submission_slots_week['#title'] = $form_slots_week_title;
          $webform->setElementProperties('slots_week_' . $i, $webform_submission_slots_week);

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

              // Keep track of the timeslots that have been offered.
              $offered_slots = array_merge(
                $offered_slots,
                array_keys($form_slots_day['#options'])
              );
            }

          }
        }
      }
      else {
        // There are no slots available. Either because slot data is missing,
        // or because slots are just no longer available.

        // Disable all slots.
        $elements['slots']['#access'] = FALSE;

        if ($slots_error) {
          // Change the existing message to an error message.
          $elements['msg_slots']['#markup'] = $elements['msg_reference_no_data']['#markup'];
        }

        // Show message.
        $elements['msg_slots']['#access'] = TRUE;

        // Prevent further progression.
        $elements['wizard_next']['#access'] = FALSE;
        $elements['preview_next']['#access'] = FALSE;
      }

      // Store timeslots that have been offered. They are used to validate
      // hidden slot preference values against the list of offered slots.
      $form_state->set('prison_visit_offered_slots', $offered_slots);

      // Handle the case where user hits previous and a preferred slot is no
      // longer present in the list of offered slots.
      if ($page_trigger_is_previous && !$slots_error
        && !($amend_booking_data && $form_state->getValue('amend_timeslot') === 'No')) {
        $remaining_preferences = [];
        $preferences_changed = FALSE;

        for ($i = 1; $i <= 5; $i++) {
          $value = $form_state->getValue('slot' . $i . '_datetime');

          if ($value === NULL || $value === '') {
            continue;
          }

          if (in_array($value, $offered_slots, TRUE)) {
            $remaining_preferences[] = $value;
          }
          else {
            $preferences_changed = TRUE;
          }
        }

        if ($preferences_changed) {
          $user_input = $form_state->getUserInput();

          for ($i = 1; $i <= 5; $i++) {
            $key = 'slot' . $i . '_datetime';
            $value = $remaining_preferences[$i - 1] ?? NULL;

            $form_state->setValue($key, $value);
            $webform_submission->setElementData($key, $value);
            $elements[$key]['#default_value'] = $value;
            $elements[$key]['#value'] = $value;
            $user_input[$key] = $value;
          }

          $form_state->setUserInput($user_input);
          $elements['msg_slot_preferences_changed']['#access'] = TRUE;
        }
      }
    }

    // Deal with remembered additional visitors versus amending
    // a booking containing additional visitors.

    $elements['msg_existing_additional_visitors']['#access'] = FALSE;
    $elements['msg_additional_visitors']['#access'] = FALSE;
    $elements['msg_amend_additional_visitors']['#access'] = FALSE;

    // Remembered additional visitors are stored in a private tempstore.
    $temp_store = $this->tempStoreFactory->get('nidirect_webforms.prison_visit_booking');

    // Retrieve visitor data, but not for virtual bookings.
    $visitor_data = [];
    if (!empty($this->bookingReference) && $this->bookingReference['visit_type'] !== 'virtual') {
      $visitor_data = $temp_store->get('visitor_data');
    }

    if (!empty($visitor_data) && $page === 'additional_visitor_details' && $page_trigger_is_next) {
      // Assume it is valid.
      $visitor_data_is_valid = TRUE;

      // It's not valid if user decides not to remember it.
      if ($visitor_data['additional_visitors_remember'] === 'No') {
        $visitor_data_is_valid = FALSE;
      }
      // It's not valid if it's missing.
      elseif (empty($visitor_data['visitor_1_id']) || empty($visitor_data['visitor_1_dob'])) {
        $visitor_data_is_valid = FALSE;
      }
      // It's not valid if main visitor id and dob in the present booking
      // differs from remembered values.  This indicates someone else
      // is making a booking using the same device, and they should not
      // see data belonging to another visitor.
      elseif ($visitor_data['visitor_1_id'] !== $form_state->getValue('visitor_1_id') || $visitor_data['visitor_1_dob'] !== $form_state->getValue('visitor_1_dob')) {
        $visitor_data_is_valid = FALSE;
      }

      if ($visitor_data_is_valid) {
        // If we are not amending a booking, we just prepopulate
        // additional visitor fields with the remembered data.
        if (empty($amend_booking_data)) {

          foreach ($visitor_data as $key => $value) {
            if (empty($form_state->getValue($key))) {
              $this->setFormElementValue($key, $value);
            }
          }

          // Show msg that existing visitor data found.
          $elements['msg_existing_additional_visitors']['#access'] = TRUE;
        }
        // Else we are amending a booking. We can only set the value of
        // the question "Do you want to remember additional visitors?.
        // The data for additional visitors has already been
        // pre-populated elsewhere.
        else {
          $this->setFormElementValue('additional_visitors_remember', $visitor_data['additional_visitors_remember']);

          // Show msg for amending additional visitors.
          $elements['msg_amend_additional_visitors']['#access'] = TRUE;
        }
      }
      else {

        // Remembered visitor data is not valid.
        // Delete it.
        $temp_store->delete('visitor_data');

        // Reset form state for additional visitors.
        foreach ($visitor_data as $key => $value) {
          if (str_starts_with($key, 'additional_visitor')) {
            $this->setFormElementValue($key);
          }
        }

        // Show the standard additional visitor message.
        $elements['msg_additional_visitors']['#access'] = TRUE;
      }
    }
    elseif (!empty($amend_booking_data)) {
      // No remembered data from a previous booking.
      // Show msg amending additional visitors.
      $elements['msg_amend_additional_visitors']['#access'] = TRUE;
    }
    else {
      // No remembered data from a previous booking, and we are not
      // amending a booking...
      // Show the standard additional visitor message.
      $elements['msg_additional_visitors']['#access'] = TRUE;
    }

    // Alterations for webform confirmation step.
    if ($page === 'webform_confirmation') {

      // Default confirmation message.
      $webform_confirmation_message = $webform->getElement('webform_confirmation_message_default')['#markup'];

      // Alter it if we are keeping, cancelling or changing booking.
      if ($form_state->getValue('amend_booking_options') === 'keep') {
        $webform_confirmation_message = $webform->getElement('webform_confirmation_message_keep')['#markup'];
      }
      elseif ($form_state->getValue('amend_booking_options') === 'cancel') {
        $webform_confirmation_message = $webform->getElement('webform_confirmation_message_cancel')['#markup'];
      }
      elseif ($form_state->getValue('amend_booking_options') === 'change') {
        $webform_confirmation_message = $webform->getElement('webform_confirmation_message_change')['#markup'];
      }

      $webform->setSetting('confirmation_message', $webform_confirmation_message);
    }
  }

  /**
   * Applies amendment alterations, returning FALSE if amendment is blocked.
   *
   * @param string $page
   *   The current page being altered.
   * @param bool $page_trigger_is_previous
   *   TRUE if the page trigger is previous, FALSE if next.
   *
   * @return bool
   *   TRUE if amendment is allowed, FALSE if amendment is blocked.
   */
  private function alterFormForBookingAmendment(string $page, bool $page_trigger_is_previous): bool {
    $amend_booking_data = $this->formState->get('amend_booking_data');
    $setup_complete = $this->formState->get('amend_booking_setup_complete');

    // Populate initially and restore original booking data on back navigation.
    if ($page === 'amend_booking_page' && ($setup_complete !== TRUE || $page_trigger_is_previous)) {
      $this->populateFormFromBookingData();
    }

    if (!empty($amend_booking_data['error'])) {
      $this->blockBookingAmendment('error_booking_already_amended');
      return FALSE;
    }

    if (!$this->bookingReference) {
      $this->processVisitBookingReference($amend_booking_data['VISIT_ORDER_NO'], $this->form, $this->formState, $this->webformSubmission);
      $this->setFormElementValue('prison_name', $this->bookingReference['prison_name'] ?? NULL);
      $this->setFormElementValue('visit_type', $this->bookingReference['visit_type'] ?? NULL);
      $this->form['#attached']['drupalSettings']['prisonVisitBooking']['booking_ref'] = $this->bookingReference;
    }

    if ($this->isBookingAmendmentExpired()) {
      $this->blockBookingAmendment('visit_order_ref_amendment_expired');
      return FALSE;
    }

    if (!$this->applyBookingAmendmentRestrictions()) {
      return FALSE;
    }

    $this->alterBookingAmendmentPresentation($page);
    return TRUE;
  }

  /**
   * Displays an amendment error and prevents further wizard progression.
   */
  private function blockBookingAmendment(string $message_element): void {
    $this->elements[$message_element]['#access'] = TRUE;
    $this->elements['booking_details']['#access'] = FALSE;
    $this->elements['amend_booking_options']['#access'] = FALSE;
    $this->elements['wizard_next']['#access'] = FALSE;
  }

  /**
   * Restricts amendments, returning FALSE if amendment cannot proceed.
   */
  private function applyBookingAmendmentRestrictions(): bool {
    $elements = $this->elements;
    $error_status = $this->bookingReference['error_status'] ?? FALSE;

    if ($error_status === self::VISIT_ORDER_REF_MISSING || $error_status === self::VISIT_ORDER_REF_INVALID) {
      $this->blockBookingAmendment('visit_order_ref_invalid');
      return FALSE;
    }

    if ($error_status === self::VISIT_ORDER_REF_EXPIRED) {
      $this->blockBookingAmendment('visit_order_ref_expired');
      return FALSE;
    }

    if ($error_status === self::VISIT_ORDER_REF_AMENDMENT_EXPIRED) {
      $this->blockBookingAmendment('visit_order_ref_amendment_expired');
      return FALSE;
    }

    // Further amendment restrictions rely on whether there are slots.
    $slots = $this->getAvailableSlots();
    $slots_error = $slots['error'] ?? FALSE;

    if ($slots_error) {
      $this->elements['booking_details_ref_no_data']['#access'] = TRUE;
      $this->elements['amend_booking_options']['#title'] = $this->t('Do you want to keep or cancel this booking?');
      unset($this->elements['amend_booking_options']['#options']['change']);
      return TRUE;
    }

    if ($error_status === self::VISIT_ORDER_REF_NOTICE_EXCEEDED || $error_status === self::VISIT_ORDER_REF_AMENDMENT_NOTICE_EXCEEDED) {
      if (!$slots) {
        $message_element = $this->bookingReference['visit_type_id'] === 'V'
          ? 'booking_details_notice_exceeded_virtual_no_slots'
          : 'booking_details_notice_exceeded_face_to_face_no_slots';
        $elements[$message_element]['#access'] = TRUE;
        $elements['amend_booking_options']['#title'] = $this->t('Do you want to keep or cancel this booking?');
        unset($elements['amend_booking_options']['#options']['change']);
        return TRUE;
      }

      // Otherwise (there are slots), the user must change the time slot.
      if ($this->bookingReference['visit_type_id'] !== 'V') {
        $elements['booking_details_notice_exceeded_face_to_face']['#access'] = TRUE;
        $elements['choose_changes']['time_slot']['#attributes']['checked'] = TRUE;
        $elements['choose_changes']['time_slot']['#attributes']['readonly'] = 'readonly';
        $elements['choose_changes']['time_slot']['#attributes']['onclick'] = 'return false';
        $elements['choose_changes']['time_slot']['#label_attributes']['onclick'] = 'return false';
      }
      else {
        $elements['booking_details_notice_exceeded_virtual']['#access'] = TRUE;
        $elements['choose_changes_virtual']['time_slot']['#attributes']['checked'] = TRUE;
        $elements['choose_changes_virtual']['time_slot']['#attributes']['readonly'] = 'readonly';
        $elements['choose_changes_virtual']['time_slot']['#attributes']['onclick'] = 'return false';
        $elements['choose_changes_virtual']['time_slot']['#label_attributes']['onclick'] = 'return false';
      }

      return TRUE;
    }

    // Visitor details can still be changed when no time slots are available and
    // no other amendment restrictions apply.
    if (!$slots) {
      $elements['booking_details_ref_no_slots']['#access'] = TRUE;
      $elements['booking_details']['#access'] = TRUE;
      unset($elements['choose_changes']['#options']['time_slot']);
      unset($elements['choose_changes_virtual']['#options']['time_slot']);
      return TRUE;
    }

    if (!$error_status) {
      $elements['booking_details']['#access'] = TRUE;
      return TRUE;
    }

    $this->blockBookingAmendment('amend_error_default');
    return FALSE;
  }

  /**
   * Updates amendment page titles, options and preview labels.
   */
  private function alterBookingAmendmentPresentation(string $page): void {
    $elements = $this->elements;
    $elements['main_visitor_details']['#title'] = $this->t('Amend main visitor details');
    $elements['additional_visitor_details']['#title'] = $this->t('Amend additional visitors');
    $elements['visitor_special_requirements']['#title'] = $this->t('Amend visitor special requirements');
    $elements['visit_preferred_day_and_time']['#title'] = $this->t('Amend visit date and time');
    $elements['webform_preview']['#title'] = $this->t('Amend booking confirmation');

    // Virtual visits have no additional visitors.
    if ($this->bookingReference['visit_type'] === 'virtual') {
      unset($elements['choose_changes']['#options']['additional_visitors']);
    }

    $amend_booking_option = $this->formState->getValue('amend_booking_options');
    if ($amend_booking_option === 'keep') {
      $this->setFormElementValue('amend_type', 'KEEP');
      if ($page === 'webform_preview') {
        $elements['preview']['#title'] = $this->t('Confirm keep booking');
        $elements['actions'][0]['#submit__label'] = $this->t('Keep booking');
      }
    }
    elseif ($amend_booking_option === 'cancel') {
      $this->setFormElementValue('amend_type', 'CANCEL');
      if ($page === 'webform_preview') {
        $elements['preview']['#title'] = $this->t('Confirm cancel booking');
        $elements['actions'][0]['#submit__label'] = $this->t('Cancel booking');
      }
    }
    elseif ($amend_booking_option === 'change') {
      $this->setFormElementValue('amend_type', 'UPDATE');
      if ($page === 'webform_preview') {
        $elements['preview']['#title'] = $this->t('Confirm amend booking');
        $elements['actions'][0]['#submit__label'] = $this->t('Amend booking');
      }
    }
  }

  /**
   * Prepopulates the form with the original booking data to be amended.
   */
  private function populateFormFromBookingData(): void {

    $amend_booking_data = $this->formState->get('amend_booking_data');

    // Populate the form.
    $this->setFormElementValue('visitor_order_number', $amend_booking_data['VISIT_ORDER_NO']);
    $this->setFormElementValue('prisoner_id', $amend_booking_data['INMATE_ID']);
    $this->setFormElementValue('visitor_1_id', $amend_booking_data['VISITOR_1_ID']);

    // Visitor 1 DOB supplied in the booking data is in UK date/time format
    // (DD/MM/YYYY HH:mm). Chop off time.
    $visitor_1_dob = explode(" ", $amend_booking_data['VISITOR_1_DOB'])[0];

    $this->setFormElementValue('visitor_1_dob', $visitor_1_dob);
    $this->setFormElementValue('visitor_1_email', $amend_booking_data['VISITOR_1_EMAIL']);
    $this->setFormElementValue('visitor_1_telephone', $amend_booking_data['VISITOR_1_PHONE']);

    // Extract additional visitors from booking data.
    $additional_visitors = [];

    for ($i = 2; $i <= 5; $i++) {
      $visitor_id = $amend_booking_data['VISITOR_' . $i . '_ID'] ?? NULL;
      $visitor_dob = $amend_booking_data['VISITOR_' . $i . '_DOB'] ?? NULL;

      if ($visitor_id && $visitor_dob) {
        $additional_visitors[] = [
          'id' => $visitor_id,
          'dob' => explode(" ", $visitor_dob)[0],
        ];
      }
    }

    $additional_visitors_count = count($additional_visitors);

    if ($additional_visitors_count > 0) {
      $this->setFormElementValue('additional_visitor_number', (string) $additional_visitors_count);

      for ($i = 0; $i < $additional_visitors_count; $i++) {
        $form_key_stub = 'additional_visitor_' . ($i + 1);

        $this->setFormElementValue($form_key_stub . '_id', $additional_visitors[$i]['id']);
        $this->setFormElementValue($form_key_stub . '_dob', $additional_visitors[$i]['dob']);
      }
    }

    // Visitor special requirements.
    if ($amend_booking_data['SPECIAL_REQUIREMENTS']) {
      $this->setFormElementValue('visitor_special_requirements_choice', 'yes');
      $this->setFormElementValue('visitor_special_requirements_details', $amend_booking_data['SPECIAL_REQUIREMENTS']);
    }
    else {
      $this->setFormElementValue('visitor_special_requirements_choice', 'no');
    }

    // Visit preferred date and time.
    $this->setFormElementValue('slot1_datetime', $amend_booking_data['SLOTDATETIME']);

    // Populate hidden form elements with original amendment data.
    foreach ($amend_booking_data as $key => $value) {
      $key = strtolower($key);
      $key = ($key !== 'bkg_id') ? 'bkg_' . $key : $key;
      $value = (str_ends_with($key, '_dob') && !empty($value)) ? str_replace(' 00:00', '', $value) : $value;

      $this->setFormElementValue($key, $value);
    }

    // Form is now pre-populated to allow booking to be amended.
    $this->formState->set('amend_booking_setup_complete', TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $page = $form_state->get('current_page');
    $this->form = &$form;
    $this->formState = $form_state;
    $this->webformSubmission = $webform_submission;
    $this->elements = WebformFormHelper::flattenElements($form);

    if ($this->isBookingAmendmentExpired()) {
      $form_state->setError($form, $this->t('The scheduled date and time for this visit have already passed, so amendments can no longer be made.'));
      return;
    }

    if ($page === 'booking_reference_prisoner_reference') {
      $form_state->set('virtual_booking_attempt', NULL);
      $this->validateVisitBookingReference($form, $form_state, $webform_submission);

      $prisoner_id = $form_state->getValue('prisoner_id');
      $prison_id = $this->bookingReference['prison_id'] ?? NULL;

      if (empty($prisoner_id)) {
        $form_state->setErrorByName('prisoner_id', $this->t('Prisoner ID is required.'));
      }

      // Rate limit virtual bookings.
      if (!$form_state->getErrors() && !$form_state->get('amend_booking_data') && ($this->bookingReference['visit_type_id'] ?? NULL) === 'V') {
        $event = 'nidirect_prisons.virtual_booking_attempt';
        $limit = Settings::get('nidirect_prisons_virtual_booking_limit', 5);
        $window = Settings::get('nidirect_prisons_virtual_booking_window', 900);

        if (!$this->flood->isAllowed($event, $limit, $window)) {
          $form_state->setErrorByName('visitor_order_number', $this->t('Too many virtual booking attempts. Please wait a few minutes before trying again.'));
          return;
        }

        $this->flood->register($event, $window);

        $affiliation_id = $this->getPrisonerHouseAffiliationId($prisoner_id, $prison_id);

        if ($affiliation_id === FALSE) {
          // Is affiliation data available?
          $has_affiliation_data = $this->database
            ->select('prisoner_house_affiliation', 'pha')
            ->fields('pha', ['prisoner_id'])
            ->range(0, 1)
            ->execute()
            ->fetchField() !== FALSE;

          if (!$has_affiliation_data) {
            $this->getLogger('prison_visits')->error('Prisoner affiliation data is unavailable.');
            $form_state->setError($form, $this->t('An error has occurred. Booking cannot proceed at this time. Try again later.'));
          }
          else {
            $form_state->setError($form, $this->t('The details you have provided may be incorrect. Check the details and try again.'));
          }

          return;
        }

        $form_state->set('virtual_booking_attempt', [
          $this->bookingReference['visitor_order_number'],
          $prisoner_id,
        ]);
      }
    }

    if ($page === 'main_visitor_details' && !$form_state->get('amend_booking_data')) {
      $this->bookingReference = $form_state->get('booking_reference_processed');

      if (empty($this->bookingReference)) {
        $form_state->setError($form, $this->t('Booking cannot proceed. Please go back and enter your visit reference number again.'));
      }
      else {
        $slots = $this->getAvailableSlots();

        if (!empty($slots['error'])) {
          $form_state->setError($form, $this->t('An error has occurred. Booking cannot proceed at this time. Try again later.'));
        }
        elseif (!$slots) {
          $form_state->setError($form, $this->t('There are no visit dates and times available for this visit reference number.'));
        }
      }
    }

    if ($page === 'additional_visitor_details') {
      $this->validateUniqueVisitorIds($form, $form_state);
      $this->validateMaxAdultVisitors($form, $form_state);
    }

    if ($page === 'visitor_special_requirements') {
      $error_msg = $this->t('Details of special requirements must be plain text.');
      $this->validatePlainText('visitor_special_requirements_details', $error_msg, $form, $form_state);
    }

    if ($page === 'visit_preferred_day_and_time') {
      $this->validateSlotPicked($form, $form_state, $webform_submission);
    }

    if ($page === 'webform_preview' && !$form_state->get('amend_booking_data')) {
      $validated = $form_state->get('prison_visit_validated_preferences');

      $preferences_match = is_array($validated)
        && ($validated['booking_reference'] ?? NULL) === $form_state->getValue('visitor_order_number')
        && ($validated['prisoner_id'] ?? NULL) === $form_state->getValue('prisoner_id')
        && !empty($validated['slots']);

      if ($preferences_match) {
        for ($i = 1; $i <= 5; $i++) {
          $submitted = $form_state->getValue('slot' . $i . '_datetime') ?? '';
          $expected = $validated['slots'][$i - 1] ?? '';

          if ($submitted !== $expected) {
            $preferences_match = FALSE;
            break;
          }
        }
      }

      if (!$preferences_match) {
        $form_state->setError($form, $this->t('Your visit preferences could not be verified. Please go back to the visit date and time page and review your choices.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $page = $form_state->get('current_page');

    if ($form_state->isValidationComplete() && $page === 'webform_preview') {

      $temp_store = $this->tempStoreFactory->get('nidirect_webforms.prison_visit_booking');
      $remember_visitors = $form_state->getValue('additional_visitors_remember');
      $form_values = $form_state->getValues();

      // Special requirements textarea needs to be encoded for JSON.
      $special_requirements = Json::encode($form_state->getValue('visitor_special_requirements_details')) ?? '""';
      $form_state->setValue('special_requirements_json', $special_requirements);
      $webform_submission->setElementData('special_requirements_json', $special_requirements);

      // If user wants to remember additional visitors...
      if ($remember_visitors === 'Yes') {

        // Capture non-sensitive visitor data and
        // store in PrivateTempStore.
        $visitor_data = [];

        foreach ($form_values as $element_name => $element_value) {
          if ($element_name === 'visitor_1_id' ||
            $element_name === 'visitor_1_dob' ||
            str_starts_with($element_name, 'additional_visitor') ||
            str_starts_with($element_name, 'visitor_special_requirements')) {
            $visitor_data[$element_name] = $element_value;
          }
        }

        $temp_store->set('visitor_data', $visitor_data);
      }
      // Otherwise delete visitor data.
      elseif ($remember_visitors === 'No') {
        $temp_store->delete('visitor_data');
      }

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
      $num_visitors = $form_values['additional_visitor_number'];

      for ($i = 1; $i <= $num_visitors; $i++) {

        $visitor_id = $form_values['additional_visitor_' . $i . '_id'] ?? NULL;
        $visitor_dob = $form_values['additional_visitor_' . $i . '_dob'] ?? NULL;

        if ($visitor_dob) {
          $visitor_dob = new \DateTime(str_replace("/", "-", $visitor_dob));
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
   * Process a visit booking reference.
   *
   * @throws \Exception
   */
  private function processVisitBookingReference(string $booking_ref, array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission): void {

    // Reset existing booking reference data.
    $this->bookingReference = [];
    $form_state->set('booking_reference_processed', NULL);

    // Basic validation with early return.
    if (empty($booking_ref)) {
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_MISSING;
      $this->bookingReference['error_status_msg'] = $this->t('Visit reference number is required');
      return;
    }
    elseif (strlen($booking_ref) !== $this->configuration['visit_order_number_length']) {
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_INVALID;
      $this->bookingReference['error_status_msg'] = $this->t('Visit reference number is invalid.');
      return;
    }

    $this->bookingReference = $this->parseVisitBookingReference($booking_ref) ?? [];

    if (empty($this->bookingReference)) {
      // Could not parse the booking reference. It is invalid.
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_INVALID;
      $this->bookingReference['error_status_msg'] = $this->t('Visit reference number is invalid.');
      return;
    }

    // Determine various dates for the booking reference.
    $booking_ref_validity_period_days = $this->configuration['visit_order_number_validity_period_days'][$this->bookingReference['visit_type_id']];
    $this->bookingReference['validity_period_days'] = $booking_ref_validity_period_days;

    // Advance notice required for a booking.
    // The advance notice required is dependent on the visit type.
    $booking_advance_notice = $this->configuration['visit_advance_notice'][$this->bookingReference['visit_type_id']];

    // Maximum period of advance issue for a booking reference number.
    // For example, a booking reference may be issued 4 weeks in
    // advance of the valid from date.
    $booking_ref_max_advance_issue = $this->configuration['visit_order_number_max_advance_issue'];

    // Work out some dates.
    $now = new \DateTime('@' . $this->time->getRequestTime());
    $now->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));

    // Date from now when a slot can be booked.
    $visit_earliest_date = clone $now;
    $visit_earliest_date->modify('+' . $booking_advance_notice);

    // Round up to the nearest hour.
    if ((int) $now->format('i') > 0) {
      $visit_earliest_date->modify('+1 hour');
      // Normalise to the top of the hour.
      $visit_earliest_date->setTime((int) $visit_earliest_date->format('H'), 0, 0);
    }

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
    $booking_ref_valid_from->setISODate($this->bookingReference['visit_year'], $this->bookingReference['visit_week'], 1);
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

    // For booking amendments, determine the latest date a visit can be amended
    // (the booked slot datetime minus the advance notice).
    $amend_booking_data = $form_state->get('amend_booking_data') ?? NULL;
    $amend_booking_date = NULL;
    $visit_latest_booking_amendment_date = NULL;

    if ($amend_booking_data) {
      $amend_booking_date = new \DateTime($amend_booking_data['SLOTDATETIME']);
      $visit_latest_booking_amendment_date = clone $amend_booking_date;
      $visit_latest_booking_amendment_date->modify('-' . $booking_advance_notice);
    }

    // Keep track of all the key dates.
    $this->bookingReference['date'] = $now;
    $this->bookingReference['date_valid_from'] = $booking_ref_valid_from;
    $this->bookingReference['date_valid_to'] = $booking_ref_valid_to;
    $this->bookingReference['date_visit_earliest'] = $visit_earliest_date;
    $this->bookingReference['date_visit_latest'] = $visit_latest_date;
    $this->bookingReference['date_advance_booking_earliest'] = $booking_ref_max_advanced_issue_date;
    $this->bookingReference['date_booking_latest'] = $visit_latest_booking_date;
    $this->bookingReference['date_booking_amendment_latest'] = $visit_latest_booking_amendment_date;

    if ($now < $booking_ref_valid_from) {
      // Determine whether week date for the booking is for a future week or
      // current week.
      $this->bookingReference['date_visit_week_start'] = $booking_ref_valid_from;
    }
    else {
      $this->bookingReference['date_visit_week_start'] = $now_week_commence;
    }

    // Check some dates and set a status.
    if ($now > $booking_ref_valid_to) {
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_EXPIRED;
      $this->bookingReference['error_status_msg'] = $this->t('Visit reference number has expired.');
      // No point in continuing.
      return;
    }
    elseif ($now < $booking_ref_max_advanced_issue_date) {
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_INVALID;
      $this->bookingReference['error_status_msg'] = $this->t('Visit reference number is not recognised.');
      // No point in continuing.
      return;
    }
    elseif ($amend_booking_date && $now > $amend_booking_date) {
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_AMENDMENT_EXPIRED;
      $this->bookingReference['error_status_msg'] = $this->t('The scheduled date and time for this visit have already passed, so amendments can no longer be made.');
      // No point in continuing.
      return;
    }
    elseif ($now > $visit_latest_booking_date) {
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_NOTICE_EXCEEDED;
      $this->bookingReference['error_status_msg'] = $this->t('Visit reference number period of notice has expired.');
      // No early return here - continue.
    }

    if ($amend_booking_data) {

      if (empty($this->bookingReference['error_status']) && $visit_latest_booking_amendment_date && $now > $visit_latest_booking_amendment_date) {
        $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_AMENDMENT_NOTICE_EXCEEDED;
        $this->bookingReference['error_status_msg'] = $this->t('The period of notice required for amending the visit date and time has not been met.');
      }
    }
    elseif (!empty($this->bookingReference['error_status'])) {
      return;
    }

    // Keep the processed booking reference in form_state.
    $form_state->set('booking_reference_processed', $this->bookingReference);

  }

  /**
   * Checks whether the original visit time has passed during an amendment.
   */
  private function isBookingAmendmentExpired(): bool {
    $booking_data = $this->formState->get('amend_booking_data');
    return $booking_data
      && (new \DateTime($booking_data['SLOTDATETIME']))->getTimestamp() < $this->time->getRequestTime();
  }

  /**
   * Parse a visit booking reference into its configured components.
   */
  private function parseVisitBookingReference(string $booking_ref): ?array {

    if (strlen($booking_ref) !== $this->configuration['visit_order_number_length']) {
      return NULL;
    }

    if (!preg_match('/^[A-Z]{3}[0-9]{4}-[0-9]{4}$/D', $booking_ref)) {
      return NULL;
    }

    $prison_id = substr($booking_ref, 0, 2);
    $visit_type_id = substr($booking_ref, 2, 1);
    $visit_week = (int) substr($booking_ref, 3, 2);
    $visit_year = (int) substr($booking_ref, 5, 2);
    $visit_sequence = (int) substr($booking_ref, 8);

    $visit_year_full = (int) DrupalDateTime::createFromFormat('y', $visit_year)->format('Y');

    $week_start = (new \DateTimeImmutable())->setISODate($visit_year_full, $visit_week, 1);
    if ((int) $week_start->format('o') !== $visit_year_full
      || (int) $week_start->format('W') !== $visit_week) {
      return NULL;
    }

    if (!array_key_exists($prison_id, $this->configuration['prisons'])
      || !array_key_exists($visit_type_id, $this->configuration['visit_type'])
      || $visit_sequence <= 0
      || $visit_sequence >= 9999) {
      return NULL;
    }

    $parsed = [
      'visitor_order_number' => $booking_ref,
      'prison_id' => $prison_id,
      'prison_name' => $this->configuration['prisons'][$prison_id],
      'visit_type_id' => $visit_type_id,
      'visit_type' => $this->configuration['visit_type'][$visit_type_id],
      'visit_week' => $visit_week,
      'visit_year' => $visit_year_full,
    ];

    if ($visit_type_id === 'E') {
      $parsed['visit_type'] = $this->configuration['visit_type']['F'];
    }

    foreach ($this->configuration['visit_order_number_categories'] as $category_key => $category_value) {
      foreach ($category_value as $subcategory_key => $subcategory_value) {
        if ($visit_sequence >= $subcategory_value[0] && $visit_sequence <= $subcategory_value[1]) {
          $parsed['prisoner_category'] = $category_key;
          $parsed['prisoner_subcategory'] = $subcategory_key + 1;
        }
      }
    }

    return $parsed;
  }

  /**
   * Validate visit booking reference.
   */
  private function validateVisitBookingReference(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $booking_ref = $form_state->getValue('visitor_order_number');

    if (!$booking_ref) {
      $this->bookingReference = [];
      $form_state->set('booking_reference_processed', NULL);
      $form_state->setErrorByName('visitor_order_number', $this->t('Visit reference number is required'));
      return;
    }

    $this->processVisitBookingReference($booking_ref, $form, $form_state, $webform_submission);

    $error_status = $this->bookingReference['error_status'] ?? NULL;
    if ($error_status && $error_status !== self::VISIT_ORDER_REF_NO_SLOTS) {
      $form_state->setErrorByName('visitor_order_number', $this->bookingReference['error_status_msg']);
    }
  }

  /**
   * Validate visitor IDs are unique.
   */
  private function validateUniqueVisitorIds(array &$form, FormStateInterface $form_state) {

    $visitorIds = [];

    if ($form_state->get('current_page') === 'additional_visitor_details') {
      // Get all additional adult visitor ids.
      $visitorIds = array_filter($form_state->getValues(), function ($v, $k) {
        return ($k === 'visitor_1_id' || (str_contains($k, 'additional_visitor_') && str_ends_with($k, '_id'))) && is_numeric($v);
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
   * Validate maximum number of additional adult visitors has not
   * been exceeded.
   */
  private function validateMaxAdultVisitors(array &$form, FormStateInterface $form_state) {
    $additional_visitor_dobs = [];
    $num_max_adults = 2;
    $additional_visitor_number = (int) $form_state->getValue('additional_visitor_number');

    for ($i = 1; $i <= $additional_visitor_number; $i++) {
      $dob = $form_state->getValue('additional_visitor_' . $i . '_dob');
      if ($this->isAdultDateOfBirth($dob)) {
        $additional_visitor_dobs[] = $dob;
      }
    }

    $additional_adult_count = count($additional_visitor_dobs);

    // The maximum number of additional adults is two.
    if ($additional_adult_count > $num_max_adults) {
      $form_state->setError($form, $this->t('Maximum number of additional adult visitors has been exceeded. Remove 1 or more adults.'));
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
  private function validateSlotPicked(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    if ($form_state->get('current_page') !== 'visit_preferred_day_and_time') {
      return;
    }

    if ($form_state->get('amend_booking_data')
      && $form_state->getValue('slot1_datetime')
      && $form_state->getValue('amend_timeslot') === 'No') {
      return;
    }

    // Validate time slots have been selected.
    // Time slots are checkboxes with keys like monday_week_1,
    // tuesday_week_2, etc., and whose value must be an array.
    $form_values = array_filter($form_state->getValues(), function ($value, $key) {
      return str_contains($key, '_week_') && is_array($value) && !empty($value);
    }, ARRAY_FILTER_USE_BOTH);

    if (empty($form_values)) {
      $form_state->setErrorByName('slots_week_1', $this->t('You did not choose a time slot for your visit.'));
      return;
    }
    elseif (empty($form_state->getValue('slot1_datetime'))) {
      // Hidden time slot elements (populated by JS) should not be
      // empty. Belt and braces, reset slots.
      $this->resetFormSlots($form, $form_state, $webform_submission);
      $form_state->setErrorByName('slots_week_1', $this->t('You did not choose a time slot for your visit.'));
      return;
    }

    // Validate hidden slot preference values against the list of offered slots.
    $offered_slots = $form_state->get('prison_visit_offered_slots');

    if (!is_array($offered_slots) || empty($offered_slots)) {
      $form_state->setError($form, $this->t('Visit time options could not be verified. Please go back and reopen the visit date and time page.'));
      return;
    }

    $booking_reference = $form_state->get('booking_reference_processed');
    $visit_type_id = $booking_reference['visit_type_id'] ?? NULL;
    $slot_limit = $this->configuration['visit_type_time_slot_limit'][$visit_type_id] ?? NULL;

    if (!is_int($slot_limit) || $slot_limit < 1 || $slot_limit > 5) {
      $form_state->setError($form, $this->t('Visit preferences could not be verified. Please go back and enter your visit reference number again.'));
      return;
    }

    $selected_slots = [];
    $has_gap = FALSE;

    for ($i = 1; $i <= 5; $i++) {
      $selected_slot = $form_state->getValue('slot' . $i . '_datetime');

      if ($selected_slot === NULL || $selected_slot === '') {
        $has_gap = TRUE;
        continue;
      }

      if (!is_string($selected_slot)
        || !in_array($selected_slot, $offered_slots, TRUE)) {
        $this->resetFormSlots($form, $form_state, $webform_submission);
        $form_state->setError($form, $this->t('One or more selected visit times were not offered on this page. Please choose your preferred times again.'));
        return;
      }

      if ($has_gap
        || $i > $slot_limit
        || in_array($selected_slot, $selected_slots, TRUE)) {
        $this->resetFormSlots($form, $form_state, $webform_submission);
        $form_state->setError($form, $this->t('Visit preferences must be distinct and ranked consecutively within the allowed number of choices. Please choose your preferred times again.'));
        return;
      }

      $selected_slots[] = $selected_slot;
    }

    // Ensure the ranked hidden preferences match the selected (checked) slots.
    $checked_slots = [];

    foreach ($form_values as $values) {
      foreach ($values as $value) {
        if ($value !== 0 && $value !== '0') {
          $checked_slots[] = $value;
        }
      }
    }

    if (count($checked_slots) !== count($selected_slots)
      || array_diff($checked_slots, $selected_slots)
      || array_diff($selected_slots, $checked_slots)) {
      $this->resetFormSlots($form, $form_state, $webform_submission);
      $form_state->setError($form, $this->t('Your selected visit times do not match your ranked preferences. Please choose your preferred times again.'));
      return;
    }

    $form_state->set('prison_visit_validated_preferences', [
      'booking_reference' => $form_state->getValue('visitor_order_number'),
      'prisoner_id' => $form_state->getValue('prisoner_id'),
      'slots' => $selected_slots,
    ]);
  }

  /**
   * Get available slots for a given prison, visit type and prisoner category.
   *
   * @throws \Exception
   */
  private function getAvailableSlots(): array {

    $available_slots = [];

    // Early return when there is no valid booking reference.
    if (empty($this->bookingReference)) {
      return ['error' => TRUE];
    }

    $visit_type = match ($this->bookingReference['visit_type']) {
      'face-to-face' => 'FACE_TO_FACE',
      'virtual' => 'VIRTUAL',
      default => NULL,
    };

    if ($visit_type === NULL) {
      return ['error' => TRUE];
    }

    $prison_id = $this->bookingReference['prison_id'];
    $prisoner_category = $this->bookingReference['prisoner_category'];
    $prisoner_subcategory = $this->bookingReference['prisoner_subcategory'];
    $prisoner_id = $this->formState->getValue('prisoner_id');

    if (empty($prison_id) || empty($prisoner_id) || empty($prisoner_category) || empty($prisoner_subcategory)) {
      return ['error' => TRUE];
    }

    // Log when the imported slots are missing or over 24 hours old.
    if (!$this->logSlotDataFreshness()) {
      return ['error' => TRUE];
    }

    // Determine available time slots.
    // The available time slots depend on the visit type, prison and prisoner affiliation.
    $affiliation_id = NULL;
    if ($visit_type === 'VIRTUAL') {

      if (!$this->formState->get('amend_booking_data') && $this->formState->get('virtual_booking_attempt') !== [
        $this->bookingReference['visitor_order_number'],
        $prisoner_id,
      ]) {
        return ['error' => TRUE];
      }

      $affiliation_id = $this->getPrisonerHouseAffiliationId($prisoner_id, $prison_id);

      if ($affiliation_id === FALSE) {
        return ['error' => TRUE];
      }
    }
    else {
      // For face-to-face visits, prisoner affiliation is determined by the
      // prisoner category and subcategory (which is encoded in the
      // booking reference).
      if ($prisoner_category === 'separates') {
        $affiliation_id = 'AFILL_' . $prisoner_subcategory;
      }
      elseif ($prisoner_category === 'integrated') {
        $affiliation_id = 'INT';
      }
    }

    if ($affiliation_id === NULL) {
      return ['error' => TRUE];
    }

    $timestamps = $this->getSlotTimestamps($visit_type, $prison_id, $affiliation_id);
    $timezone = new \DateTimeZone('Europe/London');
    foreach ($timestamps as $timestamp) {
      $slot_datetime = new \DateTime('@' . $timestamp);
      $slot_datetime->setTimezone($timezone);
      $available_slots[] = $slot_datetime;
    }

    // Discard slots that are not bookable. A slot is bookable if it
    // falls within certain dates. Weekend slots cannot be booked where
    // the visit type is enhanced (booking reference visit type
    // identifier is 'E').

    $visit_type_id = $this->bookingReference['visit_type_id'];

    $date = new \DateTime('@' . $this->time->getRequestTime());
    $date->setTimezone(new \DateTimeZone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $date_visit_earliest = clone $date;
    $date_visit_earliest->modify('+' . $this->configuration['visit_advance_notice'][$visit_type_id]);

    if ((int) $date->format('i') > 0) {
      $date_visit_earliest->modify('+1 hour');
      $date_visit_earliest->setTime((int) $date_visit_earliest->format('H'), 0, 0);
    }
    $date_visit_week_start = $this->bookingReference['date_visit_week_start'];
    $date_valid_to = $this->bookingReference['date_valid_to'];

    // The minimum date for a slot to be bookable.
    // the earliest visit date (the booking reference allows) or the start of the week for the booking reference.
    $min_date = max($date, $date_visit_earliest, $date_visit_week_start);

    foreach ($available_slots as $slot_key => $slot) {
      // Determine if this slot is bookable.
      $slot_is_bookable = TRUE;

      // Cannot book a slot.
      if ($slot < $min_date) {
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
   * Loads slot timestamps for a prison and optional affiliations.
   *
   * @param string $visit_type
   *   Visit type stored in the slots table.
   * @param string $prison_id
   *   Prison code.
   * @param string|null $affiliation_id
   *   Optional affiliation code to filter by.
   *
   * @return int[]
   *   Slot timestamps ordered chronologically.
   */
  private function getSlotTimestamps(string $visit_type, string $prison_id, ?string $affiliation_id = NULL): array {
    $query = $this->database->select('prison_visit_slots', 'slots')
      ->fields('slots', ['slot_datetime'])
      ->condition('visit_type', $visit_type)
      ->condition('prison_id', $prison_id)
      ->orderBy('slot_datetime');

    if ($affiliation_id !== NULL) {
      $query->condition('affiliation_id', $affiliation_id);
    }

    $query->distinct();

    return array_map('intval', $query->execute()->fetchCol());
  }

  /**
   * Logs when the imported slots are missing or over 24 hours old.
   */
  private function logSlotDataFreshness(): bool {
    $query = $this->database->select('prison_visit_slots', 'slots');
    $query->addExpression('MAX(updated_timestamp)', 'latest_update');
    $latest_update = (int) $query->execute()->fetchField();

    if (!$latest_update) {
      $this->getLogger('prison_visits')->error('No prison visit slots have been imported.');
      return FALSE;
    }
    elseif ($this->time->getRequestTime() - $latest_update >= 86400) {
      $this->getLogger('prison_visits')->warning('Prison visit slots data has not been updated in the last 24 hours.');
    }

    return TRUE;
  }

  /**
   * Get booking data from the request.
   */
  private function getRequestBookingData() {

    $booking_data_encrypted = $this->request->get("booking");

    // Decrypt it.
    $key = getenv('PRISON_VISIT_BOOKING_AMEND_AES_256_CBC_KEY');
    $iv = getenv('PRISON_VISIT_BOOKING_AMEND_AES_256_CBC_IV');

    if (!$key || !$iv) {
      $error_message = 'Missing environment variables:';
      $error_message .= !$key ? ' PRISON_VISIT_BOOKING_AMEND_AES_256_CBC_KEY ' : '';
      $error_message .= !$iv ? ' PRISON_VISIT_BOOKING_AMEND_AES_256_CBC_IV' : '';

      $this->getLogger('prison_visits')->error($error_message);

      // Throw an exception.
      throw new \RuntimeException($error_message);
    }

    $booking_data_decrypted = $this->decrypt($booking_data_encrypted, $key, $iv);
    $booking_data_decrypted = preg_replace('/[[:cntrl:]]/', '', $booking_data_decrypted);
    $booking_data = json_decode($booking_data_decrypted, TRUE);

    if (!empty($booking_data) && json_last_error() === JSON_ERROR_NONE) {
      return $booking_data;
    }

    return FALSE;
  }

  /**
   * Reset any additional visitor data entered into the form.
   */
  private function resetAdditionalVisitors() {
    $num_additional_visitors = (int) $this->formState->getValue('additional_visitor_number');

    for ($i = 1; $i <= $num_additional_visitors; $i++) {
      $this->setFormElementValue('additional_visitor_' . $i . '_id');
      $this->setFormElementValue('additional_visitor_' . $i . '_dob');
    }

    $this->setFormElementValue('additional_visitor_number', '0');
  }

  /**
   * Reset form slots.
   */
  private function resetFormSlots(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $elements = WebformFormHelper::flattenElements($form);

    $form_values = array_filter($form_state->getValues(), function ($key) {
      return str_contains($key, '_week_');
    }, ARRAY_FILTER_USE_KEY);

    foreach ($form_values as $element_name => $element_value) {
      $this->setFormElementValue($element_name);
    }

    $form_values = array_filter($form_state->getValues(), function ($key) {
      return str_starts_with($key, 'slot') && str_ends_with($key, 'datetime');
    }, ARRAY_FILTER_USE_KEY);

    foreach ($form_values as $element_name => $element_value) {
      $this->setFormElementValue($element_name);
    }

    // Some of the logic for setting timeslot preferences is handled
    // via clientside JS.  Flag to clientside when timeslots must be
    // reset.
    $form['#attached']['drupalSettings']['prisonVisitBooking']['resetTimeslots'] = TRUE;
  }

  /**
   * Decrypt AES-256-CBC encrypted text.
   */
  private function decrypt(string $text, $key, $iv) {
    // Text to decrypt has hexadecimal encoding.
    $encrypted_raw = hex2bin($text);

    return openssl_decrypt(
      $encrypted_raw,
      'AES-256-CBC',
      $key,
      OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
      $iv
    );
  }

  /**
   * Check date string is an adult birthdate.
   */
  private function isAdultDateOfBirth(string $date) {

    // Date string must be formatted as dd/mm/yyyy.
    if (!preg_match("/^([0-2][0-9]|3[0-1])\/(0[1-9]|1[0-2])\/\d{4}$/", $date)) {
      return FALSE;
    }

    // Create DateTime from the date string. Better to pass date
    // as dd-mm-yyyy to avoid being parsed as a US date.
    $birthdate = new \DateTime(str_replace("/", "-", $date));
    $today = new \DateTime();

    return $birthdate && $today->diff($birthdate)->y >= 18;
  }

  /**
   * Sets or clears the value of a form element across $form, $form_state,
   * and $webform_submission.
   *
   * @param string $element_key
   *   The key of the element whose value you want to set.
   * @param mixed|null $element_value
   *   The value to set. If NULL, a type-appropriate "empty" value
   *   will be used.
   */
  protected function setFormElementValue(string $element_key, mixed $element_value = NULL): void {

    if (isset($this->elements[$element_key])) {

      // Determine element type.
      $type = $this->elements[$element_key]['#type'] ?? NULL;

      // If no element value given, determine a sensible empty value.
      if ($element_value === NULL) {
        switch ($type) {
          case 'checkboxes':
          case 'webform_checkbox_other':
            $element_value = [];
            break;

          case 'hidden':
          case 'textfield':
          case 'textarea':
          case 'date':
          case 'datetime':
          case 'webform_time':
          case 'webform_datetime':
            $element_value = '';
            break;

          default:
            $element_value = NULL;
        }
      }
    }

    // Update element value in form state.
    if ($element_value === NULL) {
      $this->formState->unsetValue($element_key);
    }
    else {
      $this->formState->setValue($element_key, $element_value);
    }

    // Update submission object.
    $this->webformSubmission->setElementData($element_key, $element_value);

    // Update rendered element.
    if (isset($this->elements[$element_key])) {
      $this->elements[$element_key]['#default_value'] = $element_value;
    }
  }

  /**
   * Get the prisoner house affiliation ID for a given prisoner ID and
   * prison ID.
   *
   * @param string $prisoner_id
   *   The prisoner ID.
   * @param string $prison_id
   *   The prison ID.
   *
   * @return false|string
   *   The affiliation ID, or FALSE if not found.
   * @throws \Exception
   */
  private function getPrisonerHouseAffiliationId(string $prisoner_id, string $prison_id): false|string {

    if (empty($prisoner_id) || empty($prison_id)) {
      return FALSE;
    }

    $affiliation_id = $this->database->select('prisoner_house_affiliation', 'pha')
      ->fields('pha', ['affiliation_id'])
      ->condition('prisoner_id', $prisoner_id)
      ->condition('prison_id', $prison_id)
      ->execute()
      ->fetchField();

    return $affiliation_id === FALSE ? FALSE : (string) $affiliation_id;
  }

}

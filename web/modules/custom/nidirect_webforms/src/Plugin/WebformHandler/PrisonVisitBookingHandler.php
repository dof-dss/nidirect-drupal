<?php

namespace Drupal\nidirect_webforms\Plugin\WebformHandler;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
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

    // Store the amend booking link unique identifier for this
    // submission so that it cannot be used again.
    $amend_booking_options = $webform_submission->getData()['amend_booking_options'] ?? NULL;
    $is_amended = $amend_booking_options === 'cancel' || $amend_booking_options === 'change' ?? FALSE;

    if ($is_amended && $booking_link_id = $webform_submission->getData()['bkg_link_uniqueid']) {

      $connection = Database::getConnection();

      // Store the unique identifier with the current timestamp.
      $connection->insert('prison_visit_booking_link_ids')
        ->fields([
          'unique_identifier' => $booking_link_id,
          'created' => \Drupal::service('datetime.time')->getRequestTime(),
        ])
        ->execute();
    }

    // For security reasons, the submission is never stored.
    $webform_submission->delete();
  }

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  public function prepareForm(WebformSubmissionInterface $webform_submission, $operation, FormStateInterface $form_state) {

    // Prepare form if a booking is being amended.
    $webform = $webform_submission->getWebform();

    // A prison visit booking confirmation email contains a link to
    // allow users to amend the booking. Since the original booking
    // submission is never stored, all the booking data is encrypted
    // and embedded in the link.

    // Early return if no booking data in the request.
    if ($this->request->query->has('booking') === FALSE) {
      $webform->setElementProperties('amend_booking_page', ['#access' => FALSE]);
      return;
    }

    // Get booking data from the request.
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
        $connection = Database::getConnection();

        $query = $connection->select('prison_visit_booking_link_ids', 'pvblink_ids')
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

      // Disable booking_reference_prisoner_reference wizard page
      // and set current page to amend_booking_page.
      $webform->setElementProperties('booking_reference_prisoner_reference', ['#access' => FALSE]);
      $webform_submission->setCurrentPage('amend_booking_page');
    }
  }

  /**
   * {@inheritdoc}
   * @throws \Exception
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    // Store references to form, form state, and submission for
    // easy access.
    $this->form = &$form;
    $this->formState = $form_state;
    $this->webformSubmission = $webform_submission;
    $this->elements = WebformFormHelper::flattenElements($form);

    $page = $form_state->get('current_page');
    $page_trigger = $form_state->getTriggeringElement()['#value'] ?? NULL;
    $page_trigger_is_next = $page_trigger === 'Next';
    $page_trigger_is_previous = $page_trigger === 'Previous';

    $elements = $this->elements;
    $webform = $webform_submission->getWebform();

    // Many form alterations here are dependent on data extracted from
    // the visit_order_number or "booking reference".
    //
    // The booking reference determines:
    // - The prison being visited
    // - Whether it is a face-to-face or virtual visit
    // - The date when the visit can be booked
    // - The prisoner type and time slots available for prisoner type.
    //
    // The booking reference is collected in the very first wizard page.
    // Once it is submitted and validated, form_state will contain the
    // processed booking reference (see processVisitBookingReference()).

    $this->bookingReference = $form_state->get('booking_reference_processed');

    // Pass configuration and the processed booking reference
    // to clientside.
    $form['#attached']['drupalSettings']['prisonVisitBooking'] = $this->configuration;
    $form['#attached']['drupalSettings']['prisonVisitBooking']['booking_ref'] = $this->bookingReference;

    // Detect if booking reference (visit_order_number) is changed.
    $last_ref = $form_state->get('last_visitor_order_number');
    $curr_ref = $form_state->getValue('visitor_order_number');

    // First-time set.
    if (empty($last_ref) && !empty($curr_ref)) {
      $form_state->set('last_visitor_order_number', $curr_ref);
    }
    // Changed.
    elseif (!empty($last_ref) && $curr_ref !== $last_ref) {
      $form_state->set('last_visitor_order_number', $curr_ref);

      // Reset time slots user might have selected.
      $this->resetFormSlots($form, $form_state, $webform_submission);

      // If it's a virtual visit reset elements applicable only to
      // face-to-face visits.
      if ($this->bookingReference['visit_type'] === 'virtual') {
        // Virtual visits have no additional visitors.
        $this->resetAdditionalVisitors();
        // Virtual visits have no special_requirements.
        $this->setFormElementValue('visitor_special_requirements_details');
      }
    }

    // Pre-populate the form with any booking amendment data. Only do
    // this once.
    $amend_booking_data = $form_state->get('amend_booking_data');
    $amend_booking_setup_complete = $form_state->get('amend_booking_setup_complete');

    if ($page === 'amend_booking_page' && $amend_booking_data && ($amend_booking_setup_complete !== TRUE || $page_trigger_is_previous)) {
      $this->alterFormToAmendBooking();
    }

    // Further alterations when amending a booking...
    if ($amend_booking_data) {

      if (!empty($amend_booking_data['error'])) {

        // Show custom error message.
        $elements['error_booking_already_amended']['#access'] = TRUE;

        // Hide some amendment related elements.
        $elements['booking_details']['#access'] = FALSE;
        $elements['amend_booking_options']['#access'] = FALSE;

        // Prevent user progressing to next wizard page.
        $elements['wizard_next']['#access'] = FALSE;

        // Early return.
        return;
      }

      // Amendment data is ok...
      // Process the visitor order number if not already processed.
      if (!$this->bookingReference) {
        $this->processVisitBookingReference($amend_booking_data['VISIT_ORDER_NO'], $form, $form_state, $webform_submission);
      }

      // If there are errors with the booking reference, we might have
      // to halt the amendment process, or only allow
      // specific amendments.

      $error_status = $this->bookingReference['error_status'] ?? FALSE;

      if ($error_status) {

        // Get any error messages.
        $error_status_msg = $this->bookingReference['error_status_msg'] ?? NULL;

        if ($error_status === self::VISIT_ORDER_REF_MISSING || $error_status === self::VISIT_ORDER_REF_INVALID) {
          // Cannot proceed with booking amendment.
          $elements['visit_order_ref_invalid']['#access'] = TRUE;
          $elements['booking_details']['#access'] = FALSE;
          $elements['amend_booking_options']['#access'] = FALSE;
          $elements['wizard_next']['#access'] = FALSE;

          // Early return.
          return;
        }
        elseif ($error_status === self::VISIT_ORDER_REF_EXPIRED) {
          // Cannot proceed with booking amendment.
          $elements['visit_order_ref_expired']['#access'] = TRUE;
          $elements['booking_details']['#access'] = FALSE;
          $elements['amend_booking_options']['#access'] = FALSE;
          $elements['wizard_next']['#access'] = FALSE;

          // Early return.
          return;
        }
        elseif ($error_status === self::VISIT_ORDER_REF_AMENDMENT_EXPIRED) {
          // Cannot proceed with booking amendment.
          $elements['visit_order_ref_amendment_expired']['#access'] = TRUE;
          $elements['booking_details']['#access'] = FALSE;
          $elements['amend_booking_options']['#access'] = FALSE;
          $elements['wizard_next']['#access'] = FALSE;

          // Early return.
          return;
        }
        elseif ($error_status === self::VISIT_ORDER_REF_NOTICE_EXCEEDED || $error_status === self::VISIT_ORDER_REF_AMENDMENT_NOTICE_EXCEEDED) {
          // Display message indicating notice period not met and time
          // must be changed. The notice period differs for face-to-face
          // versus virtual visits.
          if ($this->bookingReference['visit_type_id'] !== 'V') {
            $elements['booking_details_notice_exceeded_face_to_face']['#access'] = TRUE;
          }
          else {
            $elements['booking_details_notice_exceeded_virtual']['#access'] = TRUE;
          }
          // The time slot must be changed. Check the time slot option and
          // make it readonly so the user cannot uncheck it.
          $elements['choose_changes']['time_slot']['#attributes']['checked'] = TRUE;
          $elements['choose_changes']['time_slot']['#attributes']['readonly'] = 'readonly';
          $elements['choose_changes']['time_slot']['#attributes']['onclick'] = 'return false';
          $elements['choose_changes']['time_slot']['#label_attributes']['onclick'] = 'return false';
        }
        elseif ($error_status === self::VISIT_ORDER_REF_NO_SLOTS) {
          // Cannot amend time slot, but can edit visitor details.
          $elements['booking_details_ref_no_slots']['#access'] = TRUE;
          unset($elements['choose_changes']['#options']['time_slot']);
        }
        elseif ($error_status) {
          $elements['amend_error_default']['#access'] = TRUE;
          $elements['booking_details']['#access'] = FALSE;
          $elements['amend_booking_options']['#access'] = FALSE;
          $elements['wizard_next']['#access'] = FALSE;

          // Early return.
          return;
        }
      }
      else {
        // No issue with the visit order number, so the booking data is
        // amendable. Safe to show the booking details.
        $elements['booking_details']['#access'] = TRUE;
      }

      // Alter wizard page titles.
      $elements['main_visitor_details']['#title'] = $this->t('Amend main visitor details');
      $elements['additional_visitor_details']['#title'] = $this->t('Amend additional visitors');
      $elements['visitor_special_requirements']['#title'] = $this->t('Amend visitor special requirements');
      $elements['visit_preferred_day_and_time']['#title'] = $this->t('Amend visit date and time');
      $elements['webform_preview']['#title'] = $this->t('Amend booking confirmation');

      // Alter the hidden element tracking the amendment type.
      if ($form_state->getValue('amend_booking_options') === 'keep') {
        $this->setFormElementValue('amend_type', 'KEEP');
      }
      elseif ($form_state->getValue('amend_booking_options') === 'cancel') {
        $this->setFormElementValue('amend_type', 'CANCEL');
      }
      elseif ($form_state->getValue('amend_booking_options') === 'change') {
        $this->setFormElementValue('amend_type', 'UPDATE');
      }

      // Alter change options presented to the user.
      // Virtual visits have no additional visitors.
      if ($this->bookingReference['visit_type'] === 'virtual') {
        unset($elements['choose_changes']['#options']['additional_visitors']);
      }

      // Alterations at webform preview stage.
      if ($page === 'webform_preview') {

        if ($form_state->getValue('amend_booking_options') === 'keep') {
          $elements['preview']['#title'] = $this->t('Confirm keep booking');
          $elements['actions'][0]['#submit__label'] = $this->t('Keep booking');
        }
        elseif ($form_state->getValue('amend_booking_options') === 'cancel') {
          $elements['preview']['#title'] = $this->t('Confirm cancel booking');
          $elements['actions'][0]['#submit__label'] = $this->t('Cancel booking');
        }
        elseif ($form_state->getValue('amend_booking_options') === 'change') {
          $elements['preview']['#title'] = $this->t('Confirm amend booking');
          $elements['actions'][0]['#submit__label'] = $this->t('Amend booking');
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
            }

          }
        }
      }
      else {
        // There are no slots available.
        // Disable all slots.
        $elements['slots']['#access'] = FALSE;

        // Show a message.
        $elements['msg_no_slots_available']['#access'] = TRUE;

        // Prevent further progression.
        $elements['wizard_prev']['#access'] = FALSE;
        $elements['wizard_next']['#access'] = FALSE;
        $elements['preview_next']['#access'] = FALSE;
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
   * Alter form so that an existing booking can be amended.
   */
  private function alterFormToAmendBooking() {

    $amend_booking_data = $this->formState->get('amend_booking_data');

    // Populate the form.
    $this->setFormElementValue('visitor_order_number', $amend_booking_data['VISIT_ORDER_NO']);
    $this->setFormElementValue('prisoner_id', $amend_booking_data['INMATE_ID']);
    $this->setFormElementValue('visitor_1_id', $amend_booking_data['VISITOR_1_ID']);

    // Chop off time from visitor 1 DOB.
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

    if ($page === 'booking_reference_prisoner_reference') {
      $this->validateVisitBookingReference($form, $form_state, $webform_submission);
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
      $this->validateSlotPicked($form, $form_state);
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
   */
  private function processVisitBookingReference(string $booking_ref, array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $elements = WebformFormHelper::flattenElements($form);
    $amend_booking_data = $form_state->get('amend_booking_data') ?? NULL;

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

    // Extract various bits of the booking reference.
    $prison_id = substr($booking_ref, 0, 2);
    $visit_type_id = substr($booking_ref, 2, 1);
    $visit_week = (int) substr($booking_ref, 3, 2);
    $visit_year = (int) substr($booking_ref, 5, 2);
    $visit_year_full = (int) DrupalDateTime::createFromFormat('y', $visit_year)->format('Y');
    $visit_sequence = (int) substr($booking_ref, 8);

    // Determine prison name.
    if (array_key_exists($prison_id, $this->configuration['prisons'])) {
      $this->bookingReference['prison_id'] = $prison_id;
      $this->bookingReference['prison_name'] = $this->configuration['prisons'][$prison_id];
      $this->setFormElementValue('prison_name', $this->bookingReference['prison_name']);
    }
    else {
      // Invalid visit reference number, early return.
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_INVALID;
      $this->bookingReference['error_status_msg'] = $this->t('Visit reference number is invalid.');
      return;
    }

    // Determine the visit type.
    if (array_key_exists($visit_type_id, $this->configuration['visit_type'])) {
      $this->bookingReference['visit_type_id'] = $visit_type_id;
      $this->bookingReference['visit_type'] = $this->configuration['visit_type'][$visit_type_id];

      // The "E" visit type (enhanced) is synonymous with the 'F' type
      // and so is face-to-face and has same time slots.
      if ($visit_type_id === 'E') {
        $this->bookingReference['visit_type'] = $this->configuration['visit_type']['F'];
      }

      $this->setFormElementValue('visit_type', $this->bookingReference['visit_type']);
    }
    else {
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_INVALID;
      $this->bookingReference['error_status_msg'] = $this->t('Visit reference number is invalid.');
      return;
    }

    // Determine prisoner category and subcategory.
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
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_INVALID;
      $this->bookingReference['error_status_msg'] = $this->t('Visit reference number is invalid.');
      return;
    }

    // Determine various dates for the booking reference.
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

    // Determine the latest date a visit can be amended
    // (the booked slot datetime minus the advance notice).
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
      return;
    }
    elseif ($now < $booking_ref_max_advanced_issue_date) {
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_INVALID;
      $this->bookingReference['error_status_msg'] = $this->t('Visit reference number is not recognised.');
      return;
    }
    elseif ($now > $visit_latest_booking_date) {
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_NOTICE_EXCEEDED;
      $this->bookingReference['error_status_msg'] = $this->t('Visit reference number period of notice has expired.');
      return;
    }
    elseif ($amend_booking_date && $now > $amend_booking_date) {
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_AMENDMENT_EXPIRED;
      $this->bookingReference['error_status_msg'] = $this->t('The scheduled date and time for this visit have already passed, so amendments can no longer be made.');
      return;
    }

    // Finally get available slots for the booking reference and
    // do some final checks.
    $slots = $this->getAvailableSlots();
    $slots_error = $slots['error'] ?? FALSE;

    // If there are slots, and we are amending a booking, and the
    // notice period for amending the booking is exceeded...
    if ($slots && !$slots_error && $visit_latest_booking_amendment_date && $now > $visit_latest_booking_amendment_date) {
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_AMENDMENT_NOTICE_EXCEEDED;
      $this->bookingReference['error_status_msg'] = $this->t('The period of notice required for amending the visit date and time has not been met.');
    }
    // Else if there was an error getting slots.
    elseif ($slots_error) {
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_NO_DATA;
      $this->bookingReference['error_status_msg'] = $this->t('An error has occurred. Booking cannot proceed at this time. Try again later.');
    }
    // Else if there are no remaining slots.
    elseif (!$slots) {
      $this->bookingReference['error_status'] = self::VISIT_ORDER_REF_NO_SLOTS;
      $this->bookingReference['error_status_msg'] = $this->t('There are no time slots available for visit reference number @booking_ref.', ['@booking_ref' => $booking_ref]);
    }
    else {
      $this->bookingReference['available_slots'] = $slots;
    }

    // Keep the processed booking reference in form_state.
    $form_state->set('booking_reference_processed', $this->bookingReference);
  }

  /**
   * Validate visit booking reference.
   */
  private function validateVisitBookingReference(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {

    $booking_ref = !empty($form_state->getValue('visitor_order_number')) ? $form_state->getValue('visitor_order_number') : NULL;

    $this->processVisitBookingReference($booking_ref, $form, $form_state, $webform_submission);

    if (!empty($this->bookingReference['error_status'])) {
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
  private function validateSlotPicked(array &$form, FormStateInterface $form_state) {

    if ($form_state->get('current_page') !== 'visit_preferred_day_and_time') {
      return;
    }

    if ($form_state->getValue('slot1_datetime') && $form_state->getValue('amend_timeslot') === 'No') {
      return;
    }

    $form_values = array_filter($form_state->getValues(), function ($value, $key) {
      return str_contains($key, '_week_') && is_array($value) && !empty($value);
    }, ARRAY_FILTER_USE_BOTH);

    if (empty($form_values)) {
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

}

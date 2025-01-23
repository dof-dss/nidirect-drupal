<?php

namespace Drupal\nidirect_prisons\Plugin\WebformHandler;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\Utility\WebformFormHelper;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Prisoner Payments Webform Handler.
 *
 * @WebformHandler(
 *   id = "prisoner_payments",
 *   label = @Translation("Prisoner Payments"),
 *   category = @Translation("NIDirect"),
 *   description = @Translation("Handles making a payment to prisoner."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_IGNORED,
 *   submission = \Drupal\webform\Plugin\WebformHandlerInterface::SUBMISSION_OPTIONAL,
 * )
 */
class PrisonerPaymentsWebformHandler extends WebformHandlerBase {

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
   * The token manager.
   *
   * @var \Drupal\webform\WebformTokenManagerInterface
   */
  protected $tokenManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tokenManager = $container->get('webform.token_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'debug' => FALSE,
      'prisoner_maximum_payment_amount' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    // Development.
    $form['development'] = [
      '#type' => 'details',
      '#title' => $this->t('Development settings'),
    ];
    $form['development']['debug'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debugging'),
      '#description' => $this->t('If checked, every handler method invoked will be displayed onscreen to all users.'),
      '#return_value' => TRUE,
      '#default_value' => $this->configuration['debug'],
    ];

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['debug'] = (bool) $form_state->getValue('debug');
  }

  /**
   * {@inheritdoc}
   */
  public function alterElements(array &$elements, WebformInterface $webform) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function overrideSettings(array &$settings, WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function alterForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);

    // Store references to form, form state, and submission for
    // easy access.
    $this->form = &$form;
    $this->formState = $form_state;
    $this->webformSubmission = $webform_submission;
    $this->elements = WebformFormHelper::flattenElements($form);

    $page = $form_state->get('current_page');
    $elements = WebformFormHelper::flattenElements($form);
    $webform = $webform_submission->getWebform();

    if ($page === 'page_payment_amount') {

      // The prisoner_id to be paid.
      $prisoner_id = $form_state->getValue('prisoner_id') ?? NULL;

      // The maximum amount a prisoner can be paid.
      $prisoner_max_amount = $this->getPrisonerPaymentMaxAmount($prisoner_id);

      if ($prisoner_max_amount > 0) {
        // Set a form element value for display in the webform.
        $this->setFormElementValue('prisoner_max_amount', $prisoner_max_amount);

        // Pass to clientside JS for validation purposes.
        $form['#attached']['drupalSettings']['prisonerPayments']['prisonerMaxAmount'] = $prisoner_max_amount;
      }
      else {
        // Cannot enter an amount to pay, nor proceed to next step.
        $elements['prisoner_payment_amount']['#access'] = FALSE;
        $elements['wizard_next']['#access'] = FALSE;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
    $page = $form_state->get('current_page');

    if ($page === 'page_prisoner_and_visitor_id') {

      $visitor_fullname = $form_state->getValue('visitor_fullname');
      $visitor_id = $form_state->getValue('visitor_id');

      $prisoner_fullname = $form_state->getValue('prisoner_fullname');
      $prisoner_id = $form_state->getValue('prisoner_id');

      // Validate visitor and prisoner names contain a first name and
      // last name.

      /*
       * Regex to check for:
       * - At least one first name.
       * - Optional middle names.
       * - At least one last name.
       *
       * \p{L}: Matches any Unicode letter.
       *
       */
      $regex = '/^\p{L}+(?:[ \-\'\p{L}]*\p{L})*(\s+\p{L}+(?:[ \-\'\p{L}]*\p{L})*)+\s+\p{L}+(?:[ \-\'\p{L}]*\p{L})*$/u';

      // Validate the "visitor_fullname" field.
      if (!preg_match($regex, $visitor_fullname)) {
        // Add an error if the full name is invalid.
        $form_state->setErrorByName('visitor_fullname', $this->t('Please enter a valid full name with at least a first and last name.'));
      }


      // Validate the visitor_id is nominated by prisoner_id to
      // make payments.
      $visitor_ids = $this->getPrisonerNominatedVisitorIds($prisoner_id) ?? [];

      if (!in_array($visitor_id, $visitor_ids)) {
        $form_state->setErrorByName('visitor_id', $this->t('Check your visitor ID is correct and the prisoner has nominated you to make payments to them'));
        $form_state->setErrorByName('prisoner_id', $this->t('Check prisoner ID is correct'));
      }
    }

    if ($page === 'page_payment_amount') {

      $prisoner_id = $form_state->getValue('prisoner_id');
      $prisoner_payment_amount = $form_state->getValue('prisoner_payment_amount');
      $prisoner_max_amount = $this->getPrisonerPaymentMaxAmount($prisoner_id);

      if ($prisoner_max_amount == 0) {
        $form_state->setError($form, $this->t('Prisoner has reached the maximum amount payable for this week. Try again next week.'));
      }

      if ($prisoner_payment_amount == 0 || $prisoner_payment_amount > $prisoner_max_amount) {
        $form_state->setErrorByName('prisoner_payment_amount', $this->t('Amount must be more than &pound;0'));
      }

      if ($prisoner_payment_amount > $prisoner_max_amount) {
        $form_state->setErrorByName('prisoner_payment_amount', $this->t('Amount must be &pound;@max or less', ['@max' => $prisoner_max_amount]));
      }

    }


  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function confirmForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function preCreate(array &$values) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function postCreate(WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function postLoad(WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function preDelete(WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function postDelete(WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    $this->debug(__FUNCTION__, $update ? 'update' : 'insert');
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessConfirmation(array &$variables) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function createHandler() {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function updateHandler() {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteHandler() {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function createElement($key, array $element) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function updateElement($key, array $element, array $original_element) {
    $this->debug(__FUNCTION__);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteElement($key, array $element) {
    $this->debug(__FUNCTION__);
  }

  /**
   * Display the invoked plugin method to end user.
   *
   * @param string $method_name
   *   The invoked method name.
   * @param string $context1
   *   Additional parameter passed to the invoked method name.
   */
  protected function debug($method_name, $context1 = NULL) {
    if (!empty($this->configuration['debug'])) {
      $t_args = [
        '@id' => $this->getHandlerId(),
        '@class_name' => get_class($this),
        '@method_name' => $method_name,
        '@context1' => $context1,
      ];
      $this->messenger()->addWarning($this->t('Invoked @id: @class_name:@method_name @context1', $t_args), TRUE);
    }
  }

  /**
   * Sets the value of a form element in $form, $form_state, and $webform_submission.
   *
   * @param string $element_key
   *   The key of the element whose value you want to set.
   * @param mixed $element_value
   *   The value to set for the element.
   */
  protected function setFormElementValue(string $element_key, mixed $element_value) {
    $this->formState->setValue($element_key, $element_value);
    $this->webformSubmission->setElementData($element_key, $element_value);
    if (isset($this->elements[$element_key])) {
      $this->elements[$element_key]['#default_value'] = $element_value;
    }
  }

  /**
   * Get the maximum amount a prisoner can be paid.
   */
  protected function getPrisonerPaymentMaxAmount(string $prisoner_id) {

    $prisoner_max_amount = 0.00;

    // Try to get the current maximum from db.
    try {
      $connection = Database::getConnection();

      $query = $connection->select('prisoner_payment_amount', 'pp_amount')
        ->fields('pp_amount', ['amount'])
        ->condition('prisoner_id', $prisoner_id);

      $result = $query->execute()->fetchField();

      if ($result) {
        $prisoner_max_amount = floatval($result);
      }
    }
    catch (\Exception $e) {
      $this->getLogger('nidirect_prisons')->error('Database error: @message', ['@message' => $e->getMessage()]);
    }

    return $prisoner_max_amount;
  }

  /**
   * Get nominated visitor IDs who can make payments to a prisoner ID.
   */
  protected function getPrisonerNominatedVisitorIds(string $prisoner_id) {

    $nominated_visitor_ids = [];

    try {
      $connection = \Drupal::database();
      $query = $connection->select('prisoner_payment_nominees', 'ppn');
      $query->fields('ppn', ['visitor_ids']);
      $query->condition('prisoner_id', $prisoner_id);
      $result = $query->execute()->fetchField();

      if ($result) {
        $nominated_visitor_ids = explode(',', $result);
      }
    }
    catch (\Exception $e) {
      $this->getLogger('nidirect_prisons')->error('Database error: @message', ['@message' => $e->getMessage()]);

      return NULL;
    }

    return $nominated_visitor_ids;
  }

}

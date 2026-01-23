<?php

namespace Drupal\nidirect_prisons\Plugin\WebformHandler;

use Drupal\Core\Form\FormStateInterface;
use Drupal\nidirect_prisons\Service\PrisonerPaymentManager;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\Utility\WebformFormHelper;
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
   * @var \Drupal\nidirect_prisons\Service\PrisonerPaymentManager
   */
  protected PrisonerPaymentManager $paymentManager;

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
   * The transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected $transliteration;

  /**
   * Timeouts for making a payment in seconds.
   * HARD_TIMEOUT for completing the entire process 30 mins.
   * SOFT_TIMEOUT for inactivity 10 mins.
   */
  public const HARD_TIMEOUT = 1800;
  public const SOFT_TIMEOUT = 600;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tokenManager = $container->get('webform.token_manager');
    $instance->transliteration = $container->get('transliteration');
    /** @var \Drupal\nidirect_prisons\Service\PrisonerPaymentManager $paymentManager */
    $instance->paymentManager = $container->get('nidirect_prisons.prisoner_payment_manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'debug' => FALSE,
      'prisoner_maximum_payment_amount' => 0,
      'worldpay_payment_service_url' => 'https://secure-test.worldpay.com/jsp/merchant/xml/paymentService.jsp',
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
    $form['worldpay'] = [
      '#type' => 'details',
      '#title' => $this->t('Worldpay settings'),
    ];
    $form['worldpay']['payment_service_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Payment service URL'),
      '#description' => $this->t('The URL for sending XML payment requests to. See https://docs.worldpay.com/apis/wpg/hostedintegration/quickstart for more information.'),
      '#default_value' => $this->configuration['worldpay_payment_service_url'],
    ];

    return $this->setSettingsParents($form);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['debug'] = (bool) $form_state->getValue('debug');
    $this->configuration['worldpay_payment_service_url'] = (string) $form_state->getValue('worldpay_payment_service_url');
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
    $is_prev_triggered = isset($form_state->getTriggeringElement()['#id']) && $form_state->getTriggeringElement()['#id'] === 'edit-actions-wizard-prev';
    $is_next_triggered = isset($form_state->getTriggeringElement()['#id']) && $form_state->getTriggeringElement()['#id'] === 'edit-actions-wizard-next';

    $elements = WebformFormHelper::flattenElements($form);
    $webform = $webform_submission->getWebform();

    /*if ($page === 'page_prisoner_and_visitor_id' || $page === 'page_payment_amount') {

      // If user hit previous, then there is an existing order_code
      // and pending transaction which needs to be removed.
      if ($is_prev_triggered) {
        $prev_order_code = $form_state->get('order_code');
        $prisoner_id = $form_state->get('prisoner_id');

        if ($prev_order_code && $prisoner_id) {
          $this->paymentManager->deleteTransaction($prev_order_code);
          $this->cancelWorldpayOrder($prev_order_code, $prisoner_id);
        }
      }
    }*/

    if ($page === 'page_payment_amount') {

      // The prisoner_id to be paid.
      $prisoner_id = $form_state->getValue('prisoner_id');

      // The visitor_id making the payment.
      $visitor_id = $form_state->getValue('visitor_id');

      // The maximum amount a prisoner can be paid.
      $prisoner_max_amount = $this->paymentManager->getPrisonerPaymentMaxAmount($prisoner_id);

      // Set a form element value for display in the webform.
      $this->setFormElementValue('prisoner_max_amount', $prisoner_max_amount);

      // Pass to clientside JS for validation purposes.
      $form['#attached']['drupalSettings']['prisonerPayments']['prisonerMaxAmount'] = $prisoner_max_amount;

      // Stop progress if prisoner amount that can be paid is 0.
      if ($prisoner_max_amount == 0) {

        // Show msg_payment_limit_reached.
        $elements['msg_payment_limit_reached']['#access'] = TRUE;

        // Prevent further progress.
        $elements['prisoner_payment_amount']['#access'] = FALSE;
        $elements['wizard_next']['#access'] = FALSE;
        return;
      }

      // Stop progress if there is an unexpired pending transaction
      // from another visitor.
      $pending_transaction = $this->paymentManager->getPendingTransactionForPrisoner($prisoner_id);

      if ($pending_transaction) {

        // Expire if needed.
        if ($this->paymentManager->expireIfTimedOut($pending_transaction)) {
          $pending_transaction = NULL;
        }
        // Still active and owned by someone else → block.
        elseif ($pending_transaction->visitor_id !== $visitor_id) {
          $elements['msg_payment_pending']['#access'] = TRUE;
          $elements['prisoner_payment_amount']['#access'] = FALSE;
          $elements['wizard_next']['#access'] = FALSE;
          return;
        }
      }

      $prison_id = $this->paymentManager->getPrisonId($prisoner_id);

      if ($pending_transaction && $pending_transaction->visitor_id === $visitor_id) {
        // Resume existing transaction.
        $order_code = $pending_transaction->order_key;
      }
      else {
        // Create new transaction.
        $order_code = $this->paymentManager->generateOrderCode($prison_id, $prisoner_id, $visitor_id);
        $this->paymentManager->logPendingTransaction(
          $order_code,
          $prisoner_id,
          $visitor_id,
          0
        );
      }

      // Keep order_code and prison_id for later.
      $form_state->set('order_code', $order_code);
      $form_state->set('prison_id', $prison_id);

      // Show msg_maximum_amount_payable.
      $elements['msg_maximum_amount_payable']['#access'] = TRUE;
    }

    if ($page === 'page_payment_card_details') {

      $transaction = $this->paymentManager->getTransaction($order_code);

      if (!$transaction || $this->paymentManager->expireIfTimedOut($transaction)) {
        $elements['page_payment_card_details']['#access'] = FALSE;
        $elements['submit']['#access'] = FALSE;

        \Drupal::messenger()->addError(
          $this->t('Your payment session has expired. Please start again.')
        );
        return;
      }


      $prisoner_fullname = $form_state->getValue('prisoner_fullname');
      $prisoner_id = $form_state->getValue('prisoner_id');
      $prison_id = $form_state->get('prison_id');

      $visitor_fullname = $form_state->getValue('visitor_fullname');
      $visitor_id = $form_state->getValue('visitor_id');
      $visitor_email = $form_state->getValue('visitor_email');

      $order_code = $form_state->get('order_code');
      $payment_amount = (float) $form_state->getValue('prisoner_payment_amount');

      // Update pending transaction with the payment amount.
      $this->paymentManager->updatePendingTransactionAmount($order_code, $payment_amount);

      // Update transaction timestamp.
      $this->paymentManager->touchTransaction($order_code);

      // Add clientside timeout countdown.
      $form['#attached']['library'][] = 'nidirect_prisons/prisoner_payments_timeout';
      $form['#attached']['drupalSettings']['prisonerPayments'] = [
        'orderCode' => $order_code,
        'softTimeout' => self::SOFT_TIMEOUT,
      ];

      // Generate and send Worldpay order XML request.
      $order_data_xml = $this->paymentManager->generateOrderData(
        $order_code,
        $prison_id,
        $prisoner_id,
        $prisoner_fullname,
        $payment_amount,
        $visitor_fullname,
        $visitor_email
      );

      $response_xml = $this->paymentManager->sendWorldpayRequest($order_data_xml, $prison_id);

      // Parse the Worldpay response to get the iframe URL.
      if ($response_xml && $response = $this->paymentManager->parseWorldpayResponse($response_xml)) {

        // Attach the Worldpay JS library and pass the iframe URL to drupalSettings.
        $form['#attached']['library'][] = 'nidirect_prisons/prisoner_payments_worldpay';
        $form['#attached']['drupalSettings']['worldpay'] = [
          'url' => $response['reference_url'],
          'target' => 'worldpay-html',
        ];

        // Add a container for the iframe.
        $form['elements']['page_payment_card_details']['worldpay_container'] = [
          '#markup' => '<h3>Debit card details</h3><div id="worldpay-html"></div>',
          '#allowed_tags' => ['div', 'h3'],
        ];

        // Hide submit.
        $form['actions']['submit']['#attributes']['class'][] = 'visually-hidden';

      }
      else {

        // Something went wrong with the payment request to Worldpay.
        // Update transaction status as failed.
        $this->paymentManager->updateTransactionStatus($order_code, 'expired');

        // Prevent further progress.
        $elements['page_payment_card_details']['#access'] = FALSE;
        $elements['submit']['#access'] = FALSE;

        \Drupal::messenger()->addError($this->t('An error occurred while processing your request. Try again later.'));
        $this->getLogger('nidirect_prisons')->error('Failed to parse Worldpay response: @response', [
          '@response' => $response_xml,
        ]);
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

      // Validate full name fields.
      // A full name is valid if it only contains latin alphabet
      // letters, hyphens, single apostrophes, and spaces. And it must
      // contain a first name and last name (each with at least one
      // letter once non-letter characters are removed).

      // Regex pattern to match latin alphabet letters, hyphens, single
      // apostrophes, and spaces.
      $pattern_match = '/^[\p{Latin}\-.\'\s]+$/u';

      // Regex pattern to replace any character that is not a latin
      // alphabet letter or a space.
      $pattern_replace = '/[^\p{Latin}\s]/u';

      if (!preg_match($pattern_match, $visitor_fullname)) {
        $form_state->setErrorByName('visitor_fullname', $this->t('Your name must contain letters (A–Z), hyphens (-), periods (.), apostrophes (\') and spaces only.'));
      }
      elseif (count(array_filter(array_map('trim', explode(" ", preg_replace($pattern_replace, '', $visitor_fullname))))) < 2) {
        $form_state->setErrorByName('visitor_fullname', $this->t('Your name must include both a first and last name separated by a space.'));
      }

      if (!preg_match($pattern_match, $prisoner_fullname)) {
        $form_state->setErrorByName('prisoner_fullname', $this->t('Prisoner name must contain letters (A–Z), hyphens (-), periods (.), apostrophes (\') and spaces only.'));
      }
      elseif (count(array_filter(array_map('trim', explode(" ", preg_replace($pattern_replace, '', $prisoner_fullname))))) < 2) {
        $form_state->setErrorByName('prisoner_fullname', $this->t('Prisoner name must include both a first and last name separated by a space.'));
      }

      // Validate the visitor_id is nominated by prisoner_id to
      // make payments.
      $visitor_ids = $this->paymentManager->getPrisonerNominatedVisitorIds($prisoner_id) ?? [];

      if (!in_array($visitor_id, $visitor_ids)) {
        $form_state->setErrorByName('visitor_id', $this->t('Check your visitor ID is correct and the prisoner has nominated you to make payments to them.'));
        $form_state->setErrorByName('prisoner_id', $this->t('Check prisoner ID is correct.'));
      }
    }

    if ($page === 'page_payment_amount') {

      $prisoner_id = $form_state->getValue('prisoner_id');
      $prisoner_payment_amount = $form_state->getValue('prisoner_payment_amount');
      $prisoner_max_amount = $this->paymentManager->getPrisonerPaymentMaxAmount($prisoner_id);

      if ($prisoner_max_amount == 0) {
        $form_state->setError($form, $this->t('The payment limit for this prisoner has been reached. Try again next week.'));
      }
      elseif (!is_numeric($prisoner_payment_amount) || $prisoner_payment_amount < 0.01 || $prisoner_payment_amount > $prisoner_max_amount) {
        $form_state->setErrorByName('prisoner_payment_amount', $this->t('Amount must be between &pound;0.01 and &pound;@max.', ['@max' => number_format($prisoner_max_amount, 2, '.', '')]));
      }
    }

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $this->debug(__FUNCTION__);

    $webform = $webform_submission->getWebform();
    $page = $form_state->get('current_page');

    if ($page === 'webform_confirmation') {

      // Retrieve the JSON response and order key.
      $response_data_json = $form_state->getValue('wp_response');

      // Decode JSON response.
      $response_data = json_decode($response_data_json, TRUE);
      if (json_last_error() !== JSON_ERROR_NONE) {
        $this->getLogger('nidirect_prisons')->error('Invalid Worldpay response: @error', [
          '@error' => json_last_error_msg(),
        ]);

        \Drupal::messenger()->addError(t('Payment verification failed. Contact the administrator.'));
        return;
      }

      // Verify response integrity.
      if (!$this->paymentManager->isValidWorldpayResponse($response_data)) {
        $this->getLogger('nidirect_prisons')->alert('Worldpay response verification failed for order key: @orderKey', [
          '@orderKey' => $response_data['order']['orderKey'],
        ]);

        \Drupal::messenger()->addError(t('Payment verification failed. Contact the administrator.'));
        return;
      }

      // Extract order details.
      $order_status = $response_data['order']['status'];

      if ($order_status === 'success') {
        $webform->setSetting('confirmation_message', $webform->getElement('webform_confirmation_success')['#markup']);
      }
      else {
        $webform->setSetting('confirmation_message', $webform->getElement('webform_confirmation_failure')['#markup']);
      }
    }
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

}

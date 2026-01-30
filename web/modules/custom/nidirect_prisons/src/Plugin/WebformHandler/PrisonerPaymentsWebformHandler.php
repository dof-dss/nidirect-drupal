<?php

namespace Drupal\nidirect_prisons\Plugin\WebformHandler;

use Drupal\Component\Datetime\TimeInterface;
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
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

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
   * {@inheritdoc}
   *
   * @return \Drupal\nidirect_prisons\Plugin\WebformHandler\PrisonerPaymentsWebformHandler|\Drupal\webform\Plugin\WebformHandlerBase
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tokenManager = $container->get('webform.token_manager');
    $instance->transliteration = $container->get('transliteration');
    $instance->paymentManager = $container->get('nidirect_prisons.prisoner_payment_manager');
    $instance->time = $container->get('datetime.time');
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
    $elements = $this->elements;
    $webform = $webform_submission->getWebform();

    // Attach library.
    $form['#attached']['library'][] = 'nidirect_prisons/prisoner_payments';

    if ($page === 'page_prisoner_and_visitor_id' && $form_state->get('order_code')) {
      $elements['msg_payment_in_progress']['#access'] = TRUE;
      $elements['wizard_next']['#access'] = FALSE;
    }

    if ($page === 'page_payment_amount' || $page === 'page_payment_card_details') {
      // Add clientside timeout countdown.
      $form['#attached']['library'][] = 'nidirect_prisons/prisoner_payments_timeout';

      // Hide Previous once a transaction exists.
      if ($form_state->get('order_code')) {
        $elements['wizard_prev']['#access'] = FALSE;

        // Add Cancel button.
        $form['actions']['cancel_payment'] = [
          '#type' => 'submit',
          '#value' => $this->t('Cancel payment'),
          '#submit' => [[static::class, 'cancelPaymentSubmit']],
          '#limit_validation_errors' => [],
          '#weight' => -10,
        ];
      }
    }

    if ($page === 'page_payment_amount') {

      // Created timestamp for tracking timeout on pending transactions.
      $created_timestamp = $this->time->getRequestTime();

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
        if ($pending_transaction->status !== 'pending') {
          $pending_transaction = NULL;
        }
        // Still active and owned by someone else, halt progress.
        elseif ($pending_transaction->visitor_id !== $visitor_id) {
          $elements['msg_payment_pending']['#access'] = TRUE;
          $elements['prisoner_payment_amount']['#access'] = FALSE;
          $elements['wizard_next']['#access'] = FALSE;
          return;
        }
      }

      $prison_id = $this->paymentManager->getPrisonId($prisoner_id);

      // Check for existing pending transaction from the current
      // visitor. Since we are on page_payment_amount, the visitor can
      // change the amount. Worldpay does not allow the amount in
      // existing orders to be changed.  So cancel the order and expire
      // the transaction before creating a new one.
      if ($pending_transaction && $pending_transaction->visitor_id === $visitor_id) {

        $old_order_code = $pending_transaction->order_key;
        $now = $this->time->getRequestTime();

        // Preserve original created time ONLY if still within
        // hard timeout. This prevents resurrecting a transaction that
        // is already hard-expired (e.g. if cron has not run).
        if (($now - $pending_transaction->created_timestamp) < $this->paymentManager::HARD_TIMEOUT) {
          $created_timestamp = $pending_transaction->created_timestamp;
        }
        else {
          // Hard timeout exceeded — start a fresh attempt.
          $created_timestamp = $now;
        }

        // Cancel Worldpay order for the pending transaction.
        $this->paymentManager->cancelWorldpayOrder(
          $old_order_code,
          $prison_id
        );

        // Expire the pending transaction.
        $this->paymentManager->updateTransactionStatus(
          $old_order_code,
          'expired'
        );
      }

      $order_code = $this->paymentManager->generateOrderCode(
        $prison_id,
        $prisoner_id,
        $visitor_id
      );

      $new_transaction = $this->paymentManager->logPendingTransaction(
        $order_code,
        $prisoner_id,
        $visitor_id,
        0,
        $created_timestamp
      );

      // Clientside timeout countdown needs order code, timeout values
      // and the start time for the new transaction.
      $form['#attached']['drupalSettings']['prisonerPayments'] = [
        'orderCode' => $order_code,
        'softTimeout' => $this->paymentManager::SOFT_TIMEOUT,
        'hardTimeout' => $this->paymentManager::HARD_TIMEOUT,
        'startTime' => (int) $new_transaction->created_timestamp,
      ];

      // Keep order_code and prison_id for later.
      $form_state->set('order_code', $order_code);
      $form_state->set('prison_id', $prison_id);

      // Set flag to indicate new order not sent to worldpay yet.
      $form_state->set('worldpay_order_sent', FALSE);

      // Show msg_maximum_amount_payable.
      $elements['msg_maximum_amount_payable']['#access'] = TRUE;
    }

    if ($page === 'page_payment_card_details') {

      $prisoner_fullname = $form_state->getValue('prisoner_fullname');
      $prisoner_id = $form_state->getValue('prisoner_id');
      $prison_id = $form_state->get('prison_id');

      $visitor_fullname = $form_state->getValue('visitor_fullname');
      $visitor_id = $form_state->getValue('visitor_id');
      $visitor_email = $form_state->getValue('visitor_email');

      $order_code = $form_state->get('order_code');
      $payment_amount = (float) $form_state->getValue('prisoner_payment_amount');

      // Get the transaction.
      $transaction = $this->paymentManager->getTransaction($order_code);

      // Halt progress if transaction expired.
      if (!$transaction || $transaction->status === 'expired') {
        $elements['twig_payment_card_details']['#access'] = FALSE;
        $elements['msg_session_expired']['#access'] = TRUE;
        $elements['wizard_prev']['#access'] = FALSE;
        $elements['submit']['#access'] = FALSE;
        return;
      }

      // Update pending transaction with the payment amount.
      $this->paymentManager->updatePendingTransactionAmount($order_code, $payment_amount);

      // Update transaction timestamp.
      $this->paymentManager->touchTransaction($order_code);

      // Clientside timeout countdown needs order code, timeout values
      // and the start time for the transaction.
      $form['#attached']['drupalSettings']['prisonerPayments'] = [
        'orderCode' => $order_code,
        'softTimeout' => $this->paymentManager::SOFT_TIMEOUT,
        'hardTimeout' => $this->paymentManager::HARD_TIMEOUT,
        'startTime' => (int) $transaction->created_timestamp,
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

      // Prevent duplicate Worldpay order creation on form rebuilds.
      if (!$form_state->get('worldpay_order_sent')) {

        $response_xml = $this->paymentManager->sendWorldpayRequest(
          $order_data_xml,
          $prison_id
        );

        // Mark as sent for this form/session.
        $form_state->set('worldpay_order_sent', TRUE);
      }
      else {
        // Order already sent; do not resend.
        $response_xml = NULL;
      }

      // Parse the Worldpay response to get the iframe URL.
      if ($response_xml && $response = $this->paymentManager->parseWorldpayResponse($response_xml)) {

        // Attach the Worldpay JS library and pass the iframe URL to drupalSettings.
        $form['#attached']['library'][] = 'nidirect_prisons/prisoner_payments_worldpay';
        $form['#attached']['drupalSettings']['worldpay'] = [
          'url' => $response['reference_url'],
          'target' => 'worldpay-html',
        ];

        // Add a container for the iframe.
        $form['elements']['page_payment_card_details']['worldpay_container']['card_details'] = [
          '#markup' => '<h3>Debit card details</h3><div id="worldpay-html"></div>',
          '#allowed_tags' => ['div', 'h3'],
        ];

        // Hide submit.
        $form['actions']['submit']['#attributes']['class'][] = 'visually-hidden';

      }
      else {

        // Something went wrong with the payment request to Worldpay.
        // Update transaction status as failed.
        $this->paymentManager->updateTransactionStatus($order_code, 'failed');

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

        $webform->setSetting('confirmation_message', $webform->getElement('webform_confirmation_failure')['#markup']);
        \Drupal::messenger()->addError(t('Payment verification failed. Contact the administrator.'));
        return;
      }

      // Verify response integrity.
      if (!$this->paymentManager->isValidWorldpayResponse($response_data)) {
        $this->getLogger('nidirect_prisons')->alert('Worldpay response verification failed for order key: @orderKey', [
          '@orderKey' => $response_data['order']['orderKey'],
        ]);

        $webform->setSetting('confirmation_message', $webform->getElement('webform_confirmation_failure')['#markup']);
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
   * Submit handler for cancelling a payment.
   */
  public static function cancelPaymentSubmit(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\nidirect_prisons\Plugin\WebformHandler\PrisonerPaymentsWebformHandler $handler */
    $handler = $form_state->getFormObject()->getWebform()->getHandler('prisoner_payments');

    $order_code = $form_state->get('order_code');
    $prison_id = $form_state->get('prison_id');

    if ($order_code && $prison_id) {
      $handler->cancelTransaction($order_code, $prison_id);
    }

    // Clear wizard state.
    $form_state->set('order_code', NULL);
    $form_state->set('prison_id', NULL);
    $form_state->set('worldpay_order_sent', FALSE);

    // Restart the form.
    $form_state->setRedirect('<current>');
    $form_state->setRebuild(FALSE);
  }

  /**
   * Cancels a pending transaction and cleans up external state.
   */
  protected function cancelTransaction(string $order_code, string $prison_id): void {

    $transaction = $this->paymentManager->getTransaction($order_code);

    if (!$transaction || $transaction->status !== 'pending') {
      return;
    }

    // Cancel Worldpay order if created.
    $this->paymentManager->cancelWorldpayOrder($order_code, $prison_id);

    // Mark transaction as cancelled (not expired).
    $this->paymentManager->updateTransactionStatus($order_code, 'cancelled');

    $this->getLogger('nidirect_prisons')->info(
      'Payment cancelled by user for order @order',
      ['@order' => $order_code]
    );
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

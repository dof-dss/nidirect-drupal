<?php

namespace Drupal\nidirect_prisons\Plugin\WebformHandler;

use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormStateInterface;
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
   * The transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected $transliteration;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->tokenManager = $container->get('webform.token_manager');
    $instance->transliteration = $container->get('transliteration');
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

    if ($page === 'page_prisoner_and_visitor_id' || $page === 'page_payment_amount') {

      // If user hit previous, then there is an existing order_code
      // and pending transaction which needs to be removed.
      if ($is_prev_triggered && $prev_order_code = $form_state->get('order_code')) {
        $this->deleteTransaction($prev_order_code);
      }
    }

    if ($page === 'page_payment_amount') {

      // The prisoner_id to be paid.
      $prisoner_id = $form_state->getValue('prisoner_id');

      // The visitor_id making the payment.
      $visitor_id = $form_state->getValue('visitor_id');

      // The maximum amount a prisoner can be paid.
      $prisoner_max_amount = $this->getPrisonerPaymentMaxAmount($prisoner_id);

      // Set a form element value for display in the webform.
      $this->setFormElementValue('prisoner_max_amount', $prisoner_max_amount);

      // Pass to clientside JS for validation purposes.
      $form['#attached']['drupalSettings']['prisonerPayments']['prisonerMaxAmount'] = $prisoner_max_amount;

      // Stop progress if there is pending transaction from
      // another visitor.
      $pending_transaction = $this->getPendingTransactions($prisoner_id);

      if ($pending_transaction) {

        // Check if the pending transaction has expired
        // (30 minute timeout).
        $timeout_threshold = \Drupal::time()->getRequestTime() - 1800;

        if ($pending_transaction->created_timestamp < $timeout_threshold) {
          // Mark transaction as expired.
          $this->updateTransactionStatus($pending_transaction->order_key, 'expired');
        }
        else {
          // Visitor cannot proceed. Show payment pending message.
          $elements['msg_payment_pending']['#access'] = TRUE;

          // Prevent further progress.
          $elements['prisoner_payment_amount']['#access'] = FALSE;
          $elements['wizard_next']['#access'] = FALSE;
          return;
        }
      }

      // Stop progress if prisoner amount that can be paid is 0.
      if ($prisoner_max_amount == 0) {

        // Show msg_payment_limit_reached.
        $elements['msg_payment_limit_reached']['#access'] = TRUE;

        // Prevent further progress.
        $elements['prisoner_payment_amount']['#access'] = FALSE;
        $elements['wizard_next']['#access'] = FALSE;
        return;
      }

      // Get prison_id, generate order code and log pending transaction.
      $prison_id = $this->getPrisonId($prisoner_id);
      $order_code = $this->generateOrderCode($prison_id, $prisoner_id, $visitor_id);

      // Keep order_code and prison_id for later.
      $form_state->set('order_code', $order_code);
      $form_state->set('prison_id', $prison_id);

      // Log pending transaction of £0 (as we don't know the
      // amount yet).
      $this->logPendingTransaction($order_code, $prisoner_id, $visitor_id, 0);

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

      // Update pending transaction with the payment amount.
      $this->updatePendingTransactionAmount($order_code, $payment_amount);

      // Generate and send Worldpay order XML request.
      $order_data_xml = $this->generateOrderData(
        $order_code,
        $prison_id,
        $prisoner_id,
        $prisoner_fullname,
        $payment_amount,
        $visitor_fullname,
        $visitor_email
      );

      $response_xml = $this->sendWorldpayRequest($order_data_xml);

      // Parse the Worldpay response to get the iframe URL.
      if ($response_xml && $response = $this->parseWorldpayResponse($response_xml)) {

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
        $this->updateTransactionStatus($order_code, 'failed');

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
      $visitor_ids = $this->getPrisonerNominatedVisitorIds($prisoner_id) ?? [];

      if (!in_array($visitor_id, $visitor_ids)) {
        $form_state->setErrorByName('visitor_id', $this->t('Check your visitor ID is correct and the prisoner has nominated you to make payments to them.'));
        $form_state->setErrorByName('prisoner_id', $this->t('Check prisoner ID is correct.'));
      }
    }

    if ($page === 'page_payment_amount') {

      $prisoner_id = $form_state->getValue('prisoner_id');
      $prisoner_payment_amount = $form_state->getValue('prisoner_payment_amount');
      $prisoner_max_amount = $this->getPrisonerPaymentMaxAmount($prisoner_id);

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
      if (!$this->isValidWorldpayResponse($response_data)) {
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

  /**
   * Get the maximum amount a prisoner can be paid.
   *
   * @param string $prisoner_id
   *   The prisoner id.
   * @return float|void
   *   The amount.
   */
  protected function getPrisonerPaymentMaxAmount(string $prisoner_id) {
    try {
      $database = \Drupal::database();

      // Get the maximum allowed payment amount (£100 or less).
      $max_amount = $database->select('prisoner_payment_amount', 'ppa')
        ->fields('ppa', ['amount'])
        ->condition('ppa.prisoner_id', $prisoner_id)
        ->execute()
        ->fetchField();

      return round((float) $max_amount, 2);
    }
    catch (\Exception $e) {
      $this->getLogger('nidirect_prisons')->error('Database error: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * Get the prison id for the prison which receives payments
   * for the prisoner.
   *
   * @param string $prisoner_id
   *   The prisoner id.
   * @return mixed|null
   *   The prison id that receives payments to the prisoner.
   */
  protected function getPrisonId(string $prisoner_id) {

    $prison_id = NULL;

    // Try to get the prison ID from db.
    // The prisoner_payment_amount table stores the amount that
    // can be paid to a prisoner and the prison id which receives
    // payments.
    try {
      $connection = Database::getConnection();

      $query = $connection->select('prisoner_payment_amount', 'pp_amount')
        ->fields('pp_amount', ['prison_id'])
        ->condition('prisoner_id', $prisoner_id);

      $result = $query->execute()->fetchField();

      if ($result) {
        $prison_id = $result;
      }
    }
    catch (\Exception $e) {
      $this->getLogger('nidirect_prisons')->error('Database error: @message', ['@message' => $e->getMessage()]);
    }

    return $prison_id;
  }

  /**
   * Get nominated visitor IDs who can make payments to a prisoner ID.
   *
   * @param string $prisoner_id
   *   The prison id.
   * @return array|string[]|null
   *   Return an array of visitor ids who can make payments to a
   *   prisoner or an empty array if there are no visitor ids
   *   nominated or null if an exception occurs.
   */
  protected function getPrisonerNominatedVisitorIds(string $prisoner_id) {

    $nominated_visitor_ids = [];

    try {
      $connection = \Drupal::database();

      $exists = $connection->select('prisoner_payment_amount', 'a')
        ->fields('a', ['prisoner_id'])
        ->condition('a.prisoner_id', $prisoner_id)
        ->execute()
        ->fetchField();

      if ($exists) {
        $visitor_ids = $connection->select('prisoner_payment_nominees', 'n')
          ->fields('n', ['visitor_ids'])
          ->condition('n.prisoner_id', $prisoner_id)
          ->execute()
          ->fetchField();

        if ($visitor_ids) {
          $nominated_visitor_ids = explode(',', $visitor_ids);
        }
      }
    }
    catch (\Exception $e) {
      $this->getLogger('nidirect_prisons')->error('Database error getting nominated visitors for prisoner: @message', ['@message' => $e->getMessage()]);

      return NULL;
    }

    return $nominated_visitor_ids;
  }

  /**
   * Get pending transactions for prisoner.
   *
   * @param string $prisoner_id
   *   The prisoner id.
   * @return false|mixed
   *   Returns false when there are no pending transactions. Otherwise,
   *   the pending transactions are returned (result object).
   */
  protected function getPendingTransactions(string $prisoner_id) {

    $pending_transactions = FALSE;

    try {
      $connection = \Drupal::database();
      $query = $connection->select('prisoner_payment_transactions', 'ppt')
        ->fields('ppt', [
          'order_key',
          'visitor_id',
          'amount',
          'created_timestamp'
        ])
        ->condition('prisoner_id', $prisoner_id)
        ->condition('status', 'pending')
        ->orderBy('created_timestamp', 'DESC')
        ->range(0, 1);

      $pending_transactions = $query->execute()->fetchObject();
    }
    catch (\Exception $e) {
      \Drupal::logger('nidirect_prisons')->error('Error checking pending transactions: @message', ['@message' => $e->getMessage()]);
    }

    return $pending_transactions;
  }

  /**
   * Generate the XML data for a Worldpay payment request.
   *
   * @param string $order_code
   *   The unique order code for the request.
   * @param string $prison_id
   *   The prison id which will receive the payment.
   * @param string $prisoner_id
   *   The prisoner id who will receive the payment.
   * @param string $prisoner_fullname
   *   The prisoner's full name.
   * @param float $payment_amount
   *   The amount to be paid.
   * @param string $visitor_fullname
   *   The visitor's (payee) full name.
   * @param string $visitor_email
   *   The visitor's email.
   *
   * @return string
   *   The XML string for the Worldpay payment request.
   */
  protected function generateOrderData(
    string $order_code,
    string $prison_id,
    string $prisoner_id,
    string $prisoner_fullname,
    float $payment_amount,
    string $visitor_fullname,
    string $visitor_email) {

    $merchant_code = getenv('PRISONER_PAYMENTS_WP_MERCHANT_CODE_' . $prison_id) ?: 'DEFAULT_MERCHANT_CODE';

    // Log an error if merchant code could not be retrieved.
    if (!$merchant_code || $merchant_code === 'DEFAULT_MERCHANT_CODE') {
      $this->getLogger('nidirect_prisons')->error('Missing merchant code for prison ID: @prison_id', [
        '@prison_id' => $prison_id,
      ]);
    }

    // Generate a meaningful description for the payment request.
    $description = htmlspecialchars("Payment for prisoner ID: $prisoner_id", ENT_XML1);

    // The currency for the payment request must be pounds sterling.
    $currency = 'GBP';

    // Worldpay requires the amount in the smallest currency unit (e.g., pence for GBP).
    $amount_in_pence = (int) round($payment_amount * 100);

    // Normalise (remove non A-Z characters) and split prisoner and
    // visitor full names.
    $prisoner_names = $this->splitFullName($this->cleanName($prisoner_fullname));
    $visitor_names = $this->splitFullName($this->cleanName($visitor_fullname));

    $sender_middle_name = $visitor_names['middle'] ? '<middle>' . $visitor_names['middle'] . '</middle>' : NULL;
    $recipient_middle_name = $prisoner_names['middle'] ? '<middle>' . $prisoner_names['middle'] . '</middle>' : NULL;

    // Create XML structure.
    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE paymentService PUBLIC "-//Worldpay//DTD Worldpay PaymentService v1//EN" "http://dtd.worldpay.com/paymentService_v1.dtd">
<paymentService version="1.4" merchantCode="$merchant_code">
  <submit>
    <order orderCode="$order_code" installationId="1536419">
      <description>$description</description>
      <amount value="$amount_in_pence" currencyCode="$currency" exponent="2"/>
      <orderContent> <![CDATA[
      <p>Hello?</p>
      ]]> </orderContent>
      <paymentMethodMask>
        <include code="ECMC_DEBIT-SSL"/>
        <include code="VISA_DEBIT-SSL"/>
      </paymentMethodMask>
      <shopper>
        <shopperEmailAddress>$visitor_email</shopperEmailAddress>
      </shopper>
      <fundingTransfer type="GO" category="PULL_FROM_CARD">
        <fundingParty type="sender">
          <accountReference accountType="03">HPP-PROVIDED</accountReference>
          <fullName>
            <first>{$visitor_names['first']}</first>
            $sender_middle_name
            <last>{$visitor_names['last']}</last>
          </fullName>
          <fundingAddress>
            <countryCode>GB</countryCode>
          </fundingAddress>
        </fundingParty>
        <fundingParty type="recipient">
          <accountReference accountType="07">NIPS-ACC-REFERENCE</accountReference>
          <fullName>
            <first>{$prisoner_names['first']}</first>
            $recipient_middle_name
            <last>{$prisoner_names['last']}</last>
          </fullName>
          <fundingAddress>
            <countryCode>GB</countryCode>
          </fundingAddress>
        </fundingParty>
      </fundingTransfer>
    </order>
  </submit>
</paymentService>

XML;

    return $xml;
  }

  /**
   * Generate a unique order code for Worldpay.
   *
   * @param string $prison_id
   *   The prison ID for the payment.
   *
   * @param string $prisoner_id
   *   The prisoner ID for the payment.
   *
   * @param string $visitor_id
   *   The visitor ID for the payment.
   *
   * @return string
   *   A unique order code.
   * @throws \Exception
   */
  protected function generateOrderCode(string $prison_id, string $prisoner_id, string $visitor_id) {
    $uuid_short = substr(\Drupal::service('uuid')->generate(), 0, 8); // 8-character UUID segment
    $random_part = random_int(1000, 9999); // 4-digit random number
    return "{$prison_id}_{$prisoner_id}_{$visitor_id}_{$uuid_short}{$random_part}";
  }

  /**
   * Sends xml payment request order to Worldpay.
   *
   * @param string $order_data
   *   The order data (xml).
   * @return \SimpleXMLElement|null
   *   Returns response from Worldpay as SimpleXMLElement or null if
   *   a problem occurs.
   */
  protected function sendWorldpayRequest(string $order_data) {
    $xml = NULL;
    $client = \Drupal::service('http_client');
    $url = getenv('PRISONER_PAYMENTS_WP_SERVICE_URL') ?: 'https://secure-test.worldpay.com/jsp/merchant/xml/paymentService.jsp';
    $api_username = getenv('PRISONER_PAYMENTS_WP_USERNAME');
    $api_password = getenv('PRISONER_PAYMENTS_WP_PASSWORD');

    // Validate credentials before making request.
    if (empty($api_username) || empty($api_password)) {
      $this->getLogger('nidirect_prisons')->error('Missing Worldpay API credentials.');
      return NULL;
    }

    try {
      $response = $client->post($url, [
        'headers' => [
          'Authorization' => 'Basic ' . base64_encode("$api_username:$api_password"),
          'Content-Type' => 'application/xml',
          'Accept' => 'application/xml',
        ],
        'body' => $order_data,
      ]);

      $status_code = $response->getStatusCode();
      $xml_string = $response->getBody()->getContents();

      if ($status_code == 200) {
        // Validate response is XML before parsing.
        if (str_starts_with($xml_string, '<?xml')) {
          $xml = simplexml_load_string($xml_string);
        }
        else {
          $this->getLogger('nidirect_prisons')->error('Worldpay response is not valid XML: @response', [
            '@response' => substr($xml_string, 0, 500), // Log first 500 chars for debugging.
          ]);
        }
      }
      else {
        $this->getLogger('nidirect_prisons')->error('sendWorldpayRequest received unexpected status code: @code | Response: @response', [
          '@code' => $status_code,
          '@response' => substr($xml_string, 0, 500), // Log first 500 chars.
        ]);
      }
    }
    catch (\GuzzleHttp\Exception\RequestException $e) {
      $response_body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'No response';
      $this->getLogger('nidirect_prisons')->error('sendWorldpayRequest error: @message | Response: @response', [
        '@message' => $e->getMessage(),
        '@response' => substr($response_body, 0, 500), // Avoid logging excessive data.
      ]);
    }

    return $xml;
  }

  /**
   * Parse Worldpay payment request response.
   *
   * @param \SimpleXMLElement $xml
   *   The xml response from Worldpay to be parsed.
   * @return array
   *   The response parsed into an array.
   */
  protected function parseWorldpayResponse(\SimpleXMLElement $xml) {
    $result = [
      'success' => FALSE,
      'order_code' => NULL,
      'reference_url' => NULL,
      'error' => NULL,
    ];

    if (!$xml || !($xml instanceof \SimpleXMLElement)) {
      $result['error'] = 'Invalid or empty XML response from Worldpay.';
      $this->getLogger('nidirect_prisons')->error('parseWorldpayResponse: Invalid or empty XML response.');
      return $result;
    }

    try {
      // Check if response contains an orderStatus node.
      $order_status = $xml->xpath('//orderStatus');
      if (!empty($order_status)) {
        $order_status = $order_status[0];
        $result['order_code'] = (string) $order_status['orderCode'];

        // Extract the reference URL
        $reference = $order_status->xpath('reference');
        if (!empty($reference)) {
          $result['reference_url'] = (string) $reference[0];

          if (!filter_var($result['reference_url'], FILTER_VALIDATE_URL)) {
            $this->getLogger('nidirect_prisons')->warning('parseWorldpayResponse: Reference URL is not a valid URL: @url', [
              '@url' => $result['reference_url'],
            ]);
            $result['error'] = 'Invalid reference URL returned by Worldpay.';
          } else {
            $result['success'] = TRUE;
          }
        } else {
          $result['error'] = 'Missing reference URL in Worldpay response.';
          $this->getLogger('nidirect_prisons')->warning('parseWorldpayResponse: Missing reference URL in Worldpay response.');
        }
      }
      else {
        // Handle error response from Worldpay
        $error = $xml->xpath('//error');
        if (!empty($error)) {
          $result['error'] = (string) $error[0];
          $this->getLogger('nidirect_prisons')->error('parseWorldpayResponse: Worldpay error - @error', [
            '@error' => $result['error'],
          ]);
        }
        else {
          $result['error'] = 'Unexpected response format from Worldpay.';
          $this->getLogger('nidirect_prisons')->error('parseWorldpayResponse: Unexpected response format. Raw XML: @xml', [
            '@xml' => $xml->asXML(),
          ]);
        }
      }
    }
    catch (\Exception $e) {
      $result['error'] = 'Exception parsing Worldpay response: ' . $e->getMessage();
      $this->getLogger('nidirect_prisons')->error('parseWorldpayResponse exception: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $result;
  }

  /**
   * Validates the Worldpay response using HMAC (MAC2).
   *
   * @param array $response_data
   *   The decoded response data.
   *
   * @return bool
   *   TRUE if the response is valid, FALSE otherwise.
   */
  private function isValidWorldpayResponse(array $response_data) {

    // Retrieve the secret key.
    $secret_key = getenv('PRISONER_PAYMENTS_WP_MAC_SECRET');

    if (empty($secret_key)) {
      $this->getLogger('nidirect_prisons')->error('Worldpay secret key is missing from configuration.');
      return FALSE;
    }

    // Ensure required fields exist.
    if (!isset(
      $response_data['gateway']['orderKey'],
      $response_data['gateway']['paymentAmount'],
      $response_data['gateway']['paymentCurrency'],
      $response_data['gateway']['paymentStatus'],
      $response_data['gateway']['mac2']
    )) {
      return FALSE;
    }

    // Extract values needed for signature verification.
    $order_key = $response_data['gateway']['orderKey'];
    $payment_amount = $response_data['gateway']['paymentAmount'];
    $payment_currency = $response_data['gateway']['paymentCurrency'];
    $payment_status = $response_data['gateway']['paymentStatus'];
    $mac2_received = $response_data['gateway']['mac2'];

    // Compute the expected MAC.
    $data_string  = $order_key . ':';
    $data_string .= $payment_amount . ':';
    $data_string .= $payment_currency . ':';
    $data_string .= $payment_status;

    // Compute the HMAC using SHA-256.
    $computed_mac = hash_hmac('sha256', $data_string, $secret_key);

    // Compare computed MAC with received MAC.
    return hash_equals($computed_mac, $mac2_received);
  }

  /**
   * Method to log the pending transaction.
   *
   * @param string $order_code
   *   The unique order code identifying the transaction.
   *
   * @param string $prisoner_id
   *   The prisoner id to receive payment.
   *
   * @param string $visitor_id
   *   The visitor id who is making the payment.
   *
   * @param float $payment_amount
   *   The payment amount.
   *
   */
  private function logPendingTransaction(string $order_code, string $prisoner_id, string $visitor_id, float $payment_amount) {
    $transaction_data = [
      'order_key' => $order_code,
      'prisoner_id' => $prisoner_id,
      'visitor_id' => $visitor_id,
      'amount' => $payment_amount,
      'status' => 'pending', // Or any other status you want to set
      'created_timestamp' => time(),
    ];

    \Drupal::database()->insert('prisoner_payment_transactions')
      ->fields($transaction_data)
      ->execute();
  }

  /**
   * Method to update a pending transaction amount.
   *
   * @param string $order_code
   *   The unique order code identifying the transaction.
   *
   * @param float $payment_amount
   *   The payment amount.
   *
   */
  private function updatePendingTransactionAmount(string $order_code, float $payment_amount) {
    try {
      // Get the database connection.
      $connection = \Drupal::database();

      // Build and execute the update query.
      $connection->update('prisoner_payment_transactions')
        ->fields(['amount' => $payment_amount])
        ->condition('order_key', $order_code)
        ->execute();
    }
    catch (\Exception $e) {
      \Drupal::logger('nidirect_prisons')->error('Error updating pending transaction amount: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * Method to update a transaction status.
   *
   * @param string $order_code
   *   The unique order code identifying the transaction.
   *
   * @param string $status
   *   The payment status - success|failed|pending.
   *
   */
  private function updateTransactionStatus(string $order_code, string $status) {
    try {
      // Get the database connection.
      $connection = \Drupal::database();

      // Build and execute the update query.
      $connection->update('prisoner_payment_transactions')
        ->fields(['status' => $status])
        ->condition('order_key', $order_code)
        ->execute();
    }
    catch (\Exception $e) {
      \Drupal::logger('nidirect_prisons')->error('Error updating transaction status: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * Method to delete transaction.
   *
   * @param string $order_code
   *   The unique order code identifying the transaction to delete.
   *
   */
  private function deleteTransaction(string $order_code) {
    \Drupal::database()->delete('prisoner_payment_transactions')
      ->condition('order_key', $order_code)
      ->execute();
  }

  /**
   * Method to split a full name into array of first, middle and
   * last names.
   *
   * Ensures each component is at most 35 characters long.
   *
   * @param string $full_name
   *   The full name to be split.
   *
   * @return array
   *   An array with three elements: first, middle and last name.
   */
  protected function splitFullName($full_name) {

    // Remove extra spaces and normalize whitespace.
    $full_name = trim(preg_replace('/\s+/', ' ', $full_name));
    if ($full_name === '') {
      return [
        'first' => '',
        'middle' => '',
        'last' => '',
      ];
    }

    $parts = explode(' ', $full_name);
    $count = count($parts);

    // Default values.
    $first = '';
    $middle = '';
    $last = '';

    if ($count === 1) {
      // Only one name part — treat as first name.
      $first = $parts[0];
    }
    elseif ($count === 2) {
      // Two parts — assume first and last.
      [$first, $last] = $parts;
    }
    else {
      // More than two — assume first, middle(s), last.
      $first = array_shift($parts);
      $last = array_pop($parts);
      $middle = implode(' ', $parts);
    }

    // Truncate each to max 35 characters.
    $truncate = function ($name) {
      return substr($name, 0, 35);
    };

    return [
      'first' => $truncate($first),
      'middle' => $truncate($middle),
      'last' => $truncate($last),
    ];
  }

  /**
   * Cleans a name for use in Worldpay name fields.
   *
   * - Replaces hyphens with spaces.
   * - Removes all non-Latin characters (except whitespace).
   * - Transliterates to ASCII (e.g. É → E).
   *
   * @param string $string
   *   The input name string.
   *
   * @return string
   *   Cleaned name string containing only A–Z, a–z, and spaces.
   */
  protected function cleanName(string $string): string {
    // Replace hyphens with spaces.
    $string = str_replace('-', ' ', $string);

    // Remove non-Latin characters except whitespace.
    $latin_only = preg_replace('/[^\p{Latin}\s]/u', '', $string);

    // Transliterate to ASCII.
    $ascii = $this->transliteration->transliterate($latin_only, 'en');

    // Normalize spaces and trim.
    $ascii = trim(preg_replace('/\s+/', ' ', $ascii));

    return $ascii;
  }

}

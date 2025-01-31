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
      'worldpay_payment_service_url' => 'https://secure-test.worldpay.com/jsp/merchant/xml/paymentService.jsp',
      'worldpay_username' => 'wp-test-user',
      'worldpay_password' => 'password',
      'worldpay_merchant_codes' => [
        'MY' => 'MERCHANT_CODE_MY',
        'MN' => 'MERCHANT_CODE_MN',
        'HW' => 'MERCHANT_CODE_HW',
      ],
    ];

    /*return [
      'debug' => FALSE,
      'prisoner_maximum_payment_amount' => 0,
      'worldpay_payment_service_url' => 'https://secure-test.worldpay.com/jsp/merchant/xml/paymentService.jsp',
      'worldpay_username' => 'wp-test-user',
      'worldpay_password' => getenv('PRISONER_PAYMENTS_WP_PASSWORD'),
      'worldpay_merchant_codes' => [
        'MY' => getenv('PRISONER_PAYMENTS_WP_MERCHANT_CODE_MY'),
        'MN' => getenv('PRISONER_PAYMENTS_WP_MERCHANT_CODE_MN'),
        'HW' => getenv('PRISONER_PAYMENTS_WP_MERCHANT_CODE_HW'),
      ],
    ];*/
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

    if ($page === 'page_payment_card_details') {

      // Generate and send Worldpay order XML request.
      $order_data = $this->generateOrderData($form_state);
      $response_xml = $this->sendWorldpayRequest($order_data);

      // Parse the Worldpay response to get the iframe URL.
      if ($response_xml && $response = $this->parseWorldpayResponse($response_xml)) {

        // Attach the Worldpay JS library and pass the iframe URL to drupalSettings.
        $form['#attached']['library'][] = 'nidirect_prisons/prisoner_payments_worldpay';
        $form['#attached']['drupalSettings']['worldpay'] = [
          'url' => $response['reference_url'],
          'target' => 'worldpay-html',
        ];

        // Add a container for the iframe.
        $form['worldpay_container'] = [
          '#markup' => '<div id="worldpay-html"></div>',
          '#allowed_tags' => ['div'],
        ];

      } else {
        // Handle error cases and display messages to users.
        // $form['#attached']['drupalSettings']['worldpayError'] = 'Unable to process your payment request. Please try again later.';

        // FOR TESTING PURPOSES, try adding iframe anyway...
        $form['#attached']['library'][] = 'nidirect_prisons/prisoner_payments_worldpay';
        $form['#attached']['drupalSettings']['worldpay'] = [
          'url' => 'https://payments.worldpay.com/app/hpp/integration/transaction/37d9d008-d649-4458-a7cd-c07a65debc54?ref=1718718877578485&cs=JWNvp6kQ0Xsrca_q',
          'target' => 'worldpay-html',
        ];

        // Add a container for the iframe.
        $form['worldpay_container'] = [
          '#markup' => '<div id="worldpay-html"></div>',
          '#allowed_tags' => ['div'],
        ];
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
   * Get the prison id for the prison which receives payments
   * for the prisoner.
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

  /**
   * Generate the XML data for a Worldpay payment request.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object containing user-submitted data.
   *
   * @return string
   *   The XML string for the Worldpay payment request.
   */
  protected function generateOrderData(FormStateInterface $form_state) {

    // Extract form values.
    $prisoner_id = $form_state->getValue('prisoner_id');
    $visitor_id = $form_state->getValue('visitor_id');
    $visitor_fullname = $form_state->getValue('visitor_fullname');
    $payment_amount = $form_state->getValue('prisoner_payment_amount');

    $prison_id = $this->getPrisonId($prisoner_id);
    $merchant_code = $this->configuration['worldpay_merchant_codes'][$prison_id];
    $order_code = $this->generateOrderCode($prison_id, $prisoner_id, $visitor_id);
    $description = "Payment for prisoner ID: $prisoner_id";
    $currency = 'GBP';

    // Worldpay requires the amount in the smallest currency unit (e.g., pence for GBP).
    $amount_in_cents = intval($payment_amount * 100);

    // Create XML structure
    $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><paymentService version="1.4" merchantCode="' . $merchant_code . '"></paymentService>');
    $submit = $xml->addChild('submit');
    $order = $submit->addChild('order');
    $order->addAttribute('orderCode', $order_code);
    $order->addChild('description', $description);

    // Add amount details
    $amount_node = $order->addChild('amount');
    $amount_node->addAttribute('value', intval($payment_amount * 100)); // Convert to minor units
    $amount_node->addAttribute('currencyCode', $currency);
    $amount_node->addAttribute('exponent', '2');

    // Add payment method restrictions
    $payment_method_mask = $order->addChild('paymentMethodMask');
    $payment_method_mask->addChild('include')->addAttribute('code', 'VISA-DELTA');
    $payment_method_mask->addChild('include')->addAttribute('code', 'ECMC-DEBIT');

    ksm($xml->asXML());

    return $xml->asXML();

    // Create the array representing the XML structure.
    /*
    $data = [
      'paymentService' => [
        '@attributes' => [
          'version' => '1.4',
          'merchantCode' => $merchant_code,
        ],
        'submit' => [
          'order' => [
            '@attributes' => [
              'orderCode' => $this->generateOrderCode($prison_id, $prisoner_id, $visitor_id),
              'installationId' => 'YOUR_INSTALLATION_ID',
            ],
            'description' => "Payment for prisoner ID: $prisoner_id",
            'amount' => [
              '@attributes' => [
                'currencyCode' => 'GBP',
                'exponent' => '2',
                'value' => $amount_in_cents,
              ],
            ],
            'orderContent' => "Payment made by: $visitor_fullname",
            'paymentMethodMask' => [
              'include' => [
                '@attributes' => ['code' => 'ALL'],
              ],
            ],
            'shopper' => [
              'shopperEmailAddress' => 'example@example.com', // Replace as needed.
            ],
            'billingAddress' => [
              'address' => [
                'address1' => '123 Example Street',
                'postalCode' => 'AB12CD',
                'city' => 'Example City',
                'countryCode' => 'GB',
              ],
            ],
          ],
        ],
      ],
    ];

    $order_data = $this->arrayToXml($data, new \SimpleXMLElement('<root/>'))->asXML();

    ksm($order_data);

    // Convert the array to XML.
    return $order_data;
    */
  }

  /**
   * Recursively convert an array to a SimpleXMLElement object.
   *
   * @param array $data
   *   The array to convert.
   * @param \SimpleXMLElement $xml
   *   The SimpleXMLElement object to append to.
   *
   * @return \SimpleXMLElement
   *   The updated SimpleXMLElement object.
   */
  protected function arrayToXml(array $data, \SimpleXMLElement $xml) {
    foreach ($data as $key => $value) {
      if (is_array($value)) {
        if (isset($value['@attributes'])) {
          // Handle attributes for this element.
          $child = $xml->addChild($key);
          foreach ($value['@attributes'] as $attr_key => $attr_value) {
            $child->addAttribute($attr_key, $attr_value);
          }
          // Process child elements if they exist.
          if (isset($value['@value'])) {
            $child[0] = $value['@value'];
          } else {
            $this->arrayToXml($value, $child);
          }
        } else {
          // Recurse for nested elements.
          $this->arrayToXml($value, $xml->addChild($key));
        }
      } else {
        // Add a single element.
        $xml->addChild($key, htmlspecialchars($value));
      }
    }
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
   */
  protected function generateOrderCode(string $prison_id, string $prisoner_id, string $visitor_id) {
    $uuid_short = substr(\Drupal::service('uuid')->generate(), 0, 8); // 8-character UUID segment
    $random_part = random_int(1000, 9999); // 4-digit random number
    return "{$prison_id}_{$prisoner_id}_{$visitor_id}_{$uuid_short}{$random_part}";
  }

  protected function sendWorldpayRequest(string $order_data) {
    $xml = NULL;
    $client = \Drupal::service('http_client');
    $url = $this->configuration['worldpay_payment_service_url'];
    $api_username = $this->configuration['worldpay_username']; // Worldpay API username
    $api_password = $this->configuration['worldpay_password']; // Worldpay API password

    try {
      $response = $client->post($url, [
        'auth' => [$api_username, $api_password], // Basic Authentication
        'headers' => [
          'Content-Type' => 'application/xml',
          'Accept' => 'application/xml',
        ],
        'body' => $order_data,
      ]);

      if ($response->getStatusCode() == 200) {
        $xml_string = $response->getBody()->getContents();
        $xml = simplexml_load_string($xml_string);
      }
      else {
        $this->getLogger('nidirect_prisons')->error('sendWorldpayRequest received unexpected status code: @code', [
          '@code' => $response->getStatusCode(),
        ]);
      }
    }
    catch (\Exception $e) {
      $this->getLogger('nidirect_prisons')->error('sendWorldpayRequest error: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $xml;
  }

  protected function parseWorldpayResponse(\SimpleXMLElement $xml) {
    $result = [
      'success' => FALSE,
      'order_code' => NULL,
      'reference_url' => NULL,
      'error' => NULL,
    ];

    if (!$xml) {
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
          $result['success'] = TRUE;
        }
      }
      else {
        // Handle error response from Worldpay
        $error = $xml->xpath('//error');
        if (!empty($error)) {
          $result['error'] = (string) $error[0];
        }
        else {
          $result['error'] = 'Unexpected response format from Worldpay.';
        }
        $this->getLogger('nidirect_prisons')->error('parseWorldpayResponse: @error', ['@error' => $result['error']]);
      }
    }
    catch (\Exception $e) {
      $result['error'] = 'Exception parsing Worldpay response: ' . $e->getMessage();
      $this->getLogger('nidirect_prisons')->error('parseWorldpayResponse exception: @message', ['@message' => $e->getMessage()]);
    }

    return $result;
  }



}

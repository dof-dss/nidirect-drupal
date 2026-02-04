<?php

namespace Drupal\nidirect_prisons\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Transliteration\TransliterationInterface;
use Drupal\Core\Database\Connection;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;

class PrisonerPaymentManager {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The logging service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The transliteration service.
   *
   * @var \Drupal\Component\Transliteration\TransliterationInterface
   */
  protected TransliterationInterface $transliteration;

  public const HARD_TIMEOUT = 1200;
  public const SOFT_TIMEOUT = 600;

  /**
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logging service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Component\Transliteration\TransliterationInterface $transliteration
   *   The transliteration service.
   */
  public function __construct(
    Connection $database,
    LoggerInterface $logger,
    TimeInterface $time,
    TransliterationInterface $transliteration
  ) {
    $this->database = $database;
    $this->logger = $logger;
    $this->time = $time;
    $this->transliteration = $transliteration;
  }

  /**
   * Get the prison id for the prison which receives payments
   * for the prisoner.
   *
   * @param string $prisoner_id
   *   The prisoner id.
   *
   * @return string|null
   *   The prison id that receives payments to the prisoner.
   */
  public function getPrisonId(string $prisoner_id): ?string {
    try {
      $query = $this->database->select('prisoner_payment_amount', 'pp_amount')
        ->fields('pp_amount', ['prison_id'])
        ->condition('prisoner_id', $prisoner_id);

      $result = $query->execute()->fetchField();

      return $result ?: NULL;
    }
    catch (\Throwable $e) {
      $this->logger->error(
        'Failed to load prison id for prisoner @prisoner: @message',
        [
          '@prisoner' => $prisoner_id,
          '@message' => $e->getMessage(),
        ]
      );
      return NULL;
    }
  }

  /**
   * Cancel a Worldpay order.
   *
   * IMPORTANT - Only ever cancel pending or expired transactions.
   * Never cancel authorised or completed payments because PRISM
   * is notified immediately on authorisation.
   */
  public function cancelWorldpayOrder(string $order_code, string $prison_id): void {

    // Load transaction to confirm state.
    $transaction = $this->getTransaction($order_code);

    // Early return if transaction non-existent.
    if (!$transaction) {
      $this->logger->debug('Worldpay cancel skipped: @order could not be found.', ['@order' => $order_code]);
      return;
    }

    // Early return if transaction already complete.
    if ($transaction->status === 'success') {
      $this->logger->debug('Worldpay cancel skipped: @order already complete (status = success).', ['@order' => $order_code]);
      return;
    }

    $merchant_code = getenv('PRISONER_PAYMENTS_WP_MERCHANT_CODE_' . $prison_id) ?: NULL;

    if (!$merchant_code) {
      $this->logger->error(
        'Missing merchant code for prison ID: @prison_id',
        ['@prison_id' => $prison_id]
      );
      return;
    }

    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE paymentService PUBLIC "-//Worldpay//DTD Worldpay PaymentService v1//EN" "http://dtd.worldpay.com/paymentService_v1.dtd">
<paymentService merchantCode="{$merchant_code}" version="1.4">
  <modify>
    <orderModification orderCode="{$order_code}">
      <cancel/>
    </orderModification>
  </modify>
</paymentService>

XML;

    try {
      $response = $this->sendWorldpayRequest($xml, $prison_id);

      if (!$this->isWorldpayCancelSuccessful($response)) {
        $this->logger->warning(
          'Worldpay cancel failed for order @order. Response was: @response',
          [
            '@order' => $order_code,
            '@response' => $response->asXML()
          ]
        );
      }
      else {
        $this->logger->debug(
          'Worldpay cancelled @order.',
          [
            '@order' => $order_code
          ]
        );
      }
    }
    catch (\Throwable $e) {
      // Never break execution on cleanup.
      $this->logger->error(
        'Exception cancelling Worldpay order @order: @message',
        [
          '@order' => $order_code,
          '@message' => $e->getMessage(),
        ]
      );
    }
  }

  /**
   * Helper to parse a Worldpay cancel order response.
   *
   * @param \SimpleXMLElement $response_xml
   *   The response xml from Worldpay to parse.
   * @return bool
   *   TRUE if Worldpay received cancellation order ok, FALSE otherwise.
   */
  protected function isWorldpayCancelSuccessful(\SimpleXMLElement $response_xml): bool {
    try {
      return (
        isset($response_xml->reply->ok->cancelReceived)
        && isset($response_xml->reply->ok->cancelReceived['orderCode'])
      );
    }
    catch (\Throwable $e) {
      return FALSE;
    }
  }

  /**
   * Get transaction by order key.
   *
   * @param string $order_code
   *   The unique order code identifying the transaction.
   *
   * @return object|null
   *   The transaction record, or NULL if not found.
   */
  public function getTransaction(string $order_code): ?object {

    try {
      $query = $this->database->select('prisoner_payment_transactions', 'ppt')
        ->fields('ppt', [
          'order_key',
          'prisoner_id',
          'visitor_id',
          'amount',
          'status',
          'created_timestamp',
          'updated_timestamp',
        ])
        ->condition('order_key', $order_code)
        ->range(0, 1);

      return $query->execute()->fetchObject() ?: NULL;
    }
    catch (\Throwable $e) {
      $this->logger->error(
        'Error getting transaction @order: @message',
        [
          '@order' => $order_code,
          '@message' => $e->getMessage(),
        ]
      );
    }

    return NULL;
  }

  /**
   * Expire a transaction if it has timed out.
   *
   * @param object $transaction
   *   Transaction record.
   *
   * @return bool
   *   TRUE if the transaction was expired during this call.
   */
  public function expireIfTimedOut(object $transaction): bool {
    $now = $this->time->getRequestTime();

    $hard_expired = $transaction->created_timestamp < ($now - self::HARD_TIMEOUT);
    $soft_expired = $transaction->updated_timestamp < ($now - self::SOFT_TIMEOUT);

    if (!$hard_expired && !$soft_expired) {
      return FALSE;
    }

    // Only pending transactions can be expired.
    if ($transaction->status !== 'pending') {
      return FALSE;
    }

    // Mark expired.
    $this->database->update('prisoner_payment_transactions')
      ->fields(['status' => 'expired'])
      ->condition('order_key', $transaction->order_key)
      ->execute();

    $this->updateTransactionStatus($transaction->order_key, 'expired');

    // Cancel Worldpay.
    $prison_id = $this->getPrisonId($transaction->prisoner_id);

    if ($prison_id) {
      $this->cancelWorldpayOrder($transaction->order_key, $prison_id);
    }

    return TRUE;
  }

  /**
   * Get all pending transactions.
   *
   * @return array
   *   Array of pending transaction objects.
   */
  public function getPendingTransactions(): array {
    try {
      return $this->database->select('prisoner_payment_transactions', 'ppt')
        ->fields('ppt', [
          'order_key',
          'prisoner_id',
          'visitor_id',
          'amount',
          'status',
          'created_timestamp',
          'updated_timestamp',
        ])
        ->condition('status', 'pending')
        ->orderBy('created_timestamp', 'DESC')
        ->execute()
        ->fetchAll();
    }
    catch (\Throwable $e) {
      $this->logger->error(
        'Error getting pending transactions: @message',
        ['@message' => $e->getMessage()]
      );
      return [];
    }
  }

  /**
   * Get the current pending transaction for a prisoner.
   *
   * @param string $prisoner_id
   *   The prisoner id.
   *
   * @return object|null
   *   Pending transaction or NULL if none.
   */
  public function getPendingTransactionForPrisoner(string $prisoner_id): ?object {
    try {
      $query = $this->database->select('prisoner_payment_transactions', 'ppt')
        ->fields('ppt', [
          'order_key',
          'prisoner_id',
          'visitor_id',
          'amount',
          'status',
          'created_timestamp',
          'updated_timestamp',
        ])
        ->condition('prisoner_id', $prisoner_id)
        ->condition('status', 'pending')
        ->orderBy('created_timestamp', 'DESC')
        ->range(0, 1);

      return $query->execute()->fetchObject() ?: NULL;
    }
    catch (\Throwable $e) {
      $this->logger->error(
        'Error loading pending transaction for prisoner @prisoner: @message',
        [
          '@prisoner' => $prisoner_id,
          '@message' => $e->getMessage(),
        ]
      );
      return NULL;
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
  public function getPrisonerPaymentMaxAmount(string $prisoner_id) {
    try {
      // Get the maximum allowed payment amount (£100 or less).
      $max_amount = $this->database->select('prisoner_payment_amount', 'ppa')
        ->fields('ppa', ['amount'])
        ->condition('ppa.prisoner_id', $prisoner_id)
        ->execute()
        ->fetchField();

      return round((float) $max_amount, 2);
    }
    catch (\Exception $e) {
      $this->logger->error(
        'Error getting max allowed payment amount for prisoner @prisoner: @message',
        [
          '@prisoner' => $prisoner_id,
          '@message' => $e->getMessage(),
        ]
      );
    }
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
  public function getPrisonerNominatedVisitorIds(string $prisoner_id) {

    $nominated_visitor_ids = [];

    try {
      $exists = $this->database->select('prisoner_payment_amount', 'a')
        ->fields('a', ['prisoner_id'])
        ->condition('a.prisoner_id', $prisoner_id)
        ->execute()
        ->fetchField();

      if ($exists) {
        $visitor_ids = $this->database->select('prisoner_payment_nominees', 'n')
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
      $this->logger->error(
        'Database error getting nominated visitors for @prisoner: @message',
        [
          '@prisoner' => $prisoner_id,
          '@message' => $e->getMessage(),
        ]
      );
      return NULL;
    }

    return $nominated_visitor_ids;
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
   * @param float $amount
   *   The payment amount.
   *
   * @param int|null $created_timestamp
   *   The created timestamp (optional).
   *
   * @return \stdClass
   *   Return the inserted transaction details.
   * @throws \Exception
   */
  public function logPendingTransaction(
    string $order_code,
    string $prisoner_id,
    string $visitor_id,
    float $amount,
    ?int $created_timestamp = NULL
  ): \stdClass {

    $now = $this->time->getRequestTime();
    $created_timestamp = $created_timestamp ?? $now;

    $this->database->insert('prisoner_payment_transactions')
      ->fields([
        'order_key' => $order_code,
        'prisoner_id' => $prisoner_id,
        'visitor_id' => $visitor_id,
        'amount' => $amount,
        'status' => 'pending',
        'created_timestamp' => $created_timestamp,
        'updated_timestamp' => $now,
      ])
      ->execute();

    // Return transaction object.
    return (object) [
      'order_key' => $order_code,
      'prisoner_id' => $prisoner_id,
      'visitor_id' => $visitor_id,
      'amount' => $amount,
      'status' => 'pending',
      'created_timestamp' => $created_timestamp,
      'updated_timestamp' => $now,
    ];
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
  public function updatePendingTransactionAmount(string $order_code, float $payment_amount) {
    try {
      // Build and execute the update query.
      $this->database->update('prisoner_payment_transactions')
        ->fields(['amount' => $payment_amount])
        ->condition('order_key', $order_code)
        ->condition('status', 'pending')
        ->execute();
    }
    catch (\Exception $e) {
      $this->logger->error('Error updating pending transaction amount: @message', ['@message' => $e->getMessage()]);
    }
  }

  /**
   * Update the status of a transaction.
   *
   * @param string $order_code
   *   The unique order code identifying the transaction.
   *
   * @param string $status
   *   One of: pending, expired, failed, success.
   *
   * @return bool
   *   TRUE if the status was updated, FALSE otherwise.
   */
  public function updateTransactionStatus(string $order_code, string $status): bool {

    $allowed_statuses = ['pending', 'expired', 'cancelled', 'failed', 'success'];

    if (!in_array($status, $allowed_statuses, TRUE)) {
      $this->logger->error(
        'Invalid transaction status "@status" for order @order',
        [
          '@status' => $status,
          '@order' => $order_code,
        ]
      );
      return FALSE;
    }

    try {
      $updated = $this->database->update('prisoner_payment_transactions')
        ->fields(['status' => $status])
        ->condition('order_key', $order_code)
        ->execute();

      return (bool) $updated;
    }
    catch (\Throwable $e) {
      $this->logger->error(
        'Error updating transaction @order status to "@status": @message',
        [
          '@order' => $order_code,
          '@status' => $status,
          '@message' => $e->getMessage(),
        ]
      );
      return FALSE;
    }
  }

  /**
   * Touch a pending transaction, enforcing soft and hard timeouts.
   *
   * @param string $order_code
   *   The unique order code identifying the transaction.
   *
   * @return bool
   *   TRUE if the transaction was successfully kept alive.
   *   FALSE if the transaction is missing, expired, or no longer pending.
   */
  public function touchTransaction(string $order_code): bool {

    $transaction = $this->getTransaction($order_code);

    if (!$transaction || $transaction->status !== 'pending') {
      return FALSE;
    }

    $now = $this->time->getRequestTime();

    // Enforce hard timeout (absolute lifetime).
    $age = $now - (int) $transaction->created_timestamp;
    if ($age > self::HARD_TIMEOUT) {
      $this->updateTransactionStatus($order_code, 'expired');
      return FALSE;
    }

    // Enforce soft timeout (inactivity window).
    $idle = $now - (int) $transaction->updated_timestamp;
    if ($idle > self::SOFT_TIMEOUT) {
      $this->updateTransactionStatus($order_code, 'expired');
      return FALSE;
    }

    // Transaction is still valid — touch it.
    $this->database->update('prisoner_payment_transactions')
      ->fields([
        'updated_timestamp' => $now,
      ])
      ->condition('order_key', $order_code)
      ->condition('status', 'pending')
      ->execute();

    return TRUE;
  }

  /**
   * Delete a transaction by order code.
   *
   * @param string $order_code
   *   The unique order code identifying the transaction.
   *
   * @return bool
   *   TRUE if a transaction was deleted, FALSE otherwise.
   */
  public function deleteTransaction(string $order_code): bool {
    try {
      $deleted = $this->database->delete('prisoner_payment_transactions')
        ->condition('order_key', $order_code)
        ->execute();

      return (bool) $deleted;
    }
    catch (\Throwable $e) {
      $this->logger->error(
        'Error deleting transaction @order: @message',
        [
          '@order' => $order_code,
          '@message' => $e->getMessage(),
        ]
      );
      return FALSE;
    }
  }

  /**
   * TODO: Move following functions to Worldpay Client service?
   */

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
  public function generateOrderCode(string $prison_id, string $prisoner_id, string $visitor_id) {
    $uuid_short = substr(\Drupal::service('uuid')->generate(), 0, 8);
    $random_part = random_int(1000, 9999);
    return "{$prison_id}_{$prisoner_id}_{$visitor_id}_{$uuid_short}{$random_part}";
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
  public function generateOrderData(
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
      $this->logger->error('Missing merchant code for prison ID: @prison_id', [
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
   * Helper to split a full name into array of first, middle and
   * last names for use in Worldpay name fields.
   *
   * Ensures each component is at most 35 characters long.
   *
   * @param string $full_name
   *   The full name to be split.
   *
   * @return array
   *   An array with three elements: first, middle and last name.
   */
  public function splitFullName($full_name) {

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
  public function cleanName(string $string): string {
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

  /**
   * Sends xml request to Worldpay and returns the response.
   *
   * @param string $request_xml
   *   The request xml.
   * @param string $prison_id
   *   The id of the prison.
   * @return \SimpleXMLElement|null
   *   Returns response from Worldpay as SimpleXMLElement or null if
   *   a problem occurs.
   */
  public function sendWorldpayRequest(string $request_xml, string $prison_id) {
    $response_xml = NULL;
    $client = \Drupal::service('http_client');
    $url = getenv('PRISONER_PAYMENTS_WP_SERVICE_URL') ?: 'https://secure-test.worldpay.com/jsp/merchant/xml/paymentService.jsp';
    $api_username = getenv('PRISONER_PAYMENTS_WP_USERNAME_' . $prison_id);
    $api_password = getenv('PRISONER_PAYMENTS_WP_PASSWORD_' . $prison_id);

    // Validate credentials before making request.
    if (empty($api_username) || empty($api_password)) {
      $this->logger->error('Missing Worldpay API credentials.');
      return NULL;
    }

    try {
      $response = $client->post($url, [
        'headers' => [
          'Authorization' => 'Basic ' . base64_encode("$api_username:$api_password"),
          'Content-Type' => 'application/xml',
          'Accept' => 'application/xml',
        ],
        'body' => $request_xml,
      ]);

      $status_code = $response->getStatusCode();
      $xml_string = $response->getBody()->getContents();

      if ($status_code == 200) {
        // Validate response is XML before parsing.
        if (str_starts_with($xml_string, '<?xml')) {
          $response_xml = simplexml_load_string($xml_string);
        }
        else {
          $this->logger->error('Worldpay response is not valid XML: @response', [
            '@response' => substr($xml_string, 0, 500),
          ]);
        }
      }
      else {
        $this->logger->error('sendWorldpayRequest received unexpected status code: @code | Response: @response', [
          '@code' => $status_code,
          '@response' => substr($xml_string, 0, 500),
        ]);
      }
    }
    catch (RequestException $e) {
      $response_body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'No response';
      $this->logger->error('sendWorldpayRequest error: @message | Response: @response', [
        '@message' => $e->getMessage(),
        '@response' => substr($response_body, 0, 500),
      ]);
    }

    return $response_xml;
  }

  /**
   * Parse Worldpay payment request response.
   *
   * @param \SimpleXMLElement $xml
   *   The xml response from Worldpay to be parsed.
   * @return array
   *   The response parsed into an array.
   */
  public function parseWorldpayResponse(\SimpleXMLElement $xml) {
    $result = [
      'success' => FALSE,
      'order_code' => NULL,
      'reference_url' => NULL,
      'error' => NULL,
    ];

    if (!$xml || !($xml instanceof \SimpleXMLElement)) {
      $result['error'] = 'Invalid or empty XML response from Worldpay.';
      $this->logger->error('parseWorldpayResponse: Invalid or empty XML response.');
      return $result;
    }

    try {
      // Check if response contains an orderStatus node.
      $order_status = $xml->xpath('//orderStatus');
      if (!empty($order_status)) {
        $order_status = $order_status[0];
        $result['order_code'] = (string) $order_status['orderCode'];

        // Extract the reference URL.
        $reference = $order_status->xpath('reference');
        if (!empty($reference)) {
          $result['reference_url'] = (string) $reference[0];

          if (!filter_var($result['reference_url'], FILTER_VALIDATE_URL)) {
            $this->logger->warning('parseWorldpayResponse: Reference URL is not a valid URL: @url', [
              '@url' => $result['reference_url'],
            ]);
            $result['error'] = 'Invalid reference URL returned by Worldpay.';
          }
          else {
            $result['success'] = TRUE;
          }
        }
        else {
          $result['error'] = 'Missing reference URL in Worldpay response.';
          $this->logger->warning('parseWorldpayResponse: Missing reference URL in Worldpay response.');
        }
      }
      else {
        // Handle error response from Worldpay.
        $error = $xml->xpath('//error');
        if (!empty($error)) {
          $result['error'] = (string) $error[0];
          $this->logger->error('parseWorldpayResponse: Worldpay error - @error', [
            '@error' => $result['error'],
          ]);
        }
        else {
          $result['error'] = 'Unexpected response format from Worldpay.';
          $this->logger->error('parseWorldpayResponse: Unexpected response format. Raw XML: @xml', [
            '@xml' => $xml->asXML(),
          ]);
        }
      }
    }
    catch (\Exception $e) {
      $result['error'] = 'Exception parsing Worldpay response: ' . $e->getMessage();
      $this->logger->error('parseWorldpayResponse exception: @message', [
        '@message' => $e->getMessage(),
      ]);
    }

    return $result;
  }

  /**
   * Validate Worldpay Hosted Payment Page response data to verify
   * it originated from Worldpay and has not been modified.
   *
   * @see https://docs.worldpay.com/apis/wpg/hostedintegration/securingpayments
   *
   * @param array $response_data
   *   The decoded response data.
   *
   * @return bool
   *   TRUE if the response is valid, FALSE otherwise.
   */
  public function isValidWorldpayResponse(array $response_data) {

    // Retrieve the secret key.
    $secret_key = getenv('PRISONER_PAYMENTS_WP_MAC_SECRET');

    if (empty($secret_key)) {
      $this->logger->error('Worldpay secret key is missing from configuration.');
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

}

<?php

namespace Drupal\nidirect_prisons\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Controller\ControllerBase;

class WorldpayNotificationController extends ControllerBase {

  public function handleNotification(Request $request) {

    /*
     * Handle notifications from Worldpay to track if a payment has
     * succeeded or failed.
     *
     * See https://docs.worldpay.com/apis/wpg/manage for information
     * on configuring Worldpay order notifications.
     *
     * When a payment request is initialised, the payment transaction is
     * recorded as 'pending' in the prisoner_payment_transactions table.
     * When the hosted payment page is submitted, Worldpay sends
     * notifications on the status of the payment.
     *
     * At present, notifications for the following statuses are
     * configured:
     *   - 'AUTHORISED'
     *   - 'CANCELLED'
     *   - 'SHOPPER_CANCELLED'
     *   - 'REFUSED'
     *   - 'ERROR'
     *
     * If the payment is AUTHORISED, the payment transaction is
     * updated to 'success' in the prisoner_payment_transactions table
     * and in practice this means that the payment has been
     * successfully approved by the card issuer or bank, but the funds
     * have not yet been captured or settled (debited from visitor's
     * account).
     *
     * In theory, a payment can be CANCELLED after it has been
     * AUTHORISED. However, this is not presently handled here. If
     * a pending or failed transaction is AUTHORISED and marked as
     * a success in the prisoner_payment_transactions table, further
     * notifications relating to it are effectively ignored.
     *
     * @todo: check with Prism team how cancellations will be handled.
     */

    // Check request is from a Worldpay IP by performing forward and
    // reverse DNS lookups. Deny access to non-Worldpay IP addresses.
    $ip = $request->getClientIp();

    // Reverse DNS lookup.
    $hostname = gethostbyaddr($ip);
    if (!$hostname || !str_ends_with($hostname, '.worldpay.com')) {
      \Drupal::logger('nidirect_prisons')->warning('Unrecognized hostname: @hostname from IP @ip', [
        '@hostname' => $hostname,
        '@ip' => $ip,
      ]);
      return new Response('Access Denied', 403);
    }

    // Forward DNS lookup.
    $resolved_ips = gethostbynamel($hostname);
    if (!$resolved_ips || !in_array($ip, $resolved_ips, true)) {
      \Drupal::logger('nidirect_prisons')->warning('DNS verification failed for IP @ip with hostname @hostname.', [
        '@ip' => $ip,
        '@hostname' => $hostname,
        '@resolved_ips' => implode(', ', $resolved_ips ?? []),
      ]);
      return new Response('Access Denied', 403);
    }

    // Get the raw XML from the request body.
    $xml_data = $request->getContent();

    // Return 400 bad request if there is something wrong with the
    // XML. Note Worldpay will retry sending notifications (for up to 7
    // days) if the response is anything other than a 200 OK.

    if (empty($xml_data)) {
      \Drupal::logger('nidirect_prisons')->error('Empty Worldpay notification received.');
      return new Response('Bad request', 400);
    }

    $xml = simplexml_load_string($xml_data);
    if (!$xml) {
      \Drupal::logger('nidirect_prisons')->error('Invalid XML received in Worldpay notification.');
      return new Response('Bad request', 400);
    }

    if (!isset($xml->notify)) {
      \Drupal::logger('worldpay')->error('Missing <notify> element in XML.');
      return new Response('Bad request', 400);
    }

    // Process the notification.
    $order_code = (string) $xml->notify->orderStatusEvent['orderCode'];
    $payment_status = (string) $xml->notify->orderStatusEvent->payment->lastEvent;
    $pence = (int) $xml->notify->orderStatusEvent->payment->amount['value'];
    $amount = round($pence / 100, 2);

    \Drupal::logger('worldpay')->notice('Worldpay order notification for @order_key £@amount @payment_status', [
      '@order_key' => $order_code,
      '@amount' => number_format($amount, 2, '.', ''),
      '@payment_status' => $payment_status,
    ]);

    // Check transaction for order_key exists. It must have a pending or
    // failed status. We do not change the status of transactions that
    // are marked as success.
    $db = \Drupal::database();
    $payment_transaction = $db->select('prisoner_payment_transactions', 'ppt')
      ->fields('ppt', ['order_key', 'prisoner_id', 'visitor_id', 'amount', 'status'])
      ->condition('order_key', $order_code)
      ->condition('status', ['pending', 'failed'], 'IN')
      ->execute()
      ->fetchAssoc();

    // If no transaction exists, log it.
    if (!$payment_transaction) {
      \Drupal::logger('nidirect_prisons')->notice("No pending prisoner payment transaction found for order key: {$order_code}");

      // No further processing do be done. Acknowledge the notification.
      return new Response('OK', 200);
    }

    // Allowed payment statuses. The payment statuses that can be
    // sent are configured in the Worldpay Merchant Admin Interface
    // (MAI).
    $allowed_statuses = [
      'AUTHORISED',
      'CANCELLED',
      'SHOPPER_CANCELLED',
      'REFUSED',
      'ERROR',
    ];

    // Log a warning if the status is not one we are expecting.
    if (!in_array($payment_status, $allowed_statuses, true)) {
      \Drupal::logger('nidirect_prisons')->warning('Unexpected Worldpay order notification status: @status. Have Merchant Channel HTTP Events been changed in the MAI?', [
        '@status' => $payment_status,
      ]);

      // No further processing do be done. Acknowledge the notification.
      return new Response('OK', 200);
    }

    // If payment was AUTHORISED, update prisoner balance and send
    // payment details to Prism and update transaction status
    // from 'pending' to 'success'. Else the transaction status
    // is 'failed'.
    if ($payment_status === 'AUTHORISED') {

      // Deduct from prisoner's balance.
      $db_transaction = $db->startTransaction();

      try {
        $db->update('prisoner_payment_amount')
          ->expression('amount', 'GREATEST(amount - :paid_amount, 0)', [':paid_amount' => $amount])
          ->condition('prisoner_id', $payment_transaction['prisoner_id'])
          ->execute();

        // Get sequence id for this payment.
        $sequence_id = $this->getNextSequenceId();

        // Send payment details to Prism.
        $this->sendJsonToPrism($order_code, $payment_transaction['prisoner_id'], $payment_transaction['visitor_id'], $amount, $sequence_id);

        // Change prisoner payment transaction status from 'pending' to
        // 'success'.
        $db->update('prisoner_payment_transactions')
          ->fields(['status' => 'success'])
          ->condition('order_key', $order_code)
          ->execute();
      }
      catch (\Exception $e) {
        $db_transaction->rollBack();
        \Drupal::logger('nidirect_prisons')->error('Authorised payment update failed for prisoner_id @prisoner_id for order_code @order_code for amount £@amount: @message', [
          '@amount' => $amount,
          '@prisoner_id' => $payment_transaction['prisoner_id'],
          '@order_code' => $order_code,
          '@message' => $e->getMessage()
        ]);
      }
      finally {
        unset($db_transaction);
      }
    }
    else {
      // Prisoner payment transaction failed.
      $db->update('prisoner_payment_transactions')
        ->fields(['status' => 'failed'])
        ->condition('order_key', $order_code)
        ->execute();
    }

    // Acknowledge the notification.
    return new Response('OK', 200);
  }

  private function sendJsonToPrism($order_code, $prisoner_id, $visitor_id, $amount, $sequence_id) {

    $json_data = json_encode([
      "UNIQUE_TRANSACTION_ID" => $order_code,
      "INMATE_ID" => $prisoner_id,
      "VISITOR_ID" => $visitor_id,
      "TRANSACTION_TIME" => date('d/m/Y H:i:s'),
      "AMOUNT_PAID" => number_format($amount, 2, '.', ''),
      "SEQUENCE_ID" => $sequence_id,
    ]);

    // Try sending the email
    try {
      \Drupal::service('plugin.manager.mail')->mail(
        'nidirect_prisons',
        'prisoner_payment_notification',
        getenv('PRISONER_PAYMENTS_PRISM_EMAIL') ?: 'prisoner_payments@mailhog.local',
        \Drupal::languageManager()->getDefaultLanguage()->getId(),
        ['subject' => 'PAYIN', 'body' => [$json_data]]
      );

      \Drupal::logger('nidirect_prisons')->notice("Sent prisoner payment data for order {$order_code} to Prism.");
    } catch (\Exception $e) {
      // If email fails, log the error and throw
      \Drupal::logger('nidirect_prisons')->error('Failed to send email for order @order_code: @error', [
        '@order_code' => $order_code,
        '@error' => $e->getMessage(),
      ]);
      throw new \Exception('Failed to send payment data to Prism: ' . $e->getMessage());
    }
  }

  protected function getNextSequenceId() {
    $database = \Drupal::database();
    $query = $database->insert('prisoner_payment_sequence')->fields(['id' => NULL]);

    return $query->execute();
  }

}

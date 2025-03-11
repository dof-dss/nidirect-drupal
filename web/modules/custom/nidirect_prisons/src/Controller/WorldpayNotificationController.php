<?php

namespace Drupal\nidirect_prisons\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Controller\ControllerBase;

class WorldpayNotificationController extends ControllerBase {

  public function handleNotification(Request $request) {

    $ip = $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
    if (!$ip) {
      \Drupal::logger('nidirect_prisons')->error('No IP address detected in request.');
      return new Response('Invalid request', 400);
    }

    // Reverse DNS lookup to verify request from worldpay.com.
    $hostname = gethostbyaddr($ip);
    if (!$hostname || !str_ends_with($hostname, '.worldpay.com')) {
      \Drupal::logger('nidirect_prisons')->warning('Unrecognized hostname: @hostname from IP @ip', [
        '@hostname' => $hostname,
        '@ip' => $ip,
      ]);
      return new Response('Access Denied', 403);
    }

    // Forward DNS verification
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

    if (empty($xml_data)) {
      \Drupal::logger('nidirect_prisons')->error('Empty Worldpay notification received.');
      return new Response('Invalid request', 400);
    }

    // Parse XML.
    $xml = simplexml_load_string($xml_data);
    if (!$xml) {
      \Drupal::logger('nidirect_prisons')->error('Invalid XML received in Worldpay notification.');
      return new Response('Invalid XML', 400);
    }

    // Validate XML.
    if (!isset($xml->notify)) {
      \Drupal::logger('worldpay')->error('Missing <notify> element in XML.');
      return new Response('Invalid XML structure', 400);
    }

    // Extract required fields.
    $order_code = (string) $xml->notify->orderStatusEvent['orderCode'];
    $payment_status = (string) $xml->notify->orderStatusEvent->payment->lastEvent;
    $amount = (float) $xml->notify->orderStatusEvent->payment->amount['value'] / 100; // Convert pence to pounds.

    \Drupal::logger('worldpay')->notice('Worldpay order notification for @order_key £@amount @payment_status', [
      '@order_key' => $order_code,
      '@amount' => $amount,
      '@payment_status' => $payment_status,
    ]);

    // Check transaction for order_key exists.
    $db = \Drupal::database();
    $payment_transaction = $db->select('prisoner_payment_transactions', 'ppt')
      ->fields('ppt', ['order_key', 'prisoner_id', 'visitor_id', 'amount', 'status'])
      ->condition('order_key', $order_code)
      ->condition('status', 'pending')
      ->execute()
      ->fetchAssoc();

    if (!$payment_transaction) {
      \Drupal::logger('nidirect_prisons')->error("No matching transaction found for order key: {$order_code}");
      return new Response('Transaction not found', 404);
    }

    // Handle the payment status.
    $allowed_statuses = ['AUTHORISED', 'CANCELLED', 'SHOPPER_CANCELLED', 'REFUSED', 'EXPIRED', 'REQUEST_EXPIRED', 'ERROR'];
    if (!in_array($payment_status, $allowed_statuses, true)) {
      \Drupal::logger('nidirect_prisons')->warning('Unhandled Worldpay payment status: @status', [
        '@status' => $payment_status,
      ]);
      return new Response('Unknown status', 400);
    }

    // If payment was successful, update prisoner balance and send
    // payment details to Prism.
    if ($payment_status === 'AUTHORISED') {

      // Deduct from prisoner's balance.
      $db_transaction = $db->startTransaction();

      try {
        $db->update('prisoner_payment_amount')
          ->expression('amount', 'GREATEST(amount - :paid_amount, 0)', [':paid_amount' => $amount])
          ->condition('prisoner_id', $payment_transaction['prisoner_id'])
          ->execute();
      }
      catch (\Exception $e) {
        $db_transaction->rollBack();
        \Drupal::logger('nidirect_prisons')->error('Balance update failed: @message', ['@message' => $e->getMessage()]);
        return new Response('Transaction failed', 500);
      }
      finally {
        unset($db_transaction);
      }

      // Get sequence id for this payment.
      $sequence_id = $this->getNextSequenceId();

      // Send payment details to Prism.
      $this->sendJsonToPrism($order_code, $payment_transaction['prisoner_id'], $payment_transaction['visitor_id'], $amount, $sequence_id);

      // Set transaction status as success.
      $transaction_status = 'success';
    }
    // Otherwise the payment was cancelled, refused or some other
    // status which means it failed.
    elseif ($payment_status === 'CANCELLED' || $payment_status === 'SHOPPER_CANCELLED') {
      $transaction_status = 'cancelled';
    }
    elseif ($payment_status === 'REFUSED') {
      $transaction_status = 'refused';
    }
    else {
      $transaction_status = 'failed';
    }

    // Update transaction table.
    $db->update('prisoner_payment_transactions')
      ->fields(['status' => $transaction_status])
      ->condition('order_key', $order_code)
      ->execute();


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

    \Drupal::service('plugin.manager.mail')->mail(
      'nidirect_prisons',
      'prisoner_payment_notification',
      getenv('PRISONER_PAYMENTS_PRISM_EMAIL') ?: 'prisoner_payments@mailhog.local',
      \Drupal::languageManager()->getDefaultLanguage()->getId(),
      ['subject' => 'PAYIN', 'body' => [$json_data]]
    );

    \Drupal::logger('nidirect_prisons')->notice("Sent prisoner payment data for order {$order_code} to Prism.");
  }

  protected function getNextSequenceId() {
    $database = \Drupal::database();
    $query = $database->insert('prisoner_payment_sequence')->fields(['id' => NULL]);

    return $query->execute();
  }

}

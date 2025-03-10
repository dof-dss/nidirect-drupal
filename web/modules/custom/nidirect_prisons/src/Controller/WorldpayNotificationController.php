<?php

namespace Drupal\nidirect_prisons\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Controller\ControllerBase;

class WorldpayNotificationController extends ControllerBase {

  public function handleNotification(Request $request) {

    $ip = $_SERVER['REMOTE_ADDR'];
    $hostname = gethostbyaddr($ip);

    // Ensure hostname ends with worldpay.com.
    if (!$hostname || !preg_match('/\.worldpay\.com$/', $hostname)) {
      \Drupal::logger('nidirect_prisons')->warning('Worldpay notification hostname @hostname not recognised.', ['@hostname' => $hostname]);
      return new Response('Access Denied', 403);
    }

    // Perform a forward DNS lookup to verify the hostname maps back to the same IP.
    $resolved_ips = gethostbynamel($hostname);
    if (!$resolved_ips || !in_array($ip, $resolved_ips, TRUE)) {
      \Drupal::logger('nidirect_prisons')->warning('Worldpay notification forward DNS lookup failed. @ip is not a resolved IP for @hostname. Resolved IPs are: @resolved_ips', [
        '@ip' => $ip,
        '@hostname' => $hostname,
        '@resolved_ips' => print_r($resolved_ips, TRUE),
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

    // Check transaction for order exists.
    $db = \Drupal::database();
    $transaction = $db->select('prisoner_payment_transactions', 'ppt')
      ->fields('ppt', ['order_key', 'prisoner_id', 'visitor_id', 'amount', 'status'])
      ->condition('order_key', $order_code)
      ->condition('status', 'pending')
      ->execute()
      ->fetchAssoc();

    if (!$transaction) {
      \Drupal::logger('nidirect_prisons')->error("No matching transaction found for order key: {$order_code}");
      return new Response('Transaction not found', 404);
    }

    // If payment was successful, update transaction and prisoner balance.
    if ($payment_status === 'AUTHORISED') {
      // Update transaction status.
      $db->update('prisoner_payment_transactions')
        ->fields(['status' => 'success'])
        ->condition('order_key', $order_code)
        ->execute();

      // Deduct from prisoner's balance.
      $db->update('prisoner_payment_amount')
        ->expression('amount', 'amount - :paid_amount', [':paid_amount' => $amount])
        ->condition('prisoner_id', $transaction['prisoner_id'])
        ->execute();

      // Get sequence id for this payment.
      $sequence_id = $this->getNextSequenceId();

      // Send payment details to Prism.
      $this->sendJsonToPrism($order_code, $transaction['prisoner_id'], $transaction['visitor_id'], $amount, $sequence_id);
    }

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

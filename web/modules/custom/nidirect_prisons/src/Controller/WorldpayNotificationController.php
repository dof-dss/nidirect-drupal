<?php

namespace Drupal\nidirect_prisons\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\nidirect_prisons\Enum\PaymentStatus;
use Drupal\nidirect_prisons\Service\PrisonerPaymentManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WorldpayNotificationController extends ControllerBase {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * @var \Drupal\nidirect_prisons\Service\PrisonerPaymentManager
   *  The Prisoner Payment Manager service.
   */
  protected PrisonerPaymentManager $paymentManager;

  /**
   * @var \Psr\Log\LoggerInterface
   *   The logging service.
   */
  protected LoggerInterface $logger;

  /**
   * @param \Drupal\Core\Database\Connection $database
   *   The DB connection.
   * @param \Drupal\nidirect_prisons\Service\PrisonerPaymentManager $payment_manager
   *   The Payment Manager Service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The Logger service.
   */
  public function __construct(
    Connection $database,
    PrisonerPaymentManager $payment_manager,
    LoggerInterface $logger
  ) {
    $this->database = $database;
    $this->paymentManager = $payment_manager;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('nidirect_prisons.prisoner_payment_manager'),
      $container->get('logger.channel.nidirect_prisons')
    );
  }

  /**
   * Handle notifications from Worldpay to track if a payment has
   * succeeded or failed. See https://docs.worldpay.com/apis/wpg/manage
   * for information on configuring Worldpay order notifications.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The Request.
   * @return \Symfony\Component\HttpFoundation\Response
   *   The Response.
   */
  public function handleNotification(Request $request) {

    /*
     * When a payment request is initialised, the payment transaction is
     * recorded as 'pending' in the prisoner_payment_transactions table.
     * When the hosted payment page is submitted, Worldpay sends
     * notifications on the status of the payment.
     *
     * If the payment is AUTHORISED, the payment transaction updates to
     * 'success' in the prisoner_payment_transactions table
     * and in practice this means that the payment has been
     * successfully approved by the card issuer or bank, but the funds
     * have not yet been captured or settled (debited from visitor's
     * account).
     */

    // Check request is from a Worldpay IP by performing forward and
    // reverse DNS lookups. Deny access to non-Worldpay IP addresses.
    $ip = $request->getClientIp();

    // Reverse DNS lookup.
    $hostname = gethostbyaddr($ip);
    if (!$hostname || !str_ends_with($hostname, '.worldpay.com')) {
      $this->logger->warning('Unrecognized hostname: @hostname from IP @ip', [
        '@hostname' => $hostname,
        '@ip' => $ip,
      ]);
      return new Response('Access Denied', 403);
    }

    // Forward DNS lookup.
    $resolved_ips = gethostbynamel($hostname);
    if (!$resolved_ips || !in_array($ip, $resolved_ips, TRUE)) {
      $this->logger->warning('DNS verification failed for IP @ip with hostname @hostname.', [
        '@ip' => $ip,
        '@hostname' => $hostname,
        '@resolved_ips' => implode(', ', $resolved_ips),
      ]);
      return new Response('Access Denied', 403);
    }

    // Get the raw XML from the request body.
    $xml_data = $request->getContent();

    // Return 400 bad request if there is something wrong with the
    // XML. Note Worldpay will retry sending notifications (for up to 7
    // days) if the response is anything other than a 200 OK.

    if (empty($xml_data)) {
      $this->logger->error('Empty Worldpay notification received.');
      return new Response('Bad request', 400);
    }

    $xml = simplexml_load_string($xml_data);
    if (!$xml) {
      $this->logger->error('Invalid XML received in Worldpay notification.');
      return new Response('Bad request', 400);
    }

    if (!isset($xml->notify)) {
      $this->logger->error('Missing <notify> element in XML.');
      return new Response('Bad request', 400);
    }

    // Process the notification.
    $order_code = (string) $xml->notify->orderStatusEvent['orderCode'];
    $payment_status = (string) $xml->notify->orderStatusEvent->payment->lastEvent;
    $pence = (int) $xml->notify->orderStatusEvent->payment->amount['value'];
    $amount = round($pence / 100, 2);

    // Early return if the status is not one we are expecting.
    if (!PaymentStatus::isAllowed($payment_status)) {
      $this->logger->warning('Unexpected Worldpay order notification status: @status. Have Merchant Channel HTTP Events been changed in the MAI?', [
        '@status' => $payment_status,
      ]);

      // No further processing do be done. Acknowledge the notification.
      return new Response('[OK]', 200, ['Content-Type' => 'text/plain']);
    }

    $this->logger->notice('Worldpay order notification for @order_key £@amount @payment_status', [
      '@order_key' => $order_code,
      '@amount' => number_format($amount, 2, '.', ''),
      '@payment_status' => $payment_status,
    ]);

    // Check transaction for order_key exists.
    $payment_transaction = $this->paymentManager->getTransaction($order_code);

    // Early return if transaction does not exist.
    if (!$payment_transaction) {
      $this->logger->notice("No prisoner payment transaction found for Worldpay notification: {$order_code}");

      // No further processing do be done. Acknowledge the notification.
      return new Response('[OK]', 200, ['Content-Type' => 'text/plain']);
    }

    // Early return if transaction already marked 'success'.
    if ($payment_transaction->status === 'success') {

      // No further processing do be done. Acknowledge the notification.
      return new Response('[OK]', 200, ['Content-Type' => 'text/plain']);
    }

    // If payment was AUTHORISED, update prisoner balance and send
    // payment details to Prism and update transaction status
    // from 'pending' to 'success'. Else the transaction status
    // is 'failed'.
    if ($payment_status === 'AUTHORISED') {

      // Detect late authorisation (timed out or cancelled locally, but
      // authorised by Worldpay).
      $is_late_authorisation = in_array($payment_transaction->status, [
        'expired',
        'cancelled',
      ], TRUE);

      if ($is_late_authorisation) {
        $this->logger->warning(
          'Late Worldpay AUTHORISED received for @status transaction @order',
          [
            '@status' => $payment_transaction->status,
            '@order' => $order_code,
          ]
        );
      }

      // Validate amount integrity (non-negotiable).
      if ((float) $payment_transaction->amount !== (float) $amount) {
        $this->logger->error(
          'Amount mismatch for Worldpay AUTHORISED order @order. Expected £@expected, got £@actual',
          [
            '@order' => $order_code,
            '@expected' => number_format($payment_transaction->amount, 2, '.', ''),
            '@actual' => number_format($amount, 2, '.', ''),
          ]
        );

        $this->paymentManager->updateTransactionStatus($order_code, 'failed');

        return new Response('[OK]', 200, ['Content-Type' => 'text/plain']);
      }

      $sequence_id = NULL;
      $db_transaction = NULL;

      try {
        $db_transaction = $this->database->startTransaction();

        // Attempt atomic status transition first. Only the winning process
        // continues below; all other concurrent notifications are ignored.
        $updated = $this->database->update('prisoner_payment_transactions')
          ->fields([
            'status' => 'success',
            'updated_timestamp' => \Drupal::time()->getRequestTime(),
          ])
          ->condition('order_key', $order_code)
          ->condition('status', ['pending', 'expired', 'cancelled'], 'IN')
          ->execute();

        if ($updated === 0) {
          $this->logger->notice(
            'Worldpay AUTHORISED notification for order @order already processed by another process.',
            ['@order' => $order_code]
          );

          return new Response('[OK]', 200, ['Content-Type' => 'text/plain']);
        }

        // Deduct from prisoner's balance.
        $this->database->update('prisoner_payment_amount')
          ->expression('amount', 'GREATEST(amount - :paid_amount, 0)', [':paid_amount' => $amount])
          ->condition('prisoner_id', $payment_transaction->prisoner_id)
          ->execute();

        // Queue up a Prism notification if there isn't already one.
        $existing_notification = $this->paymentManager->getPrismNotification($order_code);
        if (!$existing_notification) {
          $sequence_id = $this->paymentManager->getNextSequenceId();
          $this->database->insert('prisoner_payment_notifications')
            ->fields([
              'order_key' => $order_code,
              'prisoner_id' => $payment_transaction->prisoner_id,
              'visitor_id' => $payment_transaction->visitor_id,
              'amount' => $amount,
              'sequence_id' => $sequence_id,
              'status' => 'pending',
              'attempts' => 0,
              'created_timestamp' => \Drupal::time()->getRequestTime(),
              'updated_timestamp' => \Drupal::time()->getRequestTime(),
              'last_error' => NULL,
            ])
            ->execute();
        }
        else {
          $this->logger->notice('Worldpay AUTHORISED notification for order @order already queued.', ['@order' => $order_code]);
        }
      }
      catch (\Throwable $e) {

        if ($db_transaction !== NULL) {
          $db_transaction->rollBack();
        }

        $this->logger->error(
          'Failed processing AUTHORISED Worldpay payment for order @order: @message',
          [
            '@order' => $order_code,
            '@message' => $e->getMessage(),
          ]
        );

        return new Response('[OK]', 200, ['Content-Type' => 'text/plain']);
      }
      finally {
        unset($db_transaction);
      }

      $this->logger->notice('Queued Prism notification for order @order_key', [
        '@order_key' => $order_code,
      ]);
    }
    else {
      // Payment failed.
      $this->paymentManager->updateTransactionStatus($order_code, 'failed');
    }

    // Acknowledge the notification.
    return new Response('[OK]', 200, ['Content-Type' => 'text/plain']);
  }

}

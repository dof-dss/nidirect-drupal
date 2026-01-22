<?php

namespace Drupal\nidirect_prisons\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class PrisonerPaymentsController extends ControllerBase {

  /**
   * Handle periodic pings from clientside js to update
   * a pending transaction's updated_timestamp thus preventing
   * expiry of the transaction whilst the user is making a payment.
   *
   * @param Request $request
   * @return JsonResponse
   */
  public function heartbeat(Request $request): JsonResponse {
    $data = json_decode($request->getContent(), TRUE);
    if (empty($data['order_code'])) {
      return new JsonResponse(['status' => 'error'], 400);
    }

    $updated = \Drupal::database()
      ->update('prisoner_payment_transactions')
      ->fields([
        'updated_timestamp' => \Drupal::time()->getRequestTime(),
      ])
      ->condition('order_key', $data['order_code'])
      ->condition('status', 'pending')
      ->execute();

    if ($updated === 0) {
      \Drupal::logger('nidirect_prisons')->debug(
        'Heartbeat ignored for order @order',
        ['@order' => $data['order_code']]
      );
    }

    return new JsonResponse(['status' => 'ok']);
  }

}

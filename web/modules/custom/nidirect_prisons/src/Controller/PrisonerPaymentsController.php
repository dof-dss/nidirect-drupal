<?php

namespace Drupal\nidirect_prisons\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\nidirect_prisons\Service\PrisonerPaymentManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class PrisonerPaymentsController extends ControllerBase {

  protected PrisonerPaymentManager $paymentManager;
  protected LoggerInterface $logger;

  public function __construct(PrisonerPaymentManager $payment_manager, LoggerInterface $logger) {
    $this->paymentManager = $payment_manager;
    $this->logger = $logger;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('nidirect_prisons.prisoner_payment_manager'),
      $container->get('logger.channel.nidirect_prisons')
    );
  }

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

    if (!$this->paymentManager->touchTransaction($data['order_code'])) {
      return new JsonResponse(['status' => 'expired'], 410);
    }

    return new JsonResponse(['status' => 'ok']);
  }

}

<?php

namespace Drupal\prisons_integration\Plugin\Rest;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\prisons_integration\PrisonsIntegrationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a REST resource for maximum amounts that can be paid
 * to prisoners.
 *
 * @RestResource(
 *   id = "prisoner_amounts_resource",
 *   label = @Translation("Prisoner Amounts Resource"),
 *   uri_paths = {
 *     "create" = "/api/prisoner-payments/amounts"
 *   }
 * )
 */
class PrisonerAmountsResource extends ResourceBase {

  protected $integrationService;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, PrisonsIntegrationService $integration_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->integrationService = $integration_service;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('prisons_integration.service')
    );
  }

  /**
   * Handles POST requests to update prisoner amounts.
   */
  public function post(Request $request) {

    $data = json_decode($request->getContent(), TRUE);

    if (!$this->integrationService->isValidRequest()) {
      return new ResourceResponse(['error' => 'Unauthorized request'], 403);
    }

    if (empty($data) || !isset($data['MY']) || isset($data['MY']['ID']) || isset($data['MY']['AMT'])) {
      return new ResourceResponse(['error' => 'Invalid data'], 400);
    }

    // Process and store prisoner amounts.
    foreach ($data as $prison => $prisoner) {
      \Drupal::database()->merge('prisoner_payment_amount')
        ->key('prisoner_id', $prisoner['ID'])
        ->fields([
          'prison_id' => $prison,
          'remaining_amount' => $prisoner['AMT']
        ])
        ->execute();
    }

    return new ResourceResponse(['message' => 'Data updated successfully'], 200);
  }
}

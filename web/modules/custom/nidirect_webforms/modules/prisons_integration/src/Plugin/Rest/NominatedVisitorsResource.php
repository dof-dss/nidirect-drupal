<?php

namespace Drupal\prisons_integration\Plugin\Rest;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\prisons_integration\PrisonsIntegrationService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a REST resource for nominated visitors who can make payments
 * to prisoners.
 *
 * @RestResource(
 *   id = "nominated_visitors_resource",
 *   label = @Translation("Nominated Visitors Resource"),
 *   uri_paths = {
 *     "create" = "/api/prisoner-payments/nominees"
 *   }
 * )
 */
class NominatedVisitorsResource extends ResourceBase {

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
   * Handles POST requests to update nominated visitors.
   */
  public function post(Request $request) {

    if (!$this->integrationService->isValidRequest()) {
      return new ResourceResponse(['error' => 'Unauthorized request'], 403);
    }

    $data = json_decode($request->getContent(), TRUE);

    if (empty($data) || !isset($data['MY']) || !isset($data['MY']['ID']) || !isset($data['MY']['N1'])) {
      return new ResourceResponse(['error' => 'Invalid data'], 400);
    }

    // Process and store nominations.
    foreach ($data as $prison => $prisoner) {

      $visitor_ids = [
        $prisoner['N1'],
        $prisoner['N2'],
        $prisoner['N3'],
      ];

      \Drupal::database()->merge('prisoner_payment_nominees')
        ->key('prisoner_id', $prisoner['ID'])
        ->fields(['visitor_ids' => implode(',', $visitor_ids)])
        ->execute();
    }

    return new ResourceResponse(['message' => 'Data successfully updated'], 200);
  }
}

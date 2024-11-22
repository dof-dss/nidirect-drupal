<?php

namespace Drupal\nidirect_prisons\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\nidirect_prisons\PrisonsIntegrationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a REST resource for maximum amounts that can be paid
 * to prisoners.
 *
 * @RestResource(
 *   id = "prisoner_amounts_resource",
 *   label = @Translation("Prisoner Payments Amounts Resource"),
 *   uri_paths = {
 *     "create" = "/api/prisoner-payments/amounts"
 *   }
 * )
 */
class PrisonerAmountsResource extends ResourceBase {

  protected $integrationService;

  /**
   * Constructs a new PrisonerAmountsResource object.
   *
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param PrisonsIntegrationService $integration_service
   *   Entity Type Manager instance.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    PrisonsIntegrationService $integration_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->integrationService = $integration_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('nidirect_prisons'),
      $container->get('nidirect_prisons.service')
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

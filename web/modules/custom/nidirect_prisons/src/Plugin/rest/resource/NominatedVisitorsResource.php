<?php

namespace Drupal\nidirect_prisons\Plugin\rest\resource;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\nidirect_prisons\PrisonsIntegrationService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a REST resource for nominated visitors who can make payments to prisoners.
 *
 * @RestResource(
 *   id = "prisoner_nominees_resource",
 *   label = @Translation("Prisoner Payments Nominees Resource"),
 *   uri_paths = {
 *     "create" = "/api/prisoner-payments/nominees"
 *   }
 * )
 */
class NominatedVisitorsResource extends ResourceBase implements ContainerFactoryPluginInterface {

  /**
   * Prisons Integration service.
   *
   * @var PrisonsIntegrationService
   */
  protected $integrationService;

  /**
   * Constructs a new NominatedVisitorsResource object.
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
   * Handles POST requests to update nominated visitors.
   */
  public function post(Request $request) {

    if (!$this->integrationService->isValidRequest()) {
      return new ResourceResponse(['error' => 'Unauthorized request'], 403);
    }

    $data = json_decode($request->getContent(), TRUE);

    if (empty($data) || !is_array($data)) {
      return new ResourceResponse(['error' => 'Invalid JSON payload'], 400);
    }

    // Process each prison.
    foreach ($data as $prison_key => $prisoners) {

      // Each prison must be an array (of prisoners).
      if (empty($prisoners) || !is_array($prisoners)) {
        return new ResourceResponse(['error' => 'Invalid JSON payload: missing prison and/or prisoner data'], 400);
      }

      // Process each prisoner.
      foreach ($prisoners as $prisoner) {

        // Each prisoner must contain an ID and three
        // nominees (N1, N2, N3).
        if (!array_key_exists('ID', $prisoner)) {
          return new ResourceResponse(['error' => 'Missing required prisoner data: ID'], 400);
        }
        if (!array_key_exists('N1', $prisoner) || !array_key_exists('N2', $prisoner) || !array_key_exists('N3', $prisoner)) {
          return new ResourceResponse(['error' => 'Missing required prisoner data: N1, N2, N3'], 400);
        }

        $visitor_ids = [
          $prisoner['N1'] ?? null,
          $prisoner['N2'] ?? null,
          $prisoner['N3'] ?? null,
        ];

        // Remove null values from visitor_ids to avoid empty entries in the database.
        $visitor_ids = array_filter($visitor_ids);

        try {
          \Drupal::database()->merge('prisoner_payment_nominees')
            ->key('prisoner_id', $prisoner['ID'])
            ->fields(['visitor_ids' => implode(',', $visitor_ids)])
            ->execute();
        } catch (\Exception $e) {
          \Drupal::logger('nidirect_prisons')->error('Database error: @message', ['@message' => $e->getMessage()]);
          return new ResourceResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
      }
    }

    return new ResourceResponse(['message' => 'Data successfully updated'], 200);
  }
}

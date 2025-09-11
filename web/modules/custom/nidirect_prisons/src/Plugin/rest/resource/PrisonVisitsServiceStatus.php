<?php

namespace Drupal\nidirect_prisons\Plugin\rest\resource;

use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a REST resource for prison visits service status. When
 * not available, the prison_visit_online_booking webform is closed to
 * anonymous users.
 *
 * @RestResource(
 *   id = "prison_visits_service_status",
 *   label = @Translation("Prison Visits Service Status Resource"),
 *   uri_paths = {
 *     "create" = "/api/v1/prison-visits/service-status"
 *   }
 * )
 */
class PrisonVisitsServiceStatus extends ResourceBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new PrisonVisitsServiceStatus object.
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
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
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
      $container->get('logger.factory')->get('nidirect_prisons')
    );
  }

  /**
   * Handles POST requests to update prison visits service status.
   */
  public function post(Request $request) {
    $data = json_decode($request->getContent(), TRUE);

    if (empty($data) || !is_array($data)) {
      return new ResourceResponse(['error' => 'Invalid JSON payload'], 400);
    }

    // Load the prison_visit_online_booking webform.
    $webform_id = 'prison_visit_online_booking';
    $webform = Webform::load($webform_id);

    if (!$webform) {
      return new ResourceResponse(['error' => 'Prison visits webform not found'], 404);
    }

    if (isset($data['available'])) {
      if ($data['available'] === 'false') {
        // Set the closure message if provided. Strip html, as only
        // plain text allowed.
        if (!empty($data['message'])) {
          $webform->setSetting('form_close_message', Html::escape($data['message']));
        }

        // Close the webform for anonymous users.
        $webform->setStatus(WebformInterface::STATUS_CLOSED);

        try {
          $webform->save();
        }
        catch (\Exception $e) {
          $this->logger->error('Failed to update webform status: {message}', ['message' => $e->getMessage()]);
          return new ResourceResponse(['error' => 'Failed to update service status'], 500);
        }

        return new ResourceResponse(['message' => 'Service status updated: STATUS_CLOSED'], 200);
      }
      elseif ($data['available'] === 'true') {
        // Open the webform for anonymous users.
        $webform->setStatus(WebformInterface::STATUS_OPEN);

        try {
          $webform->save();
        }
        catch (\Exception $e) {
          $this->logger->error('Failed to update webform status: {message}', ['message' => $e->getMessage()]);
          return new ResourceResponse(['error' => 'Failed to update service status'], 500);
        }

        return new ResourceResponse(['message' => 'Service status updated: STATUS_OPEN'], 200);
      }
    }

    return new ResourceResponse(['error' => 'Invalid JSON payload'], 400);
  }

}

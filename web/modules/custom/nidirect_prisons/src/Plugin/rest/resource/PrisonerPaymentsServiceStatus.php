<?php

namespace Drupal\nidirect_prisons\Plugin\rest\resource;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\webform\Entity\Webform;
use Drupal\webform\WebformInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a REST resource for prisoner payments service status. When
 * not available, the prisoner_payments webform is closed to anonymous
 * users.
 *
 * @RestResource(
 *   id = "prisoner_payments_service_status",
 *   label = @Translation("Prisoner Payments Service Status Resource"),
 *   uri_paths = {
 *     "create" = "/api/v1/prisoner-payments/service-status"
 *   }
 * )
 */
class PrisonerPaymentsServiceStatus extends ResourceBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new PrisonerPaymentsServiceStatus object.
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
   * Handles POST requests to update prisoner payments service status.
   * @throws EntityStorageException
   */
  public function post(Request $request) {
    $data = json_decode($request->getContent(), TRUE);

    if (empty($data) || !is_array($data)) {
      return new ResourceResponse(['error' => 'Invalid JSON payload'], 400);
    }

    // Load the prisoner_payments webform.
    $webform_id = 'prisoner_payments';
    $webform = Webform::load($webform_id);

    if (!$webform) {
      return new ResourceResponse(['error' => 'Prisoner payments webform not found'], 404);
    }

    if (isset($data['available'])) {
      if ($data['available'] === 'false') {
        // Set the closure message if provided.
        if (!empty($data['message'])) {
          $webform->setSetting('closed_message', $data['message']);
        }

        // Close the webform for anonymous users.
        $webform->setStatus(WebformInterface::STATUS_CLOSED);
        $webform->save();

        return new ResourceResponse(['message' => 'Prisoner Payments service is unavailable'], 200);
      }
      elseif ($data['available'] === 'true') {
        // Open the webform for anonymous users.
        $webform->setStatus(WebformInterface::STATUS_OPEN);
        $webform->save();

        return new ResourceResponse(['message' => 'Prisoner Payments service is available'], 200);
      }
    }

    return new ResourceResponse(['error' => 'Invalid JSON payload'], 400);
  }

}

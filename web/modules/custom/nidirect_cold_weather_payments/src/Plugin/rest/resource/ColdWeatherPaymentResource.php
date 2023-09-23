<?php

namespace Drupal\nidirect_cold_weather_payments\Plugin\rest\resource;

use Drupal\nidirect_cold_weather_payments\Service\ColdWeatherPaymentsService;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a resource to view cold weather payment details.
 *
 * @RestResource(
 *   id = "cold_weather_payment_resource",
 *   label = @Translation("Cold weather payment resource"),
 *   uri_paths = {
 *     "canonical" = "/api/cwp/{postcode}"
 *   }
 * )
 */
class ColdWeatherPaymentResource extends ResourceBase {

  /**
   * Cold Weather Payments service.
   *
   * @var \Drupal\nidirect_cold_weather_payments\Service\ColdWeatherPaymentsService
   */
  protected $paymentsService;

  /**
   * Constructs a new ColdWeatherPaymentResource object.
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
   * @param \Drupal\nidirect_cold_weather_payments\Service\ColdWeatherPaymentsService $payments_service
   *   Entity Type Manager instance.
   */
  public function __construct(
      array $configuration,
      $plugin_id,
      $plugin_definition,
      array $serializer_formats,
      LoggerInterface $logger,
      ColdWeatherPaymentsService $payments_service
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->paymentsService = $payments_service;
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
    $container->get('logger.factory')->get('nidirect_cold_weather_payments'),
    $container->get('nidirect_cold_weather_payments.payments')
    );
  }

  /**
   * Responds to GET requests.
   *
   * @param string $postcode
   *   The postcode to query payment against.
   *
   * @return \Drupal\rest\ResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function get($postcode = NULL) {

    $results = $this->paymentsService->forPostcode($postcode);

    return new ResourceResponse($results, 200);
  }

}

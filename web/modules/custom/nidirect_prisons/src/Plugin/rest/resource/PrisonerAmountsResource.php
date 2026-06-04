<?php

namespace Drupal\nidirect_prisons\Plugin\rest\resource;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
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
 *     "create" = "/api/v1/prisoner-payments/amounts"
 *   }
 * )
 */
class PrisonerAmountsResource extends ResourceBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new PrisonerAmountsResource object.
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
   * Handles POST requests to update prisoner amounts.
   */
  public function post(Request $request) {

    $data = json_decode($request->getContent(), TRUE);

    if (empty($data) || !is_array($data)) {
      return new ResourceResponse(['error' => 'Invalid JSON payload'], 400);
    }

    // Start a database transaction.
    $transaction = \Drupal::database()->startTransaction();

    try {
      // Process each prison.
      foreach ($data as $prison_key => $prisoners) {

        // Each prison must be an array (of prisoners).
        if (empty($prisoners) || !is_array($prisoners)) {
          return new ResourceResponse(['error' => 'Invalid JSON payload: missing prison and/or prisoner data'], 400);
        }

        // Delete existing prisoner amounts for the current prison before inserting new data.
        \Drupal::database()->delete('prisoner_payment_amount')
          ->condition('prison_id', $prison_key)
          ->execute();

        // Process each prisoner.
        foreach ($prisoners as $prisoner) {

          // Each prisoner must contain an ID and AMT (Amount).
          if (!array_key_exists('ID', $prisoner) || !array_key_exists('AMT', $prisoner)) {
            return new ResourceResponse(['error' => 'Missing required prisoner data'], 400);
          }

          \Drupal::database()->merge('prisoner_payment_amount')
            ->key('prisoner_id', $prisoner['ID'])
            ->fields([
              'prison_id' => $prison_key,
              'amount' => number_format($prisoner['AMT'], 2, '.', ''),
            ])
            ->execute();
        }
      }

    }
    catch (\Exception $e) {
      // Rollback the transaction if an error occurs.
      $transaction->rollback();

      \Drupal::logger('nidirect_prisons')->error('Database error: @message', ['@message' => $e->getMessage()]);
      return new ResourceResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
    }

    // Commit the transaction by unsetting the $transaction variable.
    unset($transaction);

    return new ResourceResponse(['message' => 'Data successfully updated'], 200);
  }

}

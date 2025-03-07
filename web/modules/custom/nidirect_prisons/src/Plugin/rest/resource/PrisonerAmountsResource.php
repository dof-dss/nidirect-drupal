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

    $connection = \Drupal::database();

    // Check for reset_amount in payload.
    if (isset($data['reset_amount'])) {
      $reset_amount = number_format((float) $data['reset_amount'], 2, '.', '');
      try {
        $connection->update('prisoner_payment_amount')
          ->fields(['amount' => $reset_amount])
          ->execute();
      }
      catch (\Exception $e) {
        \Drupal::logger('nidirect_prisons')->error('Database error: @message', ['@message' => $e->getMessage()]);
        return new ResourceResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
      }
      return new ResourceResponse(['message' => 'All prisoner amounts reset to ' . $reset_amount], 200);
    }

    // Process each prison.
    foreach ($data as $prison_key => $prisoners) {
      if (!is_array($prisoners)) {
        return new ResourceResponse(['error' => 'Invalid JSON payload: missing prison and/or prisoner data'], 400);
      }

      foreach ($prisoners as $prisoner) {
        if (!isset($prisoner['ID']) || !isset($prisoner['AMT'])) {
          return new ResourceResponse(['error' => 'Missing required prisoner data'], 400);
        }

        $prisoner_id = $prisoner['ID'];
        $new_amount = number_format((float) $prisoner['AMT'], 2, '.', '');

        try {
          // Get the current stored amount.
          $query = $connection->select('prisoner_payment_amount', 'p')
            ->fields('p', ['amount'])
            ->condition('prisoner_id', $prisoner_id)
            ->execute()
            ->fetchField();

          if ($query === FALSE || $new_amount < $query) {
            // Update if no record exists or if new amount is less.
            $connection->merge('prisoner_payment_amount')
              ->key('prisoner_id', $prisoner_id)
              ->fields([
                'prison_id' => $prison_key,
                'amount' => $new_amount,
              ])
              ->execute();
          }
        }
        catch (\Exception $e) {
          \Drupal::logger('nidirect_prisons')->error('Database error: @message', ['@message' => $e->getMessage()]);
          return new ResourceResponse(['error' => 'Database error: ' . $e->getMessage()], 500);
        }
      }
    }

    return new ResourceResponse(['message' => 'Data successfully updated'], 200);
  }

}

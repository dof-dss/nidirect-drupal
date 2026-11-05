<?php

namespace Drupal\nidirect_prisons\Plugin\rest\resource;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Receives a full snapshot of prisoner house memberships from Prism.
 *
 * @RestResource(
 *   id = "prisoner_house_membership_resource",
 *   label = @Translation("Prisoner House Membership Resource"),
 *   uri_paths = {
 *     "create" = "/api/v1/prison-visits/inmates"
 *   }
 * )
 */
class PrisonerHouseMembershipResource extends ResourceBase {

  /**
   * Lock shared by all imports of the membership snapshot.
   */
  private const IMPORT_LOCK = 'nidirect_prisons.house_membership_import';

  /**
   * Constructs the prisoner house membership resource.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    private readonly Connection $database,
    private readonly LockBackendInterface $lock,
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
      $container->get('logger.channel.nidirect_prisons'),
      $container->get('database'),
      $container->get('lock'),
    );
  }

  /**
   * Handles POST requests replacing the complete membership snapshot.
   */
  public function post(Request $request): ModifiedResourceResponse {
    try {
      $memberships = $this->validateSnapshot($request->getContent());
    }
    catch (\JsonException | \InvalidArgumentException $exception) {
      $message = $exception instanceof \JsonException
        ? 'Invalid JSON payload.'
        : $exception->getMessage();
      $this->logger->warning('Rejected prisoner house membership snapshot: @reason', ['@reason' => $message]);
      return new ModifiedResourceResponse(['error' => $message], 400);
    }

    $locked = FALSE;

    try {
      $locked = $this->lock->acquire(self::IMPORT_LOCK, 300.0);
      if (!$locked) {
        $this->logger->warning('Prisoner house membership import already in progress.');
        return new ModifiedResourceResponse(
          ['error' => 'A membership import is already in progress. Retry this snapshot.'],
          503,
          ['Retry-After' => '5'],
        );
      }

      $this->replaceSnapshot($memberships);
    }
    catch (DatabaseException | \PDOException $exception) {
      // Database exception messages may contain SQL and prisoner identifiers.
      $this->logger->error('Prisoner house membership import failed: @exception', [
        '@exception' => get_class($exception),
      ]);
      return new ModifiedResourceResponse(['error' => 'Unable to update prisoner house memberships.'], 500);
    }
    finally {
      if ($locked) {
        $this->lock->release(self::IMPORT_LOCK);
      }
    }

    $this->logger->notice('Updated prisoner house membership snapshot with @count memberships.', [
      '@count' => count($memberships),
    ]);
    return new ModifiedResourceResponse([
      'message' => 'Prisoner house memberships successfully updated.',
      'memberships' => count($memberships),
    ]);
  }

  /**
   * Replaces memberships in a single transaction while the import lock is held.
   *
   * @param array<array{prisoner_id: string, prison_id: string, house_id: string}> $memberships
   *   Validated membership rows.
   */
  private function replaceSnapshot(array $memberships): void {
    $transaction = $this->database->startTransaction();

    try {
      $this->database->delete('prisoner_house_membership')->execute();
      foreach (array_chunk($memberships, 200) as $batch) {
        $insert = $this->database->insert('prisoner_house_membership')
          ->fields(['prisoner_id', 'prison_id', 'house_id']);
        foreach ($batch as $membership) {
          $insert->values($membership);
        }
        $insert->execute();
      }
    }
    catch (DatabaseException | \PDOException $exception) {
      $transaction->rollBack();
      throw $exception;
    }

    // Drupal commits when the transaction object is destroyed.
    unset($transaction);
  }

  /**
   * Validates and flattens a snapshot before any stored data is changed.
   *
   * @return array<array{prisoner_id: string, prison_id: string, house_id: string}>
   *   Validated membership rows.
   *
   * @throws \JsonException
   *   If the request is not valid JSON.
   * @throws \InvalidArgumentException
   *   If the snapshot structure or identifiers are invalid.
   */
  private function validateSnapshot(string $content): array {
    $data = json_decode($content, FALSE, 512, JSON_THROW_ON_ERROR);
    if (!$data instanceof \stdClass || !isset($data->INMATES) || !$data->INMATES instanceof \stdClass) {
      throw new \InvalidArgumentException('Payload must contain an INMATES object keyed by prison.');
    }

    $memberships = [];
    $seen = [];
    foreach ($data->INMATES as $prison_id => $houses) {
      if (!in_array($prison_id, ['MY', 'MN', 'HW'], TRUE)) {
        throw new \InvalidArgumentException('INMATES prison codes must be MY, MN or HW.');
      }
      if (!$houses instanceof \stdClass) {
        throw new \InvalidArgumentException('Each prison must contain an object keyed by house code.');
      }

      foreach ($houses as $house_id => $prisoners) {
        if (preg_match('/\A[A-Z0-9_]{1,64}\z/', $house_id) !== 1) {
          throw new \InvalidArgumentException('House codes must contain 1 to 64 uppercase letters, digits or underscores.');
        }
        if (!is_array($prisoners)) {
          throw new \InvalidArgumentException('Each house must contain a JSON list of prisoner IDs.');
        }

        foreach ($prisoners as $prisoner_id) {
          if (!is_string($prisoner_id) || preg_match('/\A[A-Z][0-9]{1,4}\z/', $prisoner_id) !== 1) {
            throw new \InvalidArgumentException('Prisoner IDs must be strings containing one uppercase letter and 1 to 4 digits.');
          }
          if (isset($seen[$prisoner_id])) {
            throw new \InvalidArgumentException('A prisoner ID occurs more than once in the snapshot.');
          }

          $seen[$prisoner_id] = TRUE;
          $memberships[] = [
            'prisoner_id' => $prisoner_id,
            'prison_id' => $prison_id,
            'house_id' => $house_id,
          ];
        }
      }
    }

    if (!$memberships) {
      throw new \InvalidArgumentException('Snapshot must contain at least one prisoner; empty snapshots cannot clear memberships.');
    }

    return $memberships;
  }

}

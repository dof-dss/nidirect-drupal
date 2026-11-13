<?php

namespace Drupal\nidirect_prisons\Plugin\rest\resource;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\DatabaseException;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Receives a full snapshot of prison visit slots from Prism.
 *
 * @RestResource(
 *   id = "prison_visit_slots_resource",
 *   label = @Translation("Prison Visit Slots Resource"),
 *   uri_paths = {
 *     "create" = "/api/v1/prison-visits/slots"
 *   }
 * )
 */
class PrisonVisitSlotsResource extends ResourceBase {

  /**
   * Lock shared by all imports of the visit slots snapshot.
   */
  private const IMPORT_LOCK = 'nidirect_prisons.visit_slots_import';

  /**
   * Constructs the prison visit slots resource.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    private readonly Connection $database,
    private readonly LockBackendInterface $lock,
    private readonly TimeInterface $time,
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
      $container->get('datetime.time'),
    );
  }

  /**
   * Handles POST requests replacing the complete visit slots snapshot.
   */
  public function post(Request $request): ModifiedResourceResponse {
    try {
      $slots = $this->validateSnapshot($request->getContent());
    }
    catch (\JsonException | \InvalidArgumentException $exception) {
      $message = $exception instanceof \JsonException
        ? 'Invalid JSON payload.'
        : $exception->getMessage();
      $this->logger->warning('Rejected prison visit slots snapshot: @reason', ['@reason' => $message]);
      return new ModifiedResourceResponse(['error' => $message], 400);
    }

    $locked = FALSE;

    try {
      $locked = $this->lock->acquire(self::IMPORT_LOCK, 300.0);
      if (!$locked) {
        $this->logger->warning('Prison visit slots import already in progress.');
        return new ModifiedResourceResponse(
          ['error' => 'A visit slots import is already in progress. Retry this snapshot.'],
          503,
          ['Retry-After' => '5'],
        );
      }

      $this->replaceSnapshot($slots);
    }
    catch (DatabaseException | \PDOException $exception) {
      $this->logger->error('Prison visit slots import failed: @exception', [
        '@exception' => get_class($exception),
      ]);
      return new ModifiedResourceResponse(['error' => 'Unable to update prison visit slots.'], 500);
    }
    finally {
      if ($locked) {
        $this->lock->release(self::IMPORT_LOCK);
      }
    }

    $this->logger->notice('Updated prison visit slots snapshot with @count slots.', [
      '@count' => count($slots),
    ]);
    return new ModifiedResourceResponse([
      'message' => 'Prison visit slots successfully updated.',
      'slots' => count($slots),
    ]);
  }

  /**
   * Replaces all stored visit slots in a single transaction.
   *
   * @param array<array{visit_type: string, prison_id: string, affiliation_id: string, slot_datetime: int}> $slots
   *   Validated visit slot rows.
   */
  private function replaceSnapshot(array $slots): void {
    $transaction = $this->database->startTransaction();
    $updated_timestamp = $this->time->getRequestTime();

    try {
      $this->database->delete('prison_visit_slots')->execute();
      foreach (array_chunk($slots, 200) as $batch) {
        $insert = $this->database->insert('prison_visit_slots')
          ->fields([
            'visit_type',
            'prison_id',
            'affiliation_id',
            'slot_datetime',
            'updated_timestamp',
          ]);
        foreach ($batch as $slot) {
          $slot['updated_timestamp'] = $updated_timestamp;
          $insert->values($slot);
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
   * Validates and flattens a snapshot before changing stored data.
   *
   * @return array<array{visit_type: string, prison_id: string, affiliation_id: string, slot_datetime: int}>
   *   Validated visit slot rows.
   *
   * @throws \JsonException
   *   If the request is not valid JSON.
   * @throws \InvalidArgumentException
   *   If the snapshot structure or slot values are invalid.
   */
  private function validateSnapshot(string $content): array {
    $data = json_decode($content, FALSE, 512, JSON_THROW_ON_ERROR);
    if (!$data instanceof \stdClass || !isset($data->SLOTS) || !$data->SLOTS instanceof \stdClass) {
      throw new \InvalidArgumentException('Payload must contain a SLOTS object.');
    }

    $slots = [];
    $seen = [];
    foreach ($data->SLOTS as $visit_type => $groups) {
      if (!in_array($visit_type, ['FACE_TO_FACE', 'VIRTUAL'], TRUE)) {
        throw new \InvalidArgumentException('Visit types must be FACE_TO_FACE or VIRTUAL.');
      }
      if (!$groups instanceof \stdClass) {
        throw new \InvalidArgumentException('Each visit type must contain an object of prison slots.');
      }

      if ($visit_type === 'FACE_TO_FACE') {
        foreach ($groups as $prison_affiliation => $datetimes) {
          if (preg_match('/\A(MY|MN|HW)_(.+)\z/', $prison_affiliation, $matches) !== 1) {
            throw new \InvalidArgumentException('Face-to-face slot keys must start with a prison code followed by an affiliation code.');
          }
          $prison_id = $matches[1];
          $affiliation_id = $matches[2];
          $this->validateAffiliationId($affiliation_id);
          if (!is_array($datetimes) || !array_is_list($datetimes) || empty($datetimes)) {
            throw new \InvalidArgumentException('Face-to-face slots must be a list of date/time values.');
          }

          foreach ($datetimes as $datetime) {
            $this->addSlot($slots, $seen, $visit_type, $prison_id, $affiliation_id, $datetime);
          }
        }
      }
      else {
        foreach ($groups as $prison_id => $affiliations) {
          $this->validatePrisonId($prison_id);
          if (!$affiliations instanceof \stdClass) {
            throw new \InvalidArgumentException('Each virtual visit prison must contain an object of affiliation slots.');
          }

          foreach ($affiliations as $affiliation_id => $datetimes) {
            $this->validateAffiliationId($affiliation_id);
            if (!is_array($datetimes) || !array_is_list($datetimes) || empty($datetimes)) {
              throw new \InvalidArgumentException('Virtual affiliation slots must be a list of date/time values.');
            }

            foreach ($datetimes as $datetime) {
              $this->addSlot($slots, $seen, $visit_type, $prison_id, $affiliation_id, $datetime);
            }
          }
        }
      }
    }

    if (!$slots) {
      throw new \InvalidArgumentException('Snapshot must contain at least one visit slot; empty snapshots cannot clear existing slots.');
    }

    return $slots;
  }

  /**
   * Validates and appends a slot row.
   *
   * @param array $slots
   *   Rows accumulated from the snapshot.
   * @param array $seen
   *   Slot keys already encountered.
   * @param string $visit_type
   *   Visit type.
   * @param string $prison_id
   *   Prison code.
   * @param string $affiliation_id
   *   Affiliation code.
   * @param mixed $datetime
   *   Local date/time string from Prism.
   */
  private function addSlot(array &$slots, array &$seen, string $visit_type, string $prison_id, string $affiliation_id, mixed $datetime): void {
    if (!is_string($datetime)) {
      throw new \InvalidArgumentException('Slot date/time values must be strings.');
    }

    $slot_datetime = $this->parseDatetime($datetime);
    $slot_key = implode(':', [
      $visit_type,
      $prison_id,
      $affiliation_id,
      $slot_datetime,
    ]);
    if (isset($seen[$slot_key])) {
      throw new \InvalidArgumentException('A visit slot occurs more than once in the snapshot.');
    }

    $seen[$slot_key] = TRUE;
    $slots[] = [
      'visit_type' => $visit_type,
      'prison_id' => $prison_id,
      'affiliation_id' => $affiliation_id,
      'slot_datetime' => $slot_datetime,
    ];
  }

  /**
   * Validates and parses a Prism local date/time into a Unix timestamp.
   */
  private function parseDatetime(string $datetime): int {
    $date = \DateTimeImmutable::createFromFormat(
      '!d/m/Y H:i:s',
      $datetime,
      new \DateTimeZone('Europe/London'),
    );
    $errors = \DateTimeImmutable::getLastErrors();
    if (
      $date === FALSE
      || ($errors !== FALSE && ($errors['warning_count'] || $errors['error_count']))
      || $date->format('d/m/Y H:i:s') !== $datetime
    ) {
      throw new \InvalidArgumentException('Slot date/time values must use the format DD/MM/YYYY HH:MM:SS and be valid dates.');
    }

    return $date->getTimestamp();
  }

  /**
   * Validates a supported prison code.
   */
  private function validatePrisonId(string $prison_id): void {
    if (!in_array($prison_id, ['MY', 'MN', 'HW'], TRUE)) {
      throw new \InvalidArgumentException('Prison codes must be MY, MN or HW.');
    }
  }

  /**
   * Validates an affiliation code.
   */
  private function validateAffiliationId(string $affiliation_id): void {
    if (preg_match('/\A[A-Z0-9_]{1,64}\z/', $affiliation_id) !== 1) {
      throw new \InvalidArgumentException('Affiliation codes must contain 1 to 64 uppercase letters, digits or underscores.');
    }
  }

}

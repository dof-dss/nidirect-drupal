<?php

namespace Drupal\nidirect_money_advice_articles\EventSubscriber;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Uuid\UuidInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class PostMigrationSubscriber.
 *
 * Post Migrate processes.
 */
class PostMigrationSubscriber implements EventSubscriberInterface {

  /**
   * Drupal\Core\Logger\LoggerChannel definition.
   *
   * @var \Drupal\Core\Logger\LoggerChannel
   */
  protected $logger;

  /**
   * Stores the entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal 8 database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $dbConnDrupal8;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuid;

  /**
   * PostMigrationSubscriber constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The Entity manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $logger
   *   Drupal logger.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\Component\Uuid\UuidInterface $uuid
   *   The UUID service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager,
                              LoggerChannelFactory $logger,
                              TimeInterface $time,
                              UuidInterface $uuid) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger->get('nidirect_money_advice_articles');
    $this->dbConnDrupal8 = Database::getConnection('default', 'default');
    $this->time = $time;
    $this->uuid = $uuid;
  }

  /**
   * Get subscribed events.
   *
   * @inheritdoc
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_IMPORT][] = ['onMigratePostImport'];
    return $events;
  }

  /**
   * Handle post import migration event.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The import event object.
   */
  public function onMigratePostImport(MigrateImportEvent $event) {
    $event_id = $event->getMigration()->getBaseId();

    // Only process nodes, nothing else.
    if ($event_id === 'money_advice_service_rss_articles') {
      $this->logger->notice($this->processFlags());
    }
  }

  /**
   * Set locked content flag for MAS articles.
   *
   * @return string
   *   Info about the result.
   * @throws \Exception
   */
  protected function processFlags() {
    // Clear out the flag_counts table so we can reinsert new values.
    $this->dbConnDrupal8->query("DELETE fc.* FROM {flag_counts} fc
        JOIN {migrate_map_money_advice_service_rss_articles} mm ON mm.destid1 = fc.entity_id
        WHERE fc.flag_id = 'locked_content'")->execute();
    $this->dbConnDrupal8->query("DELETE f.* FROM {flagging} f
        JOIN {migrate_map_money_advice_service_rss_articles} mm ON mm.destid1 = f.entity_id
        WHERE f.flag_id = 'locked_content'")->execute();

    // Get list of known ids from the migrate map table.
    $query = $this->dbConnDrupal8->query("SELECT destid1 from {migrate_map_money_advice_service_rss_articles}");
    $mas_nids = $query->fetchAll();

    foreach ($mas_nids as $row) {
      // Insert/update the flag_counts table to add the locked_content flag to each item.
      $query = $this->dbConnDrupal8->insert('flag_counts')->fields([
        'flag_id',
        'entity_type',
        'entity_id',
        'count',
        'last_updated',
      ]);
      $query->values([
        'locked_content',
        'node',
        $row->destid1,
        1,
        $this->time->getCurrentTime(),
      ]);
      $query->execute();

      $query = $this->dbConnDrupal8->insert('flagging')->fields([
        'flag_id',
        'uuid',
        'entity_type',
        'entity_id',
        'global',
        'uid',
        'session_id',
        'created',
      ]);
      $query->values([
        'locked_content',
        $this->uuid->generate(),
        'node',
        $row->destid1,
        TRUE,
        1,
        'NULL',
        $this->time->getCurrentTime(),
      ]);
      $query->execute();
    }

    return "Processed locked_content flags for Money Advice Service nodes";
  }

}

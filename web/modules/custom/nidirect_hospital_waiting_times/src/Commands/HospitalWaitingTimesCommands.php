<?php

namespace Drupal\nidirect_hospital_waiting_times\Commands;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for hospital waiting times.
 */
class HospitalWaitingTimesCommands extends DrushCommands {

  /**
   * Cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * {@inheritdoc}
   */
  public function __construct(CacheTagsInvalidatorInterface $cache_tags_invalidator) {
    $this->cacheTagsInvalidator = $cache_tags_invalidator;
  }

  /**
   * Clear the hospital_waiting_times cache.
   *
   * @command nidirect:hwt-cache-clear
   * @aliases hwt-cache-clear
   */
  public function flush() {
    $this->cacheTagsInvalidator->invalidateTags(['hospital_waiting_times']);
    $this->output()->writeln('Clearing Hospital waiting times caches');
  }

}

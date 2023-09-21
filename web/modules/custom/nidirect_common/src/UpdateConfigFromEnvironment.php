<?php

namespace Drupal\nidirect_common;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Utility class to update Google Map Api keys.
 */
class UpdateConfigFromEnvironment {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new UpdateConfigFromEnvironment.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * Update config from an environment variable.
   */
  public function updateApiKey($config_key, $config_subkey, $environment_key) {
    // Update the specified config with the contents of the
    // supplied environment variable. This can be useful if we don't
    // want to store sensitive data like API keys in config, but
    // we can store them in environment variables instead.
    $config = $this->configFactory->getEditable($config_key)->get('configuration');
    if (!empty($config) &&
      isset($config[$config_subkey]) &&
      ($config[$config_subkey] != getenv($environment_key))) {
      // Overwrite the sub key.
      $config[$config_subkey] = getenv($environment_key);
      $this->configFactory->getEditable($config_key)->set('configuration', $config)->save();
    }
  }

}

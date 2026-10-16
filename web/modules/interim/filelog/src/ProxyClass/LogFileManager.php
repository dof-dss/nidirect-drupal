<?php

namespace Drupal\filelog\ProxyClass;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\filelog\LogFileManager as RealLogFileManager;
use Drupal\filelog\LogFileManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a proxy class for \Drupal\filelog\LogFileManager.
 *
 * @see \Drupal\Component\ProxyBuilder\ProxyBuilder
 */
class LogFileManager implements LogFileManagerInterface {

  use DependencySerializationTrait;

  /**
   * The id of the original service.
   *
   * @var string
   */
  protected string $drupalProxyOriginalServiceId;

  /**
   * The real service, after it was lazy loaded.
   *
   * @var \Drupal\filelog\LogFileManager
   */
  protected RealLogFileManager $service;

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected ContainerInterface $container;

  /**
   * Constructs a ProxyClass Drupal proxy object.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param string $drupal_proxy_original_service_id
   *   The service ID of the original service.
   */
  public function __construct(ContainerInterface $container, string $drupal_proxy_original_service_id) {
    $this->container = $container;
    $this->drupalProxyOriginalServiceId = $drupal_proxy_original_service_id;
  }

  /**
   * Lazy loads the real service from the container.
   *
   * @return object
   *   Returns the constructed real service.
   */
  protected function lazyLoadItself(): LogFileManagerInterface {
    if (!isset($this->service)) {
      /** @var \Drupal\filelog\LogFileManager $service */
      $service = $this->container->get($this->drupalProxyOriginalServiceId);
      $this->service = $service;
    }

    return $this->service;
  }

  /**
   * {@inheritdoc}
   */
  public function ensurePath(): bool {
    return $this->lazyLoadItself()->ensurePath();
  }

  /**
   * {@inheritdoc}
   */
  public function getFileName(): string {
    return $this->lazyLoadItself()->getFileName();
  }

  /**
   * {@inheritdoc}
   */
  public function setFilePermissions(): bool {
    return $this->lazyLoadItself()->setFilePermissions();
  }

}

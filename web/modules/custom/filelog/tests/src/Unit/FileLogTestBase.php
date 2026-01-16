<?php

namespace Drupal\Tests\filelog\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\File\FileSystem;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Psr\Log\LoggerInterface;

/**
 * Base class for file-base filelog tests.
 */
abstract class FileLogTestBase extends UnitTestCase {

  /**
   * A mock of the file_system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The virtual file system, for manipulating files in-memory.
   *
   * @var \org\bovigo\vfs\vfsStreamDirectory
   */
  protected vfsStreamDirectory $virtualFileSystem;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $container = new ContainerBuilder();
    /** @var \Drupal\Core\StreamWrapper\StreamWrapperManagerInterface $swManager */
    $swManager = $this->createMock(StreamWrapperManagerInterface::class);
    $settings = new Settings([]);
    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $this->createMock(LoggerInterface::class);
    $this->fileSystem = new FileSystem($swManager, $settings, $logger);
    $container->set('file_system', $this->fileSystem);
    \Drupal::setContainer($container);

    $this->virtualFileSystem = vfsStream::setup('filelog');
  }

}

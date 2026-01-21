<?php

namespace Drupal\filelog;

/**
 * Interface for the LogFileManager service.
 */
interface LogFileManagerInterface {

  /**
   * Ensure that the log directory exists.
   *
   * @return bool
   *   TRUE if the path of the logfile exists and is writeable.
   */
  public function ensurePath(): bool;

  /**
   * Get the complete filename of the log.
   *
   * @return string
   *   The full path (relative or absolute) of the logfile.
   */
  public function getFileName(): string;

  /**
   * Set correct permissions on the log file.
   *
   * @return bool
   *   TRUE for success, FALSE in the event of an error.
   *
   * @see \Drupal\Core\File\FileSystemInterface::chmod()
   */
  public function setFilePermissions(): bool;

}

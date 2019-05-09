<?php
/**
 * @file
 *
 */

namespace Drupal\migrate_islandora_csv\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * File mimetype guesser plugin.
 *
 * @code
 * process:
 *   filemime:
 *     source: filename
 *     plugin: file_mimetype
 * @endcode
 *
 * @MigrateProcessPlugin(
 *  id = "file_mimetype"
 * )
 */
class FileMimeType extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return \Drupal::service('file.mime_type.guesser')->guess($value);
  }

}

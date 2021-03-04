<?php

namespace Drupal\migrate_islandora_csv\Plugin\migrate\process;

use Drupal\controlled_access_terms\EDTFUtils;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Skips processing the current row when the EDTF date isn't valid.
 *
 * Available configuration keys:
 * - intervals (optional): Boolean of whether this field is supporting intervals
 *   or not, defaults to TRUE.
 * - sets (optional): Boolean of whether this field is supporting sets or not,
 *   defaults to TRUE.
 * - strict (optional): Boolean of whether this field is supporting calendar
 *   dates or not, defaults to FALSE.
 *
 * @MigrateProcessPlugin(
 *   id = "valid_edtf"
 * )
 */
class ValidEDTF extends ProcessPluginBase {

  /**
   * Skips the current row when value is not set.
   *
   * @param mixed $value
   *   The input value.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   The migration in which this process is being executed.
   * @param \Drupal\migrate\Row $row
   *   The row from the source to process.
   * @param string $destination_property
   *   The destination property currently worked on. This is only used together
   *   with the $row above.
   *
   * @return mixed
   *   The input value, $value, if it is not empty.
   *
   * @throws \Drupal\migrate\MigrateSkipRowException
   *   Thrown if the source property is not set and the row should be skipped,
   *   records with STATUS_IGNORED status in the map.
   */
  public function validate($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $intervals = $this->configuration['intervals'] ?? TRUE;
    $sets = $this->configuration['sets'] ?? TRUE;
    $strict = $this->configuration['strict'] ?? FALSE;
    $errors = EDTFUtils::validate($value, $intervals, $sets, $strict);
    if (!empty($errors)) {
      throw new MigrateSkipRowException("The value: {$value} is not a valid EDTF date: " . implode(' ', $errors));
    }
    return $value;
  }

}

<?php

namespace Drupal\migrate_islandora_csv\Plugin\migrate\process;

use Drupal\Component\Plugin\ConfigurableInterface;
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
class ValidEDTF extends ProcessPluginBase implements ConfigurableInterface {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $errors = EDTFUtils::validate($value, $this->configuration['intervals'], $this->configuration['sets'], $this->configuration['strict']);
    if (!empty($errors)) {
      throw new MigrateSkipRowException("The value: {$value} is not a valid EDTF date: " . implode(' ', $errors));
    }
    return $value;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'intervals' => TRUE,
      'sets' => TRUE,
      'strict' => FALSE,
    ];
  }

}

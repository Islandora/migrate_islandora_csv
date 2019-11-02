<?php

namespace Drupal\migrate_islandora_csv\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;


/**
 * Convert a string and a key into an associative array.
 *
 * @MigrateProcessPlugin(
 *   id = "str_to_assoc"
 * )
 *
 * To transform a string into an associative array
 * to be used with sub_process:
 *
 * @code
 * placeholder1:
 *   -
 *     source: author_name
 *     plugin: skip_on_empty
 *     method: process
 *   -
 *     plugin: explode
 *     delimiter: '|'
 *   -
 *     plugin: str_to_assoc
 *     key: 'name'
 * 
 * placeholder2:
 *   plugin: sub_process
 *   source: '@placeholder1'
 *   process:
 *     entity: name
 *     relation:
 *       plugin: default_value
 *       default_value: 'relators:aut'
 *
 * field_linked_agent:
 *   plugin: sub_process
 *   source: @placeholder2
 *   process: 
 *     target_id:
 *       process: entity_generate
 *       source: entity
 *       entity_type: taxonomy_term
 *       value_key: name
 *       bundle_key: vid
 *       bundle: person
 *     rel_type: relation
 * @endcode
 *
 */
class StrToAssoc extends ProcessPluginBase {
  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_string($value)) {
	    throw new MigrateException('Plugin str_to_assoc requires a string input.');
    }
    if (!isset($this->configuration['key'])) {
	    throw new MigrateException('Plugin str_to_assoc requires a key.');
    }
    $key = $this->configuration['key'];

    return array($key => $value);
  }
}


<?php

namespace Drupal\trpcultivate\Plugin\Validators;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\ColumnIndices;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\Organism;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validate the existance of a germplasm name in the database.
 *
 * @TripalCultivateValidator(
 *   id = "germplasm_name_exists",
 *   validator_name = @Translation("Germplasm Name Exists Validator"),
 *   input_types = {"data-row"},
 * )
 */
class GermplasmNameExists extends TripalCultivateValidatorBase implements ContainerFactoryPluginInterface {
  /**
   * Validator Traits required by this validator.
   *
   * - ColumnIndices: Gets an array of indices corresponding to the cells in
   *   $row_values to validate.
   * - Organism: Gets an array of organism IDs with which to perform the
   *   germplasm lookup in the database.
   */
  use ColumnIndices;
  use Organism;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Constructs an instance of the Germplasm Name Exists validator.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ChadoConnection $chado_connection,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->chado_connection = $chado_connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tripal_chado.database'),
    );
  }

  /**
   * Validate the values within the cells of this row.
   *
   * @param array $row_values
   *   An array of values from a single row/line in the file where each key maps
   *   to a column index and each value is the content of that column.
   *
   * @return array
   *   An associative array with the following keys.
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': TRUE if the germplasm exists in the database, FALSE otherwise.
   *   - 'failedItems': an array of items that failed with the following keys.
   *     This is an empty array if the data row input was valid.
   */
  public function validateRow($row_values) {

    // Grab our indices.
    $indices = $this->getIndices();

    // Check the indices provided are valid in the context of the row.
    // Will throw an exception if there's a problem.
    $this->checkIndices($row_values, $indices);

    // Grab our list of organism IDs.
    $organism_ids = $this->getOrganismIDs();

    // Initialize our flags for keeping track of validation status.
    $empty = FALSE;
    $missing = FALSE;
    $duplicate = FALSE;
    $failedItems = [];
    // Add our array of organism IDs to failedItems for our failed cases.
    $failedItems['organism_ids'] = $organism_ids;

    // Iterate through our array of row values.
    foreach ($row_values as $index => $cell) {
      // Only validate the values in which their index is also within our
      // context array of indices.
      if (in_array($index, $indices)) {
        // Trim the contents of our cell in case we have flanking whitespace.
        $cell = trim($cell);
        // Check if our cell is empty and save the index if it is.
        if (!isset($cell) || empty($cell)) {
          $empty = TRUE;
          $failedItems['empty_cells'][] = $index;
        }
        else {
          // Check if our cell value is in the chado.stock table.
          // Note that $organism_ids is an array, hence the use of 'IN' here.
          $query = $this->chado_connection->select('1:stock', 's')
            ->fields('s', ['stock_id', 'organism_id', 'name', 'uniquename', 'type_id'])
            ->condition('s.name', $cell, '=')
            ->condition('s.organism_id', $organism_ids, 'IN');
          $records = $query->execute()->fetchAll();
          // Save the records we fetched if there's 2 or more matches.
          if (count($records) >= 2) {
            $duplicate = TRUE;
            $failedItems['duplicate_cells'][$index] = [
              'germplasm_name' => $cell,
              'duplicates' => $records,
            ];
          }
          // Report when a germplasm is missing from the database.
          if (empty($records)) {
            $missing = TRUE;
            $failedItems['missing_cells'][$index]['germplasm_name'] = $cell;
          }
        }
      }
    }

    // If any germplasm name columns were empty for this row, return only this
    // case in the message, but failedItems will have all failed cells.
    if ($empty) {
      return [
        'case' => 'Unable to lookup germplasm with empty values',
        'valid' => FALSE,
        'failedItems' => $failedItems,
      ];
    }
    if ($duplicate) {
      if ($missing) {
        $case_message = 'Missing germplasm name(s) and found duplicate(s) in the database';
      }
      else {
        $case_message = 'Duplicate(s) found in the database for germplasm name(s)';
      }
    }
    elseif ($missing) {
      $case_message = 'Missing germplasm name(s) in the database';
    }
    else {
      // Return the case when a single germplasm name has been found (ie.
      // validation has passed.)
      return [
        'case' => 'Germplasm name(s) exist(s) in the database',
        'valid' => TRUE,
        'failedItems' => [],
      ];
    }

    return [
      'case' => $case_message,
      'valid' => FALSE,
      'failedItems' => $failedItems,
    ];
  }

}

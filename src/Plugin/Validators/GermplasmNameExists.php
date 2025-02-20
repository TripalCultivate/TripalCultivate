<?php

namespace Drupal\trpcultivate\Plugin\Validators;

use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\ColumnIndices;

/**
 * Validate the existance of a germplasm name in the database.
 *
 * @TripalCultivateValidator(
 *   id = "germplasm_name_exists",
 *   validator_name = @Translation("Germplasm Name Exists Validator"),
 *   input_types = {"data-row"},
 * )
 */
class GermplasmNameExists extends TripalCultivateValidatorBase {
  /**
   * Validator Traits required by this validator.
   *
   * - ColumnIndices: Gets an array of indices corresponding to the cells in
   *   $row_values to validate.
   */
  use ColumnIndices;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Validate the values within the cells of this row.
   *
   * @param array $row_values
   *   An array of values from a single row/line in the file where each value
   *   is a single column.
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
    // $organism_ids = $this->getOrganismIDs();
    // Return the case when germplasm name has been found.
    return [
      'case' => 'Germplasm name exists in the database.',
      'valid' => TRUE,
      'failedItems' => [],
    ];
  }

}

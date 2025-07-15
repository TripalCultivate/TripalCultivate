<?php

namespace Drupal\trpcultivate\Plugin\Validators;

use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\ColumnIndices;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\ValidValues;

/**
 * Validate that column only contains a set list of values.
 *
 * @TripalCultivateValidator(
 *   id = "value_in_list",
 *   validator_name = @Translation("Value In List Validator"),
 *   input_types = {"header-row", "data-row"},
 * )
 */
class ValueInList extends TripalCultivateValidatorBase {

  /**
   * Validator Traits required by this validator.
   *
   * - ColumnIndices: Gets an array of indices corresponding to the cells in
   *   $row_values to validate.
   * - ValidValues: Gets an array of values that are allowed within the cell(s)
   *   located at the indices provided by getIndices().
   */
  use ColumnIndices;
  use ValidValues;

  /**
   * A mapping of all of the tokens supported by this validator.
   *
   * @var array
   *   An associative array mapping tokens to their details, such as the
   *   developer case string and the default message to substitute the token.
   *   The following tokens are implemented for this mapping, with the following
   *   descriptions for their 'default-msg' values:
   *   - 'case-invalid-value': the message when there is an invalid value.
   *   - 'case-insensitive-match': the message when there is invalid value with
   *     case insensitive match.
   *
   * @see TripalCultivate/src/TripalCultivateValidator/TripalCultivateValidatorBase::$mapping
   */
  protected static array $mapping = [
    'case-invalid-value' => [
      'token' => 'case-invalid-value',
      'dev-case' => 'Invalid value(s) in required column(s)',
      'default-msg' => 'The following line number and column combinations did not contain one of the following allowed values: [expected-values]. <strong>If any cell in the table below is empty, then the value given in the file for that cell was one of the allowed values.</strong>',
    ],
    'case-insensitive-match' => [
      'token' => 'case-insensitive-match',
      'dev-case' => 'Invalid value(s) in required column(s) with >=1 case insensitive match',
      'default-msg' => 'The following line number and column combinations did not contain one of the following allowed values: [expected-values] Note that values should be case sensitive. <strong>If any cell in the table below is empty, then the value given in the file for that cell was one of the allowed values.</strong>',
    ],
    'expected-values' => [
      'token' => 'expected-values',
      'default-msg' => '',
    ],
    'case-valid' => [
      'token' => 'case-valid',
      'dev-case' => 'Values in required column(s) are valid',
    ],
  ];

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
   *   - 'valid': FALSE if any of the cells being checked contain a value not in
   *     the configured list of approved values and TRUE otherwise.
   *   - 'failedItems': an array of "items" that failed. This is an empty array
   *     if the data row input was valid. The array contains key => value pairs
   *     that map to the index => cell value(s) that failed validation.
   */
  public function validateRow($row_values) {

    // Grab our indices.
    $indices = $this->getIndices();

    // Check the indices provided are valid in the context of the row.
    // Will throw an exception if there's a problem.
    $this->checkIndices($row_values, $indices);

    // Grab our valid values.
    $valid_values = $this->getValidValues();

    $valid = TRUE;
    $failed_items = [];
    // Keep track if we find a value that is the same but the wrong case (for
    // example, all caps was used when only title case is valid). This flag will
    // contribute to our error case reporting.
    $wrong_case = FALSE;
    // Convert our array of valid values to lower case for case insensitive
    // comparison.
    $valid_values_lwr = array_map('strtolower', $valid_values);

    // Iterate through our array of row values.
    foreach ($row_values as $index => $cell) {
      // Only validate the values in which their index is also within our
      // context array of indices.
      if (in_array($index, $indices)) {
        // Check if our cell value is a valid value.
        if (!in_array($cell, $valid_values)) {
          if (in_array(strtolower($cell), $valid_values_lwr)) {
            // Technically a match, but the case doesn't match the valid value.
            $wrong_case = TRUE;
          }
          $valid = FALSE;
          $failed_items[$index] = $cell;
        }
      }
    }
    // Report if any values were invalid.
    if (!$valid) {
      $case_token = ($wrong_case)
        ? self::$mapping['case-insensitive-match']['token']
        : self::$mapping['case-invalid-value']['token'];

      $validator_status = [
        'case' => self::$mapping[$case_token]['dev-case'],
        'valid' => FALSE,
        'failedItems' => $failed_items,
      ];
    }
    else {
      $validator_status = [
        'case' => self::$mapping['case-valid']['dev-case'],
        'valid' => TRUE,
        'failedItems' => [],
      ];
    }
    return $validator_status;
  }

}

<?php

namespace Drupal\trpcultivate\Plugin\Validators;

use Drupal\trpcultivate\Service\ImportValidationHelper;
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

  /**
   * Processes failed validation from ValueInList into a render array.
   *
   * @param array $validation_results
   *   An associative array that stores the validation failures by the
   *   ValueinList validator. It is keyed by the line number of the input
   *   file where validation failed, and the value is an associative array
   *   returned by the validator. Here is the overall structure of $failures:
   *   - [LINE NUMBER]:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed, where the key => value
   *       pairs map to the index => cell value(s) that failed validation.
   *       @see validateRow()
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following keys are expected:
   *   - 'expected_values': A list of valid values that was provided to the
   *     ValueinList validator instance that is being processed for
   *     feedback to the user.
   *   - 'column_headers': This contains an array of headers defined in the
   *     importer. The index in this array MUST match the position
   *     (starting with 0) of the column in the input file.
   *     Eg: 'column_headers' => [
   *           '2' => 'Header 2', // Header of column #3
   *           '4' => 'Header 4', // Header of column #5
   *         ];.
   * @param array $tokens
   *   [OPTIONAL] An array of values to use for token replacement.
   *   @see ValueInList::$mapping
   *   The following token keys will substitute the entire existing case message
   *   to the user with the value of that token.
   *   - 'case-invalid-value': the message when there is an invalid value.
   *   - 'case-insensitive-match': the message when there is invalid value with
   *     case insensitive match.
   *
   * @return array
   *   A render array of type unordered list which is used to display feedback
   *   to the user about the case(s) that failed and the failed items from the
   *   input file. This unordered list will include a table that lists the row
   *   and column combinations with an invalid value. It has the following
   *   headers:
   *   - 'Line Number'
   *   - Column Header(s) of the cell(s) that has/have an invalid value.
   *
   * @throws \Exception
   *   - If any validation result arrays are not formatted properly.
   *   - If the case string returned by the validator implied validation passed.
   *   - If the case string returned by the validator is not recognized.
   */
  public static function processValueInListFailures(array $validation_results, array $metadata, array $tokens = []) {

    // Define our table header.
    // We will start with the line number and build the header from there as we
    // go through the failures. There will be a column for each column checked
    // by this validator instance and the column header will be the same as it
    // appears in the file.
    $table_header = [-1 => 'Line Number'];
    $table['rows'] = [];

    // Wrap each value in the expected values with double quotes.
    self::$mapping['expected-values']['default-msg'] = implode(', ', array_map(function ($in_list_value) {
      return '"' . $in_list_value . '"';
    }, $metadata['expected_values']));

    $default_tokens = array_column(self::$mapping, 'default-msg', 'token');
    // Combine our provided and our default token arrays. Because array_merge
    // will overwrite values in the first array with values from the second
    // array for the same keys, we provide our default tokens first.
    $combined_tokens = array_merge($default_tokens, $tokens);

    foreach ($validation_results as $line_no => $validation_result) {
      // Check the format of the validation_result parameter.
      ImportValidationHelper::checkValidationStatusArray($validation_result, 'ValueInList', $line_no);

      // Check for the expected failed case message.
      if ($validation_result['case'] == self::$mapping['case-invalid-value']['dev-case'] ||
          $validation_result['case'] == self::$mapping['case-insensitive-match']['dev-case']) {

        $case_token = ($validation_result['case'] == self::$mapping['case-invalid-value']['dev-case'])
          ? self::$mapping['case-invalid-value']['token']
          : self::$mapping['case-insensitive-match']['token'];

        $table['message'] = $combined_tokens[$case_token];

        // Define a new row in our table for this line number.
        $table['rows'][$line_no][-1] = $line_no;
        // For each index with an invalid value, grab the column name from our
        // $headers property and add it to our table header.
        foreach ($validation_result['failedItems'] as $index => $failed_value) {
          // Grab the column name based on the index of the invalid value
          // and add it to this table header if it's not already there.
          $column_name = $metadata['column_headers'][$index]['name'];
          if (!array_key_exists($column_name, $table_header)) {
            $table_header[$index] = $column_name;
          }
          // Now add a cell to the table to indicate this invalid value.
          // We reuse the index from the original file as the key to preserve
          // the same order of the columns. We also key the row with the line
          // number to ensure that a line with more then one failure is
          // compiled into a single row.
          $table['rows'][$line_no][$index] = $failed_value;
        }
      }
      elseif ($validation_result['case'] == self::$mapping['case-valid']['dev-case']) {
        throw new \Exception("The case string returned by the ValueInList validator at line #$line_no implies validation passed, but valid is set to FALSE.");
      }
      else {
        throw new \Exception("The case string returned by the ValueInList validator at line #$line_no is not recognized as a potential case.");
      }
    }

    // If our table has more than 2 columns with failed values, then iterate
    // through and pad the table with empty strings where necessary.
    if (count($table_header) > 2) {
      foreach (array_keys($table['rows']) as $line_no) {
        foreach (array_keys($table_header) as $index) {
          if (!array_key_exists($index, $table['rows'][$line_no])) {
            $table['rows'][$line_no][$index] = '';
          }
        }
        // Finally, sort the row by keys.
        ksort($table['rows'][$line_no]);
      }
    }

    // Sort the table header.
    ksort($table_header);

    // Now replace any tokens that are in our message or items.
    // We use the Tripal Token Parser service to ensure that more complicated
    // tokens are supported.
    // NOTE: Dependency injection is NOT used since this is a static method.
    $service_TripalTokensParser = \Drupal::service('tripal.token_parser');
    $replaced_message = $service_TripalTokensParser->replaceTokens($table['message'], $combined_tokens);

    // Build the render array for our table.
    $render_array = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#attributes' => [
        'class' => [
          'tc-value-in-list-failures',
        ],
      ],
      '#items' => [
        [
          [
            '#prefix' => '<div class="case-message">',
            '#markup' => $replaced_message,
            '#suffix' => '</div>',
          ],
          [
            '#type' => 'table',
            '#header' => $table_header,
            '#attributes' => [],
            '#rows' => $table['rows'],
          ],
        ],
      ],
    ];

    return $render_array;
  }

}

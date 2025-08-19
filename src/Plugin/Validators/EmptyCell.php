<?php

namespace Drupal\trpcultivate\Plugin\Validators;

use Drupal\trpcultivate\Service\ImportValidationHelper;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\ColumnIndices;
use Drupal\trpcultivate\TripalCultivateValidator\Attribute\TripalCultivateValidator;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Validate empty cells of an importer.
 */
#[TripalCultivateValidator(
   id: 'empty_cell',
   validator_name: new TranslatableMarkup('Empty Cell Validator'),
   input_types: ['header-row', 'data-row'],
 )]
class EmptyCell extends TripalCultivateValidatorBase {

  /**
   * Validator Traits required by this validator.
   *
   * - ColumnIndices: Gets an array of indices corresponding to the cells in
   *   $row_values to validate.
   */
  use ColumnIndices;

  /**
   * A mapping of all of the tokens supported by this validator.
   *
   * @var array
   *   An associative array mapping tokens to their details, such as the
   *   developer case string and the default message to substitute the token.
   *   The following tokens are implemented for this mapping, with the following
   *   descriptions for their 'default-msg' values:
   *   - 'case-empty-value': the message when a specific row-column does not
   *     contain a value.
   *
   * @see TripalCultivate/src/TripalCultivateValidator/TripalCultivateValidatorBase::$mapping
   */
  protected static array $mapping = [
    'case-empty-value' => [
      'token' => 'case-empty-value',
      'dev-case' => 'Empty value found in required column(s)',
      'default-msg' => 'The following line number and column header combinations were empty, but a value is required.',
    ],
    'case-valid' => [
      'token' => 'case-valid',
      'dev-case' => 'No empty values found in required column(s)',
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
   *   - 'valid': FALSE if any of the cells being checked are empty and TRUE
   *     otherwise.
   *   - 'failedItems': an array of items that failed with the following keys.
   *     This is an empty array if the data row input was valid.
   *     - 'empty_indices': A list of indices in the row which were checked and
   *       found to be empty.
   */
  public function validateRow($row_values) {

    // Grab our indices.
    $indices = $this->getIndices();

    // Check the indices provided are valid in the context of the row.
    // Will throw an exception if there's a problem.
    $this->checkIndices($row_values, $indices);

    $empty = FALSE;
    $failed_indices = [];
    // Iterate through our array of row values.
    foreach ($row_values as $index => $cell) {
      // Only validate the values in which their index is also within our
      // context array of indices.
      if (in_array($index, $indices)) {
        // Trim the contents of our cell in case we have whitespace.
        $cell = trim($cell);
        // Check if our content is empty and report an error if it is.
        if (!isset($cell) || empty($cell)) {
          $empty = TRUE;
          array_push($failed_indices, $index);
        }
      }
    }
    // Report if empty values were found that should not be empty.
    if ($empty) {
      $validator_status = [
        'case' => self::$mapping['case-empty-value']['dev-case'],
        'valid' => FALSE,
        'failedItems' => [
          'empty_indices' => $failed_indices,
        ],
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
   * Processes failed validation from EmptyCell into a render array.
   *
   * @param array $validation_results
   *   An associative array that stores the validation failures by the
   *   EmptyCell validator. It is keyed by the line number of the input
   *   file where validation failed, and the value is an associative array
   *   returned by the validator. Here is the overall structure:
   *   - [LINE NUMBER]:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed, where the key => value
   *       pairs map to the index => cell value(s) that failed validation.
   *       @see validateRow()
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following keys are expected:
   *   - 'column_headers': This contains an array of headers defined in the
   *     importer. The index in this array MUST match the position
   *     (starting with 0) of the column in the input file.
   *     Eg: 'column_headers' => [
   *           '2' => 'Header 2', // Header of column #3
   *           '4' => 'Header 4', // Header of column #5
   *         ];.
   * @param array $tokens
   *   [OPTIONAL] An array of values to use for token replacement.
   *   @see EmptyCell::$mapping
   *   The following token keys will substitute the entire existing case message
   *   to the user with the value of that token.
   *   - 'case-empty-value': the message when a specific row-column does not
   *     contain a value.
   *
   * @return array
   *   A render array of type "unordered list" used to display feedback to the
   *   user about the validation failure, where each item is a
   *   markup block containing:
   *   - A message describing the case triggered.
   *   - A table that lists the row and column combinations with empty cells.
   *     It has the following headers.
   *     - 'Line Number'
   *     - 'Column(s) with empty value'
   *
   * @throws \Exception
   *   - If a validation status array was not formatted properly.
   *   - If the case string returned by the validator implied validation passed.
   *   - If the case string returned by the validator is not recognized.
   */
  public static function processListWithDescribedTable(array $validation_results, array $metadata, array $tokens = []) {

    // Define our table header.
    $table_header = ['Line Number', 'Column(s) with empty value'];
    $table['rows'] = [];

    $default_tokens = array_column(self::$mapping, 'default-msg', 'token');
    // Combine our provided and our default token arrays. Because array_merge
    // will overwrite values in the first array with values from the second
    // array for the same keys, we provide our default tokens first.
    $combined_tokens = array_merge($default_tokens, $tokens);

    foreach ($validation_results as $line_no => $validation_status) {
      // Check the format of the validation_status parameter.
      ImportValidationHelper::checkValidationStatusArray($validation_status, 'EmptyCell', $line_no);

      if ($validation_status['case'] == self::$mapping['case-empty-value']['dev-case']) {
        $table['message'] = $combined_tokens['case-empty-value'];
        // Convert indices in failedItems to column headers.
        $failed_indices = $validation_status['failedItems']['empty_indices'];
        // For each index with an empty value, grab the column name from our
        // $headers property and add to an array of header names.
        $empty_headers = [];
        foreach ($failed_indices as $index) {
          array_push($empty_headers, $metadata['column_headers'][$index]);
        }
        // Implode the empty headers array into a string and then add it as a
        // row to our table.
        $columns_string = implode(", ", $empty_headers);
        array_push($table['rows'], [
          $line_no,
          $columns_string,
        ]);
      }
      elseif ($validation_status['case'] == self::$mapping['case-valid']['dev-case']) {
        throw new \Exception("The case string returned by the EmptyCell validator at line #$line_no implies validation passed, but valid is set to FALSE.");
      }
      else {
        throw new \Exception("The case string returned by the EmptyCell validator at line #$line_no is not recognized as a potential case.");
      }
    }

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
          'tc-empty-cell-failures',
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

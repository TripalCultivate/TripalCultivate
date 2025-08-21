<?php

namespace Drupal\trpcultivate\Plugin\Validators;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\trpcultivate\Service\ImportValidationHelper;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\ColumnCount;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\FileTypes;
use Drupal\trpcultivate\TripalCultivateValidator\Attribute\TripalCultivateValidator;

/**
 * Validate that a line in a data file is properly delimited.
 */
#[TripalCultivateValidator(
   id: 'valid_delimited_file',
   validator_name: new TranslatableMarkup('Valid Delimited File Validator'),
   input_types: ['raw-row']
 )]
class ValidDelimitedFile extends TripalCultivateValidatorBase {

  /**
   * Validator Traits required by this validator.
   *
   * - FileTypes: get the MIME type of the input file (getFileMimeType)
   * - ColumnCount: get the expected number of columns (getExpectedColumns)
   */
  use FileTypes;
  use ColumnCount;

  /**
   * A mapping of all of the tokens supported by this validator.
   *
   * @var array
   *   An associative array mapping tokens to their details, such as the
   *   developer case string and the default message to substitute the token.
   *   The following tokens are implemented for this mapping, with the following
   *   descriptions for their 'default-msg' values:
   *   - 'table-unsupported': the token containing the message used by cases
   *     where raw row is empty or raw row is not delimited.
   *   - 'table-delimited': the token containing the message used by cases
   *     where raw row has excess column or raw row has missing columns.
   *   Tokens below cannot be overriden as their value is determined at runtime
   *   or case messages are designed to cover multiple cases. See above for
   *   tokens that can be changed.
   *   - 'case-empty-row': the message when a row is empty string.
   *   - 'case-no-delimiter': the message when no delimiter was used.
   *   - 'case-excess-columns': the message when row has excess columns.
   *   - 'case-missing-columns': the message when row has less columns.
   *   - 'strict-or-min': header comparison settings (strict or minimum).
   *   - 'num-expected-colums': number of expected column headers.
   *
   * @see TripalCultivate/src/TripalCultivateValidator/TripalCultivateValidatorBase::$mapping
   */
  protected static array $mapping = [
    'table-unsupported' => [
      'token' => 'table-unsupported',
      'default-msg' => 'The following lines in the input file do not contain a valid delimiter supported by this importer.',
    ],
    'table-delimited' => [
      'token' => 'table-delimited',
      'default-msg' => 'This importer requires a [strict-or-min] number of [num-expected-columns] columns for each line. The following lines do not contain the expected number of columns.',
    ],
    'case-empty-row' => [
      'token' => 'case-empty-row',
      'dev-case' => 'Raw row is empty',
    ],
    'case-no-delimiter' => [
      'token' => 'case-no-delimiter',
      'dev-case' => 'None of the delimiters supported by the file type was used',
    ],
    'case-excess-columns' => [
      'token' => 'case-excess-columns',
      'dev-case' => 'Raw row exceeds number of strict columns',
    ],
    'case-missing-columns' => [
      'token' => 'case-missing-columns',
      'dev-case' => 'Raw row has insufficient number of columns',
    ],
    'strict-or-min' => [
      'token' => 'strict-or-min',
    ],
    'num-expected-columns' => [
      'token' => 'num-expected-columns',
    ],
    'case-valid-singlecol' => [
      'token' => 'case-valid-singlecol',
      'dev-case' => 'Raw row has expected number of columns',
    ],
    'case-valid' => [
      'token' => 'case-valid',
      'dev-case' => 'Raw row is delimited',
    ],
  ];

  /**
   * Perform validation of a raw row in a data file.
   *
   * Checks include:
   * - Line is not empty.
   * - It has some delimiter used to separate values.
   * - When split, the number of values returned is equal to the expected number
   *   of values in getExpectedColumns().
   *
   * @param string $raw_row
   *   A line in the data file that is not processed (ie. split by delimiter).
   *
   * @return array
   *   An associative array with the following keys.
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': TRUE if the raw row is properly delimited, FALSE otherwise.
   *   - 'failedItems': an array of items that failed with any of the following
   *      keys. This is an empty array if row is properly delimited.
   *      - 'raw_row': The raw row as it was provided.
   */
  public function validateRawRow(string $raw_row) {

    // Parameter check, verify that raw row is not an empty string.
    if (empty(trim($raw_row))) {
      return [
        'case' => self::$mapping['case-empty-row']['dev-case'],
        'valid' => FALSE,
        'failedItems' => [
          'raw_row' => $raw_row,
        ],
      ];
    }

    // Get the expected number of columns.
    $expected_columns = $this->getExpectedColumns();

    // Get the supported delimiters based on the input file's mime type.
    $input_file_mime_type = $this->getFileMimeType();
    $input_file_type_delimiters = ImportValidationHelper::getFileDelimiters($input_file_mime_type);

    // Check the row includes at least one delimiter returned by
    // getFileDelimiters().
    $delimiters_used = [];
    foreach ($input_file_type_delimiters as $delimiter) {
      if (strpos($raw_row, $delimiter)) {
        array_push($delimiters_used, $delimiter);
      }
    }

    // Not one of the supported delimiters was detected in the raw row.
    if (empty($delimiters_used)) {

      // Validation passes if the raw row is not delimited and the expected
      // number of columns is set to 1.
      if ($expected_columns['number_of_columns'] == 1) {
        return [
          'case' => self::$mapping['case-valid-singlecol']['dev-case'],
          'valid' => TRUE,
          'failedItems' => [],
        ];
      }

      return [
        'case' => self::$mapping['case-no-delimiter']['dev-case'],
        'valid' => FALSE,
        'failedItems' => [
          'raw_row' => $raw_row,
        ],
      ];
    }

    $columns = ImportValidationHelper::splitRowIntoColumns($raw_row, $input_file_mime_type);
    $no_cols = count($columns);

    if ($no_cols > $expected_columns['number_of_columns']) {
      // The line has more columns than expected.
      if ($expected_columns['strict']) {
        return [
          'case' => self::$mapping['case-excess-columns']['dev-case'],
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row,
          ],
        ];
      }
    }

    if ($no_cols < $expected_columns['number_of_columns']) {
      // The line has less column than expected.
      return [
        'case' => self::$mapping['case-missing-columns']['dev-case'],
        'valid' => FALSE,
        'failedItems' => [
          'raw_row' => $raw_row,
        ],
      ];
    }

    return [
      'case' => self::$mapping['case-valid']['dev-case'],
      'valid' => TRUE,
      'failedItems' => [],
    ];
  }

  /**
   * Processes failed validation from ValidDelimitedFile into a render array.
   *
   * @param array $validation_results
   *   An associative array that stores the validation failures by the
   *   ValidDelimitedFile validator. It is keyed by the line number of the
   *   input file where validation failed, and the value is an associative
   *   array returned by the validator. The overall structure is:
   *   - [LINE NUMBER]:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed, where the key is
   *       a shorthand of the case and the value contains the failed item.
   *     @see validateRawRow()
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following keys are expected:
   *   - 'strict_flag': indicates whether the value for number_of_columns
   *     is minimum number of columns required (FALSE) or if it is strictly
   *     the only acceptable number of columns (TRUE).
   *   - 'number_of_columns': the number of columns that are anticipated in
   *     a data row.
   * @param array $tokens
   *   [OPTIONAL] An array of values to use for token replacement.
   *   @see ValidDelimitedFile::$mapping
   *   The following token keys will substitute the entire existing case message
   *   to the user with the value of that token.
   *     - 'table-unsupported': the token containing the message used by cases
   *       where raw row is empty or raw row is not delimited.
   *     - 'table-delimited': the token containing the message used by cases
   *       where raw row has excess column or raw row has missing columns.
   *
   * @return array
   *   A render array of type "unordered list" which is used to display feedback
   *   to the user about the validation failure, where each item is a markup
   *   block containing:
   *   - A message describing the case triggered.
   *   - A table that lists the row and column combinations with failures for
   *     this case.
   *   Each list item is a markup block describing a potential case in the
   *   $failures array using a message describing the case triggered and either:
   *   - A table for lines that are empty or contain unsupported delimiters
   *   - A table for lines that once delimited, do not contain the expected
   *     number of columns.
   *   Both tables contain the following headers:
   *   - 'Line Number'
   *   - 'Line Contents'
   *
   * @throws \Exception
   *   - If key 'strict_flag' is missing from $metadata.
   *   - If key 'number_of_columns' is missing from $metadata.
   *   - If the validation_results parameter was not formatted properly.
   *   - If the case string returned by the validator implied validation passed.
   *   - If the case string returned by the validator is not recognized.
   */
  public static function processListWithDescribedTable(array $validation_results, array $metadata, array $tokens = []) {

    // Validate that metadata contains the expected keys.
    if (!isset($metadata['strict_flag'], $metadata['number_of_columns'])) {
      throw new \Exception("Expected metadata to contain both 'strict_flag' and 'number_of_columns' when processing failures from ValidDelimiters, but it does not.");
    }

    // Grab the default messages for all of our tokens (ones with default-msg).
    $default_tokens = array_column(self::$mapping, 'default-msg', 'token');
    // Combine our provided and our default token arrays. Because array_merge
    // will overwrite values in the first array with values from the second
    // array for the same keys, we provide our default tokens first.
    $combined_tokens = array_merge($default_tokens, $tokens);

    $combined_tokens['strict-or-min'] = $metadata['strict_flag'] == TRUE ? 'strict' : 'minimum';
    $combined_tokens['num-expected-columns'] = $metadata['number_of_columns'];

    // Define our table headers.
    $table_header = ['Line Number', 'Line Contents'];

    // For this validator there can be up to 2 tables:
    // - 'table'->'unsupported': Empty rows or no supported delimiters present.
    // - 'table'->'delimited': Rows that don't delimit to the expected number of
    //   columns.
    $table = [];
    // Loop through each row in the $failures array and piece apart the
    // different cases into different tables.
    foreach ($validation_results as $line_no => $validation_result) {
      // Check the format of the validation_result parameter.
      ImportValidationHelper::checkValidationStatusArray($validation_result, 'ValidDelimitedFile', $line_no);
      // Keeps track of which table this one line's validation result gets added
      // to based on the case it triggered.
      $table_case = '';
      if (($validation_result['case'] == self::$mapping['case-empty-row']['dev-case']) ||
          ($validation_result['case'] == self::$mapping['case-no-delimiter']['dev-case'])) {

        $table_case = self::$mapping['table-unsupported']['token'];
      }
      elseif (($validation_result['case'] == self::$mapping['case-excess-columns']['dev-case']) ||
              ($validation_result['case'] == self::$mapping['case-missing-columns']['dev-case'])) {

        $table_case = self::$mapping['table-delimited']['token'];
      }
      elseif (($validation_result['case'] == self::$mapping['case-valid']['dev-case']) ||
              ($validation_result['case'] == self::$mapping['case-valid-singlecol']['dev-case'])) {

        throw new \Exception("The case string returned by the ValidDelimitedFile validator at line #$line_no implies validation passed, but valid is set to FALSE.");
      }
      else {
        throw new \Exception("The case string returned by the ValidDelimitedFile validator at line #$line_no is not recognized as a potential case.");
      }

      // Checked all cases, now add a row to our appropriate table.
      if (!array_key_exists($table_case, $table)) {
        // Declare the array storing rows for this table, if not already.
        // Check which tables were created, and assign the correct message.
        // Note that both tables can exist at the same time.
        $table[$table_case] = [
          'rows' => [],
          'message' => $combined_tokens[$table_case],
        ];
      }

      $table[$table_case]['rows'][] = [
        $line_no,
        $validation_result['failedItems']['raw_row'],
      ];
    }

    // Now replace any tokens that are in our message or items.
    // We use the Tripal Token Parser service to ensure that more complicated
    // tokens are supported.
    // NOTE: Dependency injection is NOT used since this is a static method.
    $service_TripalTokensParser = \Drupal::service('tripal.token_parser');

    // Finally, loop through our tables and build our render array.
    $tables = [];
    foreach ($table as $table_key => $table_case) {
      $replaced_message = $service_TripalTokensParser->replaceTokens($table_case['message'], $combined_tokens);

      $table_key = ltrim($table_key, 'table-');
      $tables[] = [
        [
          '#prefix' => '<div class="case-message case-' . $table_key . '">',
          '#markup' => $replaced_message,
          '#suffix' => '</div>',
        ],
        [
          '#type' => 'table',
          '#header' => $table_header,
          '#attributes' => [
            'class' => [
              'tcp-raw-row',
              'table-case-' . $table_key,
            ],
          ],
          '#rows' => $table_case['rows'],
        ],
      ];
    }

    $render_array = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#attributes' => [
        'class' => [
          'tc-valid-delimited-file-failures',
        ],
      ],
      '#items' => $tables,
    ];

    return $render_array;
  }

}

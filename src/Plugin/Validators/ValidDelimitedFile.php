<?php

namespace Drupal\trpcultivate\Plugin\Validators;

use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\ColumnCount;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\FileTypes;
use Drupal\trpcultivate\Service\ImportValidationHelper;

/**
 * Validate that a line in a data file is properly delimited.
 *
 * @TripalCultivateValidator(
 *   id = "valid_delimited_file",
 *   validator_name = @Translation("Valid Delimited File Validator"),
 *   input_types = {"raw-row"}
 * )
 */
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
   *   - 'case-empty-row': the message when a row is empty string.
   *   - 'case-no-delimiter': the message when no delimiter was used.
   *   - 'case-excess-columns': the message when row has excess columns.
   *   - 'case-insufficient-columns': the message when row has less columns.
   *
   * @see TripalCultivate/src/TripalCultivateValidator/TripalCultivateValidatorBase::$mapping
   */
  protected static array $mapping = [
    'case-empty-row' => [
      'token' => 'case-empty-row',
      'dev-case' => 'Raw row is empty',
      'default-msg' => 'The following lines in the input file do not contain a valid delimiter supported by this importer.',
    ],
    'case-no-delimiter' => [
      'token' => 'case-no-delimiter',
      'dev-case' => 'None of the delimiters supported by the file type was used',
      'default-msg' => '',
    ],
    'case-excess-columns' => [
      'token' => 'case-excess-columns',
      'dev-case' => 'Raw row exceeds number of strict columns',
      'default-msg' => '',
    ],
    'case-insufficient-columns' => [
      'token' => 'case-insufficient-columns',
      'dev-case' => 'Raw row has insufficient number of columns',
      'default-msg' => 'This importer requires a [strict-or-min] number of [num-expected-columns] columns for each line. The following lines do not contain the expected number of columns.',
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
   *      - 'expected_columns': The number of columns expected in the input file
   *        as determined by calling getExpectedColumns().
   *      - 'strict': A boolean indicating whether the number of expected
   *        columns by the validator is strict (TRUE) or is the minimum number
   *        required (FALSE).
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
            'expected_columns' => $expected_columns['number_of_columns'],
            'strict' => $expected_columns['strict'],
          ],
        ];
      }
    }

    if ($no_cols < $expected_columns['number_of_columns']) {
      // The line has less column than expected.
      return [
        'case' => self::$mapping['case-insufficient-columns']['dev-case'],
        'valid' => FALSE,
        'failedItems' => [
          'raw_row' => $raw_row,
          'expected_columns' => $expected_columns['number_of_columns'],
          'strict' => $expected_columns['strict'],
        ],
      ];
    }

    return [
      'case' => self::$mapping['case-valid']['dev-case'],
      'valid' => TRUE,
      'failedItems' => [],
    ];
  }

}

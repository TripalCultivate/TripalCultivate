<?php

namespace Drupal\trpcultivate\Plugin\Validators;

use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\ColumnCount;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\Headers;

/**
 * Validate that all expected column headers exist.
 *
 * @TripalCultivateValidator(
 *   id = "valid_headers",
 *   validator_name = @Translation("Header Row Validator"),
 *   input_types = {"header-row"}
 * )
 */
class ValidHeaders extends TripalCultivateValidatorBase {

  /**
   * Validator Traits required by this validator.
   *
   * - Headers: get expected headers (getHeaders)
   * - ColumnCount: get the expected number of columns (getExpectedColumns)
   */
  use Headers;
  use ColumnCount;

  /**
   * A mapping of all of the tokens supported by this validator.
   *
   * @var array
   *   An associative array mapping tokens to their details, such as the
   *   developer case string and the default message to substitute the token.
   *   The following tokens are implemented for this mapping, with the following
   *   descriptions for their 'default-msg' values:
   *   - 'case-empty-headers': the message when there is no header row.
   *   - 'case-mismatch-values': the message when headers are not expected.
   *   - 'case-mismatch-count': the message when headers count is not expected.
   *
   * @see TripalCultivate/src/TripalCultivateValidator/TripalCultivateValidatorBase::$mapping
   */
  protected static array $mapping = [
    'case-empty-headers' => [
      'token' => 'case-empty-headers',
      'dev-case' => 'Header row is an empty value',
      'default-msg' => 'The file has an empty row where the header was expected.',
    ],
    'case-mismatch-values' => [
      'token' => 'case-mismatch-values',
      'dev-case' => 'Headers do not match expected headers',
      'default-msg' => 'One or more of the column headers in the input file does not match what was expected. Please check if your column header is in the correct order and matches the template exactly.',
    ],
    'case-mismatch-count' => [
      'token' => 'case-mismatch-count',
      'dev-case' => 'Headers provided does not have the expected number of headers',
      'default-msg' => 'This importer requires a strict number of [num-expected-columns] column headers. Please ensure your column header matches the template exactly and remove any additional column headers from the file.',
    ],
    'case-valid' => [
      'token' => 'case-valid',
      'dev-case' => 'Headers exists and match expected headers',
    ],
  ];

  /**
   * Validate the header row.
   *
   * Checks include:
   * - Each header value is not empty.
   * - No expected header is missing.
   * - The order of headers defined by the Importer should match the input.
   *
   * @param array $headers
   *   An array created by splitting the first line of the data file into
   *   values. The index of each value represents the order it appears in.
   *
   * @return array
   *   An associative array with the following keys.
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': TRUE if the header is valid, FALSE otherwise.
   *   - 'failedItems': an array of items that failed with any of the following
   *      keys. This is an empty array if the header passed validation.
   *      - 'headers': A string indicating the header row is empty.
   *      - the headers input array.
   */
  public function validateRow($headers) {
    $input_headers = $headers;

    // Parameter check, verify that the headers array input is not empty.
    if (empty($headers)) {
      // Headers array is an empty array.
      return [
        'case' => 'Header row is an empty value',
        'valid' => FALSE,
        'failedItems' => [
          'headers' => 'headers array is an empty array',
        ],
      ];
    }

    // Get the list of expected headers.
    $expected_headers = $this->getHeaders();

    // Compare expected headers and input headers. Return a failed
    // validation status on the first instance of a mismatch.
    foreach ($expected_headers as $header) {
      $cur_input_header = array_shift($input_headers);

      if ($cur_input_header && $header != trim($cur_input_header)) {
        return [
          'case' => 'Headers do not match expected headers',
          'valid' => FALSE,
          'failedItems' => $headers,
        ];
      }
    }

    // Get the expected number of columns and strict comparison flag.
    $expected_columns = $this->getExpectedColumns();

    if ($expected_columns['strict'] && $expected_columns['number_of_columns'] != count($headers)) {
      // The importer specified a strict requirement for number of columns in
      // the input file, but the header has more or less than that amount.
      return [
        'case' => 'Headers provided does not have the expected number of headers',
        'valid' => FALSE,
        'failedItems' => $headers,
      ];
    }

    // At this point the headers input array is valid.
    return [
      'case' => 'Headers exist and match expected headers',
      'valid' => TRUE,
      'failedItems' => [],
    ];
  }

}

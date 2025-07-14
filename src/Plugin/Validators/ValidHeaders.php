<?php

namespace Drupal\trpcultivate\Plugin\Validators;

use Drupal\trpcultivate\Service\ImportValidationHelper;
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
    'num-expected-columns' => [
      'token' => 'num-expected-columns',
      'default-msg' => '',
    ],
    'missing-header-note' => [
      'token' => 'missing-header-note',
      'default-msg' => 'headers array is an empty array',
    ],
    'case-valid' => [
      'token' => 'case-valid',
      'dev-case' => 'Headers exist and match expected headers',
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
        'case' => self::$mapping['case-empty-headers']['dev-case'],
        'valid' => FALSE,
        'failedItems' => [
          'headers' => self::$mapping['missing-header-note']['default-msg'],
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
          'case' => self::$mapping['case-mismatch-values']['dev-case'],
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
        'case' => self::$mapping['case-mismatch-count']['dev-case'],
        'valid' => FALSE,
        'failedItems' => $headers,
      ];
    }

    // At this point the headers input array is valid.
    return [
      'case' => self::$mapping['case-valid']['dev-case'],
      'valid' => TRUE,
      'failedItems' => [],
    ];
  }

  /**
   * Processes failed validation from ValidHeaders into a render array.
   *
   * @param array $validation_status
   *   An associative array that was returned by the ValidHeaders validator in
   *   the event of failed validation. It contains the following keys:
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': FALSE to indicate that validation failed.
   *   - 'failedItems': an array of items that failed, either:
   *     - 'headers': A string indicating the header row is empty.
   *     - an array of column headers that was in the input file.
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
   *   A render array of type unordered list which is used to display feedback
   *   to the user about the case that failed and the failed items from the
   *   input file. This unordered list will include a table with a row of the
   *   expected headers followed by a row of the provided headers.
   *
   * @throws \Exception
   *   - If the validation_status parameter was not formatted properly.
   *   - If the case string returned by the validator implied validation passed.
   *   - If the case string returned by the validator is not recognized.
   */
  public static function processValidHeadersFailures(array $validation_status, array $metadata, array $tokens = []) {

    // Check the format of the validation_status parameter.
    ImportValidationHelper::checkValidationStatusArray($validation_status, 'ValidHeaders');

    self::$mapping['num-expected-columns']['default-msg'] = count($metadata['column_headers']);

    $default_tokens = array_column(self::$mapping, 'default-msg', 'token');
    // Combine our provided and our default token arrays. Because array_merge
    // will overwrite values in the first array with values from the second
    // array for the same keys, we provide our default tokens first.
    $combined_tokens = array_merge($default_tokens, $tokens);

    if ($validation_status['case'] == self::$mapping['case-empty-headers']['dev-case']) {
      $message = $combined_tokens['case-empty-headers'];
      $provided_headers = [];
    }
    elseif ($validation_status['case'] == self::$mapping['case-mismatch-values']['dev-case']) {
      $message = $combined_tokens['case-mismatch-values'];
      $provided_headers = $validation_status['failedItems'];
    }
    elseif ($validation_status['case'] == self::$mapping['case-mismatch-count']['dev-case']) {
      $message = $combined_tokens['case-mismatch-count'];
      $provided_headers = $validation_status['failedItems'];
    }
    elseif ($validation_status['case'] == self::$mapping['case-valid']['dev-case']) {
      throw new \Exception('The case string returned by the ValidHeaders validator implies validation passed, but valid is set to FALSE.');
    }
    else {
      throw new \Exception('The case string returned by the ValidHeaders validator is not recognized as a potential case.');
    }

    // Now replace any tokens that are in our message or items.
    // We use the Tripal Token Parser service to ensure that more complicated
    // tokens are supported.
    // NOTE: Dependency injection is NOT used since this is a static method.
    $service_TripalTokensParser = \Drupal::service('tripal.token_parser');
    $replaced_message = $service_TripalTokensParser->replaceTokens($message, $combined_tokens);

    // Get the expected and actual headers to build the rows in our table render
    // array.
    $expected_headers = array_values($metadata['column_headers']);

    // Build the render array.
    $render_array = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#attributes' => [
        'class' => [
          'tc-valid-headers-failures',
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
            '#attributes' => [],
            '#rows' => [
              [
                'data' => [
                  'header' => [
                    'data' => 'Expected Headers',
                    'header' => TRUE,
                  ],
                ] + $expected_headers,
                'class' => ['expected-headers'],
              ],
              [
                'data' => [
                  'header' => [
                    'data' => 'Provided Headers',
                    'header' => TRUE,
                  ],
                ] + $provided_headers,
                'class' => ['provided-headers'],
              ],
            ],
          ],
        ],
      ],
    ];

    return $render_array;
  }

}

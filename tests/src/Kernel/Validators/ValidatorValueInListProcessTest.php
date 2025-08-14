<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;

/**
 * Test any message process methods for the ValueInList validator.
 *
 * @group trpcultivate
 * @group validators
 */
class ValidatorValueInListProcessTest extends ChadoTestKernelBase {

  /**
   * Theme used in the test environment.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'path',
    'path_alias',
    'views',
    'field',
    'field_ui',
    'markup',
    'field_group',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
  ];

  /**
   * Drupal render service.
   *
   * @var Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * An instance of the validator.
   *
   * @var Drupal\trpcultivate\Plugin\Validators\ValueInList
   */
  protected $validator_instance;

  public const COLUMN_HEADERS = [
    [
      'name' => 'Trait Name',
      'description' => 'The name of the trait, as you would like it to appear to the user (e.g. Days to Flower)',
      'type' => 'required',
    ],
    [
      'name' => 'Trait Description',
      'description' => 'A full description of the trait. This is recommended to be at least one paragraph.',
      'type' => 'required',
    ],
    [
      'name' => 'Method Short Name',
      'description' => 'A full, unique title for the method (e.g. Days till 10% of plants/plot have flowers)',
      'type' => 'required',
    ],
    [
      'name' => 'Collection Method',
      'description' => 'A full description of how the trait was collected. This is also recommended to be at least one paragraph.',
      'type' => 'required',
    ],
    [
      'name' => 'Unit',
      'description' => 'The full name of the unit used (e.g. days, centimeters)',
      'type' => 'required',
    ],
    [
      'name' => 'Type',
      'description' => 'One of "Qualitative" or "Quantitative".',
      'type' => 'required',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    $validator_id = 'value_in_list';
    $this->validator_instance = $this->container
      ->get('plugin.manager.trpcultivate_validator')
      ->createInstance($validator_id);

    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Data Provider for testProcessListWithDescribedTable().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - The failures array that gets passed to the process method. It contains
   *     the following keys:
   *     - The line number that triggered this failed validation status.
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': array of items that failed, where the key => value
   *         pairs map to the index => cell value(s) that failed validation.
   *   - An array of additional metadata (or contextual information) needed by
   *     the process method. Here, the following keys are expected:
   *       - 'expected_values': the list of values that are considered valid
   *         by this validator.
   *       - 'column_headers': This contains an array of headers. The index in
   *       this array MUST match the position (starting with 0) of the column in
   *       the input file.
   *       Eg: 'column_headers' => [
   *           '2' => 'Header 1', // Header of column #3
   *           '4' => 'Header 2', // Header of column #5
   *         ].
   *   - An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'project'), and the value is
   *     the new value to be shown for that token.
   *   - An array of expectations that we want to find in the resulting rendered
   *     output which has the following keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 'expected_column_count': The number of columns expected in the
   *       rendered table for this scenario.
   *     - 'expected_table_rows': 1+ arrays keyed by the line number in the
   *       input file that triggered the failed validation status, further keyed
   *       by the column header name of a cell in this row and its value is the
   *       invalid value. For example:
   *       - 2 => [ 'Type' => 'Invalid Value' ]
   */
  public static function provideValueInListFailedCases() {

    $scenarios = [];
    $tokens = [];
    $column_headers = array_column(self::COLUMN_HEADERS, 'name');

    $metadata = [
      'expected_values' => [
        'Quantitative',
        'Qualitative',
      ],
      'column_headers' => $column_headers,
    ];

    // #0: An invalid value in a required column on one row.
    $scenarios[] = [
      [
        3 => [
          'case' => 'Invalid value(s) in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            // Column 'Type' is at index 5.
            5 => 'Invalid Type',
          ],
        ],
      ],
      $metadata,
      $tokens,
      [
        'expected_message' => 'The following line number and column combinations did not contain one of the following allowed values: "' . implode('", "', $metadata['expected_values']) . '".',
        'expected_column_count' => 2,
        'expected_table_rows' => [
          3 => [
            'Type' => 'Invalid Type',
          ],
        ],
      ],
    ];

    // #1: Case insensitive match.
    $scenarios[] = [
      [
        3 => [
          'case' => 'Invalid value(s) in required column(s) with >=1 case insensitive match',
          'valid' => FALSE,
          'failedItems' => [
            // Column 'Type' is at index 5.
            5 => 'qualitative',
          ],
        ],
      ],
      $metadata,
      $tokens,
      [
        'expected_message' => 'The following line number and column combinations did not contain one of the following allowed values: "' . implode('", "', $metadata['expected_values']) . '".',
        'expected_column_count' => 2,
        'expected_table_rows' => [
          3 => [
            'Type' => 'qualitative',
          ],
        ],
      ],
    ];

    // #2: Test token reversal - message.
    $scenarios[] = [
      [
        3 => [
          'case' => 'Invalid value(s) in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            // Column 'Type' is at index 5.
            5 => 'Invalid Type',
          ],
        ],
      ],
      $metadata,
      [
        'table-invalid' => 'Not Valid Value.',
      ],
      [
        'expected_message' => 'Not Valid Value',
        'expected_column_count' => 2,
        'expected_table_rows' => [
          3 => [
            'Type' => 'Invalid Type',
          ],
        ],
      ],
    ];

    // #3: Test static token - expected message remains unchanged.
    $scenarios[] = [
      [
        3 => [
          'case' => 'Invalid value(s) in required column(s) with >=1 case insensitive match',
          'valid' => FALSE,
          'failedItems' => [
            // Column 'Type' is at index 5.
            5 => 'qualitative',
          ],
        ],
      ],
      $metadata,
      [
        'expected-values' => '"Another expected values"',
      ],
      [
        'expected_message' => 'The following line number and column combinations did not contain one of the following allowed values: "' . implode('", "', $metadata['expected_values']) . '".',
        'expected_column_count' => 2,
        'expected_table_rows' => [
          3 => [
            'Type' => 'qualitative',
          ],
        ],
      ],
    ];

    $metadata = [
      'expected_values' => [
        'cm',
        'days',
        'scale',
      ],
      'column_headers' => $column_headers,
    ];

    // #4: Test static token - expected message remains unchanged.
    $scenarios[] = [
      [
        3 => [
          'case' => 'Invalid value(s) in required column(s) with >=1 case insensitive match',
          'valid' => FALSE,
          'failedItems' => [
            // Column 'Type' is at index 5.
            5 => 'qualitative',
          ],
        ],
      ],
      $metadata,
      [
        'case-invalid-value' => 'Case is invalid value.',
      ],
      [
        'expected_message' => 'The following line number and column combinations did not contain one of the following allowed values: "' . implode('", "', $metadata['expected_values']) . '".',
        'expected_column_count' => 2,
        'expected_table_rows' => [
          3 => [
            'Type' => 'qualitative',
          ],
        ],
      ],
    ];

    $metadata = [
      'expected_values' => [
        'cm',
        'days',
        'scale',
      ],
      'column_headers' => $column_headers,
    ];

    // #5: Test static token - expected message remains unchanged.
    $scenarios[] = [
      [
        3 => [
          'case' => 'Invalid value(s) in required column(s) with >=1 case insensitive match',
          'valid' => FALSE,
          'failedItems' => [
            // Column 'Type' is at index 5.
            5 => 'qualitative',
          ],
        ],
      ],
      $metadata,
      [
        'case-insensitive-match' => 'Case is insensitive match.',
      ],
      [
        'expected_message' => 'The following line number and column combinations did not contain one of the following allowed values: "' . implode('", "', $metadata['expected_values']) . '".',
        'expected_column_count' => 2,
        'expected_table_rows' => [
          3 => [
            'Type' => 'qualitative',
          ],
        ],
      ],
    ];

    $metadata = [
      'expected_values' => [
        'cm',
        'days',
        'scale',
      ],
      'column_headers' => $column_headers,
    ];

    // #6: An invalid value on multiple rows (1 column)
    $scenarios[] = [
      [
        2 => [
          'case' => 'Invalid value(s) in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            // Column 'Unit' is at index 4.
            4 => 'Amy',
          ],
        ],
        5 => [
          'case' => 'Invalid value(s) in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            4 => 'Sam',
          ],
        ],
      ],
      $metadata,
      $tokens,
      [
        'expected_message' => 'The following line number and column combinations did not contain one of the following allowed values: "' . implode('", "', $metadata['expected_values']) . '".',
        'expected_column_count' => 2,
        'expected_table_rows' => [
          2 => [
            'Unit' => 'Amy',
          ],
          5 => [
            'Unit' => 'Sam',
          ],
        ],
      ],
    ];

    // #7: Multiple different invalid values in different columns.
    $scenarios[] = [
      [
        2 => [
          'case' => 'Invalid value(s) in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            // Column 'Type' is at index 5.
            5 => 'Amy',
          ],
        ],
        5 => [
          'case' => 'Invalid value(s) in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            // Column 'Unit' is at index 4.
            4 => 'Sam',
            5 => 'Ben',
          ],
        ],
      ],
      $metadata,
      $tokens,
      [
        'expected_message' => 'The following line number and column combinations did not contain one of the following allowed values: "' . implode('", "', $metadata['expected_values']) . '".',
        'expected_column_count' => 3,
        'expected_table_rows' => [
          2 => [
            'Unit' => '',
            'Type' => 'Amy',
          ],
          5 => [
            'Unit' => 'Sam',
            'Type' => 'Ben',
          ],
        ],
      ],
    ];

    // Potential @todo scenario: ValueInList is configured for multiple columns,
    // but at least one of the columns doesn't have any failures. This isn't
    // testable since this data provider only supplies failures, but it's some-
    // thing to keep in mind if we have the opportunity to test in the future.
    return $scenarios;
  }

  /**
   * Tests the message processor method for the ValueInList validator.
   *
   * @param array $validation_results
   *   The validation results array that gets passed to the process method.
   *   It contains the following keys:
   *   - The line number that triggered this failed validation status.
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': array of items that failed, where the key => value
   *       pairs map to the index => cell value(s) that failed validation.
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by
   *   the process method. Here, the following keys are expected:
   *   - 'expected_values': the list of values that are considered valid
   *     by this validator.
   *   - 'column_headers': This contains an array of headers. The index in
   *     this array MUST match the position (starting with 0) of the column in
   *     the input file.
   *     Eg: 'column_headers' => [
   *           '2' => 'Header 1', // Header of column #3
   *           '4' => 'Header 2', // Header of column #5
   *         ].
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *   to the user. The key is the token, (ex. 'project'), and the value is
   *   the new value to be shown for that token.
   * @param array $expectations
   *   An array of expectations that we want to find in the resulting rendered
   *   output which has the following keys:
   *   - 'expected_message': The message expected in the return value of the
   *     process method for this scenario.
   *   - 'expected_column_count': The number of columns expected in the
   *     rendered table for this scenario.
   *   - 'expected_table_rows': 1+ arrays keyed by the line number in the
   *     input file that triggered the failed validation status, further keyed
   *     by the column header name of a cell in this row and its value is the
   *     invalid value. For example:
   *     - 2 => [ 'Type' => 'Invalid Value' ].
   *
   * @dataProvider provideValueInListFailedCases
   */
  public function testProcessListWithDescribedTable(array $validation_results, array $metadata, array $tokens, array $expectations) {

    // Process our test failures array for this scenario.
    $render_array = $this->validator_instance::processListWithDescribedTable($validation_results, $metadata, $tokens);
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the rendered output.
    // Check the message above this table is correct.
    $selected_message_markup = $this->cssSelect("ul li div.case-message");
    $table_message = (string) $selected_message_markup[0];
    $this->assertStringContainsString(
      $expectations['expected_message'],
      $table_message,
      'The message expected from processing ValueInList failures for this scenario did not match the message in the render array.'
    );

    // Select and save the table header.
    $selected_table_header = $this->cssSelect("thead tr");
    $select_column_headers = (array) $selected_table_header[0]->th;
    // Assert that the number of columns matches the number of we expect.
    $this->assertCount(
      $expectations['expected_column_count'],
      $select_column_headers,
      'We expected ' . $expectations['expected_column_count'] . 'columns to be in the rendered table for ValueInList failures for this scenario, but instead there are ' . count($select_column_headers) . '.'
    );

    // Select the table rows.
    $selected_rows = $this->cssSelect("tbody tr");
    // Assert that the number of rows matches what we expect.
    $expected_row_count = count($expectations['expected_table_rows']);
    $this->assertCount(
      $expected_row_count,
      $selected_rows,
      'We expected ' . $expected_row_count . 'rows in the rendered table for ValueInList failures for this scenario, but there are ' . count($selected_rows) . '.'
    );

    // Now check the cell values.
    $current_row_index = 0;
    // Loop through expectations for each row in the table.
    foreach ($expectations['expected_table_rows'] as $expected_line_no => $expected_values) {
      $select_row_cells = (array) $selected_rows[$current_row_index]->td;
      // 1st Column: Line Number
      $line_number = $select_row_cells[0];
      $this->assertEquals(
        $expected_line_no,
        $line_number,
        "Did not get the expected line number in the rendered table from processing ValueInList failures."
      );
      // 2nd Column and up: Column(s) with invalid value
      $current_column_index = 1;
      foreach ($expected_values as $column_header => $invalid_value) {
        // Check that the invalid value is under the correct column header.
        $this->assertEquals(
          $column_header,
          $select_column_headers[$current_column_index],
          "We expected the column header $column_header to be present in the rendered table's header for ValueInList failures at index $current_column_index but it was not."
        );
        // Check that the invalid value in the table matches what we expect.
        $this->assertEquals(
          $invalid_value,
          (string) $select_row_cells[$current_column_index],
          "We expected an invalid value to be listed for $column_header at line #$expected_line_no in the rendered table for ValueInList failures."
        );
        $current_column_index++;
      }
      // Move onto the next row.
      $current_row_index++;
    }
  }

  /**
   * Data Provider for triggering exceptions in process failures method.
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - An array of validation status arrays that get passed to the process
   *     method. It is keyed by the line number that triggered this failed
   *     validation status, further keyed by:
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': array of items that failed with the following keys.
   *         - 'raw_row': the data row/line.
   *   - An array of additional metadata (or contextual information) needed by
   *     the process method. Here, the following keys are expected:
   *     - 'expected_values': the list of values that are considered valid
   *       by this validator.
   *     - 'column_headers': This contains an array of headers. The index in
   *       this array MUST match the position (starting with 0) of the column in
   *       the input file.
   *       Eg: 'column_headers' => [
   *             '2' => 'Header 1', // Header of column #3
   *             '4' => 'Header 2', // Header of column #5
   *           ].
   *   - An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'project'), and the value is
   *     the new value to be shown for that token.
   *   - An array of expectations in the rendered output which has the
   *     following keys:
   *     - 'expected_message': The exception message that is expected to be
   *       triggered.
   */
  public static function providePassedAndUnrecognizableCases() {

    $scenarios = [];

    // Make tokens an empty array for now. Maybe in the future we'll want to
    // incorporate them into exception messages?
    $tokens = [];

    // #0: ValueInList passed.
    $scenarios[] = [
      [
        2 => [
          'case' => 'Values in required column(s) are valid',
          'valid' => FALSE,
          'failedItems' => [
            5 => 'Invalid value',
          ],
        ],
      ],
      [
        'expected_values' => ['Quantitative', 'Qualitative'],
        'column_headers' => [
          0 => 'Header 1',
          1 => 'Header 2',
        ],
      ],
      $tokens,
      [
        'expected_message' => 'The case string returned by the ValueInList validator at line #2 implies validation passed, but valid is set to FALSE.',
      ],
    ];

    // #1: unrecognizable validation case message.
    $scenarios[] = [
      [
        3 => [
          'case' => 'Unrecognized case string',
          'valid' => FALSE,
          'failedItems' => [
            5 => 'Invalid value',
          ],
        ],
      ],
      [
        'expected_values' => ['Quantitative', 'Qualitative'],
        'column_headers' => [
          0 => 'Header 1',
          1 => 'Header 2',
        ],
      ],
      $tokens,
      [
        'expected_message' => 'The case string returned by the ValueInList validator at line #3 is not recognized as a potential case.',
      ],
    ];

    // #2: missing expected_values from metadata.
    $scenarios[] = [
      [
        3 => [
          'case' => 'Unrecognized case string',
          'valid' => FALSE,
          'failedItems' => [
            5 => 'Invalid value',
          ],
        ],
      ],
      [
        'column_headers' => [
          0 => 'Header 1',
          1 => 'Header 2',
        ],
      ],
      $tokens,
      [
        'expected_message' => "Expected metadata to contain both 'expected_values' and 'column_headers' when processing failures from ValueInList, but it does not.",
      ],
    ];

    // #3: missing column_headers from metadata.
    $scenarios[] = [
      [
        3 => [
          'case' => 'Unrecognized case string',
          'valid' => FALSE,
          'failedItems' => [
            5 => 'Invalid value',
          ],
        ],
      ],
      [
        'expected_values' => ['Quantitative', 'Qualitative'],
      ],
      $tokens,
      [
        'expected_message' => "Expected metadata to contain both 'expected_values' and 'column_headers' when processing failures from ValueInList, but it does not.",
      ],
    ];

    // #4: missing expected_values and column_headers from metadata.
    $scenarios[] = [
      [
        3 => [
          'case' => 'Unrecognized case string',
          'valid' => FALSE,
          'failedItems' => [
            5 => 'Invalid value',
          ],
        ],
      ],
      [],
      $tokens,
      [
        'expected_message' => "Expected metadata to contain both 'expected_values' and 'column_headers' when processing failures from ValueInList, but it does not.",
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests for exceptions thrown for passed and unrecognizable case strings.
   *
   * @param array $validation_results
   *   An array of validation status arrays that get passed to the process
   *   method. It is keyed by the line number that triggered this failed
   *   validation status, further keyed by:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': array of items that failed with the following keys.
   *       - 'raw_row': the data row/line.
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by
   *   the process method. Here, the following keys are expected:
   *     - 'expected_values': the list of values that are considered valid
   *       by this validator.
   *     - 'column_headers': This contains an array of headers. The index in
   *       this array MUST match the position (starting with 0) of the column in
   *       the input file.
   *       Eg: 'column_headers' => [
   *             '2' => 'Header 1', // Header of column #3
   *             '4' => 'Header 2', // Header of column #5
   *           ].
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *   to the user. The key is the token, (ex. 'project'), and the value is
   *   the new value to be shown for that token.
   * @param array $expectations
   *   An array of expectations in the rendered output which has the
   *   following keys:
   *     - 'expected_message': The exception message that is expected to be
   *       triggered.
   *
   * @dataProvider providePassedAndUnrecognizableCases
   */
  public function testProcessListWithDescribedTableExceptions(array $validation_results, array $metadata, array $tokens, array $expectations) {

    // Test with a passed validation case string.
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->validator_instance::processListWithDescribedTable($validation_results, $metadata, $tokens);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $line = array_keys($validation_results);
    $line = reset($line);

    $this->assertTrue(
      $exception_caught,
      'We expected an exception to be caught for case ' . $validation_results[$line]['case'] . ', but one was not thrown.'
    );

    $this->assertEquals(
      $expectations['expected_message'],
      $exception_message,
      'We expected the exception message to indicate that case ' . $validation_results[$line]['case'] . ', but it does not match what was expected.'
    );
  }

}

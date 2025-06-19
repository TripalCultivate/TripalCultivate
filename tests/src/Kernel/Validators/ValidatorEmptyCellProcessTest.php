<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;

/**
 * Test any message process methods for the EmptyCell validator.
 *
 * @group trpcultivate
 * @group validators
 */
class ValidatorEmptyCellProcessTest extends ChadoTestKernelBase {

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
   * @var Drupal\trpcultivate\Plugin\Validators\EmptyCell
   */
  protected $validator_instance;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    $validator_id = 'empty_cell';
    $this->validator_instance = $this->container
      ->get('plugin.manager.trpcultivate_validator')
      ->createInstance($validator_id);

    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Data Provider for testProcessEmptyCellFailures().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - An array of validation status arrays that get passed to the process
   *     method. It is keyed by the line number that triggered this failed
   *     validation status, further keyed by:
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': array of items that failed with the following keys.
   *         - 'empty_indices': A list of column indices in the line which were
   *           checked and found to be empty.
   *   - An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'project'), and the value is
   *     the new value to be shown for that token.
   *   - An array of additional metadata (or contextual information) needed by
   *     the process method. Here, the following keys are expected:
   *     - 'column_headers': This contains an array of headers. The index in
   *       this array MUST match the position (starting with 0) of the column in
   *       the input file.
   *       Eg: 'column_headers' => [
   *             '2' => 'Header 1', // Header of column #3
   *             '4' => 'Header 2', // Header of column #5
   *           ].
   *   - An array of expectations that we want to find in the resulting rendered
   *     output which has the following keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 1+ arrays keyed by the line number in the input file that triggered
   *       the failed validation status, further keyed by:
   *       - 'expected_columns': A comma-separated list of column headers that
   *         map to the expected columns with empty values.
   */
  public static function provideEmptyCellFailedCases() {

    $scenarios = [];
    $tokens = [];
    $metadata = [
      'column_headers' => [
        'Trait Name',
        'Trait Description',
        'Method Short Name',
        'Collection Method',
        'Unit',
        'Type',
      ],
    ];

    // #0: One empty required column on line #5
    $scenarios[] = [
      [
        5 => [
          'case' => 'Empty value found in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            'empty_indices' => [0],
          ],
        ],
      ],
      $tokens,
      $metadata,
      [
        'expected_message' => 'The following line number and column header combinations were empty, but a value is required.',
        5 => [
          'expected_columns' => 'Trait Name',
        ],
      ],
    ];

    // #1: One required column is empty on multiple rows
    $scenarios[] = [
      [
        3 => [
          'case' => 'Empty value found in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            'empty_indices' => [5],
          ],
        ],
        8 => [
          'case' => 'Empty value found in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            'empty_indices' => [5],
          ],
        ],
      ],
      $tokens,
      $metadata,
      [
        'expected_message' => 'The following line number and column header combinations were empty, but a value is required.',
        3 => [
          'expected_columns' => 'Type',
        ],
        8 => [
          'expected_columns' => 'Type',
        ],
      ],
    ];

    // #2: Multiple different required columns on multiple lines
    $scenarios[] = [
      [
        2 => [
          'case' => 'Empty value found in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            'empty_indices' => [0, 2, 4],
          ],
        ],
        6 => [
          'case' => 'Empty value found in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            'empty_indices' => [4, 5],
          ],
        ],
      ],
      $tokens,
      $metadata,
      [
        'expected_message' => 'The following line number and column header combinations were empty, but a value is required.',
        2 => [
          'expected_columns' => 'Trait Name, Method Short Name, Unit',
        ],
        6 => [
          'expected_columns' => 'Unit, Type',
        ],
      ],
    ];

    // #3: Test token reversal.
    $scenarios[] = [
      [
        3 => [
          'case' => 'Empty value found in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            'empty_indices' => [5],
          ],
        ],
      ],
      [
        'case-empty-value' => 'Nothing provided.',
      ],
      $metadata,
      [
        'expected_message' => 'Nothing provided.',
        3 => [
          'expected_columns' => 'Type',
        ],
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for the EmptyCell validator.
   *
   * @param array $validation_results
   *   An array of validation status arrays that get passed to the process
   *   method. It is keyed by the line number that triggered this failed
   *   validation status, further keyed by:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       - 'empty_indices': A list of column indices in the line which were
   *         checked and found to be empty.
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'project'), and the value is
   *     the new value to be shown for that token.
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following keys are expected:
   *     - 'column_headers': This contains an array of headers. The index in
   *       this array MUST match the position (starting with 0) of the column in
   *       the input file.
   *       Eg: 'column_headers' => [
   *             '2' => 'Header 1', // Header of column #3
   *             '4' => 'Header 2', // Header of column #5
   *           ].
   * @param array $expectations
   *   An array of expectations that we want to find in the resulting rendered
   *   output which has the following keys:
   *   - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *   - 1+ arrays keyed by the line number in the input file that triggered
   *     the failed validation status, further keyed by:
   *     - 'expected_columns': A comma-separated list of column headers that
   *       map to the expected columns with empty values.
   *
   * @dataProvider provideEmptyCellFailedCases
   */
  public function testProcessListWithDescribedTable(array $validation_results, array $tokens, array $metadata, array $expectations) {

    $render_array = $this->validator_instance::processListWithDescribedTable($validation_results, $tokens, $metadata);
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the rendered output.
    // Check the message above this table is correct.
    $selected_message_markup = $this->cssSelect("ul li div.case-message");
    $table_message = (string) $selected_message_markup[0];
    $this->assertStringContainsString(
      $expectations['expected_message'],
      $table_message,
      'The message expected from processing EmptyCell failures for this scenario did not match the message in the render array.'
    );

    // Select the table rows and check for our expected values.
    $selected_rows = $this->cssSelect("tbody tr");
    // Assert that the number of rows matches what we expect.
    $expected_row_count = (count($expectations) - 1);
    $this->assertCount(
      $expected_row_count,
      $selected_rows,
      'We expected ' . $expected_row_count . 'rows in the rendered table for EmptyCell failures for this scenario, but there are ' . count($selected_rows) . '.'
    );

    $current_row_index = 0;
    // Loop through expectations for each row in the table.
    foreach ($expectations as $expected_line_no => $expected_values) {
      if ($expected_line_no == 'expected_message') {
        continue;
      }

      // Select our current row as an array.
      $select_row_cells = (array) $selected_rows[$current_row_index]->td;
      // 1st Column: Line Number.
      $line_number = $select_row_cells[0];
      $this->assertEquals($expected_line_no, $line_number, "Did not get the expected line number in the rendered table from processing EmptyCell failures.");

      // 2nd Column: Column(s) with empty value
      $empty_columns = $select_row_cells[1];
      $this->assertEquals($expected_values['expected_columns'], $empty_columns, 'Did not get the expected column names of empty cells in the rendered table from processing EmptyCell failures.');
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
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following key:
   *       - 'empty_indices': A list of column indices in the line which were
   *         checked and found to be empty.
   *   - An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'project'), and the value is
   *     the new value to be shown for that token.
   *   - An array of additional metadata (or contextual information) needed by
   *     the process method. Here, the following keys are expected:
   *     - 'column_headers': This contains an array of headers. The index in
   *       this array MUST match the position (starting with 0) of the column in
   *       the input file.
   *       Eg: 'column_headers' => [
   *             '2' => 'Header 1', // Header of column #3
   *             '4' => 'Header 2', // Header of column #5.
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
    $metadata = [
      'column_headers' => [],
    ];

    // #0: EmptyCell passed.
    $scenarios[] = [
      [
        6 => [
          'case' => 'No empty values found in required column(s)',
          'valid' => FALSE,
          'failedItems' => [
            'empty_indices' => [2, 3, 4],
          ],
        ],
      ],
      $tokens,
      $metadata,
      [
        'expected_message' => 'The case string returned by the EmptyCell validator at line #6 implies validation passed, but valid is set to FALSE.',
      ],
    ];

    // #1: unrecognizable validation case message.
    $scenarios[] = [
      [
        7 => [
          'case' => 'unrecognizable case',
          'valid' => FALSE,
          'failedItems' => [
            'empty_indices' => [2, 3, 4],
          ],
        ],
      ],
      $tokens,
      $metadata,
      [
        'expected_message' => 'The case string returned by the EmptyCell validator at line #7 is not recognized as a potential case.',
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
   *     - 'failedItems': an array of items that failed with the following key:
   *       - 'empty_indices': A list of column indices in the line which were
   *         checked and found to be empty.
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *   to the user. The key is the token, (ex. 'project'), and the value is
   *   the new value to be shown for that token.
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following keys are expected:
   *     - 'column_headers': This contains an array of headers. The index in
   *       this array MUST match the position (starting with 0) of the column in
   *       the input file.
   *       Eg: 'column_headers' => [
   *             '2' => 'Header 1', // Header of column #3
   *             '4' => 'Header 2', // Header of column #5.
   * @param array $expectations
   *   An array of expectations in the rendered output which has the following
   *   keys:
   *   - 'expected_message': The exception message that is expected to be
   *     triggered.
   *
   * @dataProvider providePassedAndUnrecognizableCases
   */
  public function testProcessListWithDescribedTableExceptions(array $validation_results, array $tokens, array $metadata, array $expectations) {

    // Test with a passed validation case string.
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->validator_instance->processListWithDescribedTable($validation_results, $tokens, $metadata);
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
      'We expected the exception message to indicate that case ' . $validation_results[$line]['case'] . ', but it does not match what was expected.',
    );
  }

}

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
   * An instance of the data file validator.
   *
   * @var object
   */
  protected $validator_instance;

  /**
   * Headers required by this importer.
   *
   * @var array
   *
   * The following keys are required:
   * - 'name': The column header name as it should appear in the input file.
   * - 'description': A user-friendly description of the header that will be
   *   displayed to the user through the form.
   * - 'type': one of "required" or "optional" to indicate whether the column
   *   needs to have values present or not.
   *
   * NOTE: Order MUST reflect the desired order of headers in the input file.
   */
  private $headers = [
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

    $validator_id = 'empty_cell';
    $this->validator_instance = \Drupal::service('plugin.manager.trpcultivate_validator')
      ->createInstance($validator_id);

    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Data Provider for testProcessEmptyCellFailures().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - The failures array that gets passed to the process method. It contains
   *     the following keys:
   *     - The line number that triggered this failed validation status.
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': array of items that failed with the following keys:
   *         - 'empty_indices': A list of column indices in the line which were
   *           checked and found to be empty.
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

    return $scenarios;
  }

  /**
   * Tests the message processor method for the EmptyCell validator.
   *
   * @param array $failures
   *   The failures array that gets passed to the process method. It contains
   *   the following keys:
   *   - The line number that triggered this failed validation status.
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       - 'empty_indices': A list of column indices in the line which were
   *         checked and found to be empty.
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
  public function testProcessListWithDescribedTable(array $failures, array $expectations) {

    $render_array = $this->validator_instance::processListWithDescribedTable($failures, $this->headers, []);
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
   * Data Provider for triggering exceptions in all process failures methods.
   *
   * @return array
   *   Each scenario contains a string of the process validator method to be
   *   called, 1 array containing a passed validation result and the expected
   *   exception message, and 1 array containing an unrecognizable case in the
   *   validation status array with the expected exception message. The 2 arrays
   *   are layed out as follows:
   *   - Passed validation case:
   *     - 'process_method_params':
   *       - The failures array that gets passed to the process method. It
   *         contains the following keys:
   *         - [ROW-LEVEL ONLY] The line number that triggered this failed
   *           validation status. This key is NOT set for non row-level
   *           validators.
   *           - 'case': a developer-focused string describing a case of passed
   *             validation.
   *           - 'valid': FALSE to indicate that validation failed.
   *           - 'failedItems': array of items that failed consistent with the
   *             validator in this scenario.
   *       - Any additional parameters IF required by the process validation
   *         method (eg. processValueInListFailures requires expected values).
   *     - 'expected_message': The expected exception message to be triggered
   *       by the case message that indicates passed validation.
   *   - Unrecognized validation case:
   *     - 'process_method_params':
   *       - The failures array that gets passed to the process method. It
   *         contains the following keys:
   *         - [ROW-LEVEL ONLY] The line number that triggered this failed
   *           validation status. This key is NOT set for non row-level
   *           validators.
   *           - 'case': a string that is NOT one of the available case strings
   *             returned by this validator (neither pass or fail).
   *           - 'valid': FALSE to indicate that validation failed.
   *           - 'failedItems': array of items that failed consistent with the
   *             validator in this scenario.
   *       - Any additional parameters IF required by the process validation
   *         method (eg. processValueInListFailures requires expected values).
   *     - 'expected_message': The expected exception message to be triggered
   *       by the case message that is not recognized by the process validation
   *       method for this validator.
   */
  public static function providePassedAndUnrecognizableCases() {

    $scenarios = [];

    // #0: EmptyCell passed + unrecognizable validation case message.
    $scenarios[] = [
      'processListWithDescribedTable',
      [
        'process_method_params' => [
          [
            6 => [
              'case' => 'No empty values found in required column(s)',
              'valid' => FALSE,
              'failedItems' => [
                'empty_indices' => [2, 3, 4],
              ],
            ],
          ],
          [],
        ],
        'expected_message' => 'The case string returned by the EmptyCell validator at line #6 implies validation passed, but valid is set to FALSE.',
      ],
      [
        'process_method_params' => [
          [
            7 => [
              'case' => 'unrecognizable case',
              'valid' => FALSE,
              'failedItems' => [
                'empty_indices' => [2, 3, 4],
              ],
            ],
          ],
          [],
        ],
        'expected_message' => 'The case string returned by the EmptyCell validator at line #7 is not recognized as a potential case.',
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests for exceptions thrown for passed and unrecognizable case strings.
   *
   * @param string $process_method
   *   The name of the process failures method being called in this test.
   * @param array $passed_case
   *   An array with the following keys:
   *   - 'process_method_params':
   *     - The failures array that gets passed to the process method. It
   *       contains the following keys:
   *       - [ROW-LEVEL ONLY] The line number that triggered this failed
   *         validation status. This key is NOT set for non row-level
   *         validators.
   *         - 'case': a developer-focused string describing a case of passed
   *           validation.
   *         - 'valid': FALSE to indicate that validation failed.
   *         - 'failedItems': array of items that failed consistent with the
   *           validator in this scenario.
   *   - 'expected_message': The expected exception message to be triggered
   *     by the case message that indicates passed validation.
   * @param array $unrecognized_case
   *   An array with the following keys:
   *   - 'process_method_params':
   *     - The failures array that gets passed to the process method. It
   *       contains the following keys:
   *       - [ROW-LEVEL ONLY] The line number that triggered this failed
   *         validation status. This key is NOT set for non row-level
   *         validators.
   *         - 'case': a string that is NOT one of the available case strings
   *           returned by this validator (pass or fail).
   *         - 'valid': FALSE to indicate that validation failed.
   *         - 'failedItems': array of items that failed consistent with the
   *           validator in this scenario.
   *   - 'expected_message': The expected exception message to be triggered
   *     by the case message that is not recognized by the process validation
   *     method for this validator.
   *
   * @dataProvider providePassedAndUnrecognizableCases
   */
  public function testProcessListWithDescribedTableExceptions(string $process_method, array $passed_case, array $unrecognized_case) {

    // Test with a passed validation case string.
    $exception_caught = FALSE;
    $exception_message = 'NONE';

    try {
      call_user_func_array([$this->validator_instance, $process_method], $passed_case['process_method_params']);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "We expected an exception to be caught for providing a passed validation case string to $process_method, but one wasn't thrown.",
    );
    $this->assertEquals(
      $passed_case['expected_message'],
      $exception_message,
      "We expected the exception message to indicate that a passed validation string was provided to $process_method, but it does not match what was expected.",
    );

    // Test with an unrecognizable validation case string.
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      call_user_func_array([$this->validator_instance, $process_method], $unrecognized_case['process_method_params']);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "We expected an exception to be caught for providing an unrecognized validation case string to $process_method, but one wasn't thrown.",
    );
    $this->assertEquals(
      $unrecognized_case['expected_message'],
      $exception_message,
      "We expected the exception message to indicate that an unrecognized validation string was provided to $process_method, but it does not match what was expected.",
    );
  }

}

<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;

/**
 * Test any message process methods for the ValidDelimitedFile validator.
 *
 * @group trpcultivate
 * @group validators
 */
class ValidatorValidDelimitedFileProcessTest extends ChadoTestKernelBase {

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
   * @var Drupal\trpcultivate\Plugin\Validators\ValidDelimitedFile
   */
  protected $validator_instance;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    $validator_id = 'valid_delimited_file';
    $this->validator_instance = $this->container
      ->get('plugin.manager.trpcultivate_validator')
      ->createInstance($validator_id);

    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Data Provider for testProcessValidDelimitedFileFailures().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - An array of validation status arrays that get passed to the process
   *     method. It is keyed by the line number that triggered this failed
   *     validation status, further keyed by:
   *     - The line number that triggered this failed validation status.
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': array of items that failed with the following keys:
   *         - 'raw_row': The contents of the raw row as it appears in the file.
   *         - 'expected_columns': The number of columns expected in the input
   *           file as determined by calling getExpectedColumns().
   *         - 'strict': A boolean indicating whether the number of expected
   *           columns by the validator is strict (TRUE) or is the minimum
   *           number required (FALSE).
   *   - An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'project'), and the value is
   *     the new value to be shown for that token.
   *   - An array of expectations that we want to find in the resulting rendered
   *     output. This array is nested by the tables expected (keyed by type -
   *     'unsupported' or 'delimited'), in the order they are expected to show
   *     up on the page (1 array per table). Each array has the following keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 1+ arrays keyed by the line number in the input file that triggered
   *       the failed validation status, further keyed by:
   *       - 'line_contents': The raw contents of this line that failed.
   */
  public static function provideValidDelimitedFileFailedCases() {

    $tokens = [];

    // #0: The first row is empty (single whitespace).
    $scenarios[] = [
      [
        1 => [
          'case' => 'Raw row is empty',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => ' ',
          ],
        ],
      ],
      $tokens,
      [
        'unsupported' => [
          'expected_message' => 'The following lines in the input file do not contain a valid delimiter supported by this importer.',
          1 => [
            'line_contents' => ' ',
          ],
        ],
      ],
    ];

    // #1: No supported delimiters were used.
    $raw_row = "This is one very long string without any supported delimiters in it.";
    $scenarios[] = [
      [
        2 => [
          'case' => 'None of the delimiters supported by the file type was used',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row,
          ],
        ],
      ],
      $tokens,
      [
        'unsupported' => [
          'expected_message' => 'The following lines in the input file do not contain a valid delimiter supported by this importer.',
          2 => [
            'line_contents' => $raw_row,
          ],
        ],
      ],
    ];

    // #2: Multiple rows with too few columns and strict = FALSE
    $raw_row_2 = "Column 1\tColumn 2";
    $raw_row_4 = "Column 1\tColumn 2\tColumn 3";
    $scenarios[] = [
      [
        2 => [
          'case' => 'Raw row has insufficient number of columns',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row_2,
            'expected_columns' => 4,
            'strict' => FALSE,
          ],
        ],
        4 => [
          'case' => 'Raw row has insufficient number of columns',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row_4,
            'expected_columns' => 4,
            'strict' => FALSE,
          ],
        ],
      ],
      $tokens,
      [
        'delimited' => [
          'expected_message' => 'This importer requires a minimum number of 4 columns for each line. The following lines do not contain the expected number of columns.',
          2 => [
            'line_contents' => $raw_row_2,
          ],
          4 => [
            'line_contents' => $raw_row_4,
          ],
        ],
      ],
    ];

    // #3: 1 row with too few columns, 1 with too many columns, strict = TRUE
    $raw_row_3 = "Column 1\tColumn 2\tColumn 3\tColumn 4";
    $raw_row_5 = "Column 1\tColumn 2\tColumn 3\tColumn 4\tColumn 5\tColumn 6\tColumn 7\tColumn 8";
    $scenarios[] = [
      [
        3 => [
          'case' => 'Raw row has insufficient number of columns',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row_3,
            'expected_columns' => 6,
            'strict' => TRUE,
          ],
        ],
        5 => [
          'case' => 'Raw row exceeds number of strict columns',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row_5,
            'expected_columns' => 6,
            'strict' => TRUE,
          ],
        ],
      ],
      $tokens,
      [
        'delimited' => [
          'expected_message' => 'This importer requires a strict number of 6 columns for each line. The following lines do not contain the expected number of columns.',
          3 => [
            'line_contents' => $raw_row_3,
          ],
          5 => [
            'line_contents' => $raw_row_5,
          ],
        ],
      ],
    ];

    // #4: A mix of unsupported delimiters and insufficient columns.
    $raw_row_6 = "Column 1 Column 2 Column 3 Column 4";
    $raw_row_7 = "Column 1\tColumn 2\tColumn 3\tColumn 4\tColumn 5\tColumn 6\tColumn 7";
    $scenarios[] = [
      [
        6 => [
          'case' => 'None of the delimiters supported by the file type was used',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row_6,
            'expected_columns' => 6,
            'strict' => TRUE,
          ],
        ],
        7 => [
          'case' => 'Raw row exceeds number of strict columns',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row_7,
            'expected_columns' => 6,
            'strict' => TRUE,
          ],
        ],
      ],
      $tokens,
      [
        'unsupported' => [
          'expected_message' => 'The following lines in the input file do not contain a valid delimiter supported by this importer.',
          6 => [
            'line_contents' => $raw_row_6,
          ],
        ],
        'delimited' => [
          'expected_message' => 'This importer requires a strict number of 6 columns for each line. The following lines do not contain the expected number of columns.',
          7 => [
            'line_contents' => $raw_row_7,
          ],
        ],
      ],
    ];

    // #5: Test token reversal - message.
    $scenarios[] = [
      [
        1 => [
          'case' => 'Raw row is empty',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => ' ',
          ],
        ],
      ],
      [
        'case-empty-row' => 'Nothing Provided.',
      ],
      [
        'unsupported' => [
          'expected_message' => 'Nothing Provided.',
          1 => [
            'line_contents' => ' ',
          ],
        ],
      ],
    ];

    // #6: Test static token - expected message remains the same.
    // 1 row with too few columns, 1 with too many columns, strict = TRUE
    $raw_row_3 = "Column 1\tColumn 2\tColumn 3\tColumn 4";
    $raw_row_5 = "Column 1\tColumn 2\tColumn 3\tColumn 4\tColumn 5\tColumn 6\tColumn 7\tColumn 8";
    $scenarios[] = [
      [
        3 => [
          'case' => 'Raw row has insufficient number of columns',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row_3,
            'expected_columns' => 6,
            'strict' => TRUE,
          ],
        ],
        5 => [
          'case' => 'Raw row exceeds number of strict columns',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => $raw_row_5,
            'expected_columns' => 6,
            'strict' => TRUE,
          ],
        ],
      ],
      [
        'strict-or-min' => 'STRICT',
        'num-expected-columns' => 'SIX',
      ],
      [
        'delimited' => [
          'expected_message' => 'This importer requires a strict number of 6 columns for each line. The following lines do not contain the expected number of columns.',
          3 => [
            'line_contents' => $raw_row_3,
          ],
          5 => [
            'line_contents' => $raw_row_5,
          ],
        ],
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for the ValidDelimitedFile validator.
   *
   * @param array $validation_results
   *   An array of validation status arrays that get passed to the process
   *   method. It is keyed by the line number that triggered this failed
   *   validation status, further keyed by:
   *     - The line number that triggered this failed validation status.
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': array of items that failed with the following keys:
   *         - 'raw_row': The contents of the raw row as it appears in the file.
   *         - 'expected_columns': The number of columns expected in the input
   *           file as determined by calling getExpectedColumns().
   *         - 'strict': A boolean indicating whether the number of expected
   *           columns by the validator is strict (TRUE) or is the minimum
   *           number required (FALSE).
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *   to the user. The key is the token, (ex. 'project'), and the value is
   *   the new value to be shown for that token.
   * @param array $expectations
   *   An array of expectations that we want to find in the resulting rendered
   *   output. This array is nested by the tables expected (keyed by type -
   *   'unsupported' or 'delimited'), in the order they are expected to show
   *   up on the page (1 array per table). Each array has the following keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 1+ arrays keyed by the line number in the input file that triggered
   *       the failed validation status, further keyed by:
   *     - 'line_contents': The raw contents of this line that failed.
   *
   * @dataProvider provideValidDelimitedFileFailedCases
   */
  public function testProcessValidDelimitedFileFailures(array $validation_results, array $tokens, array $expectations) {

    // Process our test failures array.
    $render_array = $this->validator_instance::processValidDelimitedFileFailures($validation_results, $tokens);
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the rendered output.
    // Loop through expectations one table at a time.
    foreach ($expectations as $table_case => $table) {
      // Check the message above this table is correct.
      $selected_message_markup = $this->cssSelect("ul li div.case-message.case-$table_case");
      $table_message = (string) $selected_message_markup[0];
      $this->assertStringContainsString(
        $expectations[$table_case]['expected_message'],
        $table_message,
        'The message expected from processing ValidDelimitedFile failures for this scenario did not match the message in the render array.'
      );

      // Pull out the table rows for this table case.
      $selected_rows = $this->cssSelect("table.table-case-$table_case tbody tr");
      // Assert that the number of rows matches what we expect.
      $expected_row_count = (count($table) - 1);
      $this->assertCount(
        $expected_row_count,
        $selected_rows,
        'We expected ' . $expected_row_count . 'rows in the rendered table for ValidDelimitedFile failures for this scenario, but there are ' . count($selected_rows) . '.'
      );

      $current_row_index = 0;
      // Loop through expectations for each row of a table.
      foreach ($table as $expected_line_no => $expected_values) {
        if ($expected_line_no == 'expected_message') {
          continue;
        }
        // Select our current row as an array.
        $select_row_cells = (array) $selected_rows[$current_row_index]->td;
        // 1st Column: Line Number
        $line_number = $select_row_cells[0];
        $this->assertEquals($expected_line_no, $line_number, "Did not get the expected line number in the rendered table from processing ValidDelimitedFile failures.");
        // 2nd Column: The contents of this line in the file
        $line_contents = (string) $select_row_cells[1];
        $this->assertEquals($expected_values['line_contents'], $line_contents, 'Did not get the expected line contents in the rendered table from processing ValidDelimitedFile failures.');
        // Move onto the next row.
        $current_row_index++;
      }
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
   *     - The line number that triggered this failed validation status.
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': array of items that failed with the following keys:
   *         - 'raw_row': The contents of the raw row as it appears in the file.
   *         - 'expected_columns': The number of columns expected in the input
   *           file as determined by calling getExpectedColumns().
   *         - 'strict': A boolean indicating whether the number of expected
   *           columns by the validator is strict (TRUE) or is the minimum
   *           number required (FALSE).
   *   - An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'project'), and the value is
   *     the new value to be shown for that token.
   *   - An array of expectations that we want to find in the resulting rendered
   *     output. This array is nested by the tables expected (keyed by type -
   *     'unsupported' or 'delimited'), in the order they are expected to show
   *     up on the page (1 array per table). Each array has the following keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 1+ arrays keyed by the line number in the input file that triggered
   *       the failed validation status, further keyed by:
   *       - 'line_contents': The raw contents of this line that failed.
   */
  public static function providePassedAndUnrecognizableCases() {

    $scenarios = [];

    // Make tokens an empty array for now. Maybe in the future we'll want to
    // incorporate them into exception messages?
    $tokens = [];

    // #0: ValidDelimitedFile passed.
    $scenarios[] = [
      [
        4 => [
          'case' => 'Raw row has expected number of columns',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => 'This is a non-delimited raw row.',
          ],
        ],
      ],
      $tokens,
      [
        'expected_message' => 'The case string returned by the ValidDelimitedFile validator at line #4 implies validation passed, but valid is set to FALSE.',
      ],
    ];

    // #1: unrecognizable validation case message.
    $scenarios[] = [
      [
        5 => [
          'case' => 'unrecognizable case',
          'valid' => FALSE,
          'failedItems' => [
            'raw_row' => 'This is a raw row.',
          ],
        ],
      ],
      $tokens,
      [
        'expected_message' => 'The case string returned by the ValidDelimitedFile validator at line #5 is not recognized as a potential case.',
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
   *     - The line number that triggered this failed validation status.
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': array of items that failed with the following keys:
   *         - 'raw_row': The contents of the raw row as it appears in the file.
   *         - 'expected_columns': The number of columns expected in the input
   *           file as determined by calling getExpectedColumns().
   *         - 'strict': A boolean indicating whether the number of expected
   *           columns by the validator is strict (TRUE) or is the minimum
   *           number required (FALSE).
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *   to the user. The key is the token, (ex. 'project'), and the value is
   *   the new value to be shown for that token.
   * @param array $expectations
   *   - An array of expectations that we want to find in the resulting rendered  
   *     output. It has the following keys:  
   *     - 'expected_message': The message expected in the return value of the  
   *       process method for this scenario.  
   *
   * @dataProvider providePassedAndUnrecognizableCases
   */
  public function testProcessValidDelimitedFileFailuresExceptions(array $validation_results, array $tokens, array $expectations) {

    // Test with a passed validation case string.
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->validator_instance::processValidDelimitedFileFailures($validation_results, $tokens);
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

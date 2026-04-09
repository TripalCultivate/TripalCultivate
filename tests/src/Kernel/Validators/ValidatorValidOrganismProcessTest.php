<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Core\Render\Renderer;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests any message processing methods for the ValidOrganism validator.
 */
#[Group('trpcultivate')]
#[Group('validators')]
#[RunTestsInSeparateProcesses]
class ValidatorValidOrganismProcessTest extends ChadoTestKernelBase {
  /**
   * An instance of the validator.
   *
   * @var Drupal\trpcultivate\Plugin\Validators\ValidOrganism
   */
  protected $validator_instance;

  /**
   * Drupal render service.
   *
   * @var Drupal\Core\Render\RendererInterface
   */
  protected Renderer $renderer;

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
    'file',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    $validator_id = 'valid_organism';
    $this->validator_instance = $this->container
      ->get('plugin.manager.trpcultivate_validator')
      ->createInstance($validator_id);

    // Get our renderer.
    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Data Provider for processor for ValidOrgnaism()
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - An array of validation status arrays that get passed to the process
   *     method. It is keyed by the line number that triggered this failed
   *     validation status, further keyed by:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed, where the key => value
   *       pairs map to the index => cell value(s) that failed validation.
   *   - An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'organism'), and the value is
   *     the new value to be shown for that token.
   *   - An array of metadata needed by the process method. The method supports
   *     the following keys:
   *     - 'column_headers': an array of headers for columns that
   *       are expected to contain organisms. The index in this array MUST
   *       match the position (starting with 0) of the column in the input file.
   *   - An array of expectations that we want to find in the resulting rendered
   *     output. This array is nested by the tables expected (keyed by type),
   *     in the order they are expected to show up on the page (ie. 1 array per
   *     table). Each array has the following keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 'expected_column_count': The number of columns expected in the
   *       rendered table for this scenario.
   *     - 'expected_rows': 1+ arrays keyed by the line number in the input file
   *       that triggered the failed validation status, further keyed by the
   *       column header name of a cell in this row and its value is the
   *       problematic organism. For example:
   *        '2' => [ 'Organism' => 'Invalid Value' ]
   */
  public static function provideValidOrganismFailedCases() {
    $scenarios = [];

    $metadata = [
      'input_type' => 'data-row',
      'column_headers' => [
        1 => 'Organism',
      ],
    ];

    // ------ DEFAULT CASES (no tokens) ------
    // #0: An organism cell is missing from the database
    $scenarios[] = [
      [
        3 => [
          'case' => 'Missing organism(s) in the database',
          'valid' => FALSE,
          'failedItems' => [
            'missing_cells' => [
              1 => 'Non-existant Organism',
            ],
          ],
        ],
      ],
      [],
      $metadata,
      [
        'expected_message' => 'The following organisms do not match any existing in this site. Please make sure you have entered the names exactly as they appear on their organism pages, or contact your administrator to have them added if they do not yet exist.',
        'expected_column_count' => 2,
        'expected_table_rows' => [
          3 => [
            'Organism' => 'Non-existant Organism',
          ],
        ],
      ],
    ];

    // #1: An organism is missing from the database and the token for
    // the message is passed in with a custom message.
    $scenarios[] = [
      [
        3 => [
          'case' => 'Missing organism(s) in the database',
          'valid' => FALSE,
          'failedItems' => [
            'missing_cells' => [
              1 => 'Non-existant Organism',
            ],
          ],
        ],
      ],
      [
        'case-missing-organism' => 'The following organisms do not match any existing in this site. [contact-admin]',
        'contact-admin' => 'Please contact your administrator to have them added to the database.',
      ],
      $metadata,
      [
        'expected_message' => 'The following organisms do not match any existing in this site. Please contact your administrator to have them added to the database.',
        'expected_column_count' => 2,
        'expected_table_rows' => [
          3 => [
            'Organism' => 'Non-existant Organism',
          ],
        ],
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for the ValidOrganism validator.
   *
   * @param array $validation_results
   *   - An array of validation status arrays that get passed to the process
   *     method. It is keyed by the line number that triggered this failed
   *     validation status, further keyed by:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed, where the key => value
   *       pairs map to the index => cell value(s) that failed validation.
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *   to the user. The key is the token, (ex. 'project'), and the value is
   *   the new value to be shown for that token.
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following keys are expected:
   *   - 'input_type': The type of input that was validated (should be
   *     'data-row' or 'metadata').
   *   - 'column_headers': This contains an array of headers for columns that
   *     are expected to contain organisms. The index in this array MUST
   *     match the position (starting with 0) of the column in the input file.
   * @param array $expectations
   *   - An array of expectations that we want to find in the resulting rendered
   *     output. This array is nested by the tables expected (keyed by type),
   *     in the order they are expected to show up on the page (ie. 1 array per
   *     table). Each array has the following keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 'expected_column_count': The number of columns expected in the
   *       rendered table for this scenario.
   *     - 'expected_rows': 1+ arrays keyed by the line number in the input file
   *       that triggered the failed validation status, further keyed by the
   *       column header name of a cell in this row and its value is the
   *       problematic organism. For example:
   *        '2' => [ 'Organism' => 'Invalid Value' ].
   *
   * @dataProvider provideValidOrganismFailedCases
   */
  #[DataProvider('provideValidOrganismFailedCases')]
  public function testValidOrganismProcessListWithDescribedTable(array $validation_results, array $tokens, array $metadata, array $expectations) {
    // Set the input type for this validator instance to data-row since
    // this process method is only meant to be called for data-row
    // validation results.
    $this->validator_instance->setInputType('data-row');

    // Call the process method on our validation result.
    $render_array = $this->validator_instance::processListWithDescribedTable($validation_results, $metadata, $tokens);

    // Render the array we were returned.
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the message above this table is correct.
    $selected_message_markup = $this->cssSelect("ul li div.case-message");
    $table_message = (string) $selected_message_markup[0];
    $this->assertStringContainsString(
      $expectations['expected_message'],
      $table_message,
      'The message expected from processing ValidOrganism failures for this scenario did not match the message in the render array.'
    );

    // Select and save the table header.
    $selected_table_header = $this->cssSelect("thead tr");
    $select_column_headers = (array) $selected_table_header[0]->th;
    // Assert that the number of columns matches the number of we expect.
    $this->assertCount(
      $expectations['expected_column_count'],
      $select_column_headers,
      'We expected ' . $expectations['expected_column_count'] . ' columns to be in the rendered table for ValidOrganism failures for this scenario, but instead there are ' . count($select_column_headers) . '.'
    );

    // Pull out the table rows for this table case.
    $selected_rows = $this->cssSelect("tbody tr");
    // Assert that the number of rows matches what we expect.
    $expected_row_count = (count($expectations['expected_table_rows']));
    $this->assertCount(
      $expected_row_count,
      $selected_rows,
      'We expected ' . $expected_row_count . 'rows in the rendered table for ValidOrganism failures for this scenario, but there are ' . count($selected_rows) . '.'
    );

    $current_row_index = 0;
    // Loop through expectations for each row of this table.
    foreach ($expectations['expected_table_rows'] as $expected_line_no => $expected_values) {
      $select_row_cells = (array) $selected_rows[$current_row_index]->td;

      // 1st Column: Line Number.
      $line_number = $select_row_cells[0];
      $this->assertEquals(
      $expected_line_no,
      $line_number,
      "Did not get the expected line number in the rendered table from processing ValidOrganism failures."
      );
      // 2nd Column and up: Column(s) with invalid value
      $current_column_index = 1;
      foreach ($expected_values as $column_header => $invalid_value) {
        // Check that the invalid value is under the correct column header.
        $this->assertEquals(
          $column_header,
          $select_column_headers[$current_column_index],
          "We expected the column header \"$column_header\" to be present in the rendered table's header for ValidOrganism failures at index $current_column_index but it was not."
        );
        // Check that the invalid value in the table matches what we expect.
        $this->assertEquals(
        $invalid_value,
        (string) $select_row_cells[$current_column_index],
        "We expected an invalid value to be listed for \"$column_header\" at line #$expected_line_no in the rendered table for ValidOrganism failures."
        );
        $current_column_index++;
      }

      // Move onto the next row.
      $current_row_index++;
    }

  }

  /**
   * Data Provider for case of empty cells in ValidOrganism()
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - An array of validation status arrays that get passed to the process
   *     method. It is keyed by the line number that triggered this failed
   *     validation status, further keyed by:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following key:
   *       - 'empty_cells': an array of indices which are empty in the row.
   *   - An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'organism'), and the value is
   *     the new value to be shown for that token.
   *   - An array of metadata needed by the process method. The method supports
   *     the following keys:
   *     - 'column_headers': an array of headers for columns that
   *       are expected to contain organisms. The index in this array MUST
   *       match the position (starting with 0) of the column in the input file.
   *   - A string that is the expected message to be displayed in the returned
   *     render array for this scenario.
   */
  public static function provideValidOrganismEmptyCellCase() {
    $scenarios = [];

    $metadata_basic_column_headers = [
      'input_type' => 'data-row',
      'column_headers' => [
        1 => 'Organism',
      ],
    ];

    $metadata_additional_column_headers = [
      'input_type' => 'data-row',
      'column_headers' => [
        2 => 'This Organism',
        4 => 'That Organism',
      ],
    ];

    // #0: A single organism cell is empty
    $scenarios[] = [
      [
        5 => [
          'case' => 'Unable to lookup organism with empty values',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [1],
          ],
        ],
      ],
      [],
      $metadata_basic_column_headers,
      'One or more cells which are required to contain organisms were empty. Please ensure that you have entered existing organisms for all cells in the following columns: Organism',
    ];

    // #1: Multiple empty organism cells
    $scenarios[] = [
      [
        1 => [
          'case' => 'Unable to lookup organism with empty values',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [2, 4],
          ],
        ],
      ],
      [],
      $metadata_additional_column_headers,
      'One or more cells which are required to contain organisms were empty. Please ensure that you have entered existing organisms for all cells in the following columns: This Organism, That Organism',
    ];

    // #2: An empty organism cell and a missing organism.
    $scenarios[] = [
      [
        3 => [
          'case' => 'Missing organism(s) in the database',
          'valid' => FALSE,
          'failedItems' => [
            'missing_cells' => [
              2 => 'Non-existant Orgsnism',
            ],
          ],
        ],
        7 => [
          'case' => 'Unable to lookup organism with empty values',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [6],
          ],
        ],
      ],
      [],
      $metadata_additional_column_headers,
      'One or more cells which are required to contain organisms were empty. Please ensure that you have entered existing organisms for all cells in the following columns: This Organism, That Organism',
    ];

    // #3: An empty cell and a token to replace the entire displayed message.
    $scenarios[] = [
      [
        2 => [
          'case' => 'Unable to lookup organism with empty values',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [1],
          ],
        ],
      ],
      ['case-empty-organism' => 'Oh no! You left one or more cells empty in column(s) "[column-headers]" but there should be a organism!'],
      $metadata_basic_column_headers,
      'Oh no! You left one or more cells empty in column(s) "Organism" but there should be a organism!',
    ];

    // #4: An empty cell and a token passed in for 'column-headers'.
    // This token is expected to NOT be substituted since it is reserved for
    // being determined at runtime.
    $scenarios[] = [
      [
        4 => [
          'case' => 'Unable to lookup organism with empty values',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [1],
          ],
        ],
      ],
      ['column-headers' => 'Column 1'],
      $metadata_basic_column_headers,
      'One or more cells which are required to contain organisms were empty. Please ensure that you have entered existing organisms for all cells in the following columns: Organism',
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for the ValidOrganism validator.
   *
   * @param array $validation_results
   *   - An array of validation status arrays that get passed to the process
   *     method. It is keyed by the line number that triggered this failed
   *     validation status, further keyed by:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following key:
   *       - 'empty_cells': an array of indices which are empty in the row.
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *   to the user. The key is the token, (ex. 'organism'), and the value is
   *   the new value to be shown for that token.
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following keys are expected:
   *   - 'input_type': The type of input that was validated (should be
   *     'data-row' for this process method).
   *   - 'column_headers': This contains an array of headers for columns that
   *     are expected to contain organisms. The index in this array MUST
   *     match the position (starting with 0) of the column in the input file.
   * @param string $message
   *   - The expected message to be displayed in the returned render array for
   *     this scenario.
   *
   * @dataProvider provideValidOrganismEmptyCellCase
   */
  #[DataProvider('provideValidOrganismEmptyCellCase')]
  public function testValidOrganismProcessEmptyCell(array $validation_results, array $tokens, array $metadata, string $message) {

    // Set the input type for this validator instance to data-row since
    // this process method is only meant to be called for data-row
    // validation results.
    $this->validator_instance->setInputType('data-row');

    // Call the process method on our validation result.
    $render_array = $this->validator_instance::processListWithDescribedTable($validation_results, $metadata, $tokens);

    // Render the array we were returned.
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the rendered output.
    $selected_message = $this->cssSelect('div.case-message');
    $provided_message = (string) $selected_message[0];
    $this->assertStringContainsString($message, $provided_message, 'The message expected from processing ValidOrganism failures with empty cells for this scenario did not match the one in the rendered output.');

    // Make sure we don't have any tables in rendered output.
    $select_tables = $this->cssSelect('table');
    $this->assertEmpty($select_tables, 'There should not be any tables when testing processListWithDescribedTable with empty cells where organisms should be, but there was.');
  }

  /**
   * Data Provider for triggering exceptions in processListWithDescribedTable().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - An array of validation status arrays that get passed to the process
   *     method. It is keyed by the line number that triggered this failed
   *     validation status, further keyed by:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed, where the key => value
   *       pairs map to the index => cell value(s) that failed validation.
   *   - An array of additional metadata (or contextual information) needed by
   *     the process method. Here, the following keys are expected:
   *     - 'column-headers': This contains an array of headers for columns that
   *     are expected to contain organisms. The index in this array MUST
   *     match the position (starting with 0) of the column in the input file.
   *     Eg: 'column_headers' => [
   *           '2' => 'Organism1', // Header of column #3
   *           '4' => 'Organism2', // Header of column #5
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

    $tokens = [];
    $metadata = [
      'input_type' => 'data-row',
      'column_headers' => [
        2 => 'Organism1',
        4 => 'Organism2',
      ],
    ];

    // #0: Case of an empty organism cell, but an invalid message is
    // passed in using the token for case-empty-organism.
    $scenarios[] = [
      [
        5 => [
          'case' => 'Unable to lookup organism with empty values',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [4],
          ],
        ],
      ],
      $metadata,
      ['case-empty-organism' => ''],
      [
        'expected_message' => 'Expected a non-empty string for the message passed into renderSimpleWarningMessage().',
      ],
    ];

    // #1: ValidOrganism passed validation.
    $scenarios[] = [
      [
        6 => [
          'case' => 'Organism(s) exist(s) in the database',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [2, 4],
          ],
        ],
      ],
      $metadata,
      $tokens,
      [
        'expected_message' => 'The case string returned by the ValidOrganism validator at line #6 implies validation passed, but valid is set to FALSE.',
      ],
    ];

    // #2: Unrecognizable validation case message.
    $scenarios[] = [
      [
        7 => [
          'case' => 'unrecognizable case',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [2, 4],
          ],
        ],
      ],
      $metadata,
      $tokens,
      [
        'expected_message' => 'The case string returned by the ValidOrganism validator at line #7 is not recognized as a potential case.',
      ],
    ];

    // #3: Metadata array is empty.
    $scenarios[] = [
      [
        8 => [
          'case' => 'Organism(s) exist(s) in the database',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [2, 4],
          ],
        ],
      ],
      [
        'input_type' => 'data-row',
      ],
      $tokens,
      [
        'expected_message' => "Expected metadata to contain 'column_headers' when processing failures from ValidOrganism, but it does not.",
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
   *     - 'failedItems': an array of items that failed, where the key => value
   *       pairs map to the index => cell value(s) that failed validation.
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following keys are expected:
   *    - 'input_type': The type of input that was validated (should be
   *      'data-row' for this process method).
   *     - 'column_headers': This contains an array of headers for columns that
   *     are expected to contain organisms. The index in this array MUST
   *     match the position (starting with 0) of the column in the input file.
   *     Eg: 'column_headers' => [
   *           '2' => 'Organism1', // Header of column #3
   *           '4' => 'Organism2', // Header of column #5.
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *   to the user. The key is the token, (ex. 'project'), and the value is
   *   the new value to be shown for that token.
   * @param array $expectations
   *   An array of expectations in the rendered output which has the following
   *   keys:
   *   - 'expected_message': The exception message that is expected to be
   *     triggered.
   *
   * @dataProvider providePassedAndUnrecognizableCases
   */
  #[DataProvider('providePassedAndUnrecognizableCases')]
  public function testProcessListWithDescribedTableExceptions(array $validation_results, array $metadata, array $tokens, array $expectations) {
    // Set the input type for this validator instance to data-row since
    // this process method is only meant to be called for data-row
    // validation results.
    $this->validator_instance->setInputType('data-row');

    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->validator_instance->processListWithDescribedTable($validation_results, $metadata, $tokens);
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

  /**
   * Data Provider for exceptions in processItemWithSimpleList().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - An array of validation status arrays that get passed to the process
   *    method. It is keyed by the line number that triggered this failed
   *    validation status, further keyed by:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed, where the key => value
   *       pairs map to the index => cell value(s) that failed validation.
   *   - An array of expectations in the rendered output which has the following
   *    keys:
   *    - 'expected_message': The exception message that is expected to be
   *      triggered.
   */
  public static function provideItemWithSimpleListExceptions() {
    $scenarios = [];

    $metadata = [
      'input_type' => 'metadata',
    ];

    // #0: Validation passed, but valid is set to FALSE.
    $scenarios[] = [
      [
        'case' => 'Organism(s) exist(s) in the database',
        'valid' => FALSE,
        'failedItems' => [
          'organism_provided' => 'Existing Organism',
        ],
      ],
      $metadata,
      [
        'expected_message' => 'The case string returned by the ValidOrganism validator implies validation passed, but valid is set to FALSE.',
      ],
    ];

    // #1: Unrecognizable case string.
    $scenarios[] = [
      [
        'case' => 'unrecognizable case',
        'valid' => FALSE,
        'failedItems' => [
          'organism_provided' => 'Existing Organism',
        ],
      ],
      $metadata,
      [
        'expected_message' => 'The case string returned by the ValidOrganism validator is not recognized as a potential case.',
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests for exceptions thrown in processItemWithSimpleList().
   *
   * @param array $validation_results
   *   An array of validation status arrays that get passed to the process
   *   method. It is keyed by the line number that triggered this failed
   *   validation status, further keyed by:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed, where the key => value
   *       pairs map to the index => cell value(s) that failed validation.
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following key is expected:
   *   - 'input_type': The type of input that was validated (should be
   *     'metadata' for this process method).
   * @param array $expectations
   *   An array of expectations in the rendered output which has the following
   *   keys:
   *   - 'expected_message': The exception message that is expected to be
   *     triggered.
   *
   * @dataProvider provideItemWithSimpleListExceptions
   */
  #[DataProvider('provideItemWithSimpleListExceptions')]
  public function testProcessItemWithSimpleListExceptions(array $validation_results, array $metadata, array $expectations) {
    // Set the input type for this validator instance to metadata since
    // this process method is only meant to be called for metadata
    // validation results.
    $this->validator_instance->setInputType('metadata');

    $exception_caught = FALSE;
    $exception_message = '';

    try {
      // Call the process method on our validation result.
      $this->validator_instance::processItemWithSimpleList($validation_results, $metadata);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue(
      $exception_caught,
      'We expected an exception to be caught for missing metadata in processItemWithSimpleList(), but one was not thrown.'
    );
    $this->assertEquals(
      $expectations['expected_message'],
      $exception_message,
      'We expected the exception message to indicate missing metadata in processItemWithSimpleList(), but it does not match what was expected.',
    );

  }

  /**
   * Data Provider for validateMetadata processor for ValidOrganism()
   *
   * @return array
   *   Each scenario is an array with following:
   *   - An array of validation status arrays that get passed to the process
   *   method. It keyed by:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed, where the key => value
   *     is the following:
   *     - 'organism_provided' => the oragnism provided by the user.
   *   - An array of expectations that we want to find in the resulting rendered
   *     output. Each array has the following keys:
   *     -'expected message': The message expected in the return value of the
   *     process method for this scenario.
   */
  public static function provideValidOrganismMetadataFailedCases() {
    $scenarios = [];

    $metadata = [
      'input_type' => 'metadata',
    ];

    // #0: Missing organism(s) in the database with no tokens passed in.
    $scenarios[] = [
      [
        'case' => 'Missing organism(s) in the database',
        'valid' => FALSE,
        'failedItems' => [
          'organism_provided' => 'Tripalus databasica',
        ],
      ],
      $metadata,
      [],
      [
        'expected_message' => 'The following organisms do not match any existing in this site. Please make sure you have entered the names exactly as they appear on their organism pages, or contact your administrator to have them added if they do not yet exist.',
      ],
    ];

    // #1: Missing organism(s) in the database with a token passed in for the
    // contact admin part of the message.
    $scenarios[] = [
      [
        'case' => 'Missing organism(s) in the database',
        'valid' => FALSE,
        'failedItems' => [
          'organism_provided' => 'Tripalus databasica',
        ],
      ],
      $metadata,
      ['contact-admin' => 'CONTACT ADMIN'],
      [
        'expected_message' => 'The following organisms do not match any existing in this site. Please make sure you have entered the names exactly as they appear on their organism pages, or CONTACT ADMIN to have them added if they do not yet exist.',
      ],
    ];

    // #2: Missing organism(s) in the database with a token passed in for the
    // case-missing-organism.
    $scenarios[] = [
      [
        'case' => 'Missing organism(s) in the database',
        'valid' => FALSE,
        'failedItems' => [
          'organism_provided' => 'Tripalus databasica',
        ],
      ],
      $metadata,
      ['case-missing-organism' => 'Please contact your admin as the following organisms do not exist in the database.'],
      [
        'expected_message' => 'Please contact your admin as the following organisms do not exist in the database.',
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for the validateMetadata validator.
   *
   * @param array $validation_results
   *   - An array of validation status arrays that get passed to the process
   *   method. It keyed by:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed, where the key => value
   *     is the following:
   *     - 'organism_provided' => the oragnism provided by the user.
   * @param array $metadata
   *   - An array of additional metadata (or contextual information)
   *   needed by the process method. Here, the following key is expected:
   *   - 'input_type': The type of input that was validated (should be
   *     'metadata' for this process method).
   * @param array $tokens
   *   - An array of tokens that can be used in the message. The key is the
   *     token, (ex. 'contact-admin'), and the value is the new value to be
   *     shown for that token.
   * @param array $expectations
   *   - An array of expectations that we want to find in the resulting rendered
   *     output. Each array has the following keys:
   *     -'expected message': The message expected in the return value of the
   *     process method for this scenario.
   *
   * @dataProvider provideValidOrganismMetadataFailedCases
   */
  #[DataProvider('provideValidOrganismMetadataFailedCases')]
  public function testProcessItemWithSimpleList(array $validation_results, array $metadata, array $tokens, array $expectations) {
    // Set the input type for this validator instance to metadata since
    // this process method is only meant to be called for metadata
    // validation results.
    $this->validator_instance->setInputType('metadata');

    // Call the process method on our validation result.
    $render_array = $this->validator_instance::processItemWithSimpleList($validation_results, $metadata, $tokens);

    // Render the array we were returned.
    $rendered_markup = $this->renderer->renderRoot($render_array);

    $this->assertStringContainsString(
      $expectations['expected_message'],
      $rendered_markup,
      'The message expected from processing ValidOrganism failures for this scenario did not match the message in the render array.'
    );

    $this->assertStringContainsString(
      $validation_results['failedItems']['organism_provided'],
      $rendered_markup,
      'The organism expected from processing ValidOrganism failures for this scenario did not match the message in the render array.'
    );
  }

}

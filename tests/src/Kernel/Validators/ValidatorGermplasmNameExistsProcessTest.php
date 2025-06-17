<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Core\Render\Renderer;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;

/**
 * Tests any message processing methods for the GermplasmNameExists validator.
 *
 * @group trpcultivate
 * @group validators
 */
class ValidatorGermplasmNameExistsProcessTest extends ChadoTestKernelBase {

  /**
   * Plugin Manager service.
   *
   * @var \Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager
   */
  protected TripalCultivateValidatorManager $plugin_manager;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

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

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');

    // Get our renderer.
    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Data Provider for processor for GermplasmNameExists()
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - An array of validation result arrays that get passed to the process
   *     method. It is keyed by the line number that triggered this failed
   *     validation status, further keyed by:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       @todo summarize the failedItems that are used by this processor
   *   - An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'project'), and the value is
   *     the new value to be shown for that token.
   *   - An array of metadata needed by the process method. The method supports
   *     the following keys:
   *     - 'column_headers': an array of headers for columns that
   *       are expected to contain germplasm names. The index in this array MUST
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
   *       problematic germplasm name. For example:
   *        '2' => [ 'Germplasm Name' => 'Invalid Value' ]
   */
  public static function provideGermplasmNameExistsFailedCases() {
    $scenarios = [];

    $basic_column_headers = [
      'column_headers' => [
        1 => 'Germplasm Name',
      ],
    ];

    // ------ DEFAULT CASES (no tokens) ------
    // #1: A germplasm name cell is missing from the database
    $scenarios[] = [
      [
        3 => [
          'case' => 'Missing germplasm name(s) in the database',
          'valid' => FALSE,
          'failedItems' => [
            'missing_cells' => [
              1 => [
                'germplasm_name' => 'Non-existant Germplasm',
              ],
            ],
          ],
        ],
      ],
      [],
      $basic_column_headers,
      [
        'missing_cells' => [
          'expected_message' => 'The following germplasm names could not be found in the database.',
          'expected_column_count' => 2,
          'expected_rows' => [
            3 => [
              'Germplasm Name' => 'Non-existant Germplasm',
            ],
          ],
        ],
      ],
    ];

    // #2: A germplasm name cell is duplicated in the database
    $scenarios[] = [
      [
        2 => [
          'case' => 'Duplicate(s) found in the database for germplasm name(s)',
          'valid' => FALSE,
          'failedItems' => [
            'duplicate_cells' => [
              1 => [
                'germplasm_name' => 'Duplicate Germplasm',
              ],
            ],
          ],
        ],
      ],
      [],
      $basic_column_headers,
      [
        'duplicate_cells' => [
          'expected_message' => 'The following germplasm names have 2 or more records in the database associated with them. Please resolve the duplications or contact your administrator for help with investigating.',
          'expected_column_count' => 2,
          'expected_rows' => [
            2 => [
              'Germplasm Name' => 'Duplicate Germplasm',
            ],
          ],
        ],
      ],
    ];

    $two_germplasm_column_headers = [
      'column_headers' => [
        2 => 'Maternal Parent',
        4 => 'Paternal Parent',
      ],
    ];

    // #3: Within one row, 1 cell has missing germplasm, and 1 has a duplicate
    $scenarios[] = [
      [
        5 => [
          'case' => 'Missing germplasm name(s) and found duplicate(s) in the database',
          'valid' => FALSE,
          'failedItems' => [
            'missing_cells' => [
              2 => [
                'germplasm_name' => 'Non-existant Germplasm',
              ],
            ],
            'duplicate_cells' => [
              4 => [
                'germplasm_name' => 'Duplicate Germplasm',
              ],
            ],
          ],
        ],
      ],
      [],
      $two_germplasm_column_headers,
      [
        'missing_cells' => [
          'expected_message' => 'The following germplasm names could not be found in the database.',
          'expected_column_count' => 2,
          'expected_rows' => [
            5 => [
              'Maternal Parent' => 'Non-existant Germplasm',
            ],
          ],
        ],
        'duplicate_cells' => [
          'expected_message' => 'The following germplasm names have 2 or more records in the database associated with them. Please resolve the duplications or contact your administrator for help with investigating.',
          'expected_column_count' => 2,
          'expected_rows' => [
            5 => [
              'Paternal Parent' => 'Duplicate Germplasm',
            ],
          ],
        ],
      ],
    ];
    return $scenarios;

  }

  /**
   * Tests the message processor method for the GermplasmNameExists validator.
   *
   * @param array $validation_result
   *   - An array of validation result arrays that get passed to the process
   *     method. It is keyed by the line number that triggered this failed
   *     validation status, further keyed by:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       @todo summarize the failedItems that are used by this processor.
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *   to the user. The key is the token, (ex. 'project'), and the value is
   *   the new value to be shown for that token.
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following keys are expected:
   *   - 'column_headers': This contains an array of headers for columns that
   *     are expected to contain germplasm names. The index in this array MUST
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
   *       problematic germplasm name. For example:
   *        '2' => [ 'Germplasm Name' => 'Invalid Value' ].
   *
   * @dataProvider provideGermplasmNameExistsFailedCases
   */
  public function testProcessListWithDescribedTable(array $validation_result, array $tokens, array $metadata, array $expectations) {

    // Create a plugin instance for this validator.
    $validator_id = 'germplasm_name_exists';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Call the process method on our validation result.
    $render_array = $instance->processListWithDescribedTable($validation_result, $metadata, $tokens);

    // Render the array we were returned.
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the rendered output.
    // Loop through expectations one table at a time.
    foreach ($expectations as $table_case => $table) {
      // Check the message above this table is correct.
      $selected_message_markup = $this->cssSelect("ul li div.case-message.case-$table_case");
      $table_message = (string) $selected_message_markup[0];
      $this->assertStringContainsString(
        $table['expected_message'],
        $table_message,
        'The message expected from processing GermplasmNameExists failures for this scenario did not match the message in the render array.'
      );

      // Select and save the table header.
      $selected_table_header = $this->cssSelect("table.table-case-$table_case thead tr");
      $select_column_headers = (array) $selected_table_header[0]->th;
      // Assert that the number of columns matches the number of we expect.
      $this->assertCount(
        $table['expected_column_count'],
        $select_column_headers,
        'We expected ' . $table['expected_column_count'] . 'columns to be in the rendered table for GermplasmNameExists failures for this scenario, but instead there are ' . count($select_column_headers) . '.'
      );

      // Pull out the table rows for this table case.
      $selected_rows = $this->cssSelect("table.table-case-$table_case tbody tr");
      // Assert that the number of rows matches what we expect.
      $expected_row_count = (count($table['expected_rows']));
      $this->assertCount(
        $expected_row_count,
        $selected_rows,
        'We expected ' . $expected_row_count . 'rows in the rendered table for GermplasmNameExists failures for this scenario, but there are ' . count($selected_rows) . '.'
      );

      $current_row_index = 0;
      // Loop through expectations for each row of this table.
      foreach ($table['expected_rows'] as $expected_line_no => $expected_values) {
        $select_row_cells = (array) $selected_rows[$current_row_index]->td;

        // 1st Column: Line Number.
        $line_number = $select_row_cells[0];
        $this->assertEquals(
          $expected_line_no,
          $line_number,
          "Did not get the expected line number in the rendered table from processing GermplasmNameExists failures."
        );
        // 2nd Column and up: Column(s) with invalid value
        $current_column_index = 1;
        foreach ($expected_values as $column_header => $invalid_value) {
          // Check that the invalid value is under the correct column header.
          $this->assertEquals(
            $column_header,
            $select_column_headers[$current_column_index],
            "We expected the column header \"$column_header\" to be present in the rendered table's header for GermplasmNameExists failures at index $current_column_index but it was not."
          );
          // Check that the invalid value in the table matches what we expect.
          $this->assertEquals(
            $invalid_value,
            (string) $select_row_cells[$current_column_index],
            "We expected an invalid value to be listed for \"$column_header\" at line #$expected_line_no in the rendered table for GermplasmNameExists failures."
          );
          $current_column_index++;
        }

        // Move onto the next row.
        $current_row_index++;
      }
    }
  }

  /**
   * Data Provider for case of empty cells in GermplasmNameExists()
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - An array of validation result arrays that get passed to the process
   *     method. It is keyed by the line number that triggered this failed
   *     validation status, further keyed by:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following key:
   *       - 'empty_cells': an array of indices which are empty in the row.
   *   - An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'project'), and the value is
   *     the new value to be shown for that token.
   *   - An array of metadata needed by the process method. The method supports
   *     the following keys:
   *     - 'column_headers': an array of headers for columns that
   *       are expected to contain germplasm names. The index in this array MUST
   *       match the position (starting with 0) of the column in the input file.
   *   - A string that is the expected message to be displayed in the returned
   *     render array for this scenario.
   */
  public static function provideGermplasmNameExistsEmptyCellCase() {
    $scenarios = [];

    $basic_column_headers = [
      'column_headers' => [
        1 => 'Germplasm Name',
      ],
    ];

    // #0: A single germplasm name cell is empty
    $scenarios[] = [
      [
        5 => [
          'case' => 'Unable to lookup germplasm with empty values',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [1],
          ],
        ],
      ],
      [],
      $basic_column_headers,
      'One or more cells which are expected to contain germplasm names was empty. Please ensure that you have non-empty cells for the following columns: Germplasm Name',
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for the GermplasmNameExists validator.
   *
   * @param array $validation_result
   *   - An array of validation result arrays that get passed to the process
   *     method. It is keyed by the line number that triggered this failed
   *     validation status, further keyed by:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       @todo summarize the failedItems that are used by this processor.
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *   to the user. The key is the token, (ex. 'project'), and the value is
   *   the new value to be shown for that token.
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following keys are expected:
   *   - 'column_headers': This contains an array of headers for columns that
   *     are expected to contain germplasm names. The index in this array MUST
   *     match the position (starting with 0) of the column in the input file.
   * @param string $message
   *   - The expected message to be displayed in the returned render array for
   *     this scenario.
   *
   * @dataProvider provideGermplasmNameExistsEmptyCellCase
   */
  public function testProcessListWithDescribedTableEmptyCell(array $validation_result, array $tokens, array $metadata, string $message) {

    // Create a plugin instance for this validator.
    $validator_id = 'germplasm_name_exists';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Call the process method on our validation result.
    $render_array = $instance->processListWithDescribedTable($validation_result, $metadata, $tokens);

    // Render the array we were returned.
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the rendered output.
    $selected_message = $this->cssSelect('tc-germplasm-name-exists-empty');
    //print_r($selected_message);
    $provided_message = (string) $selected_message[0];
    //$this->assertStringContainsString($message, $provided_message, 'The message expected from processing GermplasmNameExists failures with empty cells for this scenario did not match the one in the rendered output.');

  }

}

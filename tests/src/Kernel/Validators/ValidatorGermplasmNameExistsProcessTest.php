<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Core\Render\Renderer;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests any message processing methods for the GermplasmNameExists validator.
 *
 * @group trpcultivate
 * @group validators
 */
#[Group('trpcultivate')]
#[Group('validators')]
#[RunTestsInSeparateProcesses]
class ValidatorGermplasmNameExistsProcessTest extends ChadoTestKernelBase {

  /**
   * An instance of the validator.
   *
   * @var Drupal\trpcultivate\Plugin\Validators\GermplasmNameExists
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
    'file',
    'system',
    'user',
    'path',
    'path_alias',
    'views',
    'field',
    'field_ui',
    'field_group',
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

    $validator_id = 'germplasm_name_exists';
    $this->validator_instance = $this->container
      ->get('plugin.manager.trpcultivate_validator')
      ->createInstance($validator_id);

    // Get our renderer.
    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Data Provider for processor for GermplasmNameExists()
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

    $two_germplasm_column_headers = [
      'column_headers' => [
        2 => 'Maternal Parent',
        4 => 'Paternal Parent',
      ],
    ];

    // ------ DEFAULT CASES (no tokens) ------
    // #0: A germplasm name cell is missing from the database
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
          'expected_message' => 'The following germplasm names do not match any existing in this site. Please make sure you have entered the names exactly as they appear on the germplasm pages or contact your administrator to have them added if they do not yet exist.',
          'expected_column_count' => 2,
          'expected_rows' => [
            3 => [
              'Germplasm Name' => 'Non-existant Germplasm',
            ],
          ],
        ],
      ],
    ];

    // #1: A germplasm name cell is duplicated in the database
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
          'expected_message' => 'The following germplasm names in your file have been duplicated in this site (i.e. there are two or more pages for the same germplasm). If there is a more specific germplasm already existing in the site, then use that in your file. Regardless, contact your administrator to have the duplications resolved in the site.',
          'expected_column_count' => 2,
          'expected_rows' => [
            2 => [
              'Germplasm Name' => 'Duplicate Germplasm',
            ],
          ],
        ],
      ],
    ];

    // #2: Within one row, 1 cell has missing germplasm, and 1 has a duplicate
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
          'expected_message' => 'The following germplasm names do not match any existing in this site. Please make sure you have entered the names exactly as they appear on the germplasm pages or contact your administrator to have them added if they do not yet exist.',
          'expected_column_count' => 2,
          'expected_rows' => [
            5 => [
              'Maternal Parent' => 'Non-existant Germplasm',
            ],
          ],
        ],
        'duplicate_cells' => [
          'expected_message' => 'The following germplasm names in your file have been duplicated in this site (i.e. there are two or more pages for the same germplasm). If there is a more specific germplasm already existing in the site, then use that in your file. Regardless, contact your administrator to have the duplications resolved in the site.',
          'expected_column_count' => 2,
          'expected_rows' => [
            5 => [
              'Paternal Parent' => 'Duplicate Germplasm',
            ],
          ],
        ],
      ],
    ];

    // #3: Test for both duplicate and missing germplasm on multiple rows and in
    // multiple columns.
    $scenarios[] = [
      [
        1 => [
          'case' => 'Missing germplasm name(s) and found duplicate(s) in the database',
          'valid' => FALSE,
          'failedItems' => [
            'missing_cells' => [
              2 => [
                'germplasm_name' => 'Missing Germplasm 1',
              ],
            ],
            'duplicate_cells' => [
              4 => [
                'germplasm_name' => 'Duplicate Germplasm 1',
              ],
            ],
          ],
        ],
        4 => [
          'case' => 'Missing germplasm name(s) and found duplicate(s) in the database',
          'valid' => FALSE,
          'failedItems' => [
            'duplicate_cells' => [
              2 => [
                'germplasm_name' => 'Duplicate Germplasm 4',
              ],
            ],
            'missing_cells' => [
              4 => [
                'germplasm_name' => 'Missing Germplasm 4',
              ],
            ],
          ],
        ],
      ],
      [],
      $two_germplasm_column_headers,
      [
        'missing_cells' => [
          'expected_message' => 'The following germplasm names do not match any existing in this site. Please make sure you have entered the names exactly as they appear on the germplasm pages or contact your administrator to have them added if they do not yet exist.',
          // We expect 3 columns since missing was triggered by 2 separate
          // columns in the input (Line # + 2 Column Headers)
          'expected_column_count' => 3,
          'expected_rows' => [
            1 => [
              'Maternal Parent' => 'Missing Germplasm 1',
              'Paternal Parent' => '',
            ],
            4 => [
              'Maternal Parent' => '',
              'Paternal Parent' => 'Missing Germplasm 4',
            ],
          ],
        ],
        'duplicate_cells' => [
          'expected_message' => 'The following germplasm names in your file have been duplicated in this site (i.e. there are two or more pages for the same germplasm). If there is a more specific germplasm already existing in the site, then use that in your file. Regardless, contact your administrator to have the duplications resolved in the site.',
          // We expect 3 columns since duplicate was triggered by 2 separate
          // columns in the input (Line # + 2 Column Headers)
          'expected_column_count' => 3,
          'expected_rows' => [
            1 => [
              'Maternal Parent' => '',
              'Paternal Parent' => 'Duplicate Germplasm 1',
            ],
            4 => [
              'Maternal Parent' => 'Duplicate Germplasm 4',
              'Paternal Parent' => '',
            ],
          ],
        ],
      ],
    ];

    // ----------- TEST WITH TOKENS -------------
    // #4: Test for [contact-admin] in the case of a duplicate germplasm.
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
      ['contact-admin' => 'email your administrator at admin@email.com'],
      $basic_column_headers,
      [
        'duplicate_cells' => [
          'expected_message' => 'The following germplasm names in your file have been duplicated in this site (i.e. there are two or more pages for the same germplasm). If there is a more specific germplasm already existing in the site, then use that in your file. Regardless, email your administrator at admin@email.com to have the duplications resolved in the site.',
          'expected_column_count' => 2,
          'expected_rows' => [
            2 => [
              'Germplasm Name' => 'Duplicate Germplasm',
            ],
          ],
        ],
      ],
    ];

    // #5: Customize both the missing and duplicate case messages.
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
      [
        'case-missing-germplasm' => 'Where art thou, germplasm?',
        'case-duplicate-germplasm' => 'Found an imposter germplasm!',
      ],
      $two_germplasm_column_headers,
      [
        'missing_cells' => [
          'expected_message' => 'Where art thou, germplasm?',
          'expected_column_count' => 2,
          'expected_rows' => [
            5 => [
              'Maternal Parent' => 'Non-existant Germplasm',
            ],
          ],
        ],
        'duplicate_cells' => [
          'expected_message' => 'Found an imposter germplasm!',
          'expected_column_count' => 2,
          'expected_rows' => [
            5 => [
              'Paternal Parent' => 'Duplicate Germplasm',
            ],
          ],
        ],
      ],
    ];

    // #6: Provide a custom case message with default + custom tokens
    $scenarios[] = [
      [
        7 => [
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
      [
        'contact-admin' => 'Contact your admin',
        'fix' => 'resolution',
        'case-duplicate-germplasm' => 'Found an imposter germplasm in column(s): [column-headers]. [contact-admin] for help finding a [fix].',
      ],
      $basic_column_headers,
      [
        'duplicate_cells' => [
          'expected_message' => 'Found an imposter germplasm in column(s): Germplasm Name. Contact your admin for help finding a resolution.',
          'expected_column_count' => 2,
          'expected_rows' => [
            7 => [
              'Germplasm Name' => 'Duplicate Germplasm',
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
  #[DataProvider('provideGermplasmNameExistsFailedCases')]
  public function testProcessListWithDescribedTable(array $validation_results, array $tokens, array $metadata, array $expectations) {

    // Call the process method on our validation result.
    $render_array = $this->validator_instance::processListWithDescribedTable($validation_results, $metadata, $tokens);

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
   *   - An array of validation status arrays that get passed to the process
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

    $additional_column_headers = [
      'column_headers' => [
        2 => 'Maternal Germplasm',
        4 => 'Paternal Germplasm',
        6 => 'Misc Germplasm',
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
      'One or more cells which are required to contain germplasm names were empty. Please ensure that you have entered existing germplasm names for all cells in the following columns: Germplasm Name',
    ];

    // #1: Multiple empty germplasm name cells
    $scenarios[] = [
      [
        1 => [
          'case' => 'Unable to lookup germplasm with empty values',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [2, 4],
          ],
        ],
      ],
      [],
      $additional_column_headers,
      'One or more cells which are required to contain germplasm names were empty. Please ensure that you have entered existing germplasm names for all cells in the following columns: Maternal Germplasm, Paternal Germplasm, Misc Germplasm',
    ];

    // #2: An empty germplasm name cell, a missing germplasm, and a duplicate.
    $scenarios[] = [
      [
        3 => [
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
        7 => [
          'case' => 'Unable to lookup germplasm with empty values',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [6],
          ],
        ],
      ],
      [],
      $additional_column_headers,
      'One or more cells which are required to contain germplasm names were empty. Please ensure that you have entered existing germplasm names for all cells in the following columns: Maternal Germplasm, Paternal Germplasm, Misc Germplasm',
    ];

    // #3: An empty cell and a token to replace the entire displayed message.
    $scenarios[] = [
      [
        2 => [
          'case' => 'Unable to lookup germplasm with empty values',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [1],
          ],
        ],
      ],
      ['case-empty-germplasm' => 'Oh no! You left one or more cells empty in column(s) "[column-headers]" but there should be a germplasm name!'],
      $basic_column_headers,
      'Oh no! You left one or more cells empty in column(s) "Germplasm Name" but there should be a germplasm name!',
    ];

    // #4: An empty cell and a token passed in for 'column-headers'.
    // This token is expected to NOT be substituted since it is reserved for
    // being determined at runtime.
    $scenarios[] = [
      [
        4 => [
          'case' => 'Unable to lookup germplasm with empty values',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [1],
          ],
        ],
      ],
      ['column-headers' => 'Column 1'],
      $basic_column_headers,
      'One or more cells which are required to contain germplasm names were empty. Please ensure that you have entered existing germplasm names for all cells in the following columns: Germplasm Name',
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for the GermplasmNameExists validator.
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
  #[DataProvider('provideGermplasmNameExistsEmptyCellCase')]
  public function testProcessListWithDescribedTableEmptyCell(array $validation_results, array $tokens, array $metadata, string $message) {

    // Call the process method on our validation result.
    $render_array = $this->validator_instance::processListWithDescribedTable($validation_results, $metadata, $tokens);

    // Render the array we were returned.
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the rendered output.
    $selected_message = $this->cssSelect('div.case-message');
    $provided_message = (string) $selected_message[0];
    $this->assertStringContainsString($message, $provided_message, 'The message expected from processing GermplasmNameExists failures with empty cells for this scenario did not match the one in the rendered output.');

    // Make sure we don't have any tables in rendered output.
    $select_tables = $this->cssSelect('table');
    $this->assertEmpty($select_tables, 'There should not be any tables when testing processListWithDescribedTable with empty cells where germplasm names should be, but there was.');
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
   *     are expected to contain germplasm names. The index in this array MUST
   *     match the position (starting with 0) of the column in the input file.
   *     Eg: 'column_headers' => [
   *           '2' => 'Maternal Germplasm Name', // Header of column #3
   *           '4' => 'Paternal Germplasm Name', // Header of column #5
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
      'column_headers' => [
        2 => 'Maternal Parent',
        4 => 'Paternal Parent',
      ],
    ];

    // #0: Case of an empty germplasm name cell, but an invalid message is
    // passed in using the token for case-empty-germplasm.
    $scenarios[] = [
      [
        5 => [
          'case' => 'Unable to lookup germplasm with empty values',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [4],
          ],
        ],
      ],
      $metadata,
      ['case-empty-germplasm' => ''],
      [
        'expected_message' => 'Expected a non-empty string for the message passed into renderSimpleWarningMessage().',
      ],
    ];

    // #1: GermplasmNameExists passed validation.
    $scenarios[] = [
      [
        6 => [
          'case' => 'Germplasm name(s) exist(s) in the database',
          'valid' => FALSE,
          'failedItems' => [
            'empty_cells' => [2, 4],
          ],
        ],
      ],
      $metadata,
      $tokens,
      [
        'expected_message' => 'The case string returned by the GermplasmNameExists validator at line #6 implies validation passed, but valid is set to FALSE.',
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
        'expected_message' => 'The case string returned by the GermplasmNameExists validator at line #7 is not recognized as a potential case.',
      ],
    ];

    // #3: Pass in empty metadata that is missing the 'column_headers' key
    $scenarios[] = [
      [
        8 => [
          'case' => 'Duplicate(s) found in the database for germplasm name(s)',
          'valid' => FALSE,
          'failedItems' => [
            'duplicate_cells' => [
              2 => [
                'duplicate_germplasm' => 'Duplicate Germplasm',
              ],
            ],
          ],
        ],
      ],
      [],
      $tokens,
      [
        'expected_message' => "Expected metadata to contain 'column_headers' when processing failures from GermplasmNameExists, but it does not.",
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
   *     - 'column_headers': This contains an array of headers for columns that
   *     are expected to contain germplasm names. The index in this array MUST
   *     match the position (starting with 0) of the column in the input file.
   *     Eg: 'column_headers' => [
   *           '2' => 'Maternal Germplasm Name', // Header of column #3
   *           '4' => 'Paternal Germplasm Name', // Header of column #5.
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

}

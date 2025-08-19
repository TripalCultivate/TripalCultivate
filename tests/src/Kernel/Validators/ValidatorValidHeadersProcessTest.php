<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Test any message process methods for the ValidHeaders validator.
 *
 * @group trpcultivate
 * @group validators
 */
#[Group('trpcultivate')]
#[Group('validators')]
class ValidatorValidHeadersProcessTest extends ChadoTestKernelBase {

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
   * @var Drupal\trpcultivate\Plugin\Validators\ValidHeaders
   */
  protected $validator_instance;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    $validator_id = 'valid_headers';
    $this->validator_instance = $this->container
      ->get('plugin.manager.trpcultivate_validator')
      ->createInstance($validator_id);

    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Data Provider for testProcessItemWithSimpleList().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - Validation status arrays that get passed to the process
   *     method. It contains the following keys:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       - 'headers': A string indicating the header row is empty.
   *       - an array of column headers that was in the input file.
   *   - An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'project'), and the value is
   *     the new value to be shown for that token.
   *   - An array of additional metadata (or contextual information) needed by
   *     the process method. Here, the following keys are expected:
   *       - 'column_headers': This contains an array of headers. The index in
   *         this array MUST match the position (starting with 0) of the column
   *         in the input file.
   *         Eg: 'column_headers' => [
   *               '2' => 'Header 1', // Header of column #3
   *               '4' => 'Header 2', // Header of column #5
   *             ].
   *   - An array of expectations in the rendered output which has the following
   *     keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   */
  public static function provideValidHeadersFailedCases() {

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

    // #0: The header row is empty.
    $scenarios[] = [
      [
        'case' => 'Header row is an empty value',
        'valid' => FALSE,
        'failedItems' => [
          'headers' => 'headers array is an empty array',
        ],
      ],
      $metadata,
      $tokens,
      [
        'expected_message' => 'The file has an empty row where the header was expected.',
      ],
    ];

    // #1: Correct number of headers, but there's a mismatch.
    $scenarios[] = [
      [
        'case' => 'Headers do not match expected headers',
        'valid' => FALSE,
        'failedItems' => [
          'Trait Name',
          'Trait Description',
          '',
          'Method Description',
          'Unit',
          'Type',
        ],
      ],
      $metadata,
      $tokens,
      [
        'expected_message' => 'One or more of the column headers in the input file does not match what was expected.',
      ],
    ];

    // #2: Incorrect number of headers.
    $scenarios[] = [
      [
        'case' => 'Headers provided does not have the expected number of headers',
        'valid' => FALSE,
        'failedItems' => [
          'Trait Name',
          'Trait Description',
          'Method Short Name',
          'Method Description',
        ],
      ],
      $metadata,
      $tokens,
      [
        'expected_message' => 'This importer requires a strict number of 6 column headers.',
      ],
    ];

    // #3: Test token reversal - message.
    $scenarios[] = [
      [
        'case' => 'Header row is an empty value',
        'valid' => FALSE,
        'failedItems' => [
          'headers' => 'headers array is an empty array',
        ],
      ],
      $metadata,
      [
        'case-empty-headers' => 'Nothing Provided.',
      ],
      [
        'expected_message' => 'Nothing Provided.',
      ],
    ];

    // #4: Test static token - expected message remains unchanged.
    // Correct number of headers, but there's a mismatch.
    $scenarios[] = [
      [
        'case' => 'Headers provided does not have the expected number of headers',
        'valid' => FALSE,
        'failedItems' => [
          'Trait Name',
          'Trait Description',
          'Method Short Name',
          'Method Description',
        ],
      ],
      $metadata,
      [
        'num-expected-columns' => 'SIX',
      ],
      [
        'expected_message' => 'This importer requires a strict number of 6 column headers.',
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for the ValidHeaders validator.
   *
   * @param array $validation_status
   *   Validation status arrays that get passed to the process
   *   method. It contains the following keys:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       - 'headers': A string indicating the header row is empty.
   *       - an array of column headers that was in the input file.
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following keys are expected:
   *     - 'column_headers': This contains an array of headers. The index in
   *       this array MUST match the position (starting with 0) of the column in
   *       the input file.
   *       Eg: 'column_headers' => [
   *             '2' => 'Header 1', // Header of column #3
   *             '4' => 'Header 2', // Header of column #5.
   *           ];.
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *   to the user. The key is the token, (ex. 'project'), and the value is
   *   the new value to be shown for that token.
   * @param array $expectations
   *   An array of expectations in the rendered output which has the
   *   following keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   */
  #[DataProvider('provideValidHeadersFailedCases')]
  public function testProcessListWithDescribedTableFailures(array $validation_status, array $metadata, array $tokens, array $expectations) {

    // Call the process method on our validation result.
    $render_array = $this->validator_instance::processListWithDescribedTable($validation_status, $metadata, $tokens);
    // Render the array we were returned.
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the rendered output.
    // First check that we were given the correct message.
    $selected_message_markup = $this->cssSelect('ul li div.case-message');
    $this->assertStringContainsString(
      $expectations['expected_message'],
      (string) $selected_message_markup[0],
      'The message expected from processing ValidHeaders failures for this scenario did not match the message in the render array.'
    );
    // Check that we have a table that contains the expected 2 rows.
    $selected_table_rows = $this->cssSelect('tbody tr');
    $this->assertCount(2, $selected_table_rows, 'The rendered table by processListWithDescribedTable does not contain the expected 2 rows for this scenario.');
    // Check for the "Provided Headers" heading on the 2nd row.
    $selected_provided_headers_th = $this->cssSelect('tbody tr.provided-headers th');
    $this->assertEquals(
      'Provided Headers',
      (string) $selected_provided_headers_th[0],
      'The second row of the rendered table does not contain the "Provided Headers" table header for this scenario.'
    );
    // Check that the row values are the same as what we provided.
    $selected_provided_headers_td = $this->cssSelect('tbody tr.provided-headers td');
    // If the headers row was empty, check that we have no values in row 2.
    if (array_key_exists('headers', $validation_status['failedItems'])) {
      $this->assertEmpty($selected_provided_headers_td, "The values in the \"Provided Headers\" row were expected to be empty since an empty header was provided, but are not.");
    }
    else {
      // Iterate through our provided headers to compare with what's in the
      // rendered provided headers row.
      $this->assertEquals($validation_status['failedItems'], $selected_provided_headers_td, "The header row provided does not match the second row (the \'provided headers\' row) of the rendered table.");
    }
  }

  /**
   * Data Provider for triggering exceptions in process failures method.
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - Validation status arrays that get passed to the process method.
   *     It contains the following keys:
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': array of items that failed with the following keys.
   *         - 'headers': list of headers.
   *   - An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'project'), and the value is
   *     the new value to be shown for that token.
   *   - An array of additional metadata (or contextual information) needed by
   *     the process method. Here, the following keys are expected:
   *       - 'column_headers': This contains an array of headers. The index in
   *         this array MUST match the position (starting with 0) of the column
   *         in the input file.
   *         Eg: 'column_headers' => [
   *               '2' => 'Header 1', // Header of column #3
   *               '4' => 'Header 2', // Header of column #5
   *             ].
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

    // #0: ValidHeaders passed.
    $scenarios[] = [
      [
        'case' => 'Headers exist and match expected headers',
        'valid' => FALSE,
        'failedItems' => [
          'headers' => 'headers array is an empty array',
        ],
      ],
      $metadata,
      $tokens,
      [
        'expected_message' => 'The case string returned by the ValidHeaders validator implies validation passed, but valid is set to FALSE.',
      ],
    ];

    // #1: unrecognizable validation case message.
    $scenarios[] = [
      [
        'case' => 'Unrecognized case string',
        'valid' => FALSE,
        'failedItems' => [
          'headers' => 'headers array is an empty array',
        ],
      ],
      $metadata,
      $tokens,
      [
        'expected_message' => 'The case string returned by the ValidHeaders validator is not recognized as a potential case.',
      ],
    ];

    // #2: missing metadata column_headers key.
    $scenarios[] = [
      [
        'case' => 'Unrecognized case string',
        'valid' => FALSE,
        'failedItems' => [
          'headers' => 'headers array is an empty array',
        ],
      ],
      [
        'header_columns' => [],
      ],
      $tokens,
      [
        'expected_message' => "Expected metadata to contain 'column_headers' when processing failures from ValidHeaders, but it does not.",
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests for exceptions thrown for passed and unrecognizable case strings.
   *
   * @param array $validation_status
   *   Validation status arrays that get passed to the process method.
   *   It contains the following keys:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': array of items that failed with the following keys.
   *       - 'headers': list of headers.
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following keys are expected:
   *     - 'column_headers': This contains an array of headers. The index in
   *       this array MUST match the position (starting with 0) of the column in
   *       the input file.
   *         Eg: 'column_headers' => [
   *               '2' => 'Header 1', // Header of column #3
   *               '4' => 'Header 2', // Header of column #5
   *             ].
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *   to the user. The key is the token, (ex. 'project'), and the value is
   *   the new value to be shown for that token.
   * @param array $expectations
   *   An array of expectations in the rendered output which has the
   *   following keys:
   *     - 'expected_message': The exception message that is expected to be
   *       triggered.
   */
  #[DataProvider('providePassedAndUnrecognizableCases')]
  public function testProcessListWithDescribedTableExceptions(array $validation_status, array $metadata, array $tokens, array $expectations) {

    // Test with a passed validation case string.
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->validator_instance::processListWithDescribedTable($validation_status, $metadata, $tokens);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue(
      $exception_caught,
      'We expected an exception to be caught for case ' . $validation_status['case'] . ', but one was not thrown.'
    );

    $this->assertEquals(
      $expectations['expected_message'],
      $exception_message,
      'We expected the exception message to indicate that case ' . $validation_status['case'] . ', but it does not match what was expected.'
    );
  }

}

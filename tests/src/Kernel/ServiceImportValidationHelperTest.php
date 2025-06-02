<?php

namespace Drupal\Tests\tripalcultivate\Kernel;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\trpcultivate\Service\ImportValidationHelper;

/**
 * Test import validation helper service.
 *
 * @group trpcultivate
 * @group validation_helper
 */
class ServiceImportValidationHelperTest extends ChadoTestKernelBase {

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
    'markup',
    'field_group',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);
  }

  /**
   * Data Provider: provide test data (mime types) to getFileDelimiters().
   *
   * @return array
   *   Each test scenario is an array with the following values:
   *   - A string, human-readable short description of the test scenario.
   *   - A string, mime type input.
   *   - Boolean value, indicates if scenario is expecting an exception (TRUE)
   *     or not (FALSE).
   *   - The expected exception message thrown by getFileDelimtiers() on failed
   *     request.
   *   - The expected delimiter returned.
   */
  public function provideMimeTypesForFileDelimiterGetter() {
    return [
      // #0
      [
        'test empty string mime type input',
        '',
        TRUE,
        'The getFileDelimiters() getter requires a string of the input file\'s mime-type and must not be empty.',
        FALSE,
      ],

      // #1
      [
        'test tab-separated values mime type (tsv)',
        'text/tab-separated-values',
        FALSE,
        '',
        ["\t"],
      ],

      // #2
      [
        'test comma-separated values mime type (csv)',
        'text/csv',
        FALSE,
        '',
        [','],
      ],

      // #3
      [
        'test tab-separated values mime type (txt)',
        'text/plain',
        FALSE,
        '',
        ["\t", ','],
      ],

      // #4
      [
        'test unsupported mime types (docx)',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        TRUE,
        'Cannot retrieve file delimiters for the mime-type provided: application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        FALSE,
      ],
    ];
  }

  /**
   * Test validator base file delimiter getter method.
   *
   * @param string $scenario
   *   Human-readable text description of the test scenario.
   * @param string $mime_type_input
   *   Mime type input.
   * @param bool $has_exception
   *   Indicates if the test scenario is expected to throw an exception (TRUE)
   *   or not (FALSE).
   * @param string $exception_message
   *   The expected exception message if the test scenario is expected to throw
   *   an exception.
   * @param array|false $expected
   *   The expected returned file delimiter, false if none expected.
   *
   * @dataProvider provideMimeTypesForFileDelimiterGetter
   */
  public function testFileDelimiterGetter(string $scenario, string $mime_type_input, bool $has_exception, string $exception_message, array|false $expected) {

    $exception_caught = FALSE;
    $exception_get_message = '';
    $delimiter = FALSE;

    try {
      $delimiter = ImportValidationHelper::getFileDelimiters($mime_type_input);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_get_message = $e->getMessage();
    }

    $this->assertEquals($exception_caught, $has_exception, 'An exception was expected by file delimiter getter method for scenario:' . $scenario);
    $this->assertStringContainsString(
      $exception_message,
      $exception_get_message,
      'The expected exception message thrown by file delimiter getter does not match message thrown for test scenario: ' . $scenario
    );

    $this->assertEquals($delimiter, $expected, 'Value returned does not match expected value for scenario:' . $scenario);
  }

  /**
   * Data Provider: provides mime types/delimiters to splitRowIntoColumns().
   *
   * @return array
   *   Each test scenario is an array with the following values.
   *   - Mime type input.
   *   - Expected delimiter associated to the mime type provided.
   */
  public function provideMimeTypeDelimiters() {
    $sets = [];

    // #0
    $sets[] = [
      'text/tab-separated-values',
      "\t",
    ];

    // #1
    $sets[] = [
      'text/csv',
      ',',
    ];

    /* Not currently supported since multiple delimiters match this mime-type
    // #2
    $sets[] = [
    'text/plain',
    ','
    ];
     */

    return $sets;
  }

  /**
   * Tests splitRowIntoColumns().
   *
   * @param string $expected_mime_type
   *   Mime type input to the split row method.
   * @param string $expected_delimiter
   *   The expected delimiter that is associated to the mime type according to
   *   the mime type - delimiter mapping array.
   *
   * @dataProvider provideMimeTypeDelimiters
   */
  public function testSplitRowIntoColumns(string $expected_mime_type, string $expected_delimiter) {

    // Create a data row.
    // This line captures data values with quotes and leading/trailing spaces.
    $good_line = $raw_line = [
      'Value A',
      'Value "B"',
      'Value \'C\'',
      'Value D ',
      ' Value E',
      ' Value F ',
      ' Value G           ',
    ];
    // Sanitize the values so that the expected split values would be:
    // Value A, Value B, Value C, Value D, Value E, Value F and Value G.
    foreach ($good_line as &$l) {
      $l = trim(str_replace(['"', '\''], '', $l));
    }

    // At this point our line is sanitized and *sparkling*.
    // Test:
    // 1. Failed to specify a delimiter.
    // 2. Test that delimiter could not split the line.
    // 3. Line values and split values match.
    // 4. Some other delimiter.
    // Unsupported mime type and thus unknown delimiter.
    $delimiter = '~';
    $str_line = implode($delimiter, $raw_line);

    $exception_caught = FALSE;
    $exception_message = '';

    try {
      ImportValidationHelper::splitRowIntoColumns($str_line, 'text/uncertain');
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when no delimiter defined in splitRowIntoColumns().');
    $this->assertStringContainsString(
      'mime type "text/uncertain" passed into splitRowIntoColumns() is not supported', $exception_message,
      'We did not get the expected message when an unknown mime type is passed into splitRowIntoColumns().'
    );

    // Delimiter is not present in the line and could not split the line.
    // This case will return the original line.
    $delimiter = '<not_the_delimiter>';
    $str_line = implode($delimiter, $raw_line);

    $exception_caught = FALSE;
    $exception_message = '';

    try {
      ImportValidationHelper::splitRowIntoColumns($str_line, $expected_mime_type);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when splitRowIntoColumns() could not split line using the delimiter.');
    $this->assertStringContainsString(
      'line provided could not be split into columns', $exception_message,
      'Expected exception message does not match message when splitRowIntoColumns() could not split line using the delimiter.'
    );

    // Test that the sanitized line is the same as the split values.
    $delimiter = $expected_delimiter;
    $str_line = implode($delimiter, $raw_line);
    $values = ImportValidationHelper::splitRowIntoColumns($str_line, $expected_mime_type);
    $this->assertEquals($good_line, $values, 'Line values does not match expected split values.');
  }

  /**
   * Quickly test that mime-types with multiple delimiters are handled.
   */
  public function testSplitRowIntoColumnsMultiDelimiter() {

    $str_line = 'Line does not actually matter here as test/plain is not supported.';
    $expected_mime_type = 'text/plain';
    $expected_exception_message = "We don't currently support splitting mime types with multiple delimiter options";

    $exception_caught = FALSE;
    $exception_message = '';
    try {
      ImportValidationHelper::splitRowIntoColumns($str_line, $expected_mime_type);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when splitRowIntoColumns() could not split line because text/plain has two supported delimiters and we dont yet know how to pick the right one reliably.');
    $this->assertStringContainsString(
      $expected_exception_message,
      $exception_message,
      'Expected exception message does not match message when splitRowIntoColumns() could not split line because there are too many supported delimiters.'
    );
  }

  /**
   * Data Provider for triggering exceptions in checkValidationStatusArray().
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - The validation status array returned by a single validator.
   *     It is expected to contain the following keys:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': An array of items that failed.
   *   - An array of expectations that we want to find in the resulting rendered
   *     output. Each array has the following keys:
   *     - 'expected_exception': TRUE or FALSE if an exception is expected to
   *       occur for this scenario.
   *     - 'expected_errors': The number of expected problems with the array.
   *     - 'expected_details': The details in the message expected to be in the
   *        exception being triggered.
   */
  public function provideFaultyValidationStatusArray() {

    $scenarios = [];

    // #0: 'case' and 'failedItems' keys are missing.
    $scenarios[] = [
      [
        'valid' => FALSE,
        'failed_items' => [
          'item' => 'failed',
        ],
      ],
      [
        'expected_exception' => TRUE,
        'expected_errors' => 1,
        'expected_details' => "Expected to find key(s) 'case', 'failedItems' in the validation result array.",
      ],
    ];

    // #1: 'valid' key has been set to TRUE.
    $scenarios[] = [
      [
        'case' => 'Case 1',
        'valid' => TRUE,
        'failedItems' => [
          'item' => 'failed',
        ],
      ],
      [
        'expected_exception' => TRUE,
        'expected_errors' => 1,
        'expected_details' => "Expected the validation result to contain a value of FALSE for the key 'valid' since it should only reach this point if validation failed.",
      ],
    ];

    // #2: 'failedItems' key contains a value of an empty array.
    $scenarios[] = [
      [
        'case' => 'Case 2',
        'valid' => FALSE,
        'failedItems' => [],
      ],
      [
        'expected_exception' => TRUE,
        'expected_errors' => 1,
        'expected_details' => "Expected the validation result to have content for the key 'failedItems', but it was set to an empty array.",
      ],
    ];

    // #3: 'failedItems' key contains a value of string, not an array.
    $scenarios[] = [
      [
        'case' => 'Case 3',
        'valid' => FALSE,
        'failedItems' => 'item that failed',
      ],
      [
        'expected_exception' => TRUE,
        'expected_errors' => 1,
        'expected_details' => "Expected the validation result to contain an array for the key 'failedItems', but it did not.",
      ],
    ];

    // #4: Trigger 3 problems at a time.
    $scenarios[] = [
      [
        'CASE' => 'Case 4',
        'valid' => TRUE,
        'failedItems' => 'this is a string',
      ],
      [
        'expected_exception' => TRUE,
        'expected_errors' => 3,
        'expected_details' => "Expected to find key(s) 'case' in the validation result array. Expected the validation result to contain a value of FALSE for the key 'valid' since it should only reach this point if validation failed. Expected the validation result to contain an array for the key 'failedItems', but it did not.",
      ],
    ];

    // #5: Validation result array not faulty, no exceptions triggered.
    $scenarios[] = [
      [
        'case' => 'Case 5',
        'valid' => FALSE,
        'failedItems' => [
          'item' => 'failed',
        ],
      ],
      [
        'expected_exception' => FALSE,
        'expected_errors' => 0,
        'expected_details' => 'NONE',
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests the method that checks the integrity of the validation status array.
   *
   * @param array $validation_result
   *   The validation status array returned by a single validator.
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': FALSE to indicate that validation failed.
   *   - 'failedItems': An array of items that failed.
   * @param array $expectations
   *   An array of expectations that we want to find in the resulting rendered
   *   output. Each array has the following keys:
   *   - 'expected_exception': TRUE or FALSE if an exception is expected to
   *     occur for this scenario.
   *   - 'expected_errors': The number of expected problems with the array.
   *   - 'expected_details': The details in the message expected to be in the
   *     exception being triggered.
   *
   * @dataProvider provideFaultyValidationStatusArray
   */
  public function testCheckValidationStatusArray(array $validation_result, array $expectations) {

    $validator_name = 'My Validator';
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      ImportValidationHelper::checkValidationStatusArray($validation_result, $validator_name);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertEquals(
      $expectations['expected_exception'],
      $exception_caught,
      "We expected an exception to be caught for this scenario, but one wasn't thrown.",
    );
    // Check that the exception message is prepended with a message specifying
    // validator name and number of problems.
    if ($expectations['expected_exception']) {
      $this->assertStringStartsWith(
        "ERROR: Found " . $expectations['expected_errors'] . " problem(s) with the validation result array",
        $exception_message,
        "The exception thrown does not contain the number of errors we expected for this scenario.",
      );
      $this->assertStringContainsString(
        "validation result array returned by the $validator_name validator.",
        $exception_message,
        "The exception thrown does not contain the validator name within it for this scenario."
      );
      // Now check for our expected details to be in the message.
      $this->assertStringEndsWith(
        $expectations['expected_details'],
        $exception_message,
        "The exception thrown does not have the message we expected for this scenario.",
      );
    }

    // Now, check the array again but this time provide a line number.
    $line_no = 5;
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      ImportValidationHelper::checkValidationStatusArray(
        $validation_result,
        $validator_name,
        $line_no
      );
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertEquals(
      $expectations['expected_exception'],
      $exception_caught,
      "We expected an exception to be caught for this scenario when a line number was specified, but one wasn't thrown.",
    );
    if ($expectations['expected_exception']) {
      $this->assertStringContainsString(
        "returned by the $validator_name validator at line #5 of the input file.",
        $exception_message,
        "The exception thrown does not contain the correct line number within it for this scenario."
      );
    }
  }

}

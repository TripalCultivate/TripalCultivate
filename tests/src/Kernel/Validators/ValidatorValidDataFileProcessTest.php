<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;

/**
 * Test any message process methods for the ValidDataFile validator.
 *
 * @group trpcultivate
 * @group validators
 */
class ValidatorValidDataFileProcessTest extends ChadoTestKernelBase {

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
   * @var Drupal\trpcultivate\Plugin\Validators\ValidDataFile
   */
  protected $validator_instance;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    $validator_id = 'valid_data_file';
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
   *       - 'case': a developer-focused string describing the case checked.
   *       - 'valid': FALSE to indicate that validation failed.
   *       - 'failedItems': array of items that failed with the following keys.
   *         - 'filename': The provided name of the file.
   *         - 'fid': The fid of the provided file.
   *         - 'mime': The mime type of the input file if it is not supported.
   *         - 'extension': The extension of the input file if not supported.
   *   - An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'project'), and the value is
   *     the new value to be shown for that token.
   *   - An array of expectations that we want to find in the resulting rendered
   *     output which has the following keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 'expected_item_count': The number of failed items expected.
   *     - 'expected_item': The expected failed item.
   */
  public static function provideValidDataFileFailedCases() {

    $tokens = [];

    // #0: An invalid file ID is provided.
    $scenarios[] = [
      [
        'case' => 'Invalid file id number',
        'valid' => FALSE,
        'failedItems' => [
          'fid' => 'wrongid',
        ],
      ],
      $tokens,
      [
        'expected_message' => 'A problem occurred in between uploading the file and submitting it for validation.',
        'expected_item_count' => 0,
      ],
    ];

    // #1: An empty file was provided.
    $filename = 'empty_file.txt';
    $scenarios[] = [
      [
        'case' => 'The file has no data and is an empty file',
        'valid' => FALSE,
        'failedItems' => [
          'filename' => $filename,
          'fid' => 123,
        ],
      ],
      $tokens,
      [
        'expected_message' => 'The file provided has no contents in it to import. Please ensure your file has the expected header row and at least one row of data.',
        'expected_item_count' => 1,
        'expected_item' => 'Filename: ' . $filename,
      ],
    ];

    // #2: The file MIME type is unsupported.
    $mime = 'application/pdf';
    $extension = 'tsv';
    $scenarios[] = [
      [
        'case' => 'Unsupported file MIME type',
        'valid' => FALSE,
        'failedItems' => [
          'mime' => $mime,
          'extension' => $extension,
        ],
      ],
      $tokens,
      [
        'expected_message' => 'The type of file uploaded is not supported by this importer. Please ensure your file has one of the supported file extensions and was saved using software that supports that type of file.',
        'expected_item_count' => 1,
        'expected_item' => "The file extension indicates the file is \"$extension\" but our system detected the file is of type \"$mime\"",
      ],
    ];

    // #3: The data file couldn't be opened.
    $filename = 'unopenable.tsv';
    $scenarios[] = [
      [
        'case' => 'Data file cannot be opened',
        'valid' => FALSE,
        'failedItems' => [
          'filename' => $filename,
          'fid' => 456,
        ],
      ],
      $tokens,
      [
        'expected_message' => 'The file provided could not be opened. Please contact your administrator for help.',
        'expected_item_count' => 1,
        'expected_item' => 'Filename: ' . $filename,
      ],
    ];

    // #4: Test token reversal.
    $filename = 'unopenable.tsv';
    $scenarios[] = [
      [
        'case' => 'Data file cannot be opened',
        'valid' => FALSE,
        'failedItems' => [
          'filename' => $filename,
          'fid' => 456,
        ],
      ],
      [
        'case-locked-file' => 'The File is locked.',
      ],
      [
        'expected_message' => 'The File is locked.',
        'expected_item_count' => 1,
        'expected_item' => 'Filename: ' . $filename,
      ],
    ];

    // #5: Test token reversal - default message.
    $scenarios[] = [
      [
        'case' => 'Invalid file id number',
        'valid' => FALSE,
        'failedItems' => [
          'fid' => 'wrongid',
        ],
      ],
      [
        'case-invalid-fid' => 'Not a valid Drupal File ID.',
      ],
      [
        'expected_message' => 'Not a valid Drupal File ID.',
        'expected_item_count' => 0,
      ],
    ];

    // #6: Test token reversal - token.
    $filename = 'unopenable.tsv';
    $scenarios[] = [
      [
        'case' => 'Data file cannot be opened',
        'valid' => FALSE,
        'failedItems' => [
          'filename' => $filename,
          'fid' => 456,
        ],
      ],
      [
        'contact-admin' => 'contact admin',
      ],
      [
        'expected_message' => 'The file provided could not be opened. Please contact admin for help.',
        'expected_item_count' => 1,
        'expected_item' => 'Filename: ' . $filename,
      ],
    ];

    // #7: Test static tokens - expected item remain unchanged, no replacements.
    $mime = 'application/pdf';
    $extension = 'tsv';
    $scenarios[] = [
      [
        'case' => 'Unsupported file MIME type',
        'valid' => FALSE,
        'failedItems' => [
          'mime' => $mime,
          'extension' => $extension,
        ],
      ],
      [
        'file-mime' => 'a file mime',
        'file-extension' => 'unsupported extension',
      ],
      [
        'expected_message' => 'The type of file uploaded is not supported by this importer. Please ensure your file has one of the supported file extensions and was saved using software that supports that type of file.',
        'expected_item_count' => 1,
        'expected_item' => "The file extension indicates the file is \"$extension\" but our system detected the file is of type \"$mime\"",
      ],
    ];

    return $scenarios;
  }

  /**
   * Tests the message processor method for the ValidDataFile validator.
   *
   * @param array $validation_status
   *   Validation status arrays that get passed to the process method.
   *   It contains the following keys:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': array of items that failed with the following keys.
   *       - 'filename': The provided name of the file.
   *       - 'fid': The fid of the provided file.
   *       - 'mime': The mime type of the input file if it is not supported.
   *       - 'extension': The extension of the input file if not supported.
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *   to the user. The key is the token, (ex. 'project'), and the value is
   *   the new value to be shown for that token.
   * @param array $expectations
   *   An array of expectations that we want to find in the resulting rendered
   *   output which has the following keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 'expected_item_count': The number of failed items expected.
   *     - 'expected_item': The expected failed item.
   *
   * @dataProvider provideValidDataFileFailedCases
   */
  public function testProcessItemWithSimpleList(array $validation_status, array $tokens, array $expectations) {

    // Call the process method on our validation result.
    $render_array = $this->validator_instance::processItemWithSimpleList($validation_status, $tokens);
    // Render the array we were returned.
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the rendered output.
    // First check that we were given the correct message.
    $selected_message_title = $this->cssSelect('div.tc-valid-data-file-failures label');
    $provided_message = (string) $selected_message_title[0];
    $this->assertStringContainsString(
      $expectations['expected_message'],
      $provided_message,
      'The message expected from processing ValidDataFile failures for this scenario did not match the one in the rendered output.'
    );

    // Next, check for expected items.
    $selected_list_items = $this->cssSelect('div.tc-valid-data-file-failures ul li');
    $list_item_count = count($selected_list_items);
    $this->assertEquals($expectations['expected_item_count'], $list_item_count, 'We expected ' . $expectations['expected_item_count'] . ' list items in the render array from processing ValidDataFile failures, but instead found ' . $list_item_count . '.');
    // If this is a case where we expect an item, grab the contents of
    // 'SimpleXMLElement Object' and assert it matches what we expect.
    if (array_key_exists('expected_item', $expectations)) {
      $provided_item = (string) $selected_list_items[0];
      $this->assertEquals($expectations['expected_item'], $provided_item, 'The render array from processing ValidDataFile failures did not contain the expected failed item.');
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
   *         - 'fid': The fid of the provided file.
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

    // #0: ValidDataFile passed.
    $scenarios[] = [
      [
        'case' => 'Data file is valid',
        'valid' => FALSE,
        'failedItems' => [
          'fid' => 100,
        ],
      ],
      $tokens,
      [
        'expected_message' => 'The case string returned by the ValidDataFile validator implies validation passed, but valid is set to FALSE.',
      ],
    ];

    // #1: unrecognizable validation case message.
    $scenarios[] = [
      [
        'case' => 'unrecognizable case',
        'valid' => FALSE,
        'failedItems' => [
          'fid' => 100,
        ],
      ],
      $tokens,
      [
        'expected_message' => 'The case string returned by the ValidDataFile validator is not recognized as a potential case.',
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
   *       - 'fid': The fid of the provided file.
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
  public function testProcessItemWithSimpleListExceptions(array $validation_status, array $tokens, array $expectations) {

    // Test with a passed validation case string.
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->validator_instance::processItemWithSimpleList($validation_status, $tokens);
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

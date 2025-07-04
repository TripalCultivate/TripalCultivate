<?php

namespace Drupal\trpcultivate\Plugin\Validators;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\tripal\Services\TripalLogger;
use Drupal\trpcultivate\Service\ImportValidationHelper;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\FileTypes;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validate data file.
 *
 * @TripalCultivateValidator(
 *   id = "valid_data_file",
 *   validator_name = @Translation("Valid Data File Validator"),
 *   input_types = {"file"}
 * )
 */
class ValidDataFile extends TripalCultivateValidatorBase implements ContainerFactoryPluginInterface {

  /**
   * Validator Traits required by this validator.
   *
   * - FileTypes: Gets an array of all supported MIME types the importer is
   *   configured to process.
   */
  use FileTypes;

  /**
   * A mapping of all of the tokens supported by this validator.
   *
   * @var array
   *   An associative array mapping tokens to their details, such as the
   *   developer case string and the default message to substitute the token.
   *   The following tokens are implemented for this mapping, with the following
   *   descriptions for their 'default-msg' values:
   *   - 'case-invalid-fid': the message when a file id is not a valid value.
   *   - 'case-failed-fid': the message when a file id failed to load a
   *     file object.
   *   - 'case-empty-file': the message when a file has no data or is an
   *     empty file.
   *   - 'case-unsupported-mime': the message when file MIME type is
   *     not supported.
   *   - 'case-unsupported-extension': the message when mime type and extension
   *     are not supported.
   *   - 'case-locked-file': the message when a file could not be opened.
   *   - 'case-contact-admin': the phrase to use when the user needs a
   *     priviledged administrator to fix the problem.
   *
   * @see TripalCultivate/src/TripalCultivateValidator/TripalCultivateValidatorBase::$mapping
   */
  protected static array $mapping = [
    'case-invalid-fid' => [
      'token' => 'case-invalid-fid',
      'dev-case' => 'Invalid file id number',
      'default-msg' => 'A problem occurred in between uploading the file and submitting it for validation. Please try uploading and submitting it again, or [contact-admin] if the problem persists.',
    ],
    'case-failed-fid' => [
      'token' => 'case-failed-fid',
      'dev-case' => 'File id failed to load a file object',
      'default-msg' => 'A problem occurred in between uploading the file and submitting it for validation. Please try uploading and submitting it again, or [contact-admin] if the problem persists.',
    ],
    'case-empty-file' => [
      'token' => 'case-empty-file',
      'dev-case' => 'The file has no data and is an empty file',
      'default-msg' => 'The file provided has no contents in it to import. Please ensure your file has the expected header row and at least one row of data.',
    ],
    'case-unsupported-mime' => [
      'token' => 'case-unsupported-mime',
      'dev-case' => 'Unsupported file MIME type',
      'default-msg' => 'The type of file uploaded is not supported by this importer. Please ensure your file has one of the supported file extensions and was saved using software that supports that type of file. For example, a \'tsv\' file should be saved as such by a spreadsheet editor such as Microsoft Excel.',
    ],
    'case-unsupported-extension' => [
      'token' => 'case-unsupported-extension',
      'dev-case' => 'Unsupported file mime type and unsupported extension',
      'default-msg' => 'The type of file uploaded is not supported by this importer. Please ensure your file has one of the supported file extensions and was saved using software that supports that type of file. For example, a \'tsv\' file should be saved as such by a spreadsheet editor such as Microsoft Excel.',
    ],
    'case-locked-file' => [
      'token' => 'case-locked-file',
      'dev-case' => 'Data file cannot be opened',
      'default-msg' => 'The file provided could not be opened. Please [contact-admin] for help.',
    ],
    'contact-admin' => [
      'token' => 'contact-admin',
      'default-msg' => 'contact your administrator',
    ],
    'case-valid' => [
      'token' => 'case-valid',
      'dev-case' => 'Data file is valid',
    ],
  ];

  /**
   * Entity Type Manager service.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $service_EntityTypeManager;

  /**
   * Tripal logger service.
   *
   * @var \Drupal\tripal\Services\TripalLogger
   */
  protected TripalLogger $service_TripalLogger;

  /**
   * Current user service.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $service_CurrentUser;

  /**
   * Constructs an instance of the ValidDataFile validator.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param Drupal\Core\Entity\EntityTypeManagerInterface $service_EntityTypeManager
   *   The entity type manager service.
   * @param \Drupal\tripal\Services\TripalLogger $service_TripalLogger
   *   Tripal logger service.
   * @param \Drupal\Core\Session\AccountInterface $service_CurrentUser
   *   Current user service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $service_EntityTypeManager,
    TripalLogger $service_TripalLogger,
    AccountInterface $service_CurrentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    // Set services.
    $this->service_EntityTypeManager = $service_EntityTypeManager;
    $this->service_TripalLogger = $service_TripalLogger;
    $this->service_CurrentUser = $service_CurrentUser;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('tripal.logger'),
      $container->get('current_user'),
    );
  }

  /**
   * Validate that the input file is a valid file.
   *
   * Checks include:
   * - File ID parameter is positive non-zero integer, and cannot be null.
   * - Has Drupal File Id number assigned and can be loaded.
   * - File extension and mime type are configured by the importer.
   * - File exists and is not empty.
   * - File can be opened.
   *
   * @param int $fid
   *   The unique identifier (fid) of a file that is managed by
   *   Drupal File System.
   *
   * @return array
   *   An associative array with the following keys.
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': TRUE if the file passes validity checks, FALSE otherwise.
   *   - 'failedItems': an array of items that failed with any of the following
   *      keys. This is an empty array if the data row input was valid.
   *     - 'filename': The provided name of the file.
   *     - 'fid': The fid of the provided file.
   *     - 'mime': The mime type of the input file if it is not supported.
   *     - 'extension': The extension of the input file if not supported.
   */
  public function validateFile(int|null $fid) {

    // Parameter check, verify the file id number is not null, 0 or
    // a negative value.
    if (is_null($fid) || $fid <= 0) {
      return [
        'case' => self::$mapping['case-invalid-fid']['dev-case'],
        'valid' => FALSE,
        'failedItems' => [
          'fid' => $fid,
        ],
      ];
    }

    // Load the file object by fid number.
    $file_object = $this->service_EntityTypeManager
      ->getStorage('file')
      ->load($fid);

    // Check that the file input provided returned a file object.
    if (!$file_object) {
      return [
        'case' => self::$mapping['case-failed-fid']['dev-case'],
        'valid' => FALSE,
        'failedItems' => [
          'fid' => $fid,
        ],
      ];
    }

    // File object loaded successfully. Any subsequent failed checks will
    // reference the filename and file id from the established file object.
    $file_filename = $file_object->getFileName();
    $file_fid = $file_object->id();

    // Check that the file is not blank by inspecting the file size.
    $file_size = $file_object->getSize();
    if (!$file_size) {
      return [
        'case' => self::$mapping['case-empty-file']['dev-case'],
        'valid' => FALSE,
        'failedItems' => [
          'filename' => $file_filename,
          'fid' => $file_fid,
        ],
      ];
    }

    // Check that both the file MIME type and file extension are supported.
    $file_mime_type = $file_object->getMimeType();
    $file_extension = pathinfo($file_filename, PATHINFO_EXTENSION);

    // Get the supported MIME types and file extensions values.
    $supported_file_extensions = $this->getSupportedFileExtensions();
    $supported_mime_types = $this->getSupportedMimeTypes();

    if (!in_array($file_mime_type, $supported_mime_types)) {
      if (in_array($file_extension, $supported_file_extensions)) {
        // The file extension is supported but the MIME type is not.
        return [
          'case' => self::$mapping['case-unsupported-mime']['dev-case'],
          'valid' => FALSE,
          'failedItems' => [
            'mime' => $file_mime_type,
            'extension' => $file_extension,
          ],
        ];
      }
      else {
        // Both MIME type and file extension are not supported.
        return [
          'case' => self::$mapping['case-unsupported-extension']['dev-case'],
          'valid' => FALSE,
          'failedItems' => [
            'mime' => $file_mime_type,
            'extension' => $file_extension,
          ],
        ];
      }
    }

    // Check that the file can be opened.
    $file_uri = $file_object->getFileUri();
    $file_handle = @fopen($file_uri, 'r');

    if (!$file_handle) {
      return [
        'case' => self::$mapping['case-locked-file']['dev-case'],
        'valid' => FALSE,
        'failedItems' => [
          'filename' => $file_filename,
          'fid' => $file_fid,
        ],
      ];
    }

    fclose($file_handle);

    // Validator response values if data file is valid.
    return [
      'case' => self::$mapping['case-valid']['dev-case'],
      'valid' => TRUE,
      'failedItems' => [],
    ];
  }

  /**
   * Processes failed validation from ValidDataFile into a render array.
   *
   * @param array $validation_status
   *   An associative array that stores the validation failures by the
   *   ValidDataFile validator. It is an associative array returned by the
   *   validator. Here is the overall structure:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with one or more of the
   *       following keys:
   *         - 'filename': The provided name of the file.
   *         - 'fid': The fid of the provided file.
   *         - 'mime': The mime type of the input file if it is not supported.
   *         - 'extension': The extension of the input file if not supported.
   *         @see validateFile()
   * @param array $tokens
   *   [OPTIONAL] An array of values to use for token replacement.
   *   @see ValidDataFile::$mapping
   *   The following token keys will substitute the entire existing case message
   *   to the user with the value of that token.
   *     - 'case-invalid-fid': the message when a file id is not a valid value.
   *     - 'case-failed-fid': the message when a file id failed to load a
   *       file object.
   *     - 'case-empty-file': the message when a file has no data or is an
   *       empty file.
   *     - 'case-unsupported-mime': the message when file MIME type is
   *       not supported.
   *     - 'case-unsupported-extension': the message when mime type and
   *       extension are not supported.
   *     - 'case-locked-file': the message when a file could not be opened.
   *     - 'case-contact-admin': the phrase to use when the user needs a
   *       priviledged administrator to fix the problem.
   *
   * @return array
   *   A render array of type unordered list which is used to display feedback
   *   to the user about the case that failed and the failed items from the
   *   input file. The one item in the list is either the filename, as below:
   *     - Filename: $validation_status['failedItems']['filename']
   *       OR it is a message informing the user that their file's extension and
   *       mime type are not compatible.
   *
   * @throws \Exception
   *   - If the $validation_status parameter was not formatted properly.
   *   - If the case string returned by the validator implied validation passed.
   *   - If the case string returned by the validator is not recognized.
   */
  public function processItemWithSimpleList(array $validation_status, array $tokens = []) {

    // Check the format of the validation_status parameter.
    ImportValidationHelper::checkValidationStatusArray($validation_status, 'ValidDataFile');

    // Grab the default messages for all of our tokens (ones with default-msg).
    $default_tokens = array_column(self::$mapping, 'default-msg', 'token');
    // Combine our provided and our default token arrays. Because array_merge
    // will overwrite values in the first array with values from the second
    // array for the same keys, we provide our default tokens first.
    $combined_tokens = array_merge($default_tokens, $tokens);

    // Get the current user in case we trigger a case that needs to log a
    // message to the administrator.
    $username = $this->service_CurrentUser->getAccountName();

    // Define our items array.
    $items = [];

    if (($validation_status['case'] == self::$mapping['case-invalid-fid']['dev-case']) ||
        ($validation_status['case'] == self::$mapping['case-failed-fid']['dev-case'])) {

      $message = $combined_tokens['case-invalid-fid'];
      // Get the fid of the uploaded file.
      $fid = $validation_status['failedItems']['fid'];

      // Log a message for the administrator to help with debugging the issue.
      $this->service_TripalLogger
        ->info("The user $username uploaded a file with FID $fid using the Traits Importer, but could not import it as something is wrong with the filename/FID. More specifically, the case message '" . $validation_status['case'] . "' was reported.");
    }
    elseif ($validation_status['case'] == self::$mapping['case-empty-file']['dev-case']) {
      $message = $combined_tokens['case-empty-file'];
      $items = [
        'Filename: ' . $validation_status['failedItems']['filename'],
      ];
    }
    elseif (($validation_status['case'] == self::$mapping['case-unsupported-mime']['dev-case']) ||
            ($validation_status['case'] == self::$mapping['case-unsupported-extension']['dev-case'])) {

      $message = $combined_tokens['case-unsupported-mime'];
      // Give more info to the user AND log a message to the administrator using
      // these failed items:
      $file_mime = $validation_status['failedItems']['mime'];
      $file_extension = $validation_status['failedItems']['extension'];
      $items = [
        "The file extension indicates the file is \"$file_extension\" but our system detected the file is of type \"$file_mime\"",
      ];

      // Log a message for the administrator to help with debugging the issue.
      $this->service_TripalLogger
        ->info("The user $username uploaded a file to the Traits Importer with file extension \"$file_extension\" and mime type \"$file_mime\"");
    }
    elseif ($validation_status['case'] == self::$mapping['case-locked-file']['dev-case']) {
      $message = $combined_tokens['case-locked-file'];
      $filename = $validation_status['failedItems']['filename'];
      $fid = $validation_status['failedItems']['fid'];
      $items = [
        'Filename: ' . $filename,
      ];

      // Log a message for the administrator to help with debugging the issue.
      $this->service_TripalLogger
        ->info("The user $username uploaded a file with FID $fid using the Traits Importer, but the file could not be opened using \'@fopen\'. Filename was '$filename'.");
    }
    elseif ($validation_status['case'] == self::$mapping['case-valid']['dev-case']) {
      throw new \Exception('The case string returned by the ValidDataFile validator implies validation passed, but valid is set to FALSE.');
    }
    else {
      throw new \Exception('The case string returned by the ValidDataFile validator is not recognized as a potential case.');
    }

    // Now replace any tokens that are in our message or items.
    // We use the Tripal Token Parser service to ensure that more complicated
    // tokens are supported.
    // NOTE: Dependency injection is NOT used since this is a static method.
    $service_TripalTokensParser = \Drupal::service('tripal.token_parser');
    $replaced_message = $service_TripalTokensParser->replaceTokens($message, $combined_tokens);

    // To do - determine if we want to include tokens in the items.
    $items = $service_TripalTokensParser->replaceTokensArray($items, $combined_tokens);

    // Build the render array.
    $render_array = [
      '#type' => 'item',
      '#title' => $replaced_message,
      '#wrapper_attributes' => [
        'class' => [
          'tc-valid-data-file-failures',
        ],
      ],
      'items' => [
        '#theme' => 'item_list',
        '#type' => 'ul',
        '#items' => $items,
      ],
    ];

    return $render_array;
  }

}

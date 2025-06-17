<?php

namespace Drupal\trpcultivate\Plugin\TripalImporter;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal_chado\TripalImporter\ChadoImporterBase;
use Drupal\trpcultivate\Service\ImportValidationHelper;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This importer is to help you manually test generic validators.
 *
 * The validator ValidDelimitedFile has been pre-configured into this importer
 * as an example and for immediate test implementation.
 *
 * It is purposely simple and generic. To use it:
 *
 * 1. Copy this class to the src/Plugin/TripalImporter directory in your docker.
 * This may need to be created. DO NOT COMMIT modifications to this file.
 *
 * 2. Clear the cache and confirm this importer now shows up in the site under
 * Tripal > Data Loaders as "Tripal Cultivate: Test Importer".
 *
 * 3. Add configuration for your validator below the block titled
 * "CONFIGURE VALIDATOR HERE".
 *
 * 4. Call the validate method for your validator below the appropriate
 * "CALL VALIDATE" block depending on the input type.
 *
 * 5. Call the process message method for your validator below the
 * "CALL PROCESS MESSAGE" block.
 *
 * 6. Save these changes and clear the cache to see them reflected in
 * the webpage. Make sure to attach this edited file to your PR within your
 * testing instructions.
 *
 * NOTE: do not edit annotations.
 *
 * @TripalImporter(
 *   id = "trpcultivate-test-importer",
 *   label = @Translation("Tripal Cultivate: Test Importer"),
 *   description = @Translation("A Tripal Importer used to test APIs."),
 *   file_types = {"tsv"},
 *   upload_description = @Translation("Please provide a data file."),
 *   upload_title = @Translation("Import data file*"),
 *   use_analysis = FALSE,
 *   require_analysis = FALSE,
 *   use_button = True,
 *   submit_disabled = FALSE,
 *   button_text = "Import",
 *   file_upload = TRUE,
 *   file_local = FALSE,
 *   file_remote = FALSE,
 *   file_required = TRUE,
 *   cardinality = 1,
 *   menu_path = "",
 *   callback = "",
 *   callback_module = "",
 *   callback_path = "",
 * )
 */
class TestTripalImporter extends ChadoImporterBase implements ContainerFactoryPluginInterface {

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
  private array $headers = [
    [
      'name' => 'Header 1',
      'description' => 'The header 1',
      'type' => 'required',
    ],
    [
      'name' => 'Header 2',
      'description' => 'The header 2',
      'type' => 'required',
    ],
    [
      'name' => 'Header 3',
      'description' => 'The header 3',
      'type' => 'required',
    ],

    // Add more headers here.
  ];

  /**
   * The key to reference the validation result array in Drupal storage system.
   *
   * @var string
   */
  private const VALIDATION_RESULT = 'validation_result';

  /**
   * The Drupal Messenger Service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $service_Messenger;

  /**
   * The Entity Type Manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected EntityTypeManager $service_entityTypeManager;

  /**
   * The TripalCultivate validator plugin manager.
   *
   * @var \Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager
   */
  protected TripalCultivateValidatorManager $service_validatorPluginManager;

  /**
   * Constructs the Phenotypes Share importer.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
   * @param Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager $service_validatorPluginManager
   *   The TripalCultivate validator plugin manager.
   * @param Drupal\Core\Entity\EntityTypeManager $service_entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The Drupal messenger service.
   */
  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    ChadoConnection $chado_connection,
    TripalCultivateValidatorManager $service_validatorPluginManager,
    EntityTypeManager $service_entityTypeManager,
    MessengerInterface $messenger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $chado_connection);

    // This test importer is for testing purposes only and is accessible only
    // through a localhost.
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== 0) {
      $home = Url::fromRoute('<front>')->toString();

      $redirect = new RedirectResponse($home);
      $redirect->send();
      exit();
    }

    $this->service_validatorPluginManager = $service_validatorPluginManager;
    $this->service_entityTypeManager = $service_entityTypeManager;
    $this->service_Messenger = $messenger;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tripal_chado.database'),
      $container->get('plugin.manager.trpcultivate_validator'),
      $container->get('entity_type.manager'),
      $container->get('messenger'),
    );
  }

  /**
   * {@inheritDoc}
   *
   * THIS METHOD IS REQUIRED!
   */
  public function configureValidators(array $form_values, string $file_mime_type) {

    $validators = [];

    // CONFIGURE VALIDATOR HERE.
    // ..................................................
    // SEE src/Plugins/Validators for VALIDATOR IDs.
    // SEE TripalCultivateValidator/ValidatorTraits for setters and getters.
    //
    // CONFIGURATION TEMPLATE:
    // $id = 'REPLACE WITH VALIDATOR ID ANNOTATION - @id';
    $id = 'valid_delimited_file';

    // $input_type = 'REPLACE WITH VALIDATOR INPUT TYPE - @input_types';
    $input_type = 'raw-row';

    $instance = $this->service_validatorPluginManager->createInstance($id);
    //
    // Use relevant setters to set some values.
    // For example:
    // $instance->setExpectedColumns(count($this->headers), TRUE);
    // $instance->setFileMimeType($file_mime_type);
    // $instance->setHeaders($this->headers);
    // $instance->setIndices(1);
    //
    $instance->setExpectedColumns(count($this->headers), TRUE);
    $instance->setFileMimeType($file_mime_type);

    // Register the validator instance.
    $validators[$input_type][$id] = $instance;

    return $validators;
  }

  /**
   * {@inheritDoc}
   *
   * THIS METHOD IS REQUIRED!
   */
  public function processValidationMessages($failures) {

    $messages = [];
    // CALL PROCESS MESSAGE HERE.
    // ..................................................
    // SEE src/Plugins/Validators for VALIDATOR IDs.
    //
    // PROCESS MESSAGE TEMPLATE:
    // 1: Create a $messages entry.
    // $id = 'REPLACE WITH VALIDATOR ID ANNOTATION - @id';
    $id = 'valid_delimited_file';

    // Use title key to set a validator case message in $messages array.
    // $messages[$id] = ['title' => 'REPLACE WITH A MESSAGE TITLE', ...];.
    $messages[$id] = [
      'title' => 'File is delimited',
      'status' => 'todo',
      'details' => '',
    ];

    // 2: Inspect validator-specific $failures and switch status accordingly.
    if (array_key_exists($id, $failures)) {
      if (!empty($failures[$id])) {
        $messages[$id]['status'] = 'fail';
        // Use the message processor static method defined by the validator.
        // $messages[$id]['details']='ValidatorClassName::processMessageName()';
        //
        // or if the method is defined in this class, call the method as shown
        // in the following line.
        $messages[$id]['details'] = $this->processValidDelimitedFileFailures($failures[$id]);
      }
      else {
        $messages[$id]['status'] = 'pass';
      }
    }

    return $messages;
  }

  /**
   * {@inheritdoc}
   *
   * THIS METHOD IS REQUIRED!
   */
  public function formValidate($form, &$form_state) {

    $form_values = $form_state->getValues();

    $file_id = $form_values['file_upload'];
    $file = $this->service_entityTypeManager->getStorage('file')->load($file_id);
    $file_mime_type = $file->getMimeType();

    $validators = $this->configureValidators($form_values, $file_mime_type);

    $failed_validator = FALSE;

    $failures = [];

    // ************************************************************************
    // Metadata Validation
    // ************************************************************************
    if (isset($validators['metadata'])) {
      foreach ($validators['metadata'] as $validator_name => $validator) {
        $failures[$validator_name] = [];
        $result = $validator->validateMetadata($form_values);

        if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
          $failed_validator = TRUE;
          $failures[$validator_name] = $result;
        }
      }
    }

    if ($failed_validator === FALSE && isset($validators['file'])) {
      // **********************************************************************
      // File Validation
      // **********************************************************************
      foreach ($validators['file'] as $validator_name => $validator) {
        $failures[$validator_name] = [];
        $result = $validator->validateFile($file_id);

        if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
          $failed_validator = TRUE;
          $failures[$validator_name] = $result;
        }
      }
    }

    if ($failed_validator === FALSE) {

      $file_uri = $file->getFileUri();
      $handle = fopen($file_uri, 'r');

      $line_no = 0;

      while (!feof($handle)) {
        $row_has_failed = FALSE;

        $line = fgets($handle);
        $line_no++;
        if (empty(trim($line))) {
          continue;
        }

        // ********************************************************************
        // Raw Row Validation
        // ********************************************************************
        if (isset($validators['raw-row'])) {
          foreach ($validators['raw-row'] as $validator_name => $validator) {
            if (!array_key_exists($validator_name, $failures)) {
              $failures[$validator_name] = [];
            }

            $result = $validator->validateRawRow($line);

            if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
              $row_has_failed = TRUE;
              $failures[$validator_name][$line_no] = $result;
            }
          }
        }

        if ($row_has_failed === TRUE) {
          $failed_validator = TRUE;
          continue;
        }

        // ********************************************************************
        // Header Row Validation
        // ********************************************************************
        if ($line_no == 1 && isset($validators['header-row'])) {
          $header_row = ImportValidationHelper::splitRowIntoColumns($line, $file_mime_type);

          foreach ($validators['header-row'] as $validator_name => $validator) {
            if (!array_key_exists($validator_name, $failures)) {
              $failures[$validator_name] = [];
            }

            $result = $validator->validateRow($header_row);

            if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
              $row_has_failed = TRUE;
              $failures[$validator_name] = $result;
            }
          }

          // If any header-row validators failed, skip validation of the data
          // rows.
          if ($row_has_failed === TRUE) {
            $failed_validator = TRUE;
            break;
          }
        }

        // ********************************************************************
        // Data Row Validation
        // ********************************************************************
        elseif ($line_no > 1 && isset($validators['data-row'])) {
          $data_row = ImportValidationHelper::splitRowIntoColumns($line, $file_mime_type);

          foreach ($validators['data-row'] as $validator_name => $validator) {
            if (!array_key_exists($validator_name, $failures)) {
              $failures[$validator_name] = [];
            }

            $result = $validator->validateRow($data_row);
            if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
              $row_has_failed = TRUE;
              $failed_validator = TRUE;
              $failures[$validator_name][$line_no] = $result;
            }
          }
        }
      }

      fclose($handle);
    }

    $validation_feedback = $this->processValidationMessages($failures);

    $storage = $form_state->getStorage();
    $storage[self::VALIDATION_RESULT] = $validation_feedback;
    $form_state->setStorage($storage);

    $submit_form = TRUE;

    foreach ($validation_feedback as $feedback_item) {
      if ($feedback_item['status'] == 'todo' || $feedback_item['status'] == 'fail') {
        $submit_form = FALSE;

        break;
      }
    }

    if ($submit_form === FALSE) {
      $this->service_Messenger
        ->addError('Your file import was not successful. Please check the Validation Result Window for errors and try again.');

      $form_state->setRebuild(TRUE);
    }
  }

  /**
   * {@inheritDoc}
   *
   * DO NOT MODIFY.
   */
  public function form($form, &$form_state) {

    $form = parent::form($form, $form_state);
    $this->service_Messenger->addWarning('THIS IMPORTER IS FOR TESTING PURPOSES ONLY.');

    $storage = $form_state->getStorage();
    if (isset($storage[self::VALIDATION_RESULT])) {
      $validation_result = $storage[self::VALIDATION_RESULT];

      $form['validation_result'] = [
        '#type' => 'inline_template',
        '#theme' => 'validation_result_window',
        '#data' => [
          'validation_result' => $validation_result,
        ],
        '#weight' => -100,
      ];
    }

    $form['file']['file_upload_existing']['#access'] = FALSE;

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * DO NOT MODIFY.
   */
  public function formSubmit($form, &$form_state) {

    // Display successful message to user if file import was without any error.
    $this->service_Messenger
      ->addStatus('<b>Your file import was successful and a Job Process Request has been created to securely save your data.</b>');
  }

  /**
   * {@inheritDoc}
   *
   * DO NOT MODIFY.
   */
  public function run() {}

  /**
   * {@inheritdoc}
   *
   * DO NOT MODIFY.
   */
  public function postRun() {}

  /**
   * Valid delimited file process message.
   *
   * REMOVE IF NOT REQUIRED.
   *
   * @param array $failures
   *   Failures array.
   *
   * @return array
   *   A render array.
   */
  public function processValidDelimitedFileFailures(array $failures) {

    // Define our table headers.
    $table_header = ['Line Number', 'Line Contents'];

    // For this validator there can be up to 2 tables:
    // - 'table'->'unsupported': Empty rows or no supported delimiters present.
    // - 'table'->'delimited': Rows that don't delimit to the expected number of
    //   columns.
    $table = [];

    // Loop through each row in the $failures array and piece apart the
    // different cases into different tables.
    foreach ($failures as $line_no => $validation_result) {
      // Check the format of the validation_result parameter.
      ImportValidationHelper::checkValidationStatusArray($validation_result, 'ValidDelimitedFile', $line_no);
      // Keeps track of which table this one line's validation result gets added
      // to based on the case it triggered.
      $table_case = '';
      if (($validation_result['case'] == 'Raw row is empty') ||
          ($validation_result['case'] == 'None of the delimiters supported by the file type was used')) {
        $table_case = 'unsupported';
      }
      elseif (($validation_result['case'] == 'Raw row exceeds number of strict columns') ||
            ($validation_result['case'] == 'Raw row has insufficient number of columns')) {
        $table_case = 'delimited';
        if (!isset($num_expected_columns)) {
          $num_expected_columns = $validation_result['failedItems']['expected_columns'];
          $strict = $validation_result['failedItems']['strict'];
        }
      }
      elseif (($validation_result['case'] == 'Raw row has expected number of columns') ||
             ($validation_result['case'] == 'Raw row is delimited')) {
        throw new \Exception("The case string returned by the ValidDelimitedFile validator at line #$line_no implies validation passed, but valid is set to FALSE.");
      }
      else {
        throw new \Exception("The case string returned by the ValidDelimitedFile validator at line #$line_no is not recognized as a potential case.");
      }

      // Checked all cases, now add a row to our appropriate table.
      if (!array_key_exists($table_case, $table)) {
        // Declare the array storing rows for this table, if not already.
        $table[$table_case]['rows'] = [];
      }
      $table[$table_case]['rows'][] = [
        $line_no,
        $validation_result['failedItems']['raw_row'],
      ];
    }
    // Check which tables were created, and assign the correct message.
    // Note that both tables can exist at the same time.
    if (array_key_exists('unsupported', $table)) {
      $table['unsupported']['message'] = 'The following lines in the input file do not contain a valid delimiter supported by this importer.';
    }
    if (array_key_exists('delimited', $table)) {
      // Check if number of columns is strict, then set the message accordingly.
      if ($strict) {
        $strict_or_min = 'strict';
      }
      else {
        $strict_or_min = 'minimum';
      }
      $message = "This importer requires a $strict_or_min number of $num_expected_columns columns for each line. The following lines do not contain the expected number of columns.";
      $table['delimited']['message'] = $message;
    }

    // Finally, loop through our tables and build our render array.
    $tables = [];
    foreach ($table as $table_key => $table_case) {
      $tables[] = [
        [
          '#prefix' => '<div class="case-message case-' . $table_key . '">',
          '#markup' => $table_case['message'],
          '#suffix' => '</div>',
        ],
        [
          '#type' => 'table',
          '#header' => $table_header,
          '#attributes' => [
            'class' => [
              'tcp-raw-row',
              'table-case-' . $table_key,
            ],
          ],
          '#rows' => $table_case['rows'],
        ],
      ];
    }

    $render_array = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#attributes' => [
        'class' => [
          'tcp-valid-delimited-file-failures',
        ],
      ],
      '#items' => $tables,
    ];

    return $render_array;
  }

}

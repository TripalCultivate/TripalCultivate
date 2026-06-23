<?php

namespace Drupal\trpcultivate\Plugin\TripalImporter;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Url;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal_chado\TripalImporter\ChadoImporterBase;
use Drupal\trpcultivate\Plugin\Validators\ValidOrganism;
use Drupal\trpcultivate\Service\ImportValidationHelper;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\tripal\TripalImporter\Attribute\TripalImporter;
use Drupal\tripal_chado\Controller\ChadoOrganismFormElementController;

/**
 * This importer is to test Valid Organism Validator metadata.
 */
#[TripalImporter(
   id: 'trpcultivate-test-importer',
   label: new TranslatableMarkup('Tripal Cultivate: Test Importer'),
   description: new TranslatableMarkup('A Tripal Importer used to test APIs.'),
   file_types: ['tsv'],
   upload_description: new TranslatableMarkup('Please provide a data file.'),
   upload_title: new TranslatableMarkup('Import data file*'),
   use_analysis: FALSE,
   require_analysis: FALSE,
   use_button: TRUE,
   submit_disabled: FALSE,
   button_text: new TranslatableMarkup('Import'),
   file_upload: TRUE,
   file_local: FALSE,
   file_remote: FALSE,
   file_required: TRUE,
   cardinality: 1,
   menu_path: '',
   callback: '',
   callback_path: '',
  )]
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
      'name' => 'Germplasm Name',
      'description' => 'The header 1',
      'type' => 'required',
    ],
    [
      'name' => 'Organism one',
      'description' => 'The header 2',
      'type' => 'required',
    ],
    [
      'name' => 'Organism two',
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
    $id = 'valid_organism';

    // $input_type = 'REPLACE WITH VALIDATOR INPUT TYPE - @input_types';
    $input_type = 'metadata';

    $instance = $this->service_validatorPluginManager->createInstance($id);
    $instance->setInputType($input_type);

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
    $id = 'valid_organism';

    // METADATA
    // 2: Set the metadata array for each validator that requires it.
    // NOTE: Make sure this aligns with values set in configureValidators().
    $metadata = [
      'input_type' => 'metadata',
    ];

    // Use title key to set the default validator case message in $messages.
    $messages[$id] = [
      'title' => 'Organism(s) exist(s) in the database',
      'status' => 'todo',
      'details' => '',
    ];

    $tokens = [];

    // Inspect validator-specific $failures and switch status accordingly.
    if (array_key_exists($id, $failures)) {
      if (!empty($failures[$id])) {
        $messages[$id]['status'] = 'fail';
        $messages[$id]['details'] = ValidOrganism::processItemWithSimpleList($failures[$id], $metadata, $tokens);
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
   * DO NOT MODIFY.
   */
  public function formValidate($form, &$form_state) {
    $form_values = $form_state->getValues();

    $file_id = $form_values['file_upload'];
    $file = $this->service_entityTypeManager->getStorage('file')->load($file_id);
    $file_mime_type = $file->getMimeType();

    $validators = $this->configureValidators($form_values, $file_mime_type);

    $failures = [];

    // ************************************************************************
    // Metadata Validation
    // ************************************************************************
    foreach ($validators['metadata'] as $validator_name => $validator) {
      // Set failures for this validator name to an empty array to signal that
      // this validator has been run.
      $failures[$validator_name] = [];
      // Validate metadata input value.
      $result = $validator->validateMetadata($form_values);

      // Check if validation failed and save the results if it did.
      if (array_key_exists('valid', $result) && $result['valid'] === FALSE) {
        $failures[$validator_name] = $result;
      }
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

    // Field Organism:
    // Prepare select options with only active organisms.
    $all_organisms = ChadoOrganismFormElementController::getSelectOptions([]);

    // If there is only one organism, it should be the default.
    $default_organism = 0;
    if ($all_organisms && count($all_organisms) == 1) {
      $default_organism = array_keys($all_organisms)[0];
    }

    // Field organism.
    $form['organism'] = [
      '#type' => 'select',
      '#title' => 'Organism',
      '#empty_option' => '- Select -',
      '#options' => $all_organisms,
      '#default_value' => $default_organism,
      '#weight' => -99,
      '#required' => TRUE,
    ];

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

}

<?php

namespace Drupal\trpcultivate\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\File\FileSystem;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\tripal_chado\Services\ChadoTermsInit;
use Drupal\tripal\Services\TripalEntityTypeCollection;
use Drupal\tripal\Services\TripalFieldCollection;
use Drupal\tripal_layout\Controller\TripalEntityUILayoutController;

/**
 * Service class for setting up the Tripal Cultivate Module.
 */
class SetupModuleService {

  /**
   * The Drupal FileSystem service.
   *
   * @var Drupal\Core\File\FileSystem
   */
  protected $file_system;

  /**
   * The entity type manager.
   *
   * @var Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The Tripal Chado terms init service.
   *
   * @var Drupal\tripal_chado\Services\ChadoTermsInit
   */
  protected ChadoTermsInit $terms_init;

  /**
   * The Tripal entity type collection.
   *
   * @var Drupal\tripal\Services\TripalEntityTypeCollection
   */
  protected TripalEntityTypeCollection $entityTypeCollection;

  /**
   * The Tripal field collection.
   *
   * @var Drupal\tripal\Services\TripalFieldCollection
   */
  protected TripalFieldCollection $fieldCollection;

  /**
   * The Drupal extension list service.
   *
   * @var Drupal\Core\Extension\ModuleExtensionList
   */
  protected $module_extension_list;

  /**
   * Constructor for the service.
   *
   * @param Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param Drupal\tripal_chado\Services\ChadoTermsInit $terms_init
   *   The Tripal Chado terms init service.
   * @param Drupal\tripal\Services\TripalEntityTypeCollection $entityTypeCollection
   *   The Tripal entity type collection.
   * @param Drupal\tripal\Services\TripalFieldCollection $fieldCollection
   *   The Tripal field collection.
   * @param Drupal\Core\File\FileSystem $file_system
   *   The Drupal FileSystem service.
   * @param Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The drupal module extension list service.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ChadoTermsInit $terms_init,
    TripalEntityTypeCollection $entityTypeCollection,
    TripalFieldCollection $fieldCollection,
    FileSystem $file_system,
    ModuleExtensionList $module_extension_list,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->terms_init = $terms_init;
    $this->entityTypeCollection = $entityTypeCollection;
    $this->fieldCollection = $fieldCollection;
    $this->file_system = $file_system;
    $this->module_extension_list = $module_extension_list;
  }

  /**
   * Insert terms needed by this module into the default chado instance.
   */
  public function installTerms() {
    $config_id = 'trpcultivate_terms';

    $this->terms_init->installTerms($config_id);
  }

  /**
   * Import content type collections needed by this module.
   */
  public function importContenttypes() {
    $collections = [
      'trpcultivate_experiments',
    ];

    // Import the content types.
    $this->entityTypeCollection->install($collections);

    // Import the fields.
    $this->fieldCollection->install($collections);

    // Set the content for the Markup fields based on the twig files.
    // @see templates/markup_field
    $this->createMarkupFields();

    // Create Drupal Reference (i.e. relationship) fields.
    $this->createRelationshipFields();

    // Apply Layout and tweak as needed.
    $this->applyLayout();
  }

  /**
   * Create Markup fields based on the twig files in /templates/markup_field.
   */
  public function createMarkupFields() {

    $field_id_prefix = 'instruct_exp_';

    // Get a list of all the files in /templates/markup_field.
    $directory = $this->module_extension_list->getPath('trpcultivate') . '/templates/markup_field';
    if (is_dir($directory)) {
      $files = $this->file_system->scanDirectory($directory, '/.*.twig/');
      foreach ($files as $f) {
        [$content_type, $field_id] = explode('.', $f->name);
        if (!empty($field_id)) {
          $field_id = $field_id_prefix . $field_id;
          $full_file_path = DRUPAL_ROOT . '/' . $f->uri;

          // Set the markup value to render full HTML on contents of the file.
          // This render array will be rendered by the Markup field.
          $markup_value = [
            'value' => file_get_contents($full_file_path),
            'format' => 'full_html',
          ];
          // Load the field for this file based on the naming pattern.
          // It should exist already by including it in tripalfieldcollection.
          $field = FieldConfig::loadByName('tripal_entity', $content_type, $field_id);
          if (empty($field)) {
            throw new \Exception("Expected field with id $field_id on content type $content_type to already exist. Please ensure it is included in the tripalfieldcollection.");
          }
          else {
            $field->setSetting('markup', $markup_value);
            $field->save();
          }
        }
      }
    }
  }

  /**
   * Create Drupal Reference (i.e. relationship) fields.
   *
   * @todo replace with Chado Relationships once widget/field is available.
   */
  public function createRelationshipFields() {

    /**
     * @var array
     * An array describing the relationship fields we need to create.
     * The first level of the array has the key being the content type to create
     * the fields on and an array of fields to create.
     *
     * The array of fields to create has the key being the id of the field
     * and the value is an array describing the field to create:
     * - target: an array of existing content types that are the target of this
     *   field or more specifically, whose content will populate the drop down.
     * - label: the title of the field.
     * - description: the field description/help text.
     */
    $relationships_needed = [
      'research_experiment' => [
        'field_grant' => [
          'target' => [
            'research_grant' => 'research_grant',
          ],
          'label' => 'Funding Grant',
          'description' => 'Indicate all grants which provide funding for this experiment. You may need to confirm this information with your PI.',
          'termIdSpace' => 'OBI',
          'termAccession' => '0001636',
        ],
        'field_study' => [
          'target' => [
            'research_study' => 'research_study',
          ],
          'label' => 'Parent Research Study',
          'description' => 'Research studies are meant to answer broader questions and will often use multiple experiments in order to do that. You should indicate the research studies that this experiment was designed to be used for.',
          'termIdSpace' => 'SIO',
          'termAccession' => '001066',
        ],
      ],
      'research_study' => [
        'field_grant' => [
          'target' => [
            'research_grant' => 'research_grant',
          ],
          'label' => 'Funding Grant',
          'description' => 'Indicate all grants which provide funding for this experiment. You may need to confirm this information with your PI.',
          'termIdSpace' => 'OBI',
          'termAccession' => '0001636',
        ],
      ],
    ];

    foreach ($relationships_needed as $base_content_type => $fields2create) {
      foreach ($fields2create as $field_id => $field_details) {

        $field_storage = FieldStorageConfig::loadByName('tripal_entity', $field_id);
        if (!$field_storage) {
          FieldStorageConfig::create([
            'field_name' => $field_id,
            'entity_type' => 'tripal_entity',
            'type' => 'entity_reference',
            'cardinality' => -1,
            'settings' => [
              'target_type' => 'tripal_entity',
            ],
          ])->save();
        }

        $field = FieldConfig::loadByName('tripal_entity', $base_content_type, $field_id);
        if (!$field) {
          $field = FieldConfig::create([
            'field_name' => $field_id,
            'entity_type' => 'tripal_entity',
            'bundle' => $base_content_type,
            'label' => $field_details['label'],
            'description' => $field_details['description'],
            'cardinality' => -1,
            'settings' => [
              'handler' => 'default:tripal_entity',
              'handler_settings' => [
                'target_bundles' => $field_details['target'],
                'sort' => [
                  'field' => 'title',
                  'direction' => 'ASC',
                ],
                'auto_create' => FALSE,
              ],
            ],
          ]);
        }

        // Set the cvterm.
        $field->setThirdPartySetting('tripal', 'termIdSpace', $field_details['termIdSpace']);
        $field->setThirdPartySetting('tripal', 'termAccession', $field_details['termAccession']);
        $field->save();
      }
    }

  }

  /**
   * Automatically apply the Tripal Layouts and tweak as needed.
   */
  public function applyLayout() {
    $bundle = 'research_experiment';
    $research_experiment = $this->entityTypeManager->getStorage('tripal_entity_type')->load($bundle);

    // Automatically apply both layouts.
    $controller = new TripalEntityUILayoutController();
    $controller->applyViewLayout($research_experiment);
    $controller->applyFormLayout($research_experiment);

    // Now modify the form display.
    $config_entity_storage = $this->entityTypeManager->getStorage('entity_form_display');
    $display = $config_entity_storage->load('tripal_entity.' . $bundle . '.default');

    // -- Ensure only the objectives vertical tab is open by default.
    $field_groups = $display->getThirdPartySettings('field_group');
    foreach ($field_groups as $id => $group) {
      if ($group['format_type'] === 'tab' && $id !== 'exp-tab-objectives') {
        $group['format_settings']['formatter'] = 'closed';
        $display->setThirdPartySetting('field_group', $id, $group);
      }
    }

    // -- Set a number of properties to use the "Short Text" widget.
    $property_fields = ['exp_featureofinterest', 'exp_germgenus',
      'exp_germspecies', 'exp_germcollection', 'exp_site_locations',
      'exp_timepoints', 'exp_pot_growingmedia', 'exp_bchem_technique',
    ];
    foreach ($property_fields as $component_name) {
      $options = $display->getComponent($component_name);
      $options['type'] = 'chado_property_string_widget_default';
      $options['settings'] = [];
      $display->setComponent($component_name, $options);
    }

    // -- Set a number of properties to be smaller with no format toolbar.
    $property_fields = ['exp_objectives', 'exp_hypothesis'];
    foreach ($property_fields as $component_name) {
      $options = $display->getComponent($component_name);
      $options['settings']['filter_format'] = 'plain_text';
      $options['settings']['num_rows'] = 2;
      $display->setComponent($component_name, $options);
    }

    // -- Expand rows for a few longer description fields.
    $fields = ['exp_design', 'exp_pheno_method', 'exp_bchem_assayprotocol'];
    foreach ($fields as $component_name) {
      $options = $display->getComponent($component_name);
      $options['settings']['num_rows'] = 6;
      $display->setComponent($component_name, $options);
    }

    // -- Finally save it.
    $display->save();

    // Setup field funder in research study content type.
    $bundle = 'research_study';
    $research_study = $this->entityTypeManager->getStorage('tripal_entity_type')->load($bundle);

    // Automatically apply to form layout.
    $controller->applyFormLayout($research_study);
  }

  /**
   * Runs all setup tasks for this module.
   *
   * Expected to be run by a Tripal Job.
   */
  public static function runSetupModuleTripalJob($job_id) {

    // Get the service.
    $service = \Drupal::service('trpcultivate.setup_module_service');

    // Submit job to install terms needed by this module.
    $service->installTerms();

    // Submit job to import content types and fields used by this module.
    $service->importContenttypes();
  }

}

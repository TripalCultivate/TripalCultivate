<?php

namespace Drupal\Tests\trpcultivate\Kernel\Service;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the SetupModuleService class.
 */
#[RunTestsInSeparateProcesses]
class SetupModuleServiceTest extends ChadoTestKernelBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'path',
    'path_alias',
    'views',
    'field',
    'file',
    'field_ui',
    'field_group',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
  ];

  /**
   * The service.
   *
   * @var \Drupal\trpcultivate\Service\SetupModuleService
   */
  protected $setupService;

  /**
   * Sets up the test.
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Add any schema needed for the functionality I am testing.
    $this->prepareEnvironment(['TripalEntity', 'TripalTerm']);

    $this->installConfig(['trpcultivate']);

    $this->installSchema('tripal', ['tripal_jobs', 'tripal_collection']);
    $this->installConfig('tripal_chado');
    // ... we need the layout entities for our content types.
    $this->installEntitySchema('tripal_layout_default_form');
    $this->installEntitySchema('tripal_layout_default_view');

    // Initialize the chado instance with all the records
    // that would be present after running prepare.
    $this->chado_connection = $this->getTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);

    // ... we need our own modules config.
    $this->setupService = \Drupal::service('trpcultivate.setup_module_service');
  }

  /**
   * Test the intallTerms method and installContentTypes method.
   */
  public function testInstallMethods() {

    // Install terms defined in tripal.
    $terms_setup = \Drupal::service('tripal_chado.terms_init');
    $terms_setup->installTerms();

    // Install the terms defined in this module.
    $this->setupService->installTerms();

    // Check if the terms are installed properly.
    $idsmanager = \Drupal::service('tripal.collection_plugin_manager.idspace');
    $idSpace = $idsmanager->loadCollection('local');
    $term_id = $idSpace->getTerm('project_germcollection');
    $this->assertNotNull($term_id, 'The terms are not installed properly.');

    // Import the content types defined in this module.
    $this->setupService->importContenttypes();

    // Test if the content types are imported successfully.
    $entity_type_manager = \Drupal::entityTypeManager();
    $entity_type = $entity_type_manager->getStorage('tripal_entity_type')->load('research_experiment');

    $this->assertNotNull($entity_type, 'The research experiment content type was not installed successfully.');

    // Test if the field types are imported successfully.
    $field_manager = \Drupal::service('entity_field.manager');
    $field_defs = $field_manager->getFieldDefinitions('tripal_entity', 'research_experiment');

    $fields = [
      'exp_objectives',
      'exp_hypothesis',
      'exp_description',
      'exp_dataset',
      'exp_research_outputs',
      'exp_pub',
      'instruct_exp_addtmetadata',
      'exp_featureofinterest',
      'exp_germgenus',
      'exp_germspecies',
      'exp_germcollection',
      'exp_datacollector',
      'exp_custodian',
      'exp_curator',
      'exp_org',
      'exp_pheno_method',
      'exp_site_locations',
      'exp_design',
      'exp_timepoints',
      'exp_pot_growingmedia',
      'exp_bchem_technique',
      'exp_bchem_assayprotocol',
    ];

    foreach ($fields as $field_id) {
      $this->assertArrayHasKey($field_id, $field_defs, "The field $field_id was not created successfully.");
    }
  }

}

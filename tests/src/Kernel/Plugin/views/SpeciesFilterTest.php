<?php

namespace Drupal\Tests\trpcultivate\Kernel\Plugin\views;

use Drupal\Core\Form\FormState;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\views\Views;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\Tests\ViewResultAssertionTrait;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal\Entity\TripalEntityType;

/**
 * Tests the views species filter.
 */
#[RunTestsInSeparateProcesses]
class SpeciesFilterTest extends ChadoTestKernelBase {

  use UserCreationTrait;
  use ViewResultAssertionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'path',
    'path_alias',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'views',
    'field',
    'trpcultivate',
    'trpcultivate_test_views',
  ];

  /**
   * The database connection to the test chado.
   *
   * @var Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * An array of test organisms created.
   *
   * @var array
   */
  protected array $organisms;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Ensure we install the schema/modules we need.
    $this->prepareEnvironment(['TripalTerm', 'TripalEntity']);
    // -- additionally we need tripal_chado config to access the yaml files.
    // Install module configuration.
    $this->installConfig(['tripal_chado', 'trpcultivate']);
    $this->installConfig(['trpcultivate_test_views']);

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    // Create some test organisms.
    $this->organisms = [
      1 => [
        'genus' => 'Tripalus',
        'species' => 'bogusii',
        'type_id' => NULL,
      ],
      2 => [
        'genus' => 'Tripalus',
        'species' => 'databasica',
        'type_id' => NULL,
      ],
      3 => [
        'genus' => 'Tripalus',
        'species' => 'fictus',
        'type_id' => NULL,
      ],
      4 => [
        'genus' => 'Lens',
        'species' => 'culinaris',
        'type_id' => NULL,
      ],
      5 => [
        'genus' => 'Lens',
        'species' => 'ervoides',
        'type_id' => NULL,
      ],
      6 => [
        'genus' => 'Phaseolus',
        'species' => 'vulgaris',
        'type_id' => NULL,
      ],
    ];

    foreach ($this->organisms as $organism) {
      $insert = $this->chado_connection->insert('1:organism');
      $insert->fields([
        'genus' => $organism['genus'],
        'species' => $organism['species'],
        'type_id' => $organism['type_id'] ?? NULL,
      ]);
      $insert->execute();
    }

    // Create the terms for the field property storage types.
    $idsmanager = \Drupal::service('tripal.collection_plugin_manager.idspace');
    foreach (
      [
        'OBI',
        'local',
        'TAXRANK',
        'NCBITaxon',
        'SIO',
        'schema',
        'data',
        'NCIT',
        'operation',
        'OBCS', 'SWO',
        'IAO',
        'TPUB',
        'rdfs',
      ] as $termIdSpace) {
      $idsmanager->createCollection($termIdSpace, "chado_id_space");
    }
    $vmanager = \Drupal::service('tripal.collection_plugin_manager.vocabulary');
    foreach (
      [
        'obi',
        'local',
        'taxonomic_rank',
        'ncbitaxon',
        'SIO',
        'schema',
        'EDAM',
        'ncit',
        'OBCS',
        'swo',
        'IAO',
        'tripal_pub',
      ] as $termVocab) {
      $vmanager->createCollection($termVocab, "chado_vocabulary");
    }

    // Create terms for organism_dbxref since it seems to be missing.
    $term_details = [
      'vocab_name' => 'sbo',
      'id_space_name' => 'SBO',
      'term' => [
        'name' => 'reference annotation',
        'definition' => 'Additional information that supplements existing data, usually in a document, by providing a link to more detailed information, which is held externally, or elsewhere.',
        'accession' => '0000552',
      ],
    ];
    $this->createTripalTerm($term_details, 'chado_id_space', 'chado_vocabulary');

    // Create the content types + fields that we need.
    $this->createContentTypeFromConfig('general_chado', 'organism', TRUE);

    $publish_service = \Drupal::service('tripal.backend_publish');
    $chado_publish = $publish_service->createInstance('chado_storage', []);
    $publish_options = ['bundle' => 'organism', 'datastore' => 'chado_storage', 'schema_name' => $this->testSchemaName];
    $chado_publish->publish($publish_options);

    $organism_bundle = TripalEntityType::load('organism');
    $organism_bundle->save();
  }

  /**
   * Tests that the exposed form for the dynamic filter is built as expected.
   */
  public function testBuildExposedForm() {
    $view = Views::getView('test_species_search');
    $this->assertNotNull($view);

    $view->setDisplay();
    $view->initHandlers();

    $this->assertArrayHasKey('species_filter', $view->filter);

    $filter = $view->filter['species_filter'];

    $form = [];
    $form_state = new FormState();

    $filter->buildOptionsForm($form, $form_state);
    $this->assertArrayHasKey('organism_field', $form, "The options form does not have the expected organism field.");
    $this->assertEquals('select', $form['organism_field']['#type'], "The organism field in the options form is not the expected select type.");

    $filter->buildExposedForm($form, $form_state);
    $this->assertArrayHasKey('value', $form, "The exposed form does not have the expected value field.");

    $this->assertArrayHasKey('crop', $form['value'], "The exposed form's value field does not have the expected crop field.");
    $this->assertArrayHasKey('genus', $form['value'], "The exposed form's value field does not have the expected genus field.");
    $this->assertArrayHasKey('species', $form['value'], "The exposed form's value field does not have the expected species field.");

  }

}

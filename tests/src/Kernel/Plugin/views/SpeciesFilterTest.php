<?php

namespace Drupal\Tests\trpcultivate\Kernel\Plugin\views;

use Drupal\Core\Form\FormState;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\tripal\Entity\TripalEntityType;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal_chado\Controller\ChadoCVTermAutocompleteController;
use Drupal\user\Entity\Role;
use Drupal\views\Tests\ViewResultAssertionTrait;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

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
    'filter',
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
    $this->installConfig(['tripal', 'tripal_chado', 'trpcultivate']);
    $this->installConfig(['trpcultivate_test_views']);

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    // Create a user with permissions to view the content we will create.
    $role = Role::create([
      'id' => 'test_role',
      'label' => 'Test Role',
    ]);
    $role->grantPermission('view all organism content');
    $role->grantPermission('view all germplasm content');
    $role->save();

    $account = $this->createUser([], NULL, FALSE, ['test_role']);
    $this->container->get('current_user')->setAccount($account);

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

    $type_id = ChadoCVTermAutocompleteController::getCVtermId('germplasm (EFO:0007059)');

    $this->chado_connection->insert('1:stock')
      ->fields([
        'name' => 'my_stock_1',
        'organism_id' => 4,
        'uniquename' => 'UNIQUENAME1',
        'type_id' => $type_id,
      ])
      ->execute();

    $this->chado_connection->insert('1:stock')
      ->fields([
        'name' => 'my_stock_2',
        'organism_id' => 5,
        'uniquename' => 'UNIQUENAME2',
        'type_id' => $type_id,
      ])
      ->execute();

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

    $term_details = [
      'vocab_name' => 'efo',
      'id_space_name' => 'EFO',
      'term' => [
        'name' => 'germplasm',
        'definition' => 'A germplasm is a collection of genetic resources for an organism. It can be a seed, a plant cutting, or any other material that can be used to propagate the organism. Germplasm collections are important for preserving genetic diversity and for breeding programs.',
        'accession' => '0007059',
      ],
    ];

    $this->createTripalTerm($term_details, 'chado_id_space', 'chado_vocabulary');

    // Create the content types + fields that we need.
    $this->createContentTypeFromConfig('general_chado', 'organism', TRUE);

    $this->createContentTypeFromConfig('germplasm_chado', 'germplasm', TRUE);

    $publish_service = \Drupal::service('tripal.backend_publish');
    $chado_publish = $publish_service->createInstance('chado_storage', []);
    $publish_options = ['bundle' => 'organism', 'datastore' => 'chado_storage', 'schema_name' => $this->testSchemaName];
    $chado_publish->publish($publish_options);

    $organism_bundle = TripalEntityType::load('organism');
    $organism_bundle->save();

    $publish_options = ['bundle' => 'germplasm', 'datastore' => 'chado_storage', 'schema_name' => $this->testSchemaName];
    $chado_publish->publish($publish_options);

    $germplasm_bundle = TripalEntityType::load('germplasm');
    $germplasm_bundle->save();
  }

  /**
   * Provides data for testing the buildExposedForm method.
   */
  public static function provideDataForTestBuildExposedForm() {
    return [
      'input with crop only' => [
        'input' => [
          'crop' => 'Lens',
        ],
        'expected' => [
          'genus' => 'Lens',
          'species' => 'culinaris',
        ],
      ],
      'input with crop, genus, and species' => [
        'input' => [
          'crop' => 'Cicer',
          'genus' => 'Cicer',
          'species' => 'arietinum',
          'crop_used' => '1',
        ],
        'expected' => [
          'genus' => 'Cicer',
          'species' => 'arietinum',
        ],
      ],
      'input with genus and species only' => [
        'input' => [
          'genus' => 'Tripalus',
          'species' => 'databasica',
        ],
        'expected' => [
          'genus' => 'Tripalus',
          'species' => 'databasica',
        ],
      ],
    ];
  }

  /**
   * Tests that the exposed form for the dynamic filter is built as expected.
   *
   * @param array $input
   *   The input similar to exposed form user input.
   * @param array $expected
   *   The expected values to be set on the filter after processing the input.
   *
   * @dataProvider provideDataForTestBuildExposedForm
   */
  #[DataProvider('provideDataForTestBuildExposedForm')]
  public function testBuildExposedForm(array $input, array $expected) {
    $view = Views::getView('test_species_search');
    $this->assertNotNull($view);

    $view->setDisplay('default');
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

    $form_state->setUserInput($input);
    $filter->buildExposedForm($form, $form_state);
    $filter->acceptExposedInput($input);

    $this->assertEquals($expected['genus'], $filter->value['genus'], "The genus was not set correctly by the filter exposed form");
    $this->assertEquals($expected['species'], $filter->value['species'], "The species was not set correctly by the filter exposed form");
    $this->assertEquals("{$expected['genus']} {$expected['species']}", $filter->adminSummary(), "The adminSummary method did not return the correct string with the organism name.");
  }

  /**
   * Provides data for testing the query method.
   */
  public static function provideDataForTestQueryMethod() {
    return [
      'filter by Lens genus' => [
        'scenario' => 'filter by Lens genus',
        'input' => [
          'genus' => 'Lens',
        ],
        'expected' => ['my_stock_1', 'my_stock_2'],
      ],
      'filter by Tripalus genus' => [
        'scenario' => 'filter by Tripalus genus',
        'input' => [
          'genus' => 'Tripalus',
        ],
        'expected' => [],
      ],
      'filter by Lens culinaris species' => [
        'scenario' => 'filter by Lens culinaris species',
        'input' => [
          'genus' => 'Lens',
          'species' => 'culinaris',
        ],
        'expected' => ['my_stock_1'],
      ],
      'filter by Lens with crop field' => [
        'scenario' => 'filter by Lens with crop field',
        'input' => [
          'crop' => 'Lens',
          'genus' => 'Lens',
          'species' => 'culinaris',
        ],
        'expected' => ['my_stock_1'],
      ],
      'filter by species only' => [
        'scenario' => 'filter by species only',
        'input' => [
          'species' => 'culinaris',
        ],
        'expected' => ['my_stock_1'],
      ],
    ];
  }

  /**
   * Tests that the query is correctly modified by the filter.
   *
   * @param string $scenario
     *   A description of the test scenario for better readability of results.
   * @param array $input
   *   The input similar to exposed form user input.
   * @param array $expected
   *   The expected array of stock names that should be returned by the view
   *   after applying the filter with the given input.
   *
   * @dataProvider provideDataForTestQueryMethod
   */
  #[DataProvider('provideDataForTestQueryMethod')]
  public function testQueryMethod(string $scenario, array $input, array $expected) {
    $view = Views::getView('test_species_search');
    $view->initHandlers();
    $view->setExposedInput($input);
    $view->execute();

    $labels = array_map(function ($row) {
      return (string) $row->_entity->label();
    }, $view->result);

    $this->assertEquals($expected, $labels, 'The array resulted does not match the expected array in scenario' . " $scenario.");
  }

}

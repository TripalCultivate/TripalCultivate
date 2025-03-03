<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;

/**
 * Tests Tripal Cultivate Germplasm Name Exists Validator Plugin.
 *
 * @group trpcultivate
 * @group validators
 * @group row_validators
 */
class ValidatorGermplasmNameExistsTest extends ChadoTestKernelBase {

  /**
   * Plugin Manager service.
   *
   * @var \Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager
   */
  protected TripalCultivateValidatorManager $plugin_manager;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * The organism ID of the first organism inserted into Chado for this test.
   *
   * @var int
   */
  protected int $first_organism_id;

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
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate']);

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');

    // Insert an organism into chado.
    $genus = 'Tripalus';
    $species = 'databasica';
    $this->first_organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => $species,
      ])
      ->execute();
    $this->assertIsNumeric($this->first_organism_id, 'We were not able to create the organism ' . $genus . ' ' . $species . ' in Chado for testing.');
  }

  /**
   * Data Provider for providing different scenarios of row values.
   *
   * @return array
   *   Each test scenario is an array with the following values:
   */
  public function provideRowToGermplasmNameExists() {

    $scenarios = [];

    // #0: A simple row where index 1 is a germplasm name that exists.
    $scenarios[] = [
      [1],
      [
        1 => 'Germplasm1',
        2 => 'Column2',
        3 => 'Column3',
      ],
    ];

    return $scenarios;
  }

  /**
   * Test Germplasm Name Exists Plugin Validator.
   *
   * @param array $indices
   *   An array where each value is the key of the column the validator instance
   *   should act on. It must be either an integer or string.
   * @param array $row_values
   *   An array of values from a single row/line in the file where each key maps
   *   to a column index (see @param $indices above) and each value is the
   *   content of that column.
   *
   * @dataProvider provideRowToGermplasmNameExists
   */
  public function testValidatorGermplasmNameExists(array $indices, array $row_values) {

    // Create a plugin instance for this validator.
    $validator_id = 'germplasm_name_exists';
    $instance = $this->plugin_manager->createInstance($validator_id);

    $instance->setIndices($indices);
    $instance->setOrganismID($this->first_organism_id);
    $instance->validateRow($row_values);
  }

}

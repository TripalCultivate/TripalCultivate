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
   * The organism ID of the organism inserted into Chado for this test.
   *
   * @var int
   */
  protected int $organism_id;

  /**
   * The germplasm name of a germplasm inserted into Chado for this test.
   *
   * @var string
   */
  protected string $inserted_germplasm_name = 'stock1';

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

    // Insert an organism into chado.organism.
    $genus = 'Tripalus';
    $species = 'databasica';
    $this->organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => $species,
      ])
      ->execute();
    $this->assertIsNumeric($this->organism_id, 'We were not able to create the organism ' . $genus . ' ' . $species . ' in Chado for testing.');

    // Insert a germplasm into chado.stock.
    $values = [
      'organism_id' => $this->organism_id,
      'name' => $this->inserted_germplasm_name,
      'uniquename' => 'TEST:1',
      'type_id' => 9,
    ];
    $stock_id = $this->chado_connection->insert('1:stock')
      ->fields($values)->execute();
    $this->assertIsNumeric($stock_id, 'We were not able to create the stock ' . $this->inserted_germplasm_name . ' in Chado for testing.');
  }

  /**
   * Data Provider for providing different scenarios of row values.
   *
   * @return array
   *   Each test scenario is an array with the following values:
   *   - An array where each value is the key of the column in $row_values that
   *     the validator instance should act on.
   *   - An array of values from a single row/line in the file where each key
   *     maps to a column index and each value is the content of that column.
   *   - An array of the expected contents of the returned validation result:
   *     - 'expected_valid': The expected validation status (TRUE if pass, FALSE
   *       fail)
   *     - 'expected_case': The expected case message.
   *     - 'expected_failedItems': The expected contents of the 'failedItems'
   *       array. This should be an empty array if validation is expected to
   *       pass.
   */
  public function provideRowToGermplasmNameExists() {
    $scenarios = [];

    // #0: A simple row where column 1 is a germplasm name that doesn't exist.
    $scenarios[] = [
      [1],
      [
        1 => 'Germplasm1',
        2 => 'Column2',
        3 => 'Column3',
      ],
      [
        'expected_valid' => FALSE,
        'expected_case' => 'Unable to find germplasm name in the database',
        'expected_failedItems' => [
          'failed_cells' => [1 => 'Germplasm1'],
        ],
      ],
    ];

    // #1: A simple row where column 1 is a germplasm name that exists.
    $scenarios[] = [
      [1],
      [
        1 => $inserted_germplasm_name,
        2 => 'Column2',
        3 => 'Column3',
      ],
      [
        'expected_valid' => TRUE,
        'expected_case' => 'Germplasm name exists in the database',
        'expected_failedItems' => [],
      ],
    ];

    return $scenarios;
  }

  /**
   * Test Germplasm Name Exists Plugin Validator.
   *
   * @param array $indices
   *   An array where each value is the key of the column the validator instance
   *   should act on. Each key must be either an integer or string.
   * @param array $row_values
   *   An array of values from a single row/line in the file where each key maps
   *   to a column index (see @param $indices above) and each value is the
   *   content of that column.
   * @param array $expectations
   *   An array of the expected contents of the returned validation result:
   *   - 'expected_valid': The expected validation status (TRUE if pass, FALSE
   *     if fail)
   *   - 'expected_case': The expected case message.
   *   - 'expected_failedItems': The expected contents of the failedItems array.
   *     This should be an empty array if validation is expected to pass.
   *
   * @dataProvider provideRowToGermplasmNameExists
   */
  public function testValidatorGermplasmNameExists(array $indices, array $row_values, array $expectations) {

    // Create a plugin instance for this validator.
    $validator_id = 'germplasm_name_exists';
    $instance = $this->plugin_manager->createInstance($validator_id);

    $instance->setIndices($indices);
    $instance->setOrganismID($this->organism_id);
    $validation_status = $instance->validateRow($row_values);

    $this->assertSame(
      $expectations['expected_valid'],
      $validation_status['valid'],
      'Germplasm Name Exists validation did not return the expected status for this scenario.',
    );
    $this->assertEquals(
      $expectations['expected_case'],
      $validation_status['case'],
      'Germplasm Name Exists validation did not return the expected case message for this scenario.',
    );
    if (array_key_exists('failed_cells', $expectations['expected_failedItems'])) {
      $this->assertContains(
        $expectations['expected_failedItems']['failed_cells'],
        $validation_status['failedItems'],
        'Germplasm Name Exists validation did not return the expected failed cells for this scenario.',
      );
    }
    else {
      $this->assertSameSize(
        $expectations['expected_failedItems'],
        $validation_status['failedItems'],
        'Germplasm Name Exists validation did not contain an empty array for failedItems despite validation passing.',
      );
    }
  }

}

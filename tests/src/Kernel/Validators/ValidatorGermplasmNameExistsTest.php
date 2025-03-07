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
   * The name of a germplasm inserted into Chado for this test.
   *
   * @var string
   */
  protected string $inserted_germplasm_name = 'stock1';

  /**
   * Insert values for inserting the same germplasm name twice into Chado.
   *
   * @var array
   */
  protected array $duplicate_insert_values = [
    'dup1-1' => [
      'organism_id' => 1,
      'name' => 'duplicate1',
      'uniquename' => 'TEST_DUP:1',
      'type_id' => 9,
    ],
    'dup1-2' => [
      'organism_id' => 1,
      'name' => 'duplicate1',
      'uniquename' => 'TEST_DUP:2',
      'type_id' => 9,
    ],
    'dup2-1' => [
      'organism_id' => 1,
      'name' => 'duplicate2',
      'uniquename' => 'TEST_DUP:3',
      'type_id' => 9,
    ],
    'dup2-2' => [
      'organism_id' => 1,
      'name' => 'duplicate2',
      'uniquename' => 'TEST_DUP:4',
      'type_id' => 9,
    ],
  ];

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

    // Insert our set of duplicate germplasm names into chado.stock.
    foreach ($this->duplicate_insert_values as $key => $duplicate) {
      $stock_id = $this->chado_connection->insert('1:stock')
        ->fields($duplicate)->execute();
      $this->assertIsNumeric($stock_id, 'We were not able to create the stock with uniquename ' . $duplicate['uniquename'] . ' in Chado for testing.');
      // Update our array with the stock_id.
      $this->duplicate_insert_values[$key]['stock_id'] = $stock_id;
    }
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
        'expected_case' => 'Missing germplasm name(s) in the database',
        'expected_failedItems' => [
          'missing_cells' => [
            1 => [
              'germplasm_name' => 'Germplasm1',
            ],
          ],
        ],
      ],
    ];

    // #1: A simple row where column 1 is a germplasm name that exists.
    $scenarios[] = [
      [1],
      [
        1 => $this->inserted_germplasm_name,
        2 => 'Column2',
        3 => 'Column3',
      ],
      [
        'expected_valid' => TRUE,
        'expected_case' => 'Germplasm name(s) exist(s) in the database',
        'expected_failedItems' => [],
      ],
    ];

    // #2: A row with 3 germplasm in separate columns, only 1 exists.
    $scenarios[] = [
      [1, 3, 5],
      [
        1 => 'Germplasm1',
        2 => 'Column2',
        3 => $this->inserted_germplasm_name,
        4 => 'Column4',
        5 => 'Germplasm3',
      ],
      [
        'expected_valid' => FALSE,
        'expected_case' => 'Missing germplasm name(s) in the database',
        'expected_failedItems' => [
          'missing_cells' => [
            1 => [
              'germplasm_name' => 'Germplasm1',
            ],
            5 => [
              'germplasm_name' => 'Germplasm3',
            ],
          ],
        ],
      ],
    ];

    // #3: A row with a germplasm name that is duplicated at the database level.
    $germplasm_name = 'duplicate1';
    $scenarios[] = [
      [2],
      [
        1 => 'Column1',
        2 => $germplasm_name,
        3 => 'Column3',
      ],
      [
        'expected_valid' => FALSE,
        'expected_case' => 'Duplicate(s) found in the database for germplasm name(s)',
        'expected_failedItems' => [
          'duplicate_cells' => [
            2 => [
              'germplasm_name' => $germplasm_name,
              'duplicates' => ['dup1-1', 'dup1-2'],
            ],
          ],
        ],
      ],
    ];

    // #4: A row with 3 germplasm in separate columns, 1 exists and 2 are
    // separate duplicates.
    $dup_germplasm_name_1 = 'duplicate1';
    $dup_germplasm_name_2 = 'duplicate2';
    $scenarios[] = [
      [1, 2, 3],
      [
        1 => $dup_germplasm_name_1,
        2 => $this->inserted_germplasm_name,
        3 => $dup_germplasm_name_2,
      ],
      [
        'expected_valid' => FALSE,
        'expected_case' => 'Duplicate(s) found in the database for germplasm name(s)',
        'expected_failedItems' => [
          'duplicate_cells' => [
            1 => [
              'germplasm_name' => $dup_germplasm_name_1,
              'duplicates' => ['dup1-1', 'dup1-2'],
            ],
            3 => [
              'germplasm_name' => $dup_germplasm_name_2,
              'duplicates' => ['dup2-1', 'dup2-2'],
            ],
          ],
        ],
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
    if (array_key_exists('missing_cells', $expectations['expected_failedItems'])) {
      $this->assertContains(
        $expectations['expected_failedItems']['missing_cells'],
        $validation_status['failedItems'],
        'Germplasm Name Exists validation did not return the expected missing cells for this scenario.',
      );
    }
    if (array_key_exists('duplicate_cells', $expectations['expected_failedItems'])) {
      foreach ($indices as $index) {
        $expected_germplasm_name = $expectations['expected_failedItems']['duplicate_cells'][$index]['germplasm_name'];
        $this->assertEquals(
          $expected_germplasm_name,
          $validation_status['failedItems']['duplicate_cells'][$index]['germplasm_name'],
          'Germplasm Name Exists validation did not return the expected duplicated germplasm name for this scenario.',
        );
        // Pull out expected duplicates based on the scenario.
        $expected_duplicates = [];
        foreach ($expectations['expected_failedItems']['duplicate_cells'][$index]['duplicates'] as $property_index) {
          $current_stock = $this->duplicate_insert_values[$property_index];
          $expected_duplicates[$current_stock['stock_id']] = $current_stock;
        }
        // Loop through failedItems duplicate records and compare with the
        // expected duplicates above.
        foreach ($validation_status['failedItems']['duplicate_cells'][$index]['duplicates'] as $duplicate_record) {
          $this->assertArrayHasKey(
            $duplicate_record->stock_id,
            $expected_duplicates,
            'The current failedItem does not exist in our expected duplicates according to stock_id.'
          );
          // Now loop through our remaining keys in our duplicated insert values
          // for this stock_id.
          foreach ($expected_duplicates[$duplicate_record->stock_id] as $key => $value) {
            $this->assertEquals(
              $value,
              $duplicate_record->$key,
              'Germplasm Name Exists validation did not contain the expected value for ' . $key . ' for duplicated germplasm ' . $expected_germplasm_name . ' or that key does not exist.',
            );
          }
        }
      }
    }
  }

}

<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests Row Validation for Valid Organism Validator.
 *
 * @group trpcultivate
 * @group validators
 * @group row_validators
 */
#[Group('trpcultivate')]
#[Group('validators')]
#[Group('row_validators')]
class ValidatorValidOrganismValidateRowTest extends ChadoTestKernelBase {
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
    $this->installConfig('trpcultivate');

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');

    // Insert an organism into chado.organism.
    $genus = 'Lens';
    $species = 'culinaris';
    $this->organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => $species,
      ])
      ->execute();
    $this->assertIsNumeric($this->organism_id, 'We were not able to create the organism ' . $genus . ' ' . $species . ' in Chado for testing.');
  }

  /**
   * Data Provider: Provide different row values with organism names.
   *
   * @return array
   *   Each test scenario is an array with the following values:
   *   - An array where each value is the key of the column is $row_values that
   *     the validator instance should act on.
   *   - An array of values from a single row/line in the file where each key
   *     maps to a column index and each value is the content of that column.
   *   - An array of the expected contents of the returned validation result:
   *     - 'expected_valid': The expected validation status (TRUE if pass,
   *       FALSE if fail)
   *     - 'expected_case': The expected case message.
   *     - 'expected_failedItems': The expected contents of the 'failedItems'
   *       array with the following keys. This should be an empty array if
   *       validation is expected to pass.
   *       - 'missing_cells' (OPTIONAL): Present if an organism is missing
   *         from the databse.
   *         - 1+ arrays keyed by the column number (first column = 1) in the
   *           input row that contains a missing organism, further keyed by:
   *           - 'organism': The name of the missing organism.
   *       - 'empty_cells' (OPTIONAL): Present if a cell index is empty and thus
   *         cannot be looked up in the database.
   *         - A list containing the indices of the empty cells (This can only
   *           be a subset of $indices).
   */
  public static function provideRowToValidOrganism() {
    $scenario = [];

    // #0: A simple row where column 1 is an organism that doesn't exist.
    $scenario[] = [
      [1],
      [
        1 => 'Organism1',
        2 => 'Column2',
        3 => 'Column3',
      ],
      [
        'expected_valid' => FALSE,
        'expected_case' => 'Missing organism(s) in the database',
        'expected_failedItems' => [
          'missing_cells' => [
            1 => [
              'organism' => 'Organism1',
            ],
          ],
        ],
      ],
    ];

    // #1: A simple row where column 1 is an organism that exists.
    $scenario[] = [
      [1],
      [
        1 => 'Lens culinaris',
        2 => 'Column2',
        3 => 'Column3',
      ],
      [
        'expected_valid' => TRUE,
        'expected_case' => 'Organism(s) exist(s) in the database',
        'expected_failedItems' => [],
      ],
    ];

    // #2: A row with 3 organisms in seperate columns, only 1 exists.
    $scenario[] = [
      [1, 3, 5],
      [
        1 => 'Organism1',
        2 => 'Column2',
        3 => 'Lens culinaris',
        4 => 'Column4',
        5 => 'Organism3',
      ],
      [
        'expected_valid' => FALSE,
        'expected_case' => 'Missing organism(s) in the database',
        'expected_failedItems' => [
          'missing_cells' => [
            1 => [
              'organism' => 'Organism1',
            ],
            5 => [
              'organism' => 'Organism3',
            ],
          ],
        ],
      ],
    ];

    // #3: A row with two empty cells where organisms should be.
    // All other cases are also present, but we expect to only be told about the
    // empty cells.
    $scenario[] = [
      [1, 2, 3, 4],
      [
        1 => 'Lens culinaris',
        2 => '',
        3 => 'Missing organism',
        4 => ' ',
      ],
      [
        'expected_valid' => FALSE,
        'expected_case' => 'Unable to lookup organism with empty values',
        'expected_failedItems' => [
          'empty_cells' => [2, 4],
        ],
      ],
    ];

    return $scenario;
  }

  /**
   * Test Valid Organism Validator Plugin Row Validate.
   *
   * @param array $indices
   *   An array where each value is the key of the column validator instance
   *   should act on. Each key must be either an integer or string.
   * @param array $row_values
   *   An array of values from a single row/line in the file where each key maps
   *   to a column index (see @param $indices above) and each value is the
   *   content of that column.
   * @param array $expectations
   *   - An array of the expected contents of the returned validation result:
   *     - 'expected_valid': The expected validation status (TRUE if pass,
   *       FALSE if fail)
   *     - 'expected_case': The expected case message.
   *     - 'expected_failedItems': The expected contents of the 'failedItems'
   *       array with the following keys. This should be an empty array if
   *       validation is expected to pass.
   *       - 'missing_cells' (OPTIONAL): Present if an organism is missing
   *         from the databse.
   *         - 1+ arrays keyed by the column number (first column = 1) in the
   *           input row that contains a missing organism, further keyed by:
   *           - 'organism': The name of the missing organism.
   *       - 'empty_cells' (OPTIONAL): Present if a cell index is empty and thus
   *         cannot be looked up in the database.
   *         - A list containing the indices of the empty cells (This can only
   *           be a subset of $indices).
   *
   * @dataProvider provideRowToValidOrganism
   */
  #[DataProvider('provideRowToValidOrganism')]
  public function testValidatorValidOrganismRow(
    array $indices,
    array $row_values,
    array $expectations,
  ) {
    // Create a plugin instance for this validator.
    $validator_id = 'valid_organism';
    $instance = $this->plugin_manager->createInstance($validator_id);

    $instance->setIndices($indices);
    $validation_status = $instance->validateRow($row_values);

    $this->assertEquals(
      $expectations['expected_valid'],
      $validation_status['valid'],
      'Valid Organism validation did not return the expected status for this scenario.',
    );
    $this->assertEquals(
      $expectations['expected_case'],
      $validation_status['case'],
      'Valid Organism validation did not return the expected case message for this scenario.',
    );
    foreach ($indices as $index) {
      if (array_key_exists('empty_cells', $expectations['expected_failedItems'])) {
        // Check for expected empty columns.
        $this->assertEquals(
          $expectations['expected_failedItems']['empty_cells'],
          $validation_status['failedItems']['empty_cells'],
          'Valid Organism validation did not return the expected list of empty cells for this scenario.',
        );
      }
      if (array_key_exists('missing_cells', $expectations['expected_failedItems'])) {
        // Check for expected missing columns.
        if (array_key_exists($index, $expectations['expected_failedItems']['missing_cells'])) {
          $expected_organism = $expectations['expected_failedItems']['missing_cells'][$index]['organism'];
          $this->assertEquals(
            $expected_organism,
            $validation_status['failedItems']['missing_cells'][$index],
            'Valid Organism validation did not return the expected missing cells for this scenario.',
          );
        }
      }
    }
  }

}

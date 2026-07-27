<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Core\Form\FormState;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;
use Drupal\tripal_chado\Database\ChadoConnection;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Metadata Validation for TripalCultivate Valid Organism Validator.
 */
#[Group('trpcultivate')]
#[Group('validators')]
#[RunTestsInSeparateProcesses]
class ValidatorValidOrganismMetadataTest extends ChadoTestKernelBase {
  /**
   * The Validators plugin manager for creating new validator instances.
   *
   * @var \Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager
   */
  protected TripalCultivateValidatorManager $plugin_manager;

  /**
   * An array of organisms for testing.
   *
   * @var array
   */
  protected array $test_organisms;

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
    'field_group',
    'tripal',
    'tripal_chado',
    'tripal_layout',
    'trpcultivate',
  ];

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

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

    // Create our organism and configure it.
    $organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => 'Lens',
        'species' => 'culinaris',
      ])
      ->execute();
    $this->assertIsNumeric($organism_id,
      "We were not able to create an organism for testing.");

    $this->test_organisms = [
      'valid_organism' => 'Lens culinaris',
      'invalid_organism' => 'Tripulas databasica',
      'valid_organism_id' => 1,
      'invalid_organism_id' => 100,
    ];
  }

  /**
   * Provide data for testValidOrganismMetadataExceptions.
   *
   * @return array
   *   Each scenario is an array with the following values:
   *   - A human-readable short description of the test scenario.
   *   - The form values to pass to the validator that will cause an exception.
   *   - The expected exception message or a string contained in the expected
   *     exception message.
   */
  public static function provideInvalidFormValuesForValidOrganismValidator() {
    $scenarios = [];
    // #0: No organism field provided.
    $scenarios[] = [
      'no organism field provided',
      ['not_organism' => 'Tripulas databasica'],
      'Failed to locate organism field element. ValidOrganism validator expects a form field element name organism.',
    ];

    // #1: Passing a string instead of an array.
    $scenarios[] = [
      'passing a string instead of an array',
      'INVALID ORGANISM',
      'Argument #1 ($form_values) must be of type array, string given',
    ];

    // #2: Passing a Drupal $form_state object instead of an array.
    $form_state = new FormState();
    $form_state->setValues(['organism' => uniqid()]);
    $scenarios[] = [
      'passing a Drupal $form_state object instead of an array',
      $form_state,
      'Argument #1 ($form_values) must be of type array, Drupal\Core\Form\FormState given',
    ];

    return $scenarios;
  }

  /**
   * Test exceptions thrown by valid organism metadata validation.
   *
   * @param string $scenario
   *   A human-readable short description of the test scenario.
   * @param mixed $form_values
   *   The form values to pass to the validator that will cause an exception.
   * @param string $expected_exception_message
   *   The expected exception message or a string contained in the expected
   *   exception message.
   *
   * @dataProvider provideInvalidFormValuesForValidOrganismValidator
   */
  #[DataProvider('provideInvalidFormValuesForValidOrganismValidator')]
  public function testValidOrganismMetadataExceptions($scenario, $form_values, $expected_exception_message) {
    // Create a plugin instance for this validator.
    $validator_id = 'valid_organism';
    $instance = $this->plugin_manager->createInstance($validator_id);

    $instance->setInputType('metadata');

    $exception_caught  = FALSE;
    $exception_message = '';
    try {
      $instance->validateMetadata($form_values);
    }
    catch (\Exception $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }
    catch (\TypeError $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when ' . $scenario . ' in the form values for Valid Organism validator.');
    $this->assertStringContainsString(
      $expected_exception_message,
      $exception_message,
      'Expected exception message does not match message when ' . $scenario . ' in the form values for Valid Organism validator.');
  }

  /**
   * Data provider for testValidOrganismMetadata.
   *
   * @return array
   *   Each scenario is an array with the follwing values:
   *   - A human-readable short description of the test scenario.
   *   - A string, test key that will reference an organism in $test_organisms.
   *   - Expected validation response with the following keys:
   *     - 'case': the validation test case message.
   *     - 'valid': true if validation passed, false if failed.
   *     - 'failedItems': the failed items or input value returned by the
   *        validator if it returned failed status. This key has the following
   *        sub-key:
   *        - 'organism_provided': The organism provided that failed validation.
   */
  public static function provideOrganismToValidateOrganism() {
    return [
      [
        'Valid organism',
        'valid_organism',
        [
          'case' => 'Organism(s) exist(s) in the database',
          'valid' => TRUE,
          'failedItems' => [],
        ],
      ],
      [
        'Invalid organism',
        'invalid_organism',
        [
          'case' => 'Missing organism(s) in the database',
          'valid' => FALSE,
          'failedItems' => ['organism_provided' => 'Tripulas databasica'],
        ],
      ],
      [
        'Valid organismID',
        'valid_organism_id',
        [
          'case' => 'Organism(s) exist(s) in the database',
          'valid' => TRUE,
          'failedItems' => [],
        ],
      ],
      [
        'Invalid organismID',
        'invalid_organism_id',
        [
          'case' => 'Missing organism(s) in the database',
          'valid' => FALSE,
          'failedItems' => ['organism_provided' => 100],
        ],
      ],
    ];
  }

  /**
   * Test valid organism metadata.
   *
   * @param string $scenario
   *   A human-readable short description of the test scenario.
   * @param string $test_key
   *   A string, test key that will reference an organism in $test_organisms.
   * @param array $expected
   *   Expected validation response with the following keys:
   *   - 'case': the validation test case message.
   *   - 'valid': true if validation passed, false if failed.
   *   - 'failedItems': the failed items or input value returned by the
   *      validator if it returned a failed status. This key has the following
   *      sub-key:
   *      -'organism_provided': The organism provided that failed validation.
   *
   * @dataProvider provideOrganismToValidateOrganism
   */
  #[DataProvider('provideOrganismToValidateOrganism')]
  public function testMetadataOrganismExists(string $scenario, string $test_key, array $expected) {
    // Create a plugin instance for this valiator.
    $validator_id = 'valid_organism';
    $instance = $this->plugin_manager->createInstance($validator_id);

    $instance->setInputType('metadata');

    $form_values = ['organism' => $this->test_organisms[$test_key]];
    $validation_status = $instance->validateMetadata($form_values);

    $this->assertEquals(
      $expected['case'],
      $validation_status['case'],
      'The validation case title returned by Valid Organism Validator matches the expected validation case title in scenario ' . $scenario,
    );

    $this->assertEquals(
      $expected['valid'],
      $validation_status['valid'],
      'The validation valid key returned by Valid Organism Validator matches the expected validation valid key value in scenario ' . $scenario,
    );

    $this->assertEquals(
      $expected['failedItems'],
      $validation_status['failedItems'],
      'The validation failedItems returned by Valid Organism Validator matches the expected validation failedItems in scenario ' . $scenario,
    );
  }

}

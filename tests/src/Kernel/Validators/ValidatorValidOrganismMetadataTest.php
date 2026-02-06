<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Core\Form\FormState;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;
use Drupal\tripal_chado\Database\ChadoConnection;

/**
 * Tests Metadata Validation for TripalCultivate Valid Organism Validator.
 *
 * @group trpcultivate
 * @group validators
 */
#[Group('trpcultivate')]
#[Group('validators')]
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
    'markup',
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
    ];
  }

  /**
   * Test exceptions thrown by valid organism metadata validation.
   */
  public function testValidOrganismMetadataExceptions() {
    // Create a plugin instance for this validator.
    $validator_id = 'valid_organism';
    $instance = $this->plugin_manager->createInstance($validator_id);

    $form_values = 'INVALID ORGANISM';

    $exception_caught  = FALSE;
    $exception_message = '';
    try {
      $instance->validateMetadata($form_values);
    }
    catch (\TypeError $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when passing a string to Valid Organism validator.');
    $this->assertStringContainsString(
      'Argument #1 ($form_values) must be of type array, string given',
      $exception_message,
      'Expected exception message does not match message when passing string to Valid Organism validator.');

    // A Drupal $form_state object.
    $form_state = new FormState();
    // A random field.
    $form_state->setValues(['organism' => uniqid()]);

    $exception_caught  = FALSE;
    $exception_message = '';
    try {
      $instance->validateMetadata($form_state);
    }
    catch (\TypeError $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when passing a $form_state to Valid Organism validator.');
    $this->assertStringContainsString(
      'Argument #1 ($form_values) must be of type array, Drupal\Core\Form\FormState given',
      $exception_message,
      'Expected exception message does not match message when passing $form_state to Valid Organism validator.');
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
          'case' => 'case-valid',
          'valid' => TRUE,
          'failedItems' => [],
        ],
      ],
      [
        'Invalid organism',
        'invalid_organism',
        [
          'case' => 'case-missing-organism',
          'valid' => FALSE,
          'failedItems' => ['organism_provided' => 'Tripulas databasica'],
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
  public function testMetadataOrganismExists(
    string $scenario,
    string $test_key,
    array $expected,
  ) {
    // Create a plugin instance for this valiator.
    $validator_id = 'valid_organism';
    $instance = $this->plugin_manager->createInstance($validator_id);

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

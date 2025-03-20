<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators\ValidatorOrganismNOConnection;

/**
 * Tests the Organism validator trait.
 *
 * @group trpcultivate
 * @group validator_traits
 */
class ValidatorTraitOrganismMissingConnectionTest extends ChadoTestKernelBase {

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
   * The validator instance to use for testing.
   *
   * @var \Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators\ValidatorOrganismNOConnection
   */
  protected ValidatorOrganismNOConnection $instance;

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

    // Create a fake plugin instance for testing.
    $configuration = [];
    $validator_id = 'validator_requiring_organism_no_connection';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using Organism Trait NO Connection',
      'input_types' => ['header-row', 'data-row'],
    ];
    $instance = new ValidatorOrganismNOConnection(
      $configuration,
      $validator_id,
      $plugin_definition,
      $this->chado_connection,
    );
    $this->assertIsObject(
      $instance,
      "Unable to create $validator_id validator instance to test the Organism trait without a ChadoConnection."
    );

    $this->instance = $instance;
  }

  /**
   * Tests the Organism setters when there's no ChadoConnection.
   *
   * Specifically,
   *   - setOrganismID()
   *   - setGenus()
   */
  public function testOrganismSetters() {
    // Try setting the organism ID when ChadoConnection dependency is not met.
    $expected_message = 'Using setOrganismID() by the Organism Trait needs an instance of ChadoConnection (tripal_chado.database) injected via the create() and set to $this->chado_connection.';
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->instance->setOrganismID(5);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "Calling setOrganismID() when missing an instance of ChadoConnection should throw an exception, but it did not."
    );
    $this->assertEquals(
      $expected_message,
      $exception_message,
      "The exception thrown does not have the message we expected when calling setOrganismID() and missing an instance of ChadoConnection."
    );

    // Try setting the genus when ChadoConnection dependency is not met.
    $expected_message = 'Using setGenus() by the Organism Trait needs an instance of ChadoConnection (tripal_chado.database) injected via the create() and set to $this->chado_connection.';
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->instance->setGenus('Tripalus');
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "Calling setGenus() when missing an instance of ChadoConnection should throw an exception, but it did not."
    );
    $this->assertEquals(
      $expected_message,
      $exception_message,
      "The exception thrown does not have the message we expected when calling setGenus() and missing an instance of ChadoConnection."
    );
  }

}

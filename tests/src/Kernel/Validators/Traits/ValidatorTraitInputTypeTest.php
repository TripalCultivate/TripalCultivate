<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators\ValidatorInputType;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the Input Types trait properly sets and retrieves the input types.
 *
 * @group trpcultivate
 * @group validator_traits
 */
#[Group('trpcultivate')]
#[Group('validator_traits')]
#[RunTestsInSeparateProcesses]
class ValidatorTraitInputTypeTest extends ChadoTestKernelBase {

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
   * An instance of the ValidatorInputType fake validator for testing.
   *
   * @var \Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators\ValidatorInputType
   */
  protected ValidatorInputType $instance;

  /**
   * An array of input types which are invalid.
   *
   * @var array
   */
  protected array $invalid_input_types;

  /**
   * An array of input types which are valid.
   *
   * @var array
   */
  protected array $valid_input_types;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate']);

    // Setup our invalid input types array.
    $invalid_input_types = [
      'invalid value',
      123,
    ];
    $this->invalid_input_types = $invalid_input_types;

    // Setup our valid input types array.
    $valid_input_types = [
      'data-row',
      'metadata',
    ];
    $this->valid_input_types = $valid_input_types;

    // Create a fake plugin instance for testing.
    $configuration = [];
    $validator_id = 'validator_requiring_input_type';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using Input Type Trait',
      'input_types' => ['metadata', 'data-row'],
    ];
    $instance = new ValidatorInputType(
      $configuration,
      $validator_id,
      $plugin_definition
    );
    $this->assertIsObject(
      $instance,
      "Unable to create $validator_id validator instance to test the InputType trait."
    );

    $this->instance = $instance;
  }

  /**
   * Tests InputTypeTrait::setInputType() and InputTypeTrait::getInputType().
   */
  public function testInputTypeSetterGetter() {
    // Try to get input type before any have been set.
    // Exception message should trigger.
    $expected_message = 'Input type has not yet been set for this instance of the validator.';

    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->instance->getInputType();
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Calling getInputType() when no input type has been set should have thrown an exception but did not.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'The exception thrown does not have the message we expected when trying to get input type but none have been set yet.'
    );

    // Try to set invalid input types.
    // Exception message should trigger.
    foreach ($this->invalid_input_types as $input_type) {
      $expected_message = "Input type $input_type is not supported by this validator.";

      $exception_caught = FALSE;
      $exception_message = 'NONE';
      try {
        $this->instance->setInputType($input_type);
      }
      catch (\Exception $e) {
        $exception_caught = TRUE;
        $exception_message = $e->getMessage();
      }

      $this->assertTrue($exception_caught, 'Calling setInputType() with an invalid input type should have thrown an exception but did not.');
      $this->assertStringContainsString(
        $expected_message,
        $exception_message,
        'The exception thrown does not have the message we expected when trying to set input type with an array that has an invalid value.'
      );
    }

    // Set a valid input type and then check that they've been set.
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->instance->setInputType($this->valid_input_types[0]);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertFalse(
      $exception_caught,
      "Calling setInputType() with a valid input type should not have thrown an exception but it threw '$exception_message'"
    );

    // Check that we can get the input type we just set.
    $grabbed_input_type = $this->instance->getInputType();
    $this->assertEquals(
      $this->valid_input_types[0],
      $grabbed_input_type,
      'Could not grab the set input type using getInputType() despite having called setInputType() on it.'
    );

    // Try to set a second input type after already setting one.
    // Exception message should trigger.
    $expected_message = 'Input type has already been set for this instance of the validator. Each instance can only validate a single input type.';

    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->instance->setInputType($this->valid_input_types[1]);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Calling setInputType() after already setting an input type should have thrown an exception but did not.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      'The exception thrown does not have the message we expected when trying to set input type a second time.'
    );

  }

}

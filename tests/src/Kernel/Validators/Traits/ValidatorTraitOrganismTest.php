<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators\ValidatorOrganism;

/**
 * Tests the Organism validator trait.
 *
 * @group trpcultivate
 * @group validator_traits
 */
class ValidatorTraitOrganismTest extends ChadoTestKernelBase {

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
   * @var \Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators\ValidatorOrganism
   */
  protected ValidatorOrganism $instance;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set test environment.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Install module configuration.
    $this->installConfig(['trpcultivate']);

    // Create a fake plugin instance for testing.
    $configuration = [];
    $validator_id = 'validator_requiring_organism';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using Organism Trait',
      'input_types' => ['header-row', 'data-row'],
    ];
    $instance = new ValidatorColumnIndices(
      $configuration,
      $validator_id,
      $plugin_definition
    );
    $this->assertIsObject(
      $instance,
      "Unable to create $validator_id validator instance to test the Organism trait."
    );

    $this->instance = $instance;
  }

}

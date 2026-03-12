<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Core\Form\FormState;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;
use Drupal\tripal_chado\Database\ChadoConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Tripal Cultivate Project Exists Validator Plugins.
 *
 * @group trpcultivate
 * @group validators
 */
#[Group('trpcultivate')]
#[Group('validators')]
#[RunTestsInSeparateProcesses]
class ValidatorProjectExistsTest extends ChadoTestKernelBase {

  /**
   * The Validators plugin manager for creating new validator instances.
   *
   * @var \Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager
   */
  protected TripalCultivateValidatorManager $plugin_manager;

  /**
   * An array of projects for testing.
   *
   * @var array
   */
  protected array $test_project;

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

    // Create test project.
    $project_name = 'ATP - Advanced Test Project';
    $project_id = $this->chado_connection->insert('1:project')
      ->fields([
        'name' => $project_name,
        'description' => 'A test project - ' . $project_name,
      ])
      ->execute();

    $this->assertIsNumeric($project_id, 'We were not able to create the project ' . $project_name . ' for testing.');

    $this->test_project = [
      'non-existent' => [
        'name' => 'a non existent project',
        'id' => 1223,
      ],
      'project-exists' => [
        'name' => $project_name,
        'id' => $project_id,
      ],
    ];
  }

  /**
   * Data Provider: provides tests project to test ProjectExists validator.
   *
   * @return array
   *   Each scenario/element is an array with the following values:
   *   - A human-readable short description of the test scenario.
   *   - A string, test key that will reference a project in
   *     the $test_project property.
   *   - Expected validation response with the following keys:
   *     - 'case': the validation test case message.
   *     - 'valid': true if validation passed, false if failed.
   *     - 'failedItems': the failed items or input value retured by the
   *       validator if it returned a failed status. This key has the
   *       following keys:
   *       - 'name': the failed item if the input was the project name.
   *       - 'id': the failed item if the input was the project id.
   *       Both name and id key corresponds to project name and project id value
   *       in the $test_project property, respectively.
   */
  public static function provideProjectToProjectExistsValidator() {
    return [
      // # 0: A project that does not exist.
      [
        'non-existent project',
        'non-existent',
        [
          'case' => 'Project does not exist',
          'valid' => FALSE,
          'failedItems' => [
            'name' => ['project_provided' => 'name'],
            'id' => ['project_provided' => 'id'],
          ],
        ],
      ],

      // # 1: A project that does exist.
      [
        'existing project',
        'project-exists',
        [
          'case' => 'Project exists',
          'valid' => TRUE,
          'failedItems' => [
            'name' => [],
            'id' => [],
          ],
        ],
      ],
    ];
  }

  /**
   * Test project input that will trigger an exception.
   *
   * Test items that will throw exception:
   *  - Passing a string value.
   *  - Failed to implement a form field element with project name/key.
   *  - Passing object or the entire $form_state.
   */
  public function testProjectExistsExceptionCases() {
    // Create a plugin instance for this validator.
    $validator_id = 'project_exists';
    $instance = $this->plugin_manager->createInstance($validator_id);

    $form_values = 'Not a valid form values';

    $exception_caught  = FALSE;
    $exception_message = '';
    try {
      $instance->validateMetadata($form_values);
    }
    catch (\TypeError $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when passing a string to project exists validator.');
    $this->assertStringContainsString(
      'Argument #1 ($form_values) must be of type array, string given',
      $exception_message,
      'Expected exception message does not match message when passing string to project exists validator.');

    // A Drupal $form_state object.
    $form_state = new FormState();
    // A random field.
    $form_state->setValues(['project' => uniqid()]);

    $exception_caught  = FALSE;
    $exception_message = '';
    try {
      $instance->validateMetadata($form_state);
    }
    catch (\TypeError $e) {
      $exception_caught  = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Failed to catch exception when passing a $form_state to project exists validator.');
    $this->assertStringContainsString(
      'Argument #1 ($form_values) must be of type array, Drupal\Core\Form\FormState given',
      $exception_message,
      'Expected exception message does not match message when passing $form_state to project exists validator.');
  }

  /**
   * Test project exists.
   *
   * @param string $scenario
   *   A human-readable short description of the test scenario.
   * @param string $test_key
   *   A string, test key that will reference a project in
   *   the $test_project property.
   * @param array $expected
   *   Expected validation response with the following keys:
   *   - 'case': the validation test case message.
   *   - 'valid': true if validation passed, false if failed.
   *   - 'failedItems': the failed items or input value retured by the
   *     validator if it returned a failed status. This key has the
   *     following keys:
   *     - 'name': the failed item if the input was the project name.
   *     - 'id': the failed item if the input was the project id.
   *     Both name and id key corresponds to project name and project id value
   *     in the $test_project property, respectively.
   *
   * @dataProvider provideProjectToProjectExistsValidator
   */
  #[DataProvider('provideProjectToProjectExistsValidator')]
  public function testProjectExists($scenario, $test_key, $expected) {
    // Create a plugin instance for this validator.
    $validator_id = 'project_exists';
    $instance = $this->plugin_manager->createInstance($validator_id);

    foreach (['name', 'id'] as $input_type) {
      $validation_status = '';
      $form_values = ['project' => $this->test_project[$test_key][$input_type]];
      $validation_status = $instance->validateMetadata($form_values);

      $this->assertEquals(
        $expected['case'],
        $validation_status['case'],
        'The validation case title returned by Project Exists Validator does match expected validation case title in scenario ' . $scenario
      );

      $this->assertEquals(
        $expected['valid'],
        $validation_status['valid'],
        'The validation valid key returned by Project Exists Validator does match expected validation valid key value in scenario ' . $scenario
      );

      $expected['failedItems'][$input_type] = ($validation_status['valid'])
        ? [] : ['project_provided' => $this->test_project[$test_key][$input_type]];
      $project_provided = $expected['failedItems'][$input_type];

      $this->assertEquals(
        $project_provided,
        $validation_status['failedItems'],
        'The validation failedItems returned by Project Exists Validator does match expected validation failedItems in scenario ' . $scenario
      );
    }
  }

}

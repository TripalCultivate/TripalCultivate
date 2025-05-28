<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators\BasicallyBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;

/**
 * Tests Tripal Cultivate Validator Base functions.
 *
 * @group trpcultivate
 * @group validators
 */
class ValidatorBaseTest extends ChadoTestKernelBase {

  /**
   * The Validators plugin manager for creating new validator instances.
   *
   * @var \Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager
   */
  protected TripalCultivateValidatorManager $plugin_manager;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Configuration.
   *
   * @var config_entity
   */
  private $config;

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
    $this->config = \Drupal::configFactory()->getEditable('trpcultivate.settings');

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');
  }

  /**
   * Test the checkIndices() function in the Validator Base class.
   */
  public function testValidatorBaseCheckIndices() {

    $configuration = [];
    $validator_id = 'fake_basically_base';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Basically Base Validator',
      'input_types' => ['header-row', 'data-row'],
    ];
    $instance = new BasicallyBase($configuration, $validator_id, $plugin_definition);
    $this->assertIsObject(
      $instance,
      "Unable to create fake_basically_base validator instance to test the base class."
    );

    // Simulates a row within the Trait Importer.
    $file_row = [
      'My trait',
      'My trait description',
      'My method',
      'My method description',
      'My unit',
      'Qualitative',
    ];

    // Provide a valid list of indices.
    $indices = [0, 1, 2, 3, 4, 5];
    $exception_caught = FALSE;
    try {
      $instance->checkIndices($file_row, $indices);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
    }
    $this->assertFalse($exception_caught, 'Caught an exception from checkIndices() in spite of valid indices being provided.');

    // ------------ ERROR CASES ---------------
    // Provide an empty array of indices
    $indices = [];
    $exception_caught = FALSE;
    try {
      $instance->checkIndices($file_row, $indices);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
    }
    $this->assertTrue($exception_caught, 'Did not catch exception that should have occurred due to passing in an empty array of indices.');
    $this->assertStringContainsString('An empty indices array was provided.', $e->getMessage(), "Did not get the expected exception message when providing an empty array of indices.");

    // Provide too many indices.
    $indices = [0, 1, 2, 3, 4, 5, 6, 7];
    $exception_caught = FALSE;
    try {
      $instance->checkIndices($file_row, $indices);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
    }
    $this->assertTrue($exception_caught, 'Did not catch exception that should have occurred due to passing in too many indices compared to number of cells in the row.');
    $this->assertStringContainsString('Too many indices were provided (8) compared to the number of cells in the provided row (6)', $e->getMessage(), "Did not get the expected exception message when providing 8 indices compared to 6 values.");

    // Provide invalid indices.
    $indices = [1, -4, 77];
    $exception_caught = FALSE;
    try {
      $instance->checkIndices($file_row, $indices);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
    }
    $this->assertTrue($exception_caught, 'Did not catch exception that should have occurred due to passing in invalid indices.');
    $this->assertStringContainsString('One or more of the indices provided (-4, 77) is not valid when compared to the indices of the provided row', $e->getMessage(), "Did not get the expected exception message when providing 2 different invalid indices.");
  }

  /**
   * Test the basic getters.
   *
   * - getValidatorName()
   * - getConfigAllowNew()
   */
  public function testBasicValidatorGetters() {

    $configuration = [];
    $validator_id = 'fake_basically_base';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Basically Base Validator',
      'input_types' => ['header-row', 'data-row'],
    ];
    $instance = new BasicallyBase($configuration, $validator_id, $plugin_definition);
    $this->assertIsObject(
      $instance,
      "Unable to create fake_basically_base validator instance to test the base class."
    );

    // Check that we can get the name of the validator we requested above.
    // NOTE: this is the validator_name in the annotation.
    $expected_name = $plugin_definition['validator_name'];
    $returned_name = $instance->getValidatorName();
    $this->assertEquals($expected_name, $returned_name,
      "We did not recieve the name we expected when using getValidatorName() for $validator_id validator.");
  }

  /**
   * Test the input type focused getters.
   *
   * - getSupportedInputTypes()
   * - checkInputTypeSupported()
   */
  public function testInputTypeValidatorGetters() {

    $configuration = [];
    $validator_id = 'fake_basically_base';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Basically Base Validator',
      'input_types' => ['header-row', 'data-row'],
    ];
    $instance = new BasicallyBase($configuration, $validator_id, $plugin_definition);
    $this->assertIsObject(
      $instance,
      "Unable to create fake_basically_base validator instance to test the base class."
    );

    // Check that we can get the supported input types for this validator.
    // NOTE: assertEqualsCanonicalizing ensures order of arrays does NOT matter.
    $expected_input_types = ['data-row', 'header-row'];
    $returned_input_types = $instance->getSupportedInputTypes();
    $this->assertEqualsCanonicalizing($expected_input_types, $returned_input_types,
      "We did not get the expected input types for $validator_id validator when using getSupportedInputTypes().");

    // Check that we rightly get told the data-row is a supported input type.
    $dataRow_supported = $instance->checkInputTypeSupported('data-row');
    $this->assertTrue($dataRow_supported,
      "The data-row input type should be supported by $validator_id validator but checkInputTypeSupported() doesn't confirm this.");

    // Check that we rightly get told the data-row is a supported input type.
    $metadata_supported = $instance->checkInputTypeSupported('metadata');
    $this->assertFalse(
      $metadata_supported,
      "The metadata input type should NOT be supported by $validator_id validator but checkInputTypeSupported() doesn't confirm this."
    );

    // Check with an invalid inputType.
    $invalid_supported = $instance->checkInputTypeSupported('SARAH');
    $this->assertFalse(
      $invalid_supported,
      "The SARAH input type is invalid and thus should NOT be supported by $validator_id validator but checkInputTypeSupported() doesn't confirm this."
    );
  }

  /**
   * Test the validate methods.
   *
   * - validateMetadata()
   * - validateFile()
   * - validateRawRow()
   * - validateRow()
   *
   * NOTE: These should all throw an exception in the base class.
   */
  public function testValidatorValidateMethods() {

    $configuration = [];
    $validator_id = 'fake_basically_base';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Basically Base Validator',
      'input_types' => ['header-row', 'data-row'],
    ];
    $instance = new BasicallyBase($configuration, $validator_id, $plugin_definition);
    $this->assertIsObject(
      $instance,
      "Unable to create fake_basically_base validator instance to test the base class."
    );

    // Tests Base Class validateMetadata().
    $exception_caught = NULL;
    $exception_message = NULL;
    try {
      $form_values = ['genus' => 'Fred', 'project_id' => 123];
      $instance->validateMetadata($form_values);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "We expect to have an exception thrown when calling BasicallyBase::validateMetadata() since it should use the base class version."
    );
    $this->assertStringContainsString(
      'Method validateMetadata() from base class',
      $exception_message,
      "We did not get the exception message we expected when calling BasicallyBase::validateMetadata()"
    );

    // Tests Base Class validateFile().
    $exception_caught = NULL;
    $exception_message = NULL;
    try {
      $fid = 123;
      $instance->validateFile($fid);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "We expect to have an exception thrown when calling BasicallyBase::validateFile() since it should use the base class version."
    );
    $this->assertStringContainsString(
      'Method validateFile() from base class',
      $exception_message,
      "We did not get the exception message we expected when calling BasicallyBase::validateFile()"
    );

    // Tests Base Class validateRawRow().
    $exception_caught = NULL;
    $exception_message = NULL;
    try {
      $row_values = ['col1', 'col2', 'col3', 'col4', 'col5'];
      $row_string = implode("\t", $row_values);
      $instance->validateRawRow($row_string);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "We expect to have an exception thrown when calling BasicallyBase::validateRawRow() since it should use the base class version."
    );
    $this->assertStringContainsString(
      'Method validateRawRow() from base class',
      $exception_message,
      "We did not get the exception message we expected when calling BasicallyBase::validateRawRow()"
    );

    // Tests Base Class validateRow().
    $exception_caught = NULL;
    $exception_message = NULL;
    try {
      $row_values = ['col1', 'col2', 'col3', 'col4', 'col5'];
      $instance->validateRow($row_values);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "We expect to have an exception thrown when calling BasicallyBase::validateRow() since it should use the base class version."
    );
    $this->assertStringContainsString(
      'Method validateRow() from base class',
      $exception_message,
      "We did not get the exception message we expected when calling BasicallyBase::validateRow()"
    );
  }

  /**
   * Tests the Tripal Logger setter and getter.
   *
   * - ValidatorBase::setLogger()
   * - ValidatorBase::getLogger()
   */
  public function testTripalLoggerGetterSetter() {
    $configuration = [];
    $validator_id = 'fake_basically_base';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Basically Base Validator',
      'input_types' => ['header-row', 'data-row'],
    ];
    $instance = new BasicallyBase($configuration, $validator_id, $plugin_definition);
    $this->assertIsObject(
      $instance,
      "Unable to create fake_basically_base validator instance to test the base class."
    );

    // Try to get the logger before it has been set
    // Exception message should trigger.
    $expected_message = 'Cannot retrieve the Tripal Logger property as one has not been set for this validator using the setLogger() method.';
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $instance->getLogger();
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }

    $this->assertTrue($exception_caught, 'Calling getLogger() before the setLogger() method should have thrown an exception but did not.');
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      "The exception thrown does not have the message we expected when trying to get the Tripal Logger property but it hasn't been set yet."
    );

    // Create a TripalLogger object and set it using setLogger()
    $my_logger = \Drupal::service('tripal.logger');

    $exception_caught = FALSE;
    try {
      $instance->setLogger($my_logger);
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertFalse(
      $exception_caught,
      "Calling setLogger() with a valid TripalLogger object should not have thrown an exception but it threw '$exception_message'"
    );

    // Now make sure we can get the logger that was set.
    $grabbed_logger = NULL;
    $exception_caught = FALSE;
    try {
      $grabbed_logger = $instance->getLogger();
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertFalse(
      $exception_caught,
      "Calling getLogger() after being set with setLogger() should not have thrown an exception but it threw '$exception_message'"
    );
    $this->assertEquals(
      $my_logger,
      $grabbed_logger,
      'Could not grab the TripalLogger object using getLogger() despite having called setLogger() on it.'
    );
  }

}

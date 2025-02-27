<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators\ValidatorOrganism;
use Drupal\tripal\Services\TripalLogger;
use Drupal\tripal_chado\Database\ChadoConnection;

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
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var \Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

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

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    // Create a fake plugin instance for testing.
    $configuration = [];
    $validator_id = 'validator_requiring_organism';
    $plugin_definition = [
      'id' => $validator_id,
      'validator_name' => 'Validator Using Organism Trait',
      'input_types' => ['header-row', 'data-row'],
    ];
    $instance = new ValidatorOrganism(
      $configuration,
      $validator_id,
      $plugin_definition,
      $this->chado_connection,
    );
    $this->assertIsObject(
      $instance,
      "Unable to create $validator_id validator instance to test the Organism trait."
    );

    // We need to mock the logger to test the progress reporting.
    $mock_logger = $this->getMockBuilder(TripalLogger::class)
      ->onlyMethods(['error'])
      ->getMock();
    $mock_logger->method('error')
      ->willReturnCallback(function ($message, $context, $options) {
        print str_replace(array_keys($context), $context, $message);
        return NULL;
      });
    // Finally, use setLogger() for this validator instance.
    $instance->setLogger($mock_logger);

    $this->instance = $instance;
  }

  /**
   * Tests the Organism setters and getter.
   *
   * Specifically,
   *   - setOrganismID()
   *   - setGenus()
   *   - getOrganismIDs()
   */
  public function testOrganismSetterGetter() {

    // Try setting the organism with a random (nonexisting) organism ID.
    $organism_id = 5;
    $printed_output = '';
    $expected_message = "The organism ID $organism_id was not found in chado.organism.";
    ob_start();
    $this->instance->setOrganismID($organism_id);
    $printed_output = ob_get_clean();
    $this->assertStringContainsString(
      $expected_message,
      $printed_output,
      "The logged error message does not have the message we expected for an organism that doesn't even exist in chado."
    );

    // Try calling the getter, after setOrganismID was not successful.
    $expected_message = "Cannot retrieve an array of organism IDs as one has not been set by either the setOrganismID() or setGenus() method.";
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $this->instance->getOrganismIDs();
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertTrue(
      $exception_caught,
      "Calling getOrganismIDs() when an organism ID/genus has not been succesfully set yet should have thrown an exception but didn't."
    );
    $this->assertStringContainsString(
      $expected_message,
      $exception_message,
      "The exception thrown does not have the message we expected when trying to get an array of organism IDs but one hasn't been set yet."
    );

    // Insert an organism into chado, then try setting it using setOrganismID().
    $genus = 'Tripalus';
    $species = 'databasica';
    $inserted_organism_id = $this->chado_connection->insert('1:organism')
      ->fields([
        'genus' => $genus,
        'species' => $species,
      ])
      ->execute();
    $this->assertIsNumeric($inserted_organism_id, 'We were not able to create the organism ' . $genus . ' ' . $species . ' in Chado for testing.');
    // Cast our ID to an int since querying Chado gives us a string.
    $inserted_organism_id = (int) $inserted_organism_id;
    $this->instance->setOrganismID($inserted_organism_id);

    // Now use our getter to grab our organism ID.
    $exception_caught = FALSE;
    $exception_message = 'NONE';
    try {
      $grabbed_organism_id = $this->instance->getOrganismIDs();
    }
    catch (\Exception $e) {
      $exception_caught = TRUE;
      $exception_message = $e->getMessage();
    }
    $this->assertFalse(
      $exception_caught,
      "Calling getOrganismIDs() when one was created and then set should not have thrown an exception but threw '$exception_message'."
    );
    // Check that we were returned an array of IDs.
    $this->assertIsArray(
      $grabbed_organism_id,
      "We expected getOrganismIDs() to return an array, but it did not."
    );
    // Check that we were returned an array with a single ID.
    $this->assertCount(1, $grabbed_organism_id, "We expected getOrganismIDs to return an array with only 1 ID, but it contained a different amount.");
    $this->assertContains(
      $inserted_organism_id,
      $grabbed_organism_id,
      "The organism ID retrieved using getOrganismIDs() is not the same as the ID given to setOrganismID()."
    );
  }

}

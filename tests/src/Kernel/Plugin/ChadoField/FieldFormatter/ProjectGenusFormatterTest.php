<?php

namespace Drupal\Tests\trpcultivate\Kernel\Plugin\ChadoField\FieldFormatter;

use Drupal\Core\Render\Element;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\tripal_chado\Traits\ChadoFieldTestTrait;
use Drupal\tripal\Entity\TripalEntity;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the ProjectGenus field formatter.
 */
#[Group('tripal-field')]
#[Group('chado-field')]
#[RunTestsInSeparateProcesses]
class ProjectGenusFormatterTest extends ChadoTestKernelBase {

  use ChadoFieldTestTrait;

  /**
   * The modules that this test depends on.
   *
   * NOTE: since this is a kernel test, these modules are not being installed
   * but are available to be installed.
   *
   * @var array
   */
  protected static $modules = [
    'system',
    'user',
    'path',
    'path_alias',
    'field',
    'datetime',
    'tripal',
    'tripal_chado',
    'trpcultivate',
  ];

  /**
   * The YAML file indicating the scenarios to test and how to setup the enviro.
   *
   * @var string
   */
  protected string $yaml_info_file = __DIR__ . '/../FieldFormatter/ProjectGenusFormatter-TestInfo.yml';

  /**
   * The test chado connection. It is also set in the container.
   *
   * @var ChadoConnection
   */
  protected object $chado_connection;

  /**
   * The test drupal connection. It is also set in the container.
   *
   * @var object
   */
  protected object $drupal_connection;

  /**
   * Describes the environment to setup for this test.
   *
   * @var array
   *   An array with the following keys:
   *   - chado_version: the version of chado to test under.
   *   - bundle: an array defining the tripal entity type to create.
   *   - fields: a list of fields to be attached the above bundle.
   */
  protected array $system_under_test;

  /**
   * The TripalEntityType id of the bundle being used in this test.
   *
   * @var string
   */
  protected string $bundle_name;

  /**
   * Describes the scenarios to test.
   *
   * This will be used in combination with the data provider. It can't be
   * accessed directly in the dataProvider due to the way that PHPUnit is
   * setup.
   *
   * @var array
   *  A list of scenarios where each one has the following keys:
   *  - label: A human-readable label for the scenario to be used in assert
   *    messages.
   *  - descrition: A description of the scenario and what you are wanting to
   *    test. This will not be used in the test but is rather there to help
   *    people reading the YAML file and to make it easier to maintain.
   *  - user input: An array of the values to be provided when creating a
   *    TripalEntity. There should be a key matching the name of each field in
   *    the system-under-test and it's value should be an array containing all
   *    the property types for that field mapped to a value.
   *  - is_empty: A boolean indicating whether the field is empty or not.
   *  - expected_genus: An array describing what we expect for the genus values.
   *   - count: The expected number of values for the genus list.
   *   - values: An array of the expected values of the genus list.
   *  - expected_sciname: An array describing what we expect for the
   *    scientific name values.
   *   - count: The expected number of values for the scientific name list.
   *   - values: An array of the expected values of the scientific name list.
   */
  protected array $scenarios;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupal_connection = $this->container->get('database');
    [$this->system_under_test, $this->scenarios] = $this->getTestInfoFromYaml($this->yaml_info_file);
    $this->bundle_name = $this->system_under_test['bundle']['id'];

    $this->chado_connection = $this->getTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO, $this->system_under_test['chado_version']);
    $this->setupChadoEntityFieldTestEnvironment($this->system_under_test);
    $this->installSchema('tripal_chado', ['tripal_custom_tables', 'tripal_mviews']);
  }

  /**
   * Data Provider: works with the YAML to provide scenarios for testing.
   *
   * @return array
   *   List of scenarios to test where each one matches a key and label in the
   *   associated YAML scenarios.
   */
  public static function provideScenarios() {
    $scenarios = [];

    $scenarios[] = [
      0,
      "Single organism",
    ];

    $scenarios[] = [
      1,
      "Three organisms, two share genus",
    ];

    $scenarios[] = [
      2,
      "No organisms",
    ];

    return $scenarios;
  }

  /**
   * Tests that the formatter renders the genus and scientific name.
   *
   * @param int $current_scenario_key
   *   The key of the scenario in the YAML.
   * @param string $current_scenario_label
   *   The label of the scenario in the YAML.
   *
   * @dataProvider provideScenarios
   */
  #[DataProvider('provideScenarios')]
  public function testFormatterRendersGenusAndScientificName(int $current_scenario_key, string $current_scenario_label) {
    $scenario = $this->getYamlScenario($current_scenario_key, $current_scenario_label);

    // Create an entity with the expected field values.
    $entity = TripalEntity::create([
      'title' => $this->randomString(),
      'type' => $this->bundle_name,
    ] + $scenario['user_input']);
    $this->assertInstanceOf(TripalEntity::class, $entity);
    $status = $entity->save();
    $this->assertEquals(SAVED_NEW, $status);

    // Render the field directly to ensure the formatter's render array is
    // returned for inspection.
    $build = $entity->get('exp_organism')->view();

    // Retrieve only the children of the render array that is produced by
    // the formatter.
    $formatter_children = Element::children($build);

    // If this is a scenario where there are no organisms,
    // then the formatter should return an empty render array.
    if ($scenario['is_empty']) {
      $this->assertEmpty($formatter_children, 'The formatter should not return any render array children when there are no organisms.');
      return;
    }

    // First we want to confirm we have an item list with two elements returned,
    // one for genus and one for scientific name.
    $this->assertCount(2, $formatter_children, 'Formatter should produce two elements: the genus and scientific name.');

    // The first element should be the genus.
    // It should be an item_list with the 1+ genus value(s) in it.
    $genus_list = $build[0]['#items'];
    $this->assertCount($scenario['expected_genus']['count'], $genus_list, 'The genus list should contain the expected number of values.');
    $this->assertEqualsCanonicalizing($scenario['expected_genus']['values'], $genus_list, 'The genus list should contain the expected values.');

    // The second element should be the scientific name.
    // It should be an item_list with the 1+ scientific name value(s) in it.
    $sciname_list = $build[1]['#items'];
    $this->assertCount($scenario['expected_sciname']['count'], $sciname_list, 'The scientific name list should contain the expected number of values.');
    $this->assertEqualsCanonicalizing($scenario['expected_sciname']['values'], $sciname_list, 'The scientific name list should contain the expected values.');
  }

}

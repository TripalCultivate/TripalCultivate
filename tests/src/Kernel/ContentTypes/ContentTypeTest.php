<?php

namespace Drupal\Tests\trpcultivate\Kernel\ContentTypes;

use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the content types and fields associated with them are created.
 *
 * @group ContentTypes
 * @group Fields
 */
#[Group('ContentTypes')]
#[Group('Fields')]
#[RunTestsInSeparateProcesses]
class ContentTypeTest extends ChadoTestKernelBase {

  /**
   * Theme used in the test environment.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
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
   * The setup module service.
   *
   * @var \Drupal\trpcultivate\Service\SetupModuleService
   */
  protected $setupService;

  /**
   * The expected content types imported by this module.
   *
   * @var array
   */
  protected $expected_contenttypes = [
    'Research Management' => [
      'research_grant' => 11,
      'grant_section' => 7,
      'research_study' => 17,
      'research_experiment' => 37,
    ],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Firs prepare our test environment.
    $this->prepareEnvironment(['TripalTerm', 'TripalEntity']);
    // ... we need the term yamls for chado.
    $this->installConfig('tripal_chado');
    // ... we need the layout entities for our content types.
    $this->installEntitySchema('tripal_layout_default_form');
    $this->installEntitySchema('tripal_layout_default_view');
    // ... we need our own modules config.
    $this->installConfig('trpcultivate');

    // Initialize the chado instance with all the records
    // that would be present after running prepare.
    $this->chado_connection = $this->getTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);

    // ... we need our own modules config.
    $this->setupService = \Drupal::service('trpcultivate.setup_module_service');
  }

  /**
   * Tests importing content type collections.
   *
   * More specifically, run the callback that imports our content type
   * collections and confirm all types and fields are added.
   */
  public function testImportContentTypeCallback() {

    // First import the needed terms.
    $this->setupService->installTerms();

    // -- And create the terms added by core.
    $terms_setup = \Drupal::service('tripal_chado.terms_init');
    $terms_setup->installTerms();

    // Then import the content types and their fields.
    $this->setupService->importContenttypes();

    // Now select all content types by category
    // and see if they match expectations.
    foreach ($this->expected_contenttypes as $category => $expected_types) {
      $found_types = \Drupal::entityTypeManager()
        ->getStorage('tripal_entity_type')
        ->loadByProperties(['category' => $category]);
      $expected_count = count($expected_types);
      $this->assertCount($expected_count, $found_types,
        "We did not get the expected number of types in the $category category.");
      foreach ($expected_types as $expected_id => $expected_field_count) {
        $this->assertArrayHasKey($expected_id, $found_types,
          "This particular type was expected but not found when selecting by category $category.");

        // Now check that this content type has fields.
        $found_fields = \Drupal::service('entity_field.manager')
          ->getFieldDefinitions('tripal_entity', $expected_id);
        // This returns the 9 base fields too, so we add them to the list.
        // Specifically, id, type, uid, title, status, created, changed, path.
        $expected_count = $expected_field_count + 8;
        $this->assertCount($expected_count, $found_fields,
          "We did not see the expected number of fields attached to the $expected_id content type.");
      }
    }
  }

}

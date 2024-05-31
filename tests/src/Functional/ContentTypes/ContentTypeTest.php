<?php

namespace Drupal\Tests\trpcultivate\Functional\ContentTypes;

use Drupal\Tests\tripal_chado\Functional\ChadoTestBrowserBase;

/**
 * Tests that the content types and fields associated with them are created.
 *
 * @group ContentTypes
 * @group Fields
 */
class ContentTypeTest extends ChadoTestBrowserBase {
  protected $defaultTheme = 'stark';

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['tripal','user','field','trpcultivate'];

  /**
   * Test Chado connection.
   *
   * @var ChadoConnection
   */
  protected $connection;

  /**
   * The expected content types imported by this module.
   *
   * @var array
   */
  protected $expected_contenttypes = [
    'Experiment Types' => [
      'field_experiment' => 10,
      'greenhouse_experiment' => 10,
      'growthchamber_experiment' => 10,
      'biochem_experiment' => 10,
    ],
  ];


  /**
   * {@inheritdoc}
   */
  protected function setUp() :void {
    parent::setUp();

    // Initialize the chado instance with all the records that would be present after running prepare.
    $this->connection = $this->getTestSchema(ChadoTestBrowserBase::PREPARE_TEST_CHADO);
  }

  /**
   * Run the callback that imports our content type collections
   * and confirm all types and fields are added.
   */
  public function testImportContentTypeCallback() {

    // First import the needed terms.
    \trpcultivate_install_terms();
    // -- And create the terms added by core.
    $terms_setup = \Drupal::service('tripal_chado.terms_init');
    $terms_setup->installTerms();

    // Then import the content types and their fields.
    \trpcultivate_import_contenttypes();

    // Now select all content types by category and see if they match expectations.
    foreach ($this->expected_contenttypes as $category => $expected_types) {
      $found_types = \Drupal::entityTypeManager()
        ->getStorage('tripal_entity_type')
        ->loadByProperties(['category' => $category]);
      $expected_count = sizeof($expected_types);
      $this->assertCount($expected_count, $found_types,
        "We did not get the expected number of types in the $category category.");
      foreach ($expected_types as $expected_id => $expected_field_count) {
        $this->assertArrayHasKey($expected_id, $found_types,
          "This particular type was expected but not found when selecting by category $category.");

        // Now check that this content type has fields.
        $found_fields = \Drupal::service('entity_field.manager')
          ->getFieldDefinitions('tripal_entity', $expected_id);
        // This returns the 8 base fields too (i.e. id, type, uid, title, status, created, changed)
        // so we add them to the list.
        $expected_count = $expected_field_count + 8;
        $this->assertCount($expected_count, $found_fields,
          "We did not see the expected number of fields attached to the $expected_id content type.");
      }
    }
  }
}

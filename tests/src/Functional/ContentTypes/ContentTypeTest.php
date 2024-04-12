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
      'field_experiment', 'greenhouse_experiment', 'growthchamber_experiment',
      'biochem_experiment', 'ecosystem_survey'
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
  }
}

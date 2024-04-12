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
    // -- And create the idspace/vocab for terms we used that were added by core.
    $idsmanager = \Drupal::service('tripal.collection_plugin_manager.idspace');
    $vmanager = \Drupal::service('tripal.collection_plugin_manager.vocabulary');
    $idsmanager->createCollection('schema', "chado_id_space");
    $vmanager->createCollection('schema', "chado_vocabulary");
    $idsmanager->createCollection('TPUB', "chado_id_space");
    $vmanager->createCollection('tripal_pub', "chado_vocabulary");
    $this->createTripalTerm([
      'vocab_name' => 'sbo',
      'id_space_name' => 'SBO',
      'term' => [
        'name' => 'reference annotation',
        'definition' => 'Additional information that supplements existing data, usually in a document, by providing a link to more detailed information, which is held externally, or elsewhere.',
        'accession' =>'0000552',
      ]],
      'chado_id_space', 'chado_vocabulary'
    );

    // Then import the content types and their fields.
    \trpcultivate_import_contenttypes();
  }
}

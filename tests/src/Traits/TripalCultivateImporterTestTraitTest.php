<?php

namespace Drupal\Tests\tripalcultivate\Traits;

use Drupal\file\Entity\File;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\Tests\trpcultivate\Traits\TripalCultivateImporterTestTrait;

/**
 * Test importer test trait.
 *
 * @group trpcultivate_test_trait
 */
class TripalCultivateImporterTestTraitTest extends ChadoTestKernelBase {

  use TripalCultivateImporterTestTrait;

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
  protected function setUp() :void {
    parent::setUp();

    \Drupal::state()->set('is_a_test_environment', TRUE);
    $this->installEntitySchema('file');
  }

  /**
   * Test createTestFile() method in the test trait.
   */
  public function testCreateTestFile() {
    $file_details = [
      'ext' => 'txt',
      'mime' => 'text/plain',
      'content' => [
        'string' => '',
        'file' => 'pdf.txt',
      ],
    ];

    $file_fixtures = [
      // Use default module's test Fixtures directory.
      '',
      // Use this specific test Fixtures directory.
      __DIR__ . '/../Fixtures/',
    ];

    foreach ($file_fixtures as $fixture) {
      $new_file_details = $file_details;
      $new_file_details['fixturepath'] = $fixture;
      $file = $this->createTestFile($new_file_details);

      $this->assertInstanceOf(
        File::class,
        $file,
        'Test trait createTestFile() failed to create expected file object'
      );
    }
  }

}

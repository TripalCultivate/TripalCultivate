<?php

namespace Drupal\Tests\trpcultivate\Kernel\Importer;

use Drupal\Core\Render\Markup;
use Drupal\Core\Render\Renderer;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;

/**
 * Tests window templates used in importer.
 *
 * @group trpcultivate
 * @group templates
 */
class ImporterWindowTemplatesTest extends ChadoTestKernelBase {

  /**
   * The Drupal Renderer.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected Renderer $service_Renderer;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
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

    $container = \Drupal::getContainer();
    $this->service_Renderer = $container->get('renderer');
  }

  /**
   * Test importer describe header window.
   */
  public function testDescribeHeaderWindow() {
    $theme_name = 'describe_header_window';
    $theme_data = [
      'headers' => [
        [
          'name' => 'Header 1',
          'description' => 'The header 1',
          'type' => 'required',
        ],
        [
          'name' => 'Header 2',
          'description' => 'The header 2',
          'type' => 'optional',
        ],
      ],
      'file_extensions' => 'txt, tsv, xlsx',
      'notes' => 'Your data file must contain a header row.',
      'template_file' => 'localhost/mysite/templatefile.tsv',
    ];

    $header_window = [
      '#theme' => $theme_name,
      '#data' => $theme_data,
    ];

    $window_markup = $this->service_Renderer
      ->renderInIsolation($header_window);

    $this->assertInstanceOf(Markup::class, $window_markup);
  }

  /**
   * Test importer validation result window.
   */
  public function testValidationResultWindow() {
    $theme_name = 'validation_result_window';
    $theme_data = [
      'validation_result' => [
        'Genus Exists' => [
          'status' => 'pass',
          'failedItems' => [],
        ],
      ],
    ];

    $validation_window = [
      '#theme' => $theme_name,
      '#data' => $theme_data,
    ];

    $window_markup = $this->service_Renderer
      ->renderInIsolation($validation_window);

    $this->assertInstanceOf(Markup::class, $window_markup);
  }

}

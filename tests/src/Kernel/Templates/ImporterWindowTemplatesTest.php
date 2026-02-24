<?php

namespace Drupal\Tests\trpcultivate\Kernel\Importer;

use Drupal\Core\Render\Markup;
use Drupal\Core\Render\Renderer;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests window templates used in importer.
 *
 * @group trpcultivate
 * @group templates
 */
#[Group('trpcultivate')]
#[Group('templates')]
#[RunTestsInSeparateProcesses]
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
   *
   * The test headers render array provided below is a copy of the render array
   * defined by phenotypes traits importer.
   */
  public function testDescribeHeaderWindow() {
    $theme_name = 'describe_header_window';
    $theme_data = [
      'headers' => [
        [
          'name' => 'Trait Name',
          'description' => 'The name of the trait, as you would like it to appear to the user (e.g. Days to Flower)',
          'type' => 'required',
        ],
        [
          'name' => 'Trait Description',
          'description' => 'A full description of the trait. This is recommended to be at least one paragraph.',
          'type' => 'required',
        ],
        [
          'name' => 'Method Short Name',
          'description' => 'A full, unique title for the method (e.g. Days till 10% of plants/plot have flowers)',
          'type' => 'required',
        ],
        [
          'name' => 'Collection Method',
          'description' => 'A full description of how the trait was collected. This is also recommended to be at least one paragraph.',
          'type' => 'required',
        ],
        [
          'name' => 'Unit',
          'description' => 'The full name of the unit used (e.g. days, centimeters)',
          'type' => 'required',
        ],
        [
          'name' => 'Type',
          'description' => 'One of "Qualitative" or "Quantitative".',
          'type' => 'required',
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
   *
   * The test validation result render array below is a render array that a
   * trait importer would create if data file is empty.
   */
  public function testValidationResultWindow() {
    $theme_name = 'validation_result_window';
    $theme_data = [
      'validation_result' => [
        'genus_exists' => [
          'title' => 'The genus is valid',
          'status' => 'pass',
          'details' => [],
        ],
        'valid_data_file' => [
          'title' => 'File is valid and not empty',
          'status' => 'fail',
          'details' => [
            '#type' => 'item',
            '#title' => 'The file provided has no contents in it to import. Please ensure your file has the expected header row and at least one row of data.',
            '#wrapper_attributes' => [
              'class' => [
                'tcp-valid-data-file-failures',
              ],
            ],
            'items' => [
              '#theme' => 'item_list',
              '#type' => 'ul',
              '#items' => [
                'Filename: my-template-file.tsv',
              ],
            ],
          ],
        ],
        'valid_delimited_file' => [
          'title' => 'Lines are properly delimited',
          'status' => 'todo',
          'details' => '',
        ],
        'valid_header' => [
          'title' => 'File has all of the column headers expected',
          'status' => 'todo',
          'details' => '',
        ],
        'empty_cell' => [
          'title' => 'Required cells contain a value',
          'status' => 'todo',
          'details' => '',
        ],
        'valid_data_type' => [
          'title' => 'Values in required cells are valid',
          'status' => 'todo',
          'details' => '',
        ],
        'duplicate_traits' => [
          'title' => 'All trait-method-unit combinations are unique',
          'status' => 'todo',
          'details' => '',
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

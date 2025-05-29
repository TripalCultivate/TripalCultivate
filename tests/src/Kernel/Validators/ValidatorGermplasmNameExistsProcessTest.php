<?php

namespace Drupal\Tests\trpcultivate_phenotypes\Kernel\Validators;

use Drupal\Core\Render\Renderer;
use Drupal\Tests\tripal_chado\Kernel\ChadoTestKernelBase;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorManager;

/**
 * Tests any message processing methods for the GermplasmNameExists validator.
 *
 * @group trpcultivate
 * @group validators
 */
class ValidatorGermplasmNameExistsProcessTest extends ChadoTestKernelBase {

  /**
   * Plugin Manager service.
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
   * Drupal render service.
   *
   * @var Drupal\Core\Render\RendererInterface
   */
  protected Renderer $renderer;

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
    'file',
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

    // Ensure we see all logging in tests.
    \Drupal::state()->set('is_a_test_environment', TRUE);

    // Test Chado database.
    // Create a test chado instance and then set it in the container for use by
    // our service.
    $this->chado_connection = $this->createTestSchema(ChadoTestKernelBase::PREPARE_TEST_CHADO);
    $this->container->set('tripal_chado.database', $this->chado_connection);

    // Set plugin manager service.
    $this->plugin_manager = \Drupal::service('plugin.manager.trpcultivate_validator');

    // Get our renderer.
    $this->renderer = $this->container->get('renderer');
  }

  /**
   * Data Provider for processor for GermplasmNameExists()
   *
   * @return array
   *   Each scenario is an array with the following:
   *   - The validation result array that gets passed to the process method. It
   *     contains the following keys:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed with the following keys.
   *       @todo summarize the failedItems that are used by this processor
   *   - An array of tokens to use for altering the messages that get displayed
   *     to the user. The key is the token, (ex. 'project'), and the value is
   *     the new value to be shown for that token.
   *   - An array of metadata needed by the process method. The method supports
   *     the following keys:
   *     - 'column_headers': an array of headers for columns that
   *       are expected to contain germplasm names. The index in this array MUST
   *       match the position (starting with 0) of the column in the input file.
   *   - An array of expectations in the rendered output which has the following
   *     keys:
   *     - 'expected_message': The message expected in the return value of the
   *       process method for this scenario.
   *     - 'expected_column_count': The number of columns expected in the
   *       rendered table for this scenario.
   *     - 'expected_table_rows': 1+ arrays keyed by the line number in the
   *       input file that triggered the failed validation status, further keyed
   *       by the column header name of a cell in this row and its value is the
   *       invalid value. For example:
   *       - 2 => [ 'Type' => 'Invalid Value' ]
   */
  public function provideGermplasmNameExistsFailedCases() {
    $scenarios = [];

    return $scenarios;

  }

  /**
   * Tests the message processor method for the GermplasmNameExists validator.
   *
   * @param array $validation_result
   *   The validation result array that gets passed to the process method. It
   *   contains the following keys:
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': FALSE to indicate that validation failed.
   *   - 'failedItems': an array of items that failed with the following keys.
   *     - 'project_provided': The name of the project provided.
   *     - 'genus_provided': The name of the genus provided.
   * @param array $tokens
   *   An array of tokens to use for altering the messages that get displayed
   *   to the user. The key is the token, (ex. 'project'), and the value is
   *   the new value to be shown for that token.
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following keys are expected:
   *   - 'column_headers': This contains an array of headers for columns that
   *     are expected to contain germplasm names. The index in this array MUST
   *     match the position (starting with 0) of the column in the input file.
   * @param array $expectations
   *   An array of the expected items in the rendered output. It has the
   *   following keys:
   *   - 'expected_message': The message expected in the return value of the
   *     process method for this scenario.
   *   - 'expected_column_count': The number of columns expected in the
   *     rendered table for this scenario.
   *   - 'expected_table_rows': 1+ arrays keyed by the line number in the
   *     input file that triggered the failed validation status, further keyed
   *     by the column header name of a cell in this row and its value is the
   *     invalid value. For example:
   *     - 2 => [ 'Type' => 'Invalid Value' ].
   *
   * @dataProvider provideGermplasmNameExistsFailedCases
   */
  public function testProcess(array $validation_result, array $tokens, array $metadata, array $expectations) {

    // Create a plugin instance for this validator.
    $validator_id = 'germplasm_name_exists';
    $instance = $this->plugin_manager->createInstance($validator_id);

    // Call the process method on our validation result.
    $render_array = $instance->process($validation_result, $tokens, $metadata);

    // Render the array we were returned.
    $rendered_markup = $this->renderer->renderRoot($render_array);
    $this->setRawContent($rendered_markup);

    // Check the render array here.
  }

}

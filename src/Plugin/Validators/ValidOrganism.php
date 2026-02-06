<?php

namespace Drupal\trpcultivate\Plugin\Validators;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal_chado\ChadoBuddy\PluginManagers\ChadoBuddyPluginManager;
use Drupal\tripal_chado\Plugin\ChadoBuddy\ChadoOrganismBuddy;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\Attribute\TripalCultivateValidator;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\ColumnIndices;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\Organism;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validates organism data.
 */
#[TripalCultivateValidator(
   id: 'valid_organism',
   validator_name: new TranslatableMarkup('Valid Organism Validator'),
   input_types: ['metadata', 'data-row']
 )]
class ValidOrganism extends TripalCultivateValidatorBase implements ContainerFactoryPluginInterface {

  /**
   * Validator Traits required by this validator.
   *
   * - ColumnIndices: Gets an array of indices corresponding to the cells in
   *   $row_values to validate.
   * - ColumnIndices: Gets an array of indices corresponding to the cells in
   *   $row_values to validate.
   * - Organism: Gets an array of organism IDs.
  */
  use ColumnIndices;
  use Organism;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * The Chado Buddy service manager.
   *
   * @var Drupal\tripal_chado\ChadoBuddy\PluginManagers\ChadoBuddyPluginManager
   */
  protected ChadoBuddyPluginManager $buddy_manager;

  /**
   * The Chado Buddy organism.
   *
   * @var \Drupal\tripal_chado\Plugin\ChadoBuddy\ChadoOrganismBuddy
   */
  protected ChadoOrganismBuddy $organism_buddy;

  /**
   * Mapping of validation cases to tokens and messages.
   *
   * @var array
   */
  protected static array $mapping = [
    'case-empty-organism' => [
      'token' => 'case-empty-organism',
      'dev-case' => 'Unable to lookup organism with empty values',
      'default-msg' => 'One or more cells which are required to contain organism names were empty. Please ensure that you have entered existing organism names for all cells in the following columns: [column-headers]',
    ],
    'case-missing-organism' => [
      'token' => 'case-missing-organism',
      'dev-case' => 'Missing organism name(s) in the database',
      'default-msg' => 'The following organism names do not match any existing in this site. Please make sure you have entered the names exactly as they appear on the organism pages or [contact-admin] to have them added if they do not yet exist.',
    ],
    'case-valid' => [
      'token' => 'case-valid',
      'dev-case' => 'Organism name(s) exist(s) in the database',
    ],
  ];

  /**
   * Constructs a ValidOrganism validator plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   A Database query interface for querying Chado using Tripal DBX.
   * @param \Drupal\tripal_chado\ChadoBuddy\PluginManagers\ChadoBuddyPluginManager $buddy_manager
   *   The Chado Buddy service manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ChadoConnection $chado_connection,
    ChadoBuddyPluginManager $buddy_manager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->chado_connection = $chado_connection;
    $this->buddy_manager = $buddy_manager;
    $this->organism_buddy = $this->buddy_manager->createInstance('chado_organism_buddy', []);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tripal_chado.database'),
      $container->get('tripal_chado.chado_buddy'),
    );
  }

  /**
   * Validates that the organism data is correct.
   *
   * @param array $form_values
   *   An array of values from the submitted form where each key maps to a form
   *   element and the value is what the user entered.
   *   Each form element value can be accessed using the field element key
   *   ie. field name/key organism - $form_values['organism'].
   *
   *   This array is the result of calling $form_state->getValues().
   *
   * @return array
   *   An associative array with the following keys.
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': TRUE if the provided organism data is valid, FALSE otherwise.
   *   - 'failedItems': an array of items that failed with the following keys.
   *     This is an empty array if the metadata input was valid.
   *     - 'organism_provided': The name(s) of the organism(s) provided.
   *
   * @throws \Exception
   *   - If the expected keys do not exist in $form_values.
   */
  public function validateMetadata(array $form_values) {
    $expected_field_key = 'organism';

    if (!array_key_exists($expected_field_key, $form_values)) {
      throw new \Exception('Failed to locate organism field element. ValidOrganism validator expects a form field element name organism.');
    }

    $case = 'case-valid';
    $valid = TRUE;
    $failed_items = [];

    $organism_input = trim($form_values[$expected_field_key]);

    $organism_id_array = $this->organism_buddy->getOrganismFromScientificName($organism_input);
    $organism_id = 0;
    if (array_key_exists(0, $organism_id_array)) {
      $organism_id = $organism_id_array[0]->getValue('organism.organism_id');
    }

    if ($organism_id <= 0 || empty($organism_id)) {
      $case = 'case-missing-organism';
      $valid = FALSE;
      $failed_items = ['organism_provided' => $organism_input];
    }

    return [
      'case' => $case,
      'valid' => $valid,
      'failedItems' => $failed_items,
    ];
  }

  /**
   * Validates that the organism data in the row is correct.
   *
   * @param array $row_values
   *   An array of values from a single row where each key maps to a column
   *   index and the value is what the user entered for that column.
   *
   * @return array
   *   An associative array with the following keys.
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': TRUE if the provided organism data is valid, FALSE otherwise.
   *   - 'failedItems': an array of items that failed with the following keys.
   *     This is an empty array if the row input was valid.
   *     - 'organism_ids': The organism IDs that were being looked up.
   *     - 'empty_cells': An array of indices for cells that were empty.
   *     - 'missing_cells': An array of indices and organism names for cells
   *       where the organism name was not found in the database.
   *
   * @throws \Exception
   *   - If the specified indices are not valid for the provided row values.
   *   - If no organism IDs were set via setOrganismID() or setGenus().
   */
  public function validateRow(array $row_values) {

    // Grab our indices.
    $indices = $this->getIndices();

    // Chack the indices provided are valid in the context of the row.
    // Will throw an exception if there's a problem.
    $this->checkIndices($row_values, $indices);

    // Grab our list of organism IDs.
    $organism_ids = $this->getOrganismIDs();

    // Initialize our flags for keeping track of validation status.
    $empty = FALSE;
    $missing = FALSE;
    $failedItems = [];

    // Add our array of organism IDs to failedItems for our failed cases.
    $failedItems['organism_ids'] = $organism_ids;

    // Iterate through our array of row values.
    foreach ($row_values as $index => $cell) {
      // Only validate the cells at the specified indices.
      if (in_array($index, $indices)) {
        $cell = trim($cell);
        // Check for empty cells.
        if (!isset($cell) || empty($cell)) {
          $empty = TRUE;
          $failedItems['empty_cells'][] = $index;
        }
        else {
          $organism_id_array = $this->organism_buddy->getOrganismFromScientificName($cell);
          $organism_id = 0;
          if (array_key_exists(0, $organism_id_array)) {
            $organism_id = $organism_id_array[0]->getValue('organism.organism_id');
          }
          $this->organism_ids[$cell] = $organism_id;
          // Check for missing organism.
          if ($organism_id <= 0 || empty($organism_id)) {
            $missing = TRUE;
            $failedItems['missing_cells'][$index]['organism'] = $cell;
          }
        }
      }
    }

    // If any organism name columns were empty for this row, return only this
    // case in the message, but failedItems will have all failed cells.
    if ($empty) {
      return [
        'case' => 'Unable to lookup organism with empty values',
        'valid' => FALSE,
        'failedItems' => $failedItems,
      ];
    }
    if ($missing) {
      $case_message = 'Missing organism(s) in the database';
    }
    else {
      // Return the case when a single organism name has been found (ie.
      // validation has passed.)
      return [
        'case' => 'Organism(s) exist(s) in the database',
        'valid' => TRUE,
        'failedItems' => [],
      ];
    }

    return [
      'case' => $case_message,
      'valid' => FALSE,
      'failedItems' => $failedItems,
    ];
  }

  /**
   * Process failed validation from ValidOrganism validator into a render array.
   *
   * @param array $validation_results
   *   An associative array that stores the validation failures by the
   *   ValidOrganism validator. It is keyed by the line number of the
   *   input file where validation failed, and the value is an associative
   *   array returned by the validator. The overall structure is:
   *   - [LINE NUMBER]:
   *     - 'case': a developer-focused string describing the case checked.
   *     - 'valid': FALSE to indicate that validation failed.
   *     - 'failedItems': an array of items that failed, where the key => value
   *       pairs map to the index => cell value(s) that failed validation.
   *       @see validateRow()
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following keys are expected:
   *   - 'column_headers': This contains an array of headers for columns that
   *     are expected to contain organisms. The index in this array MUST
   *     match the position (starting with 0) of the column in the input file.
   * @param array $tokens
   *   [OPTIONAL] An array of values to use for token replacement.
   *   @see $mapping
   *   The following tokens can be specfied as keys, with value as the
   *   replacement value for the token. These apply to all failure cases.
   *   - 'contact-admin': the phrase to use when the user needs a privileged
   *     administrator to fix the problem.
   *   The following token keys will substitute the entire existing case message
   *   to the user with the value of that token.
   *   - 'case-empty-organism': the message to show when the failure is due to
   *     empty cells in organism name columns.
   *   - 'case-missing-organism': the message to show when the failure is due to
   *     organism names not being found in the database.
   *
   * @return array
   *   A render array of type "unordered list" used to display feedback to the
   *   user about the validation failure, where each item is a markup block
   *   containing:
   *   - A message describing the case triggered
   *   - A table that lists the row and column combinations with failures for
   *     this case.
   *   Each case triggered will have its own markup block. The table headers for
   *   each case are:
   *     - Organism is empty: 'Row Number', 'Column Header'
   *     - Missing organism from the database:
   *       'Row Number', 'Column Header', 'Organism'
   *
   * @throws \Exception
   *   - If key 'column_headers' is missing from $metadata
   *   - If a validation status array was not formatted properly.
   *   - If the message for token 'case-empty-organism' is an empty string.
   *   - If the case string returned by the validator implied validation passed.
   *   - If the case string returned by the validator is not recognized.
   */
  public static function processListWithDescribedTable(array $validation_results, array $metadata, array $tokens = []) {
    // Validate that metadata contains the expected keys.
    if (!array_key_exists('column_headers', $metadata)) {
      throw new \Exception("Expected metadata to contain 'column_headers' when processing failures from ValidOrganism, but it does not.");
    }

    // We use the Tripal Token Parser service to ensure that more complicated
    // tokens are supported.
    // NOTE: Dependency injection is NOT used since this is a static method.
    $service_TripalTokensParser = \Drupal::service('tripal.token_parser');
    // Grab the default messages for all of our tokens (ones with default-msg).
    $default_tokens = array_column(self::$mapping, 'default-msg', 'token');
    // Combine our provided and our default token arrays. Because array_merge
    // will overwrite values in the first array with values from the second
    // array for the same keys, we provide our default tokens first.
    $combined_tokens = array_merge($default_tokens, $tokens);
    // Add a token for the column header names of the organisn columns.
    $combined_tokens['column-headers'] = implode(', ', $metadata['column_headers']);

    // Loop through each row in the $failures array and piece apart the
    // different cases into different tables.
    foreach ($validation_results as $line_no => $validation_status) {
      // Check the format of this line's validation status.
      ImporterValidationHelper::checkValidationStatusArray($validation_status, 'ValidOrganism', $line_no);

      // If any cells were found to be empty, this case takes presendence over
      // any other cases, and we return a warning message right away.
      if ($validation_status['case'] == 'Unable to lookup organism with empty values') {
        $message = $service_TripalTokensParser->replaceTokens(
          $combined_tokens['case-empty-organism'],
          $combined_tokens
        );
        return ImportValidationHelper::renderSimpleWarningMessage(
          $message,
          ['case-message', 'tc-valid-organism-empty'],
        );
      }

      if ($validation_status['case'] == 'Missing organism(s) in the database') {
        $table['message'] = $combined_tokens['case-missing-organism'];
        $table['rows'][$line_no][-1] = $line_no;
        foreach ($validation_status['failedItems'][$case] as $index => $organism) {
          $column_name = $metadata['column_headers'][$index];
          if (!array_key_exists($column_name, $table['header'])) {
            $table['header'][$column_name] = $column_name;
          }
          $table['rows'][$line_no][$index] = $organism['organism'];
        }
      }
      elseif ($validation_status['case'] == 'Organism(s) exist(s) in the database') {
        throw new \Exception("The case string returned by the ValidOrganism validator at line #$line_no implies validation passed, but valid is set to FALSE.");
      }
      else {
        throw new \Exception("The case string returned by the ValidOrganism validator at line #$line_no is not recognized as a potential case.");
      }
    }
    $tables = [];
    ImportValidationHelper::fillTableGaps($table['header'],
        $table['rows']);
    array_push($tables, [
      [
        '#prefix' => '<div class="case-message case-missing-organism">',
        // Replace any tokens that are in our table message.
        '#markup' => $service_TripalTokensParser->replaceTokens($table['message'], $combined_tokens),
        '#suffix' => '</div>',
      ],
      [
        '#type' => 'table',
        '#header' => $table['header'],
        '#attributes' => [
          'class' => [
            'table-case-missing-organism',
          ],
        ],
        '#rows' => $table['rows'],
      ],
    ]);

    $render_array = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#attributes' => [
        'class' => [
          'tc-valid-organism-failures',
        ],
      ],
      '#items' => $tables,
    ];

    return $render_array;
  }

}

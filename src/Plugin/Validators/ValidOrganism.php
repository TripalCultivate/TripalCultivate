<?php

namespace Drupal\trpcultivate\Plugin\Validators;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tripal_chado\ChadoBuddy\PluginManagers\ChadoBuddyPluginManager;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\tripal_chado\Plugin\ChadoBuddy\ChadoOrganismBuddy;
use Drupal\trpcultivate\Service\ImportValidationHelper;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\Attribute\TripalCultivateValidator;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\ColumnIndices;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\InputTypeTrait;
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
   * - InputTypeTrait: Manages the input type (metadata or data-row) that this
   *   instance of the validator is set to validate, and ensures that it is
   *   only set to validate a single input type.
  */
  use ColumnIndices;
  use InputTypeTrait;

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * The Chado Buddy organism.
   *
   * @var \Drupal\tripal_chado\Plugin\ChadoBuddy\ChadoOrganismBuddy
   */
  protected ChadoOrganismBuddy $organism_buddy;

  /**
   * A mapping of all of the tokens supported by this validator.
   *
   * @var array
   *   An associative array mapping tokens to their details, such as the
   *   developer case string and the default message to substitute the token.
   *   The following tokens are implemented for this mapping, with the following
   *   descriptions for their 'default-msg' values:
   *   - 'contact-admin': the phrase to use when the user needs a privileged
   *     administrator to fix the problem.
   *   - 'case-empty-organism': the message when a cell that should contain a
   *     organism is empty.
   *   - 'case-missing-organism': the message when a organism is missing
   *     in the database.
   *   Tokens below cannot be overriden as their value is determined at runtime:
   *   - 'column-headers': The column header names for organism columns.
   *
   * @see TripalCultivate/src/TripalCultivateValidator/TripalCultivateValidatorBase::$mapping
   */
  protected static array $mapping = [
    'organism' => [
      'token' => 'organism',
      'default-msg' => 'organism',
    ],
    'case-empty-organism' => [
      'token' => 'case-empty-organism',
      'dev-case' => 'Unable to lookup organism with empty values',
      'default-msg' => 'One or more cells which are required to contain organisms were empty. Please ensure that you have entered the full scientific name of existing organisms for all cells in the following columns: [column-headers]',
    ],
    'case-missing-organism' => [
      'token' => 'case-missing-organism',
      'dev-case' => 'Missing organism(s) in the database',
      'default-msg' => 'The following organisms do not match any existing in this site. Please make sure you have entered the full scientific names exactly as they appear on their organism pages, or [contact-admin] to have them added if they do not yet exist.',
    ],
    'case-valid' => [
      'token' => 'case-valid',
      'dev-case' => 'Organism(s) exist(s) in the database',
    ],
    'contact-admin' => [
      'token' => 'contact-admin',
      'default-msg' => 'contact your administrator',
    ],
    'column-headers' => [
      'token' => 'column_headers',
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
    $this->organism_buddy = $buddy_manager->createInstance('chado_organism_buddy', []);
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
   * Validates that the organism metadata is correct.
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

    // Get the input type.
    $input_type = $this->getInputType();

    if ($input_type != 'metadata') {
      throw new \Exception("ValidOrganism validator instance is set to validate input type $input_type, but validateMetadata was called. This method should only be called for instances set to validate 'metadata' input type.");
    }

    $case = 'Organism(s) exist(s) in the database';
    $valid = TRUE;
    $failed_items = [];

    $organism_id = 0;
    if (is_int($form_values[$expected_field_key])) {
      $organism_id = $form_values[$expected_field_key];
    }
    else {
      $organism_input = trim($form_values[$expected_field_key]);
      $organism_id_array = $this->organism_buddy->getOrganismFromScientificName($organism_input);
      if (isset($organism_id_array[0])) {
        $organism_id = $organism_id_array[0]->getValue('organism.organism_id');
      }
    }

    if ($organism_id <= 0 || empty($organism_id)) {
      $case = 'Missing organism(s) in the database';
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
   * Processes failed validation from validateMetadata into a render array.
   *
   * @param array $validation_status
   *   An associative array that was returned by the validateMetadata method in
   *   the event of failed validation. It contains the following keys:
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': FALSE to indicate that validation failed.
   *   - 'failedItems': an array of items that failed with the following keys.
   *     - 'organism_provided': The name of the organism provided.
   *   @see validateMetadata()
   * @param array $metadata
   *   An array of additional metadata (or contextual information) needed by the
   *   process method. Here, the following keys are expected:
   *   - 'input_type': $validator->getInputType() The type of input that was
   *      validated (should be 'metadata' for this process method). This is used
   *      to ensure that this process method is being called in the correct
   *      context since this method is only meant to be called for metadata
   *      validation results.
   * @param array $tokens
   *   An array of tokens to be used in the render array.
   *
   * @return array
   *   A render array of type unordered list which is used to display feedback
   *   to the user about the case that failed and the failed items from the
   *   input file. Each item in the list contains the organism that was selected
   *   in the form which failed validation.
   *
   * @throws \Exception
   *   - If the validation_status parameter was not formatted properly.
   *   - If the case string returned by the validator implied validation passed.
   *   - If the case string returned by the validator is not recognized.
   */
  public static function processItemWithSimpleList(array $validation_status, array $metadata, array $tokens = []) {
    // Validate that metadata contains the expected keys.
    if (!array_key_exists('input_type', $metadata)) {
      throw new \Exception("Expected metadata to contain 'input_type' when processing failures from ValidOrganism, but it does not.");
    }

    // Check if the method was called with the correct validation method.
    if ($metadata['input_type'] != 'metadata') {
      throw new \Exception("ValidOrganism::processItemWithSimpleList is expected to be called with 'metadata' input type, but input type is " . $metadata['input_type']);
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

    // Check the format of the validation_status parameter.
    ImportValidationHelper::checkValidationStatusArray($validation_status, 'ValidOrganism');

    // Check for one of the expected cases.
    if ($validation_status['case'] == self::$mapping['case-missing-organism']['dev-case']) {
      $message = $combined_tokens['case-missing-organism'];
    }
    elseif ($validation_status['case'] == self::$mapping['case-valid']['dev-case']) {
      throw new \Exception('The case string returned by the ValidOrganism validator implies validation passed, but valid is set to FALSE.');
    }
    else {
      throw new \Exception('The case string returned by the ValidOrganism validator is not recognized as a potential case.');
    }

    $service_TripalTokensParser = \Drupal::service('tripal.token_parser');
    $replaced_message = $service_TripalTokensParser->replaceTokens($message, $combined_tokens);

    // Build the render array.
    $render_array = [
      '#type' => 'item',
      '#title' => $replaced_message,
      '#wrapper_attributes' => [
        'class' => [
          'tc-valid-organism-failures',
        ],
      ],
      'items' => [
        '#theme' => 'item_list',
        '#type' => 'ul',
        '#items' => [
          [
            '#markup' => $validation_status['failedItems']['organism_provided'],
          ],
        ],
      ],
    ];

    return $render_array;
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
   *     - 'empty_cells': An array of indices for cells that were empty.
   *     - 'missing_cells': An array of indices and organisms for cells
   *       where the organism was not found in the database.
   *
   * @throws \Exception
   *   - If the specified indices are not valid for the provided row values.
   */
  public function validateRow(array $row_values) {

    // Get the input type.
    $input_type = $this->getInputType();

    if ($input_type != 'data-row') {
      throw new \Exception("ValidOrganism validator instance is set to validate input type $input_type, but validateRow was called. This method should only be called for instances set to validate 'data-row' input type.");
    }

    // Grab our indices.
    $indices = $this->getIndices();

    // Check the indices provided are valid in the context of the row.
    // Will throw an exception if there's a problem.
    $this->checkIndices($row_values, $indices);

    // Initialize our flags for keeping track of validation status.
    $empty = FALSE;
    $missing = FALSE;
    $failedItems = [];

    // Iterate through our array of row values.
    foreach ($row_values as $index => $cell) {
      // Only validate the cells at the specified indices.
      if (!in_array($index, $indices)) {
        continue;
      }
      $cell = trim($cell);
      // Check for empty cells.
      if (!isset($cell) || empty($cell)) {
        $empty = TRUE;
        $failedItems['empty_cells'][] = $index;
      }
      else {
        $organism_id_array = $this->organism_buddy->getOrganismFromScientificName($cell);
        $organism_id = 0;
        if (isset($organism_id_array[0])) {
          $organism_id = $organism_id_array[0]->getValue('organism.organism_id');
        }

        // Check for missing organism.
        if ($organism_id <= 0 || empty($organism_id)) {
          $missing = TRUE;
          $failedItems['missing_cells'][$index] = $cell;
        }
      }
    }

    // If any organism columns were empty for this row, return only this
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
      // Return the case when all organisms in this row have been found (ie.
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
   *   - 'input_type': $validator->getInputType();
   *      The type of input that was validated (should be 'data-row' for this
   *      process method). This is used to ensure that this process method is
   *      being called in the correct context since this method is only meant
   *      to be called for data-row validation results.
   *   - 'column_headers': This contains an array of headers for columns that
   *     are expected to contain organisms. The index in this array MUST
   *     match the position (starting with 0) of the column in the input file.
   * @param array $tokens
   *   [OPTIONAL] An array of values to use for token replacement.
   *   @see $mapping
   *   The following tokens can be specified as keys, with value as the
   *   replacement value for the token. These apply to all failure cases.
   *   - 'contact-admin': the phrase to use when the user needs a privileged
   *     administrator to fix the problem.
   *   The following token keys will substitute the entire existing case message
   *   to the user with the value of that token.
   *   - 'case-empty-organism': the message to show when the failure is due to
   *     empty cells in organism columns.
   *   - 'case-missing-organism': the message to show when the failure is due to
   *     organisms not being found in the database.
   *
   * @return array
   *   A render array depending on the cases triggered the validation results:
   *   - A simple warning message is returned if any row had empty cells for
   *     organism columns.
   *   - An "unordered list" is returned when the organism is missing from the
   *     database to informing the user about the validation failure, where
   *     each item is a markup block containing:
   *     - A message describing the case triggered
   *     - A table that lists the row and column combinations with failures for
   *       this case. The table has the following structure:
   *       - 'Line Number', 'Column Header'
   *
   * @throws \Exception
   *   - If key 'column_headers' is missing from $metadata
   *   - If a validation result array was not formatted properly.
   *   - If the message for token 'case-empty-organism' is an empty string.
   *   - If the case string returned by the validator implied validation passed.
   *   - If the case string returned by the validator is not recognized.
   */
  public static function processListWithDescribedTable(array $validation_results, array $metadata, array $tokens = []) {
    // Validate that metadata contains the expected keys.
    if (!array_key_exists('input_type', $metadata)) {
      throw new \Exception("Expected metadata to contain 'input_type' when processing failures from ValidOrganism, but it does not.");
    }
    if (!array_key_exists('column_headers', $metadata)) {
      throw new \Exception("Expected metadata to contain 'column_headers' when processing failures from ValidOrganism, but it does not.");
    }

    // Check if the method was called with the correct validation method.
    if ($metadata['input_type'] != 'data-row') {
      throw new \Exception("ValidOrganism::processListWithDescribedTable is expected to be called with 'data-row' input type, but input type is " . $metadata['input_type']);
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
    // Add a token for the column header names of the organism columns.
    $combined_tokens['column-headers'] = implode(', ', $metadata['column_headers']);

    // Define our table header.
    // We will start with the line number and build the header from there as we
    // go through the failures. There will be a column for each column checked
    // by this validator instance and the column header will be the same as it
    // appears in the file.
    $table_header = [-1 => 'Line Number'];
    $table['rows'] = [];

    // Loop through each row in the $failures array and check for our 2
    // different cases.
    foreach ($validation_results as $line_no => $validation_result) {
      // Check the format of this line's validation status.
      ImportValidationHelper::checkValidationStatusArray($validation_result, 'ValidOrganism', $line_no);

      // If any cells were found to be empty, this case takes presendence over
      // any other cases, and we return a warning message right away.
      if ($validation_result['case'] == self::$mapping['case-empty-organism']['dev-case']) {
        $message = $service_TripalTokensParser->replaceTokens(
          $combined_tokens['case-empty-organism'],
          $combined_tokens
        );
        return ImportValidationHelper::renderSimpleWarningMessage(
          $message,
          ['case-message', 'tc-valid-organism-empty'],
        );
      }
      // If this file row is missing one or more organisms in the database, then
      // add a new row to our table of missing organisms.
      if ($validation_result['case'] == self::$mapping['case-missing-organism']['dev-case']) {

        // Define a new row in our table for this line number.
        $table['message'] = $combined_tokens['case-missing-organism'];

        // Define a new row in our table for this line number.
        $table['rows'][$line_no][-1] = $line_no;

        // For each index with an invalid value, grab the column name from our
        // $headers property and add it to our table header.
        foreach ($validation_result['failedItems']['missing_cells'] as $index => $failed_value) {
          // Grab the column name based on the index of the invalid value
          // and add it to this table header if it's not already there.
          $column_name = $metadata['column_headers'][$index];
          if (!array_key_exists($column_name, $table_header)) {
            $table_header[$index] = $column_name;
          }
          // Now add a cell to the table to indicate this invalid value.
          // We reuse the index from the original file as the key to preserve
          // the same order of the columns. We also key the row with the line
          // number to ensure that a line with more then one failure is
          // compiled into a single row.
          $table['rows'][$line_no][$index] = $failed_value;
        }
      }
      elseif ($validation_result['case'] == self::$mapping['case-valid']['dev-case']) {
        throw new \Exception("The case string returned by the ValidOrganism validator at line #$line_no implies validation passed, but valid is set to FALSE.");
      }
      else {
        throw new \Exception("The case string returned by the ValidOrganism validator at line #$line_no is not recognized as a potential case.");
      }
    }
    // If our table has more than 2 columns with failed values, then iterate
    // through and fill empty cells with empty strings.
    ImportValidationHelper::fillTableGaps($table_header, $table['rows']);

    $service_TripalTokensParser = \Drupal::service('tripal.token_parser');
    $replaced_message = $service_TripalTokensParser->replaceTokens($table['message'], $combined_tokens);

    // Build the render array for our table.
    $render_array = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#attributes' => [
        'class' => [
          'tc-valid-organism-failures',
        ],
      ],
      '#items' => [
        [
          [
            '#prefix' => '<div class="case-message">',
            '#markup' => $replaced_message,
            '#suffix' => '</div>',
          ],
          [
            '#type' => 'table',
            '#header' => $table_header,
            '#attributes' => [],
            '#rows' => $table['rows'],
          ],
        ],
      ],
    ];

    return $render_array;
  }

}

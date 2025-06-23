<?php

namespace Drupal\trpcultivate\Plugin\Validators;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tripal_chado\Database\ChadoConnection;
use Drupal\trpcultivate\Service\ImportValidationHelper;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\ColumnIndices;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\Organism;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Validate the existance of a germplasm name in the database.
 *
 * @TripalCultivateValidator(
 *   id = "germplasm_name_exists",
 *   validator_name = @Translation("Germplasm Name Exists Validator"),
 *   input_types = {"data-row"},
 * )
 */
class GermplasmNameExists extends TripalCultivateValidatorBase implements ContainerFactoryPluginInterface {
  /**
   * Validator Traits required by this validator.
   *
   * - ColumnIndices: Gets an array of indices corresponding to the cells in
   *   $row_values to validate.
   * - Organism: Gets an array of organism IDs with which to perform the
   *   germplasm lookup in the database.
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
   * A mapping of all of the tokens supported by this validator.
   *
   * @var array
   *   An associative array mapping tokens to their details, such as the
   *   developer case string and the default message to substitute the token.
   *   The following tokens are implemented for this mapping, with the following
   *   descriptions for their 'default-msg' values:
   *  - 'contact-admin': the phrase to use when the user needs a privileged
   *    administrator to fix the problem.
   *  - 'case-empty-germplasm': the message when a cell that should contain a
   *    germplasm name is empty.
   *  - 'case-missing-germplasm': the message when a germplasm name is missing
   *    in the database.
   *  - 'case-duplicate-germplasm': the message when a germplasm name is
   *    duplicated in the database.
   *
   * @see TripalCultivate/src/TripalCultivateValidator/TripalCultivateValidatorBase::$mapping
   */
  protected static array $mapping = [
    'case-empty-germplasm' => [
      'token' => 'case-empty-germplasm',
      'dev-case' => 'Unable to lookup germplasm with empty values',
      'default-msg' => 'One or more cells which are expected to contain germplasm names was empty. Please ensure that you have non-empty cells for the following columns: [column-headers]',
    ],
    'case-missing-germplasm' => [
      'token' => 'case-missing-germplasm',
      'dev-case' => 'Missing germplasm name(s) in the database',
      'default-msg' => 'The following germplasm names could not be found in the database.',
    ],
    'case-duplicate-germplasm' => [
      'token' => 'case-duplicate-germplasm',
      'dev-case' => 'Duplicate(s) found in the database for germplasm name(s)',
      'default-msg' => 'The following germplasm names have 2 or more records in the database associated with them. Please resolve the duplications or [contact-admin] for help with investigating.',
    ],
    'case-missing-and-duplicate-germplasm' => [
      'token' => 'case-missing-and-duplicate-germplasm',
      'dev-case' => 'Missing germplasm name(s) and found duplicate(s) in the database',
    ],
    'case-valid' => [
      'token' => 'case-valid',
      'dev-case' => 'Germplasm name(s) exist(s) in the database',
    ],
    'contact-admin' => [
      'token' => 'contact-admin',
      'default-msg' => 'contact your administrator',
    ],
  ];

  /**
   * Constructs an instance of the Germplasm Name Exists validator.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param Drupal\tripal_chado\Database\ChadoConnection $chado_connection
   *   The connection to the Chado database.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ChadoConnection $chado_connection,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->chado_connection = $chado_connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('tripal_chado.database'),
    );
  }

  /**
   * Validate the values within the cells of this row.
   *
   * @param array $row_values
   *   An array of values from a single row/line in the file where each key maps
   *   to a column index and each value is the content of that column.
   *
   * @return array
   *   An associative array with the following keys.
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': TRUE if the germplasm exists in the database, FALSE otherwise.
   *   - 'failedItems': an array of items that failed with the following keys.
   *     This is an empty array if the data row input was valid.
   *     - 'organism_ids': The list of organism IDs that were configured using
   *       the Organism trait (setOrganismID() or setGenus()).
   *     - 'missing_cells': Present if 1+ germplasm name(s) are missing from the
   *       database.
   *       - 1+ arrays keyed by the column index (matches a value configured
   *         using the ColumnIndices trait prior to validation) in the input row
   *         that contains a missing germplasm name, further keyed by:
   *         - 'germplasm_name': The name of the missing germplasm.
   *     - 'duplicate_cells': Present if 1+ germplasm name(s) are duplicated in
   *       the database.
   *       - 1+ arrays keyed by the column index (matches a value configured
   *         using the ColumnIndices trait prior to validation) in the input row
   *         that contains a duplicate germplasm name, further keyed by:
   *         - 'germplasm_name': The name of the duplicate germplasm.
   *         - 'duplicates': A list of 2+ records that were returned by the
   *           query for the germplasm name in the database.
   *     - 'empty_cells': Present if 1+ germplasm name(s) are empty and thus
   *       cannot be looked up in the database.
   *       - A list containing the indices of the empty cells (This can only
   *         be a subset of the values configured by the ColumnIndices trait
   *         prior to validation).
   */
  public function validateRow($row_values) {

    // Grab our indices.
    $indices = $this->getIndices();

    // Check the indices provided are valid in the context of the row.
    // Will throw an exception if there's a problem.
    $this->checkIndices($row_values, $indices);

    // Grab our list of organism IDs.
    $organism_ids = $this->getOrganismIDs();

    // Initialize our flags for keeping track of validation status.
    $empty = FALSE;
    $missing = FALSE;
    $duplicate = FALSE;
    $failedItems = [];
    // Add our array of organism IDs to failedItems for our failed cases.
    $failedItems['organism_ids'] = $organism_ids;

    // Iterate through our array of row values.
    foreach ($row_values as $index => $cell) {
      // Only validate the values in which their index is also within our
      // context array of indices.
      if (in_array($index, $indices)) {
        // Trim the contents of our cell in case we have flanking whitespace.
        $cell = trim($cell);
        // Check if our cell is empty and save the index if it is.
        if (!isset($cell) || empty($cell)) {
          $empty = TRUE;
          $failedItems['empty_cells'][] = $index;
        }
        else {
          // Check if our cell value is in the chado.stock table.
          // Note that $organism_ids is an array, hence the use of 'IN' here.
          $query = $this->chado_connection->select('1:stock', 's')
            ->fields('s', ['stock_id', 'organism_id', 'name', 'uniquename', 'type_id'])
            ->condition('s.name', $cell, '=')
            ->condition('s.organism_id', $organism_ids, 'IN');
          $records = $query->execute()->fetchAll();
          // Save the records we fetched if there's 2 or more matches.
          if (count($records) >= 2) {
            $duplicate = TRUE;
            $failedItems['duplicate_cells'][$index] = [
              'germplasm_name' => $cell,
              'duplicates' => $records,
            ];
          }
          // Report when a germplasm is missing from the database.
          if (empty($records)) {
            $missing = TRUE;
            $failedItems['missing_cells'][$index]['germplasm_name'] = $cell;
          }
        }
      }
    }

    // If any germplasm name columns were empty for this row, return only this
    // case in the message, but failedItems will have all failed cells.
    if ($empty) {
      return [
        'case' => 'Unable to lookup germplasm with empty values',
        'valid' => FALSE,
        'failedItems' => $failedItems,
      ];
    }
    if ($duplicate) {
      if ($missing) {
        $case_message = 'Missing germplasm name(s) and found duplicate(s) in the database';
      }
      else {
        $case_message = 'Duplicate(s) found in the database for germplasm name(s)';
      }
    }
    elseif ($missing) {
      $case_message = 'Missing germplasm name(s) in the database';
    }
    else {
      // Return the case when a single germplasm name has been found (ie.
      // validation has passed.)
      return [
        'case' => 'Germplasm name(s) exist(s) in the database',
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
   * Process failed validation from GermplasmNameExists into a render array.
   *
   * This process method renders up to 2 tables, one for germplasm missing from
   * the database, and one for duplicate germplasm entries based on the name.
   * NOTE: The rendered validation result does NOT include information on the
   * duplicate records, but only lists the germplasm names. Future work may
   * include a separate process method that displays the information stored in
   * 'duplicates' of the 'failedItems' array.
   *
   * @param array $validation_results
   *   An associative array that stores the validation failures by the
   *   GermplasmNameExists validator. It is keyed by the line number of the
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
   *     are expected to contain germplasm names. The index in this array MUST
   *     match the position (starting with 0) of the column in the input file.
   *     Eg: 'column_headers' => [
   *           '2' => 'Maternal Germplasm Name', // Header of column #3
   *           '4' => 'Paternal Germplasm Name', // Header of column #5
   *         ];.
   * @param array $tokens
   *   [OPTIONAL] An array of values to use for token replacement.
   *   @see $mapping
   *   The following tokens can be specfied as keys, with value as the
   *   replacement value for the token. These apply to all failure cases.
   *   - 'contact-admin': the phrase to use when the user needs a privileged
   *     administrator to fix the problem.
   *   The following token keys will substitute the entire existing case message
   *   to the user with the value of that token.
   *   - 'case-empty-germplasm': the message when a cell that should contain a
   *    germplasm name is empty.
   *   - 'case-missing-germplasm': the message when a germplasm name is missing
   *     in the database.
   *   - 'case-duplicate-germplasm': the message when a germplasm name is
   *     duplicated in the database.
   *
   * @return array
   *   A render array of type "unordered list" used to display feedback to the
   *   user about the validation failure, where:
   *   - The 'title' is a sentence describing the case triggered
   *   - The 'items' include a table for each potential case in the $failures
   *     array:
   *     - If a germplasm name is empty
   *       - Headers include 'Row Number', 'Column Header'
   *     - A duplicate germplasm name seen in the database
   *       - Headers include 'Row Number', 'Column Header', 'Germplasm Name'
   *     - A missing germplasm name from the database
   *       - Headers include 'Row Number', 'Column Header', 'Germplasm Name'
   *
   * @throws \Exception
   *   - If a validation status array was not formatted properly.
   *   - If the case string returned by the validator implied validation passed.
   *   - If the case string returned by the validator is not recognized.
   */
  public static function processListWithDescribedTable(array $validation_results, array $metadata, array $tokens = []) {

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

    // For this validator there are can be up to 2 tables:
    // - 'table'->'missing_cells': Germplasm name not found in the database.
    // - 'table'->'duplicate_cells': Germplasm name has multiple records.
    $table = [];

    // Loop through each row in the $failures array and piece apart the
    // different cases into different tables.
    foreach ($validation_results as $line_no => $validation_status) {
      // Check the format of this line's validation status.
      ImportValidationHelper::checkValidationStatusArray($validation_status, 'GermplasmNameExists', $line_no);

      // If any cells were found to be empty, this case takes presendence over
      // any other cases, and we return a warning message right away.
      if ($validation_status['case'] == 'Unable to lookup germplasm with empty values') {
        // Add a token for the column header names of the germplasm columns.
        $combined_tokens['column-headers'] = implode(', ', $metadata['column_headers']);
        $message = $service_TripalTokensParser->replaceTokens($combined_tokens['case-empty-germplasm'], $combined_tokens);
        return self::renderSimpleWarningMessage($message, ['tc-germplasm-name-exists-empty']);
      }
      // Keeps track of which table this one line's validation result gets added
      // to based on the case it triggered.
      $table_case = [];
      if ($validation_status['case'] == 'Missing germplasm name(s) in the database') {
        $table_case = ['missing_cells'];
      }
      elseif ($validation_status['case'] == 'Duplicate(s) found in the database for germplasm name(s)') {
        $table_case = ['duplicate_cells'];
      }
      elseif ($validation_status['case'] == 'Missing germplasm name(s) and found duplicate(s) in the database') {
        $table_case = ['missing_cells', 'duplicate_cells'];
      }
      elseif ($validation_status['case'] == 'Germplasm name(s) exist(s) in the database') {
        throw new \Exception("The case string returned by the GermplasmNameExists validator at line #$line_no implies validation passed, but valid is set to FALSE.");
      }
      else {
        throw new \Exception("The case string returned by the GermplasmNameExists validator at line #$line_no is not recognized as a potential case.");
      }
      // Now set values that should appear for this row in the table(s) for this
      // particular case.
      foreach ($table_case as $case) {
        // Declare the array storing content for this table, if not already.
        if (!array_key_exists($case, $table)) {
          // Set the first column to hold the line number of the failure.
          // Use -1 to ensure it is the first column and doesn't conflict with
          // column indices in the input file.
          $table[$case]['header'][-1] = 'Line Number';
          $table[$case]['rows'] = [];
        }
        // Define a new row in our table for this line number.
        $table[$case]['rows'][$line_no][-1] = $line_no;
        // For each index with an failed germplasm, grab the column name from
        // $metadata and add it to our table header.
        foreach ($validation_status['failedItems'][$case] as $index => $germplasm) {
          // Grab the column name based on the index of the germplasm
          // and add it to this table header if it's not already there.
          $column_name = $metadata['column_headers'][$index];
          if (!array_key_exists($column_name, $table[$case]['header'])) {
            $table[$case]['header'][$index] = $column_name;
          }
          // Now add a cell to the table to indicate this germplasm.
          // We reuse the index from the original file as the key to preserve
          // the same order of the columns. We also key the row with the line
          // number to ensure that a line with more then one failure is
          // compiled into a single row.
          $table[$case]['rows'][$line_no][$index] = $germplasm['germplasm_name'];
        }
      }
    }
    // Check which tables were created, and assign the correct message.
    // Note that both tables can exist at the same time, hence not an 'elseif'.
    if (array_key_exists('missing_cells', $table)) {
      $table['missing_cells']['message'] = $combined_tokens['case-missing-germplasm'];
    }
    if (array_key_exists('duplicate_cells', $table)) {
      $table['duplicate_cells']['message'] = $combined_tokens['case-duplicate-germplasm'];
    }

    // Finally, loop through our tables and build our render array.
    $tables = [];
    foreach ($table as $table_key => &$table_case) {
      // If our table(s) have more than 2 columns with failed values, then
      // iterate through and pad each table with empty strings where necessary.
      self::fillTableGaps($table_case);
      array_push($tables, [
        [
          '#prefix' => '<div class="case-message case-' . $table_key . '">',
          // Replace any tokens that are in our table message.
          '#markup' => $service_TripalTokensParser->replaceTokens($table_case['message'], $combined_tokens),
          '#suffix' => '</div>',
        ],
        [
          '#type' => 'table',
          '#header' => $table_case['header'],
          '#attributes' => [
            'class' => [
              'table-case-' . $table_key,
            ],
          ],
          '#rows' => $table_case['rows'],
        ],
      ]);
    }
    $render_array = [
      '#theme' => 'item_list',
      '#type' => 'ul',
      '#attributes' => [
        'class' => [
          'tc-germplasm-name-exists-failures',
        ],
      ],
      '#items' => $tables,
    ];

    return $render_array;
  }

  /**
   * A helper method that will process any simple message into a render array.
   *
   * @param string $message
   *   A non-empty string that is the message to be displayed to the user. If
   *   desired, this string may include css formatting.
   * @param array $classes
   *   [OPTIONAL] An array of strings to give to '#wrapper_attributes' of the
   *   render array as a set of css classed. By default, this method adds the
   *   class:
   *   - 'simple-validation-warning'.
   *
   * @return array
   *   A render array of type "markup", used to display a warning to the user
   *   regarding a failed validation result.
   *
   * @throws \Exception
   *   - If $message is an empty string.
   */
  public static function renderSimpleWarningMessage(string $message, array $classes = []) {

    if (empty($message)) {
      throw new \Exception('Expected a non-empty string for the message passed into renderSimpleWarningMessage().');
    }

    // Add our universal class for simple validation warning messages.
    $classes[] = 'simple-validation-warning';

    return [
      '#type' => 'markup',
      '#prefix' => '<div class="case-message simple-validation-warning">',
      '#markup' => $message,
      '#suffix' => '</div>',
      '#wrapper_attributes' => [
        'class' => [
          $classes,
        ],
      ],
    ];
  }

  /**
   * Take an array with table contents and fills empty cells with empty strings.
   *
   * @param array $table
   *   An associative array representing the contents of a single table, with
   *   the following keys:
   *   - 'header': The contents of the table's header, where key = index of the
   *     column header, and value = content of the column header.
   *   - 'rows': The contents of the table's rows. Each row is keyed by the line
   *     number of the original input file that triggered validation failure,
   *     followed by the index of the column, followed by the column's contents.
   *
   *   Essentially, $table is structured as follows:
   *   - ['header'][COLUMN INDEX][COLUMN VALUE]
   *   - ['rows'][LINE NUMBER][COLUMN INDEX][COLUMN VALUE]
   *
   *   The resulting array is the same as @param table, but with added keys and
   *   values (empty string) to fully represent all cells in the table.
   *   NOTE: $table is passed in by reference, meaning that the original array
   *   is being modified directly and thus there is no return value.
   */
  public static function fillTableGaps(array &$table) {
    // Sort the table header.
    ksort($table['header']);
    if (count($table['header']) > 2) {
      foreach (array_keys($table['rows']) as $line_no) {
        foreach (array_keys($table['header']) as $index) {
          if (!array_key_exists($index, $table['rows'][$line_no])) {
            $table['rows'][$line_no][$index] = '';
          }
        }
        // Finally, sort the row by keys.
        ksort($table['rows'][$line_no]);
      }
    }
  }

}

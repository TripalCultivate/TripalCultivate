<?php

namespace Drupal\trpcultivate\Service;

/**
 * Provides helper methods for validation during data import.
 *
 * More specifically, this service can be used by either TripalImporter classes
 * or TripalCultivate Validator classes. It provides a centralized place for
 * helper methods that are not only used by the validator plugin instances.
 *
 * NOTE: Currently all the methods in this service are static. This was done to
 * keep them isolated from each other and for performance reasons.
 */
class ImportValidationHelper {

  /**
   * A mapping of supported file mime-types and their supported delimiters.
   *
   * More specifically, the file is split based on the appropriate delimiter
   * for the mime-type passed in. For example, the mime-type
   * "text/tab-separated-values" maps to the tab (i.e. "\t") delimiter.
   *
   * By using this mapping approach, we can actually support a number of
   * file types with different delimiters for the same importer while keeping
   * the performance hit to a minimum. Especially since in many cases this is a
   * one-to-one mapping. If it is not a one-to-one mapping, then we loop through
   * the options.
   *
   * @var array
   *   An associative array where the key is the official mime type and the
   *   value is an array of supported delimiters for that mime type. Delimiters
   *   should be enclosed in double quotes for consistency.
   *
   * Official mime types are managed by IANA.
   * @see https://www.iana.org/assignments/media-types/media-types.xhtml
   */
  public static array $mime_to_delimiter_mapping = [
    'text/tab-separated-values' => ["\t"],
    'text/csv' => [","],
    'text/plain' => ["\t", ","],
  ];

  /**
   * A mapping of file extensions and their supported mime-types.
   *
   * More specifically, based on the supported file extensions of the
   * current importer, a list of valid mime-types for the extension(s) is looked
   * up in this mapping.
   *
   * @var array
   *   An associative array where the key is the file extension (do not include
   *   the dot separator) and the value is an array of official mime types
   *   that are allowed to use this file extension.
   *
   * Official mime types are managed by IANA.
   * @see https://www.iana.org/assignments/media-types/media-types.xhtml
   */
  public static array $extension_to_mime_mapping = [
    'tsv' => ['text/tab-separated-values'],
    'csv' => ['text/csv'],
    'txt' => ['text/plain'],
  ];

  /**
   * Gets the list of delimiters supported by the input file's mime-type.
   *
   * NOTE: This method is static to allow for it to also be used by the static
   * method splitRowIntoColumns().
   *
   * @param string $mime_type
   *   A string that is the mime-type of the input file.
   *
   *   HINT: You can get the mime-type of a file from the 'mime-type' property
   *   of a file object.
   *
   * @return array
   *   The list of delimiters that are supported by the file mime-type.
   *
   * @throws \Exception
   *   - If mime_type is an empty string.
   *   - If mime_type does not exist as a key in the mime_to_delimiter_mapping
   *     array.
   */
  public static function getFileDelimiters(string $mime_type) {

    // Check if mime type is an empty string.
    if (empty($mime_type)) {
      throw new \Exception("The getFileDelimiters() getter requires a string of the input file's mime-type and must not be empty.");
    }

    // Grab the delimiters for this mime-type.
    if (array_key_exists($mime_type, self::$mime_to_delimiter_mapping)) {
      return self::$mime_to_delimiter_mapping[$mime_type];
    }
    else {
      throw new \Exception('Cannot retrieve file delimiters for the mime-type provided: ' . $mime_type);
    }
  }

  /**
   * Split a data file line/row values into an array using a delimiter.
   *
   * More specifically, the file is split based on the appropriate delimiter
   * for the mime type passed in. For example, the mime type
   * text/tab-separated-values maps to the tab (i.e. "\t") delimiter.
   *
   * By using this mapping approach, we can actually support a number of
   * file types with different delimiters for the same importer while keeping
   * the performance hit to a minimum. Especially since in many cases this is a
   * one-to-one mapping. If it is not a one-to-one mapping, then we loop through
   * the options.
   *
   * @param string $row
   *   A line in the data file which has not yet been split into columns.
   * @param string $mime_type
   *   The mime type of the file currently being validated or imported (i.e. the
   *   mime type of the file this line is from).
   *
   * @return array
   *   An array containing the values extracted from the line after splitting it
   *   based on a delimiter value.
   *
   * @throws \Exception
   *   - If $mime_type is not in static array $mime_to_delimiter_mapping.
   *   - If $mime_type contains multiple delimiter options (@todo update in
   *     issue #118).
   *   - If $row was unable to be split with a supported delimiter.
   */
  public static function splitRowIntoColumns(string $row, string $mime_type) {

    $mime_to_delimiter_mapping = self::$mime_to_delimiter_mapping;

    // Ensure that the mime type is in our delimiter mapping.
    if (!array_key_exists($mime_type, $mime_to_delimiter_mapping)) {
      throw new \Exception('The mime type "' . $mime_type . '" passed into splitRowIntoColumns() is not supported. We support the following mime types:' . implode(', ', array_keys($mime_to_delimiter_mapping)) . '.');
    }

    // Determine the delimiter we should use based on the mime type.
    $supported_delimiters = self::getFileDelimiters($mime_type);

    $delimiter = NULL;
    // If there is only one supported delimiter then simply split the row!
    if (count($supported_delimiters) === 1) {
      $delimiter = end($supported_delimiters);
      $columns = str_getcsv($row, $delimiter);
    }

    // @todo Address in issue #118.
    // Otherwise we will have to try to determine which one is "right"?!?
    // Points to remember in the future:
    // - We can't use the one that splits into the most columns as a text column
    // could include multiple commas which could overpower the overall number of
    // tabs in a tab-delimited plain text file.
    // - It would be good to confirm we are getting the same number of columns
    // for each line in a file but since this needs to be a static method we
    // would pass that information in.
    // - If we try to check for the same number of columns as expected, we have
    // to remember that researchers routinely add "Comments" columns to the end,
    // sometimes without a header.
    // - If going based on the number of columns in the header, the point above
    // still impacts this, plus this method is called when splitting the header
    // before any validators run!
    else {

      throw new \Exception("We don't currently support splitting mime types with multiple delimiter options as its not trivial to choose the correct one.");

      /*
      $results = [];
      $counts = [];
      foreach ($supported_delimiters as $delimiter) {
      $results[$delimiter] = str_getcsv($row, $delimiter);
      $counts[$delimiter] = count($results[$delimiter]);
      }

      // Now lets choose the one with the most columns --shrugs-- not ideal
      // but I'm not sure there is a better option. asort() is from smallest
      // to largest preserving the keys so we want to choose the last element.
      asort($counts);
      $winning_delimiter = array_key_last($counts);
      $columns = $results[ $winning_delimiter ];
      $delimiter = $winning_delimiter;
       */
    }

    // Now lets double check that we got some values...
    if (count($columns) == 1 && $columns[0] === $row) {
      // The delimiter failed to split the row and returned the original row.
      throw new \Exception('The data row or line provided could not be split into columns. The supported delimiter(s) are "' . implode('", "', $supported_delimiters) . '".');
    }

    // Sanitize values.
    foreach ($columns as &$value) {
      if ($value) {
        $value = trim(str_replace(['"', '\''], '', $value));
      }
    }

    return $columns;
  }

  /**
   * Sanity checks to ensure the validation status array is compliant.
   *
   * @param array $validation_result
   *   An associative array that was returned by a validator in the event of
   *   failed validation. It should contain the following keys:
   *   - 'case': a developer-focused string describing the case checked.
   *   - 'valid': FALSE to indicate that validation failed.
   *   - 'failedItems': an array of items that failed which is specific to the
   *     validator.
   * @param string $validator_name
   *   The name of the validator that produced the validation_result array.
   * @param int|null $line_no
   *   The line number in the input file that triggered the failed validation
   *   status.
   *
   * @return bool
   *   Returns TRUE if the validation_result array is compliant and ready for
   *   processing, FALSE otherwise.
   *
   * @throws \Exception
   *   If any one or more of the following occur:
   *   - The validation_result array does not contain one of the following
   *     keys: 'case', 'valid', 'failedItems'.
   *   - The value for 'valid' is not FALSE, indicating it was not properly
   *     set to be a failed validation status.
   *   - The value for 'failedItems' is not an array.
   *   - The value for 'failedItems' is an empty array.
   */
  public static function checkValidationStatusArray(array $validation_result, string $validator_name, int|null $line_no = NULL) {

    $error_message = '';
    $errors_found = 0;

    // Check for validation status keys: 'case', 'valid', 'failedItems'.
    $keys = ['case', 'valid', 'failedItems'];
    $missing_keys = array_diff($keys, array_keys($validation_result));

    if ($missing_keys) {
      $errors_found++;
      $error_message = "Expected to find key(s) '" . implode("', '", $missing_keys) . "' in the validation result array. ";
    }
    // Check that key 'valid' is set to FALSE.
    if (array_key_exists('valid', $validation_result) && ($validation_result['valid'] !== FALSE)) {
      $errors_found++;
      $error_message .= "Expected the validation result to contain a value of FALSE for the key 'valid' since it should only reach this point if validation failed. ";
    }
    if (array_key_exists('failedItems', $validation_result)) {
      // Check that 'failedItems' contains a value of type array.
      if (!is_array($validation_result['failedItems'])) {
        $errors_found++;
        $error_message .= "Expected the validation result to contain an array for the key 'failedItems', but it did not. ";
      }
      // Check that 'failedItems' is not an empty array.
      elseif ($validation_result['failedItems'] === []) {
        $errors_found++;
        $error_message .= "Expected the validation result to have content for the key 'failedItems', but it was set to an empty array. ";
      }
    }
    // If any errors were found, throw an exception that includes the number of
    // errors, line number if applicable, and a sentence describing each error.
    if ($errors_found > 0) {
      $error_message = trim($error_message);
      if ($line_no) {
        $append_line_no = " at line #$line_no of the input file";
      }
      else {
        $append_line_no = '';
      }
      throw new \Exception("ERROR: Found $errors_found problem(s) with the validation result array returned by the $validator_name validator$append_line_no. Details: $error_message");
    }

    return TRUE;
  }

  /**
   * Take an array with table contents and fill empty cells with empty strings.
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
   *   values (as empty string) to fully represent all cells in the table.
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

  /**
   * A helper method that will process any simple message into a render array.
   *
   * @param string $message
   *   A non-empty string that is the message to be displayed to the user. If
   *   desired, this string may include HTML tags.
   * @param array $classes
   *   [OPTIONAL] An array of strings to give to '#wrapper_attributes' of the
   *   render array as a set of css classes. By default, this method adds the
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

}

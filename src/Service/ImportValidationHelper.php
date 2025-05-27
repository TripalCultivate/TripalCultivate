<?php

namespace Drupal\trpcultivate\Service;

/**
 * Import validation helper class.
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
   */ 
  public static array $mime_to_delimeter_mapping = [
    'text/tab-separated-values' = ["\t"],
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

}

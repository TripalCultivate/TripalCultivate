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

}

<?php

namespace Drupal\trpcultivate\TripalCultivateValidator\Attribute;

use Drupal\Component\Plugin\Attribute\Plugin;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Defines a data validator attribute object.
 *
 * Plugin Namespace: Drupal\trpcultivate\TripalCultivateValidator.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class TripalCultivateValidator extends Plugin {

  /**
   * Constructs a TripalCultivateValidator attribute.
   *
   * @param string $id
   *   The plugin ID.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup|null $validator_name
   *   The human-readable name of the validator.
   * @param array $input_types
   *   The type of the data this validator supports validating.
   *   This should be one or more of the following:
   *   - metadata: for validating the form values of the importer not including
   *     the file.
   *   - file: for validating the file object but not its contents.
   *   - header-row: for validating the first row in the file.
   *   - data-row: for validating all data rows in the file.
   */
  public function __construct(
    public readonly string $id,
    public readonly ?TranslatableMarkup $validator_name = NULL,
    public readonly ?array $input_types = [],
  ) {}

}

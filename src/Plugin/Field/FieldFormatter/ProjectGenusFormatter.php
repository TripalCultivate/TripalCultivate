<?php

namespace Drupal\trpcultivate\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tripal\TripalField\Attribute\TripalFieldFormatter;
use Drupal\tripal_chado\TripalField\ChadoFormatterBase;

/**
 * Plugin implementation of the 'project_genus_formatter' field formatter.
 */
#[TripalFieldFormatter(
  id: 'project_genus_formatter',
  label: new TranslatableMarkup('Genus and Scientific name Elements'),
  description: new TranslatableMarkup('Displays one element showing the genus and another showing the scientific name.'),
  field_types: [
    'project_genus',
  ],
)]
class ProjectGenusFormatter extends ChadoFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    // Use render arrays to generate markup for your field.
    foreach ($items as $delta => $item) {
      $elements[$delta] = [
        "#markup" => $item->get("value")->getString(),
      ];
    }

    return $elements;
  }

}

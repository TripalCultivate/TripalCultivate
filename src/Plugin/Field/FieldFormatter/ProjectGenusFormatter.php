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
    parent::viewElements($items, $langcode);
    $elements = [];
    $genus_arr = [];
    $sciname_arr = [];

    // Use render arrays to generate markup for your field.
    foreach ($items as $delta => $item) {
      $genus_arr[$delta] = $item->get('genus_value')->getString();
      $sciname_arr[$delta] = $item->get('sciname_value')->getString();
    }
    $elements[0] = [
      '#markup' => 'Genus',
    ];
    $elements[1] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $genus_arr,
      '#wrapper_attributes' => ['class' => 'container'],
    ];
    $elements[2] = [
      '#markup' => 'Scientific Name',
    ];
    $elements[3] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $sciname_arr,
      '#wrapper_attributes' => ['class' => 'container'],
    ];

    return $elements;
  }

}

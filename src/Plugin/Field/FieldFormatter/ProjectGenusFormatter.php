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
      $genus = $item->get('genus_value')->getString();
      $sciname = $item->get('sciname_value')->getString();

      if ($genus and $genus != '') {
        $genus_arr[$delta] = $genus;
      }

      if ($sciname and $sciname != '') {
        $sciname_arr[$delta] = $sciname;
      }
    }
    $elements[0] = [
      '#theme' => 'item_list',
      '#title' => 'Genus',
      '#list_type' => 'ul',
      '#items' => $genus_arr,
      '#wrapper_attributes' => ['class' => 'container'],
      '#prefix' => '<div class="field-genus-list">',
      '#suffix' => '</div>',
    ];
    $elements[1] = [
      '#theme' => 'item_list',
      '#title' => 'Scientific Name',
      '#list_type' => 'ul',
      '#items' => $sciname_arr,
      '#wrapper_attributes' => ['class' => 'container'],
      '#prefix' => '<div class="field-sciname-list">',
      '#suffix' => '</div>',
    ];

    return $elements;
  }

}

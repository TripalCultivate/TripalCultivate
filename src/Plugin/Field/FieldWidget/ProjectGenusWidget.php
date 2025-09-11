<?php

namespace Drupal\trpcultivate\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tripal\TripalField\Attribute\TripalFieldWidget;
use Drupal\tripal_chado\TripalField\ChadoWidgetBase;

/**
 * Plugin implementation of the 'project_genus_widget' field widget.
 */
#[TripalFieldWidget(
  id: 'project_genus_widget',
  label: new TranslatableMarkup('Project Genus Select List'),
  description: new TranslatableMarkup('Provides a select list of organisms in this site.'),
  field_types: [
    'project_genus',
  ],
)]
class ProjectGenusWidget extends ChadoWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    // Grab the values for our properties based on the passed in delta.
    // For fields with a cardinality above 1, this is called one per record
    // with the delta indicating the current record.
    $item_vals = $items[$delta]->getValue();

    // Define your form elements here.
    $element['value'] = [
      '#type' => 'textfield',
      '#default_value' => $item_vals['value'] ?? '',
    ];

    // You can define extra elements that are not saved in chado as well.
    $element['suffix'] = [
      '#type' => 'textfield',
      '#default_value' => $item_vals['suffix'] ?? '',
    ];

    return $element;
  }

  /**
   * {@inheritDoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    // You may need to massage the values submitted by the widget before
    // passing it on to the backend storage. The values will be passed on to
    // the backend storage. For example, if you had a second element in the
    // widget that indicated the suffix to attach to the value you could
    // add it like this assuming a cardinality: 1.
    $values[0]['value'] = $values[0]['value'] . ' ' . $values[0]['suffix'];

    return $values;
  }

}

<?php

namespace Drupal\trpcultivate\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tripal\TripalField\Attribute\TripalFieldWidget;
use Drupal\tripal_chado\TripalField\ChadoWidgetBase;
use Drupal\tripal_chado\Controller\ChadoOrganismAutocompleteController;

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
    $linker_fkey_column = 'projectprop_id';
    $property_definitions = $items[$delta]->getFieldDefinition()->getFieldStorageDefinition()->getPropertyDefinitions();
    $field_name = $items->getFieldDefinition()->get('field_name');

    $item_vals = $items[$delta]->getValue();
    $record_id = $item_vals['record_id'] ?? 0;

    $elements = [];
    $elements['record_id'] = [
      '#type' => 'value',
      '#default_value' => $record_id,
    ];
    // Pass the field machine name through the form for massageFormValues()
    $elements['field_name'] = [
      '#type' => 'value',
      '#default_value' => $field_name,
    ];

    // Insert the select element, either a select or an autocomplete depending
    // on the number of options.
    $options = [];
    $select_element = $this->organismSelectElement($organism_id, $options);
    $elements[$linker_fkey_column] = $element + $select_element;

    foreach ($property_definitions as $property => $definition) {
      $default_value = $item_vals[$property] ?? 1;
      $elements[$property] = [
        '#type' => 'value',
        '#default_value' => $default_value,
      ];
    }

    // Save some initial values to allow later handling of the "Remove" button.
    $this->saveInitialValues($delta, $field_name, $record_id, $form_state);

    return $elements;
  }

  /**
   * Select form element generator. For a small number of values
   * this creates a select, for many values this creates an autocomplete.
   *
   * @param int|null $default_id
   *   The pkey_id value of the default, if one exists.
   * @param array $options
   *   'match_operator' - Either "CONTAINS" or "STARTS_WITH"
   *   'match_limit' -Number of records that the autoselect will present
   *   'size' - Size of the autocomplete form field
   *   'placeholder' - Placeholder before autocomplete is filled
   *   'select_limit' - The maximum number of records for a select. If more,
   *       then use autocomplete. Use zero if autocomplete always wanted.
   *       If NULL or empty string, then the global setting will be used.
   *
   * @return array
   *   The appropriate form element
   */
  protected function organismSelectElement(?int $default_id, array $options): array {

    // Set some defaults to keep each of the fields simpler.
    $options['select_limit'] = $this->getSelectLimit($options['select_limit'] ?? NULL);
    $options['match_operator'] ??= $this->getSetting('match_operator') ?? 'CONTAINS';
    $options['match_limit'] ??= $this->getSetting('match_limit') ?? 10;
    $options['size'] ??= $this->getSetting('size');
    $options['placeholder'] ??= $this->getSetting('placeholder');

    $element = [];

    // Construct a query
    // A single wildcard indicates that all records are to be returned.
    $string = '%';
    // Add one to select limit so we know if it is exceeded.
    $count_options = $options;
    $count_options['match_limit'] = $options['select_limit'] + 1;
    $query = ChadoOrganismAutocompleteController::getQuery($string, $count_options);

    // Get a count of the number of possible values, unless forcing always autocomplete.
    $count = 1;
    if ($options['select_limit'] > 0) {
      $count = $query->countQuery()->execute()->fetchField();
    }

    // For a large number of options, or if limit is zero, use an autocomplete.
    if ($count > $options['select_limit']) {
      // Look up the default value if one was specified.
      $default_value = '';
      if ($default_id) {
        // We can reuse the existing query since only one change is needed.
        $query->condition('organism_id', $default_id, '=');
        $result = $query->execute()->fetchObject();
        if ($result) {
          // Strip HTML tags if present, e.g. in Pub title.
          $default_value = strip_tags($result->organism ?? '');
          // Append the chado pkey id value.
          $default_value .= ' (' . $default_id . ')';
        }
      }
      $element = [
        '#type' => 'textfield',
        '#default_value' => $default_value,
        '#autocomplete_route_name' => 'tripal_chado.organism_autocomplete',
        '#autocomplete_route_parameters' => ['match_limit' => $options['match_limit']],
        '#size' => $options['size'],
        '#placeholder' => $options['placeholder'],
      ];
      unset($options['size']);
      unset($options['placeholder']);
      $element['#autocomplete_route_parameters'] = $options;
    }

    // For a small number of options, use a select.
    else {
      $select_query = ChadoOrganismAutocompleteController::getQuery($string, $options);
      $results = $select_query->execute();
      $select_options = [];
      while ($record = $results->fetchObject()) {
        // Strip HTML tags if present, but this is not likely for organism.
        $organism = strip_tags($record->abbreviation ?: $record->organism ?? '');
        $select_options[$record->pkey] = $organism;
      }
      natcasesort($select_options);
      $element = [
        '#type' => 'select',
        '#options' => $select_options,
        '#default_value' => $default_id,
        '#empty_option' => $this->t('- Select -'),
      ];
    }
    $element['#element_validate'] = [[static::class, 'validateAutocomplete']];
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
    // $values[0]['value'] = $values[0]['value'] . ' ' . $values[0]['suffix'];.
    foreach ($values as $key => $item) {
      // Note: If the property is empty then the value key will not be present.
      if (array_key_exists('value', $item)) {
        $values[$key]['value'] = $item['value']['value'];
      }
    }
    return $values;
  }

}

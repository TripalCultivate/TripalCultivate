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

    // Get the field settings.
    $field_definition = $items[$delta]->getFieldDefinition();
    $field_name = $field_definition->get('field_name');

    // Current Item Values:
    $item_vals = $items[$delta]->getValue();
    $record_id = $item_vals['record_id'] ?? 0;
    $organism_id = $item_vals['organism_id'] ?? 0;

    // ID space manager to get the terms later.
    $idSpace_manager = \Drupal::service('tripal.collection_plugin_manager.idspace');

    $elements = [];
    $elements['record_id'] = [
      '#type' => 'value',
      '#value' => $record_id,
    ];
    $elements['field_name'] = [
      '#type' => 'value',
      '#value' => $field_name,
    ];

    // GENUS.
    $genus_prop_id = $item_vals['genus_prop_id'] ?? 0;
    $genus_prop_fkey = $item_vals['genus_prop_fkey'] ?? 0;
    $genus_value = $item_vals['genus_value'] ?? '';
    // -- get the term.
    $idSpace_taxrank = $idSpace_manager->loadCollection('TAXRANK');
    $genus_term_id = $idSpace_taxrank->getTerm('0000005')->getInternalId();
    // -- now define the elements.
    $elements['genus_prop_id'] = [
      '#type' => 'value',
      '#default_value' => $genus_prop_id,
    ];
    $elements['genus_prop_fkey'] = [
      '#type' => 'value',
      '#default_value' => $genus_prop_fkey,
    ];
    $elements['genus_type_id'] = [
      '#type' => 'value',
      '#value' => $genus_term_id,
    ];
    $elements['genus_rank'] = [
      '#type' => 'value',
      '#value' => $delta,
    ];
    $elements['genus_value'] = [
      '#type' => 'value',
      '#title' => 'Genus',
      '#default_value' => $genus_value,
    ];

    // Scientific Name.
    $sciname_prop_id = $item_vals['sciname_prop_id'] ?? 0;
    $sciname_prop_fkey = $item_vals['sciname_prop_fkey'] ?? 0;
    $sciname_value = $item_vals['sciname_value'] ?? '';
    // -- get the term.
    $idSpace_ncbitaxon = $idSpace_manager->loadCollection('NCBITaxon');
    $sciname_term_id = $idSpace_ncbitaxon->getTerm('scientific_name')->getInternalId();
    // -- now define the elements.
    $elements['sciname_prop_id'] = [
      '#type' => 'value',
      '#default_value' => $sciname_prop_id,
    ];
    $elements['sciname_prop_fkey'] = [
      '#type' => 'value',
      '#default_value' => $sciname_prop_fkey,
    ];
    $elements['sciname_type_id'] = [
      '#type' => 'value',
      '#value' => $sciname_term_id,
    ];
    $elements['sciname_rank'] = [
      '#type' => 'value',
      '#value' => $delta,
    ];
    $elements['sciname_value'] = [
      '#type' => 'value',
      '#title' => 'Scientific Name',
      '#default_value' => $sciname_value,
    ];

    // Insert the select element, either a select or an autocomplete depending
    // on the number of options.
    $options = [];
    $elements['organism_id'] = $element + $this->organismSelectElement($organism_id, $options);

    // Save some initial values to allow later handling of the "Remove" button.
    // Note: We do this manually instead of using saveInitialValues() because
    // we have two properties in a single item.
    // We want the initial values, so never update them once saved.
    $storage = $form_state->getStorage();
    if (!($storage['initial_values'][$field_name][$delta] ?? FALSE)) {
      $storage['initial_values'][$field_name][$delta] = [
        'genus_prop_id' => $genus_prop_id,
        'sciname_linker_id' => $sciname_prop_id,
        'organism_id' => $organism_id,
      ];
      $form_state->setStorage($storage);
    }

    return $elements;
  }

  /**
   * Select form element generator.
   *
   * Note: For a small number of values this creates a select, for many values
   * this creates an autocomplete.
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

    // Get a count of the number of possible values,
    // unless forcing always autocomplete.
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

    // If there are no values to massage then move on.
    if (!$values) {
      return $values;
    }

    // Note: I think that massaging to remove empty or deleted properties
    // will be much easier and less error prone once we get the select list in
    // place. We still cannot use the massagePropertyFormValues() parent method
    // but we can follow the same logic but looking at the organism_id
    // property and the genus_prop_id property.
    // @todo implement handling of remove empty values after select.


    // The field name for the field. There are usually multiple
    // copies of a property field, so this distinguishes them.
    $first_delta = array_key_first($values);
    $field_name = $values[$first_delta]['field_name'];

    // Look up the rank term
    $storage = \Drupal::entityTypeManager()->getStorage('chado_term_mapping');
    $mapping = $storage->load('core_mapping');
    $rank_term = $this->sanitizeKey($mapping->getColumnTermId('projectprop', 'rank'));
    // Convert the widget fields into an updated $values array
    // with the items expected by the field type
    $this->preMassageFormValues($values);

    $val = 'organism_id';
    $linker_key = 'organism_id';

    // Handle any empty values so that chado storage properly
    // deletes the linking record in chado. This happens when an
    // existing record is changed to "- Select -"
    $retained_records = [];
    foreach ($values as $val_key => $value) {
      if ($value[$linker_key]) {
        $retained_records[$val_key] = $value[$linker_key];
      }
      if ($value[$val] == '') {
        if ($value['record_id']) {
          // If there is a record_id, but no value, this
          // means we need to pass in this record to chado storage
          // to have the linker record be deleted there. Here,
          // the empty string is the correct primitive type,
          // so nothing to change.
        }
        else {
          // If there is no record_id, then it is the empty
          // field at the end of the list, and can be ignored.
          unset($values[$val_key]);
        }
      }
    }

    // If there were any values in the initial values that are not
    // present in the current form state, then an existing record
    // was deleted by clicking the "Remove" button. Similarly to
    // the code above, we need to include these in the values array
    // so that chado storage is informed to delete the linking record.
    $next_delta = $values ? array_key_last($values) + 1 : 0;
    $storage_values = $form_state->getStorage();
    $initial_values = $storage_values['initial_values'][$field_name];
    foreach ($initial_values as $initial_value) {
      // For initial values, the key is always 'linker_id', regardless of $linker_key value.
      $organism_id = $initial_value['organism_id'];
      if ($organism_id and !in_array($organism_id, $retained_records)) {
        // This item was removed from the form. Add back a value
        // so that chado storage knows to remove the chado record.
        $values[$next_delta][$linker_key] = $organism_id;
        $values[$next_delta][$val] = '';
        $next_delta++;
      }
    }

    // Reset the weights
    $i = 0;
    foreach ($values as $val_key => $value) {
      if ($values[$val_key][$val]) {
        $values[$val_key]['_weight'] = $i;
        if ($rank_term) {
          $values[$val_key][$rank_term] = $i;
        }
        $i++;
      }
    }
    return $values;
  }

  /**
   * Convert the values from the widget form fields into an updated
   * array containing the items that are expected by the field type.
   *
   * @param array &$values
   *   The values array passed to massageFormValues
   * @return void
   */
  protected function preMassageFormValues(array &$values): void {
    $chado = \Drupal::service('tripal_chado.database');
    $values = $this->genericSelectMassageFormValues('organism_id', $values);
    foreach ($values as $delta => $value) {
      $new_value = $value;
      $new_value['genus_value'] = '';
      $new_value['sciname_value'] = '';

      if ($value['organism_id']) {
        if ($value['organism_id'] !=  '') {
          $query = $chado->select('1:organism', 'o')
            ->fields('o', ['genus', 'species'])
            ->condition('o.organism_id', $value['organism_id'], '=')
            ->execute()
            ->fetchAll();
          $new_value['genus_value'] = $query[0]->genus;
          $new_value['sciname_value'] = $query[0]->genus . ' ' . $query[0]->species;
        }
      }
      $values[$delta] = $new_value;
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return self::defaultSelectSettings() + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    return $this->selectSettingsForm($form, $form_state) + parent::settingsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return $this->selectSettingsSummary() + parent::settingsSummary();
  }

}

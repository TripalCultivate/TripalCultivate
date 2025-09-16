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
      '#type' => 'textfield',
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
      '#type' => 'textfield',
      '#title' => 'Scientific Name',
      '#default_value' => $sciname_value,
    ];

    // Insert the select element, either a select or an autocomplete depending
    // on the number of options.
    // $options = [];
    // $select_element = $this->organismSelectElement($organism_id, $options);
    // $elements[$linker_fkey_column] = $element + $select_element;.

    // Save some initial values to allow later handling of the "Remove" button.
    // Note: We do this manually instead of using saveInitialValues() because
    // we have two properties in a single item.
    // We want the initial values, so never update them once saved.
    $storage = $form_state->getStorage();
    if (!($storage['initial_values'][$field_name][$delta] ?? FALSE)) {
      $storage['initial_values'][$field_name][$delta] = [
        'genus_prop_id' => $genus_prop_id,
        'sciname_linker_id' => $sciname_prop_id,
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
        '#value' => $default_value,
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
        '#value' => $default_id,
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
    return $values;
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

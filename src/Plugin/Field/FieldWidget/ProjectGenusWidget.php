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
    // Get the field settings.
    $field_definition = $items[$delta]->getFieldDefinition();
    $field_settings = $field_definition->getSettings();
    $field_name = $field_definition->get('field_name');

    $item_vals = $items[$delta]->getValue();
    $record_id = $item_vals['record_id'] ?? 0;
    $organism_id = $item_vals['organism_id'] ?? 0;
    $genus_prop_id = $item_vals['genus_prop_id'] ?? 0;
    $genus_prop_fkey = $item_vals['genus_prop_fkey'] ?? 0;
    $sciname_prop_id = $item_vals['sciname_prop_id'] ?? 0;
    $sciname_prop_fkey = $item_vals['sciname_prop_fkey'] ?? 0;
    $genus_value = $item_vals['genus_value'] ?? 'Lens';
    $sciname_value = $item_vals['sciname_value'] ?? 'Lens culinaris';
    $idSpace_manager = \Drupal::service('tripal.collection_plugin_manager.idspace');
    $idSpace = $idSpace_manager->loadCollection('TAXRANK');

    $term = $idSpace->getTerm('0000005');
    $genus_term_id = $term->getInternalId();
    $idSpace_manager = \Drupal::service('tripal.collection_plugin_manager.idspace');
    $idSpace = $idSpace_manager->loadCollection('NCBITaxon');

    $term = $idSpace->getTerm('scientific_name');
    $sciname_term_id = $term->getInternalId();

    $elements = [];
    $elements['record_id'] = [
      '#type' => 'value',
      '#value' => $record_id,
    ];
    $elements['field_name'] = [
      '#type' => 'value',
      '#value' => $field_name,
    ];
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
      '#value' => $genus_value,
    ];
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
      '#value' => $sciname_value,
    ];

    // Insert the select element, either a select or an autocomplete depending
    // on the number of options.
    $options = [];
    $select_element = $this->organismSelectElement($organism_id, $options);
    $elements[$linker_fkey_column] = $element + $select_element;

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

    // You may need to massage the values submitted by the widget before
    // passing it on to the backend storage. The values will be passed on to
    // the backend storage. For example, if you had a second element in the
    // widget that indicated the suffix to attach to the value you could
    // add it like this assuming a cardinality: 1.
    // $values[0]['value'] = $values[0]['value'] . ' ' . $values[0]['suffix'];.
    // foreach ($values as $key => $item) {
    //   // Note: If the property is empty then the value key will not be present.
    //   if (array_key_exists('value', $item)) {
    //     $values[$key]['value'] = $item['value']['value'];
    //   }
    // }
    // $values = $this->genericSelectMassageFormValues('organism_id', $values);.
    // Return $this->massageLinkingFormValues('organism_id', $values, $form_state);.
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

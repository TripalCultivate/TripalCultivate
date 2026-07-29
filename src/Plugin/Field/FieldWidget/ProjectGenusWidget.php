<?php

namespace Drupal\trpcultivate\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tripal\TripalField\Attribute\TripalFieldWidget;
use Drupal\tripal_chado\Controller\ChadoCVTermAutocompleteController;
use Drupal\tripal_chado\TripalField\ChadoWidgetBase;
use Drupal\tripal_chado\Controller\ChadoOrganismFormElementController;

/**
 * Plugin implementation of the 'project_genus_widget' field widget.
 */
#[TripalFieldWidget(
  id: 'project_genus_widget',
  label: new TranslatableMarkup('Organism Select List'),
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
    $field_settings = $field_definition->getSettings();
    $field_name = $field_definition->get('field_name');

    $options = [];
    // Set some defaults to keep each of the fields simpler.
    $options['select_limit'] = $this->getSelectLimit($options['select_limit'] ?? NULL);
    $options['match_operator'] ??= $this->getSetting('match_operator') ?? 'CONTAINS';
    $options['match_limit'] ??= $this->getSetting('match_limit') ?? 10;
    $options['size'] ??= $this->getSetting('size');
    $options['placeholder'] ??= $this->getSetting('placeholder');

    // Get the select options for the organism select list.
    $select_options = ChadoOrganismFormElementController::getSelectOptions($options);

    // Current Item Values:
    $item_vals = $items[$delta]->getValue();
    $record_id = $item_vals['record_id'] ?? 0;

    // Determine the current organism_id based on the scientific name value.
    $organism_id = 0;
    if (array_key_exists('sciname_value', $item_vals) and $item_vals['sciname_value'] != '') {
      $query = ChadoOrganismFormElementController::getQuery($item_vals['sciname_value'], [])->execute()->fetchAll();
      $sciname_string = $query[0]->organism;
      $abbreviation = $query[0]->abbreviation;
      foreach ($select_options as $id => $option) {
        if ($option == $sciname_string or $option == $abbreviation) {
          $organism_id = $id ?? 0;
        }
      }
    }

    $cv_autocomplete = new ChadoCVTermAutocompleteController();

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
    $genus_term = $field_settings['genus_term'];
    $genus_term_id = $cv_autocomplete->getCVtermId($genus_term);
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
    $sciname_term = $field_settings['sciname_term'];
    $sciname_term_id = $cv_autocomplete->getCVtermId($sciname_term);
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

    // Get the organism select element or auto-complete element.
    $select_element = ChadoOrganismFormElementController::getFormElement($elements, $organism_id, $options);
    $storage = $form_state->getStorage();

    $elements['organism_id'] = $element + $select_element;

    // Save some initial values to allow later handling of the "Remove" button.
    // Note: We do this manually instead of using saveInitialValues() because
    // we have two properties in a single item.
    // We want the initial values, so never update them once saved.
    $messenger = \Drupal::messenger();
    if (!($storage['initial_values'][$field_name][$delta] ?? FALSE)) {
      if (($organism_id == 0) and ($sciname_prop_id != 0 or ($genus_prop_id != 0))) {
        // Add an error message.
        $messenger->addError('The project entity has invalid content and cannot be deleted. Please contact the site administrator.');
      }
      else {
        $storage['initial_values'][$field_name][$delta] = [
          'genus_prop_id' => $genus_prop_id,
          'sciname_linker_id' => $sciname_prop_id,
          'organism_id' => $organism_id,
        ];

        $form_state->setStorage($storage);
      }
    }

    return $elements;
  }

  /**
   * {@inheritDoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    // If there are no values to massage then move on.
    if (!$values) {
      return $values;
    }

    // The field name for the field. There are usually multiple
    // copies of a property field, so this distinguishes them.
    $first_delta = array_key_first($values);
    $field_name = $values[$first_delta]['field_name'];

    // Look up the rank term.
    $storage = \Drupal::entityTypeManager()->getStorage('chado_term_mapping');
    $mapping = $storage->load('core_mapping');
    $rank_term = $this->sanitizeKey($mapping->getColumnTermId('projectprop', 'rank'));
    // Convert the widget fields into an updated $values array
    // with the items expected by the field type.
    $this->preMassageFormValues($values);

    $val = 'organism_id';
    $linker_key = 'sciname_prop_id';

    // Handle any empty values so that chado storage properly
    // deletes the linking record in chado. This happens when an
    // existing record is changed to "- Select -".
    $retained_records = [];
    foreach ($values as $val_key => $value) {
      if ($value[$linker_key]) {
        $retained_records[$val_key] = $value[$linker_key];
      }
      if (array_key_exists($val, $value) and ($value[$val] == '')) {
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
      // For initial values, the key is always 'linker_id', regardless of
      // $linker_key value.
      $sciname_prop_id = $initial_value['sciname_linker_id'];
      $genus_prop_id = $initial_value['genus_prop_id'];
      if ($sciname_prop_id and !in_array($sciname_prop_id, $retained_records)) {
        // This item was removed from the form. Add back a value
        // so that chado storage knows to remove the chado record.
        $values[$next_delta][$linker_key] = $sciname_prop_id;
        $values[$next_delta]['genus_prop_id'] = $genus_prop_id;
        $values[$next_delta][$val] = '';
        $values[$next_delta]['genus_value'] = '';
        $values[$next_delta]['sciname_value'] = '';
        $next_delta++;
      }
    }

    // Reset the weights.
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
   * Convert the values from the widget form fields into an updated array.
   *
   * The array should contain the items that are expected by the field type.
   *
   * @param array &$values
   *   The values array passed to massageFormValues.
   */
  protected function preMassageFormValues(array &$values): void {
    $values = $this->genericSelectMassageFormValues('organism_id', $values);
    $chado = \Drupal::service('tripal_chado.database');
    foreach ($values as $delta => $value) {
      $new_value = $value;
      $new_value['genus_value'] = '';
      $new_value['sciname_value'] = '';

      if ($value['organism_id']) {
        if ($value['organism_id'] != '') {
          $query = ChadoOrganismFormElementController::getQuery('%', []);
          $query->condition('organism_id', $value['organism_id'], '=');
          $result = $query->execute()->fetchAll();
          $new_value['sciname_value'] = $result[0]->organism;
          $sql = 'SELECT genus FROM {1:organism} WHERE organism_id=' . $value['organism_id'];
          $genus = $chado->query($sql)->fetchField();
          $new_value['genus_value'] = $genus;
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

  /**
   * {@inheritdoc}
   */
  protected function formMultipleElements(FieldItemListInterface $items, array &$form, FormStateInterface $form_state) {

    $elements = parent::formMultipleElements($items, $form, $form_state);
    $genus_field_name = $items->getName();
    $max_field_delta = $elements['#max_delta'];

    // Ensure only one organism at a time by appying field states property to
    // add more button to disable itself if an organism select field has not
    // been set a value.
    $item_el = $genus_field_name . '[' . $max_field_delta . '][organism_id]';
    $elements['add_more']['#states'] = [
      'disabled' => [
        ':input[name="' . $item_el . '"]' => ['value' => '']
      ],
      'enabled' => [
        ':input[name="' . $item_el . '"]' => ['filled' => TRUE]
      ]
    ];

    // Before adding another select field, disable already set organism - no
    // more alteration at this point.
    $trigger_el = $form_state->getTriggeringElement() ?? 0;
    if ($trigger_el && $trigger_el['#name'] == 'exp_organism_add_more') {
      $current_values = $form_state->getUserInput()[$genus_field_name] ?? [];

      $used_organism = [];
      foreach ($current_values as $delta => $values) {
        if ($values['organism_id'] != '') {
          $elements[$delta]['organism_id']['#attributes'] = [
            'readonly' => 'readonly',
            'style' => 'pointer-events: none; background-color: #F0F0F0',
          ];

          $used_organism[] = $values['organism_id'];
        }
      }

      // Update the available organism for selection in the newly added
      // select organism field.
      foreach($used_organism as $organism_id) {
        unset($elements[$max_field_delta]['organism_id']['#options'][$organism_id]);
      }
    }


    // Disable organism select field with organism already set on page load.
    if (($storage_initial_values = $form_state->getStorage()['initial_values']) != NULL) {

      $used_organism = [];
      foreach ($storage_initial_values[$genus_field_name] as $delta => $values) {
        if ($values['organism_id'] > 0) {
          $elements[$delta]['organism_id']['#attributes'] = [
            'readonly' => 'readonly',
            'style' => 'pointer-events: none; background-color: #F0F0F0',
          ];

          $used_organism[] = $values['organism_id'];
        }
      }

      // Update the the available organism for selection in added select
      // organism field.
      foreach($used_organism as $organism_id) {
        unset($elements[$max_field_delta]['organism_id']['#options'][$organism_id]);
      }
    }


    return $elements;
  }

}

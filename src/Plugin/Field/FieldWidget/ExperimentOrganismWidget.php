<?php

namespace Drupal\trpcultivate\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tripal\TripalField\Attribute\TripalFieldWidget;
use Drupal\tripal_chado\TripalField\ChadoWidgetBase;
use Drupal\tripal_chado\Controller\ChadoOrganismAutocompleteController;

/**
 * Plugin implementation of the 'experiment_organism_widget' field widget.
 */
#[TripalFieldWidget(
  id: 'experiment_organism_widget',
  label: new TranslatableMarkup('Organism Select List'),
  description: new TranslatableMarkup('Provides a select list of organisms in this site.'),
  field_types: [
    'experiment_organism',
  ],
)]
class ExperimentOrganismWidget extends ChadoWidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {

    // Grab the values for our properties based on the passed in delta.
    // For fields with a cardinality above 1, this is called one per record
    // with the delta indicating the current record.
    $item_vals = $items[$delta]->getValue();

    $options = [];

    // Set some defaults to keep each of the fields simpler.
    $options['select_limit'] = $this->getSelectLimit($options['select_limit'] ?? NULL);
    $options['match_operator'] ??= $this->getSetting('match_operator') ?? 'CONTAINS';
    $options['match_limit'] ??= $this->getSetting('match_limit') ?? 10;
    $options['size'] ??= $this->getSetting('size');
    $options['placeholder'] ??= $this->getSetting('placeholder');

    // Construct a query
    // A single wildcard indicates that all records are to be returned.
    $string = '%';
    // Add one to select limit so we know if it is exceeded.
    $count_options = $options;
    $count_options['match_limit'] = $options['select_limit'] + 1;
    $query = ChadoOrganismAutocompleteController::getQuery($string, $count_options);
    $default_id = $item_vals['organism_id'] ?? 0;

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
      $organism_element = [
        '#type' => 'textfield',
        '#default_value' => $default_value,
        '#autocomplete_route_name' => 'tripal_chado.organism_autocomplete',
        '#autocomplete_route_parameters' => ['match_limit' => $options['match_limit']],
        '#size' => $options['size'],
        '#placeholder' => $options['placeholder'],
      ];
      unset($options['size']);
      unset($options['placeholder']);
      $organism_element['#autocomplete_route_parameters'] = $options;
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
      $organism_element = [
        '#type' => 'select',
        '#options' => $select_options,
        '#default_value' => $default_id,
        '#empty_option' => $this->t('- Select -'),
      ];
    }
    $organism_element['#element_validate'] = [[static::class, 'validateAutocomplete']];

    return $element + $organism_element;
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

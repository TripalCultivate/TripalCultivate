<?php

namespace Drupal\trpcultivate\Plugin\Field\FieldType;

use Drupal\core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tripal\TripalField\Attribute\TripalFieldType;
use Drupal\tripal\Entity\TripalEntityType;
use Drupal\tripal_chado\Controller\ChadoCVTermAutocompleteController;
use Drupal\tripal_chado\TripalField\ChadoFieldItemBase;
use Drupal\tripal_chado\TripalStorage\ChadoIntStoragePropertyType;
// Make sure to include the Property type class you are going to create
// in your addTypes() method below.
use Drupal\tripal_chado\TripalStorage\ChadoVarCharStoragePropertyType;

/**
 * Plugin implementation of the 'project_genus' field type.
 */
#[TripalFieldType(
  id: 'project_genus',
  category: 'tripal_chado',
  label: new TranslatableMarkup('Project Organism'),
  description: new TranslatableMarkup('Allows you to associate an organism with a project through properties since there is no linking table in chado.'),
  default_widget: 'project_genus_widget',
  default_formatter: 'project_genus_formatter',
)]
class ProjectGenusTypeItem extends ChadoFieldItemBase {

  /**
   * The unique identifier of this field.
   *
   * NOTE: must match the id in the Attribute.
   *
   * @var string
   */
  public static $id = "project_genus";

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $settings = parent::defaultFieldSettings();
    // CV Term is 'Genus'.
    $settings['termIdSpace'] = 'TAXRANK';
    $settings['termAccession'] = '0000005';

    $settings['genus_term'] = 'genus (TAXRANK:0000005)';
    $settings['sciname_term'] = 'scientific name (NCBITaxon:scientific_name)';

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    $settings = parent::defaultStorageSettings();
    $settings['storage_plugin_id'] = 'chado_storage';
    $settings['storage_plugin_settings']['prop_table'] = 'projectprop';

    return $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function mainPropertyName() {
    // Note: this could be either sciname_value or genus_value so we just
    // randomly picked one. This is currently used by core Tripal when checking
    // if this field is empty.
    return 'sciname_value';
  }

  /**
   * {@inheritdoc}
   */
  public static function tripalTypes($field_definition) {

    // Retrieve the storage settings.
    $entity_type_id = $field_definition->getTargetEntityTypeId();
    $settings = $field_definition->getSetting('storage_plugin_settings');
    $base_table = $settings['base_table'];
    if (!$base_table) {
      return;
    }

    return [
      new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'record_id', self::$record_id_term, [
        'action' => 'store_id',
        'drupal_store' => TRUE,
        'path' => $base_table . '.project_id',
      ]),
      new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'genus_prop_id', self::$record_id_term, [
        'action' => 'store_pkey',
        'drupal_store' => TRUE,
        'path' => 'project.project_id>genusprop.projectprop_id',
        'table_alias_mapping' => ['genusprop' => 'projectprop'],
      ]),
      new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'genus_prop_fkey', self::$record_id_term, [
        'action' => 'store_link',
        'path' => 'project.project_id>genusprop.project_id',
        'table_alias_mapping' => ['genusprop' => 'projectprop'],
      ]),
      new ChadoVarCharStoragePropertyType($entity_type_id, self::$id, 'genus_value', 'NCIT:C25712', 100, [
        'action' => 'store',
        'path' => 'project.project_id>genusprop.project_id;value',
        'table_alias_mapping' => ['genusprop' => 'projectprop'],
        'delete_if_empty' => TRUE,
      ]),
      new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'genus_rank', 'OBCS:0000117', [
        'action' => 'store',
        'path' => 'project.project_id>genusprop.project_id;rank',
        'table_alias_mapping' => ['genusprop' => 'projectprop'],
      ]),
      new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'genus_type_id', 'schema:additionalType', [
        'action' => 'store',
        'path' => 'project.project_id>genusprop.project_id;type_id',
        'table_alias_mapping' => ['genusprop' => 'projectprop'],
      ]),
      new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'sciname_prop_id', self::$record_id_term, [
        'action' => 'store_pkey',
        'drupal_store' => TRUE,
        'path' => 'project.project_id>scinameprop.projectprop_id',
        'table_alias_mapping' => ['scinameprop' => 'projectprop'],
      ]),
      new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'sciname_prop_fkey', self::$record_id_term, [
        'action' => 'store_link',
        'path' => 'project.project_id>scinameprop.project_id',
        'table_alias_mapping' => ['scinameprop' => 'projectprop'],
      ]),
      new ChadoVarCharStoragePropertyType($entity_type_id, self::$id, 'sciname_value', 'NCIT:C25712', 100, [
        'action' => 'store',
        'path' => 'project.project_id>scinameprop.project_id;value',
        'table_alias_mapping' => ['scinameprop' => 'projectprop'],
        'delete_if_empty' => TRUE,
      ]),
      new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'sciname_rank', 'OBCS:0000117', [
        'action' => 'store',
        'path' => 'project.project_id>scinameprop.project_id;rank',
        'table_alias_mapping' => ['scinameprop' => 'projectprop'],
      ]),
      new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'sciname_type_id', 'schema:additionalType', [
        'action' => 'store',
        'path' => 'project.project_id>scinameprop.project_id;type_id',
        'table_alias_mapping' => ['scinameprop' => 'projectprop'],
      ]),
    ];
  }

  /**
   * We need to set the type_id property value to match the cvterm_id.
   *
   * To do this we'll override the tripalValuesTemplate() and give the
   * `type_id` property a default value.
   *
   * {@inheritDoc}
   *
   * @see \Drupal\tripal\TripalField\TripalFieldItemBase::tripalValuesTemplate()
   */
  public function tripalValuesTemplate($field_definition, $default_value = NULL) {
    $cv_autocomplete = new ChadoCVTermAutocompleteController();

    // Use the parent method to get a template values array.
    $prop_values = parent::tripalValuesTemplate($field_definition, $default_value);

    // Term: genus.
    $genus_term = $this->getSetting('genus_term');
    // Term: scientific name.
    $sciename_term = $this->getSetting('sciname_term');

    // FIX the type_id for both our properties using the terms above.
    foreach ($prop_values as $index => $prop_value) {
      if ($prop_value->getKey() == 'genus_type_id') {
        $prop_values[$index]->setValue($cv_autocomplete->getCVtermId($genus_term));
      }
      elseif ($prop_value->getKey() == 'sciname_type_id') {
        $prop_values[$index]->setValue($cv_autocomplete->getCVtermId($sciename_term));
      }
    }

    return $prop_values;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();
    return $constraints;
  }

  /**
   * {@inheritDoc}
   *
   * @see \Drupal\tripal_chado\TripalField\ChadoFieldItemBase::isCompatible()
   */
  public function isCompatible(TripalEntityType $entity_type) : bool {
    $compatible = TRUE;

    // Get the base table for the content type.
    $base_table = $entity_type->getThirdPartySetting('tripal', 'chado_base_table');
    if ($base_table != 'project') {
      $compatible = FALSE;
    }
    return $compatible;
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::fieldSettingsForm($form, $form_state);

    $elements['genus_term'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Genus'),
      '#required' => FALSE,
      '#default_value' => $this->getSetting('genus_term'),
      '#disabled' => FALSE,
      '#autocomplete_route_name' => 'tripal.cvterm_autocomplete',
      '#autocomplete_route_parameters' => ['count' => 10],
      '#element_validate' => [[static::class, 'validateGenusAutocomplete']],
      '#description' => $this->t('The term to use as the type_id for the project property describing the genus saved by this field.'),
    ];

    $elements['sciname_term'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Scientific Name'),
      '#required' => FALSE,
      '#default_value' => $this->getSetting('sciname_term'),
      '#disabled' => FALSE,
      '#autocomplete_route_name' => 'tripal.cvterm_autocomplete',
      '#autocomplete_route_parameters' => ['count' => 10],
      '#element_validate' => [[static::class, 'validateScinameAutocomplete']],
      '#description' => $this->t('The term to use as the type_id for the project property describing the scientific name saved by this field.'),
    ];

    return $elements;
  }

  /**
   * Form element validation handler for the Genus term field.
   *
   * @param array $form
   *   The form element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   */
  public static function validateGenusAutocomplete($form, FormStateInterface $form_state) {
    $element_parents = $form['#parents'];
    $element_value = $form['#value'];

    if ($element_value) {
      $cv_autocomplete = new ChadoCVTermAutocompleteController();
      $cvterm_id = $cv_autocomplete->getCVtermId($element_value);
      if (!$cvterm_id) {
        $form_state->setErrorByName(implode('][', $element_parents),
            t('The Controlled Vocabulary Term "@term" is not a valid term', ['@term' => $element_value]));
      }
    }
  }

  /**
   * Form element validation handler for the Scientific name term field.
   *
   * @param array $form
   *   The form element being validated.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   */
  public static function validateScinameAutocomplete($form, FormStateInterface $form_state) {
    $element_parents = $form['#parents'];
    $element_value = $form['#value'];

    if ($element_value) {
      $cv_autocomplete = new ChadoCVTermAutocompleteController();
      $cvterm_id = $cv_autocomplete->getCVtermId($element_value);
      if (!$cvterm_id) {
        $form_state->setErrorByName(implode('][', $element_parents),
            t('The Controlled Vocabulary Term "@term" is not a valid term', ['@term' => $element_value]));
      }
    }
  }

}

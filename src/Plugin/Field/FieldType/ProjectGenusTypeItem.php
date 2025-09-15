<?php

namespace Drupal\trpcultivate\Plugin\Field\FieldType;

use Drupal\core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\tripal\TripalField\Attribute\TripalFieldType;
use Drupal\tripal\Entity\TripalEntityType;
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
  label: new TranslatableMarkup('Project Genus'),
  description: new TranslatableMarkup('Germplasm species being observed in this experiment.'),
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
    $field_settings = parent::defaultFieldSettings();
    // CV Term is 'Genus'.
    $field_settings['termIdSpace'] = 'TAXRANK';
    $field_settings['termAccession'] = '0000005';
    return $field_settings;
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
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data) {
    $elements = [];
    // We need to set the prop table for this field but we need to know
    // the base table to do that. So we'll add a new validation function so
    // we can get it and set the proper storage settings.
    $elements = parent::storageSettingsForm($form, $form_state, $has_data);
    $elements['storage_plugin_settings']['base_table']['#element_validate'] = [
      [static::class, 'storageSettingsFormValidate'],
    ];
    return $elements;
  }

  /**
   * Form element validation handler.
   *
   * @param array $form
   *   The form where the settings form is being included in.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state of the (entire) configuration form.
   */
  public static function storageSettingsFormValidate(array $form, FormStateInterface $form_state) {
    $settings = self::getFormStateSettings($form_state);
    if (!array_key_exists('storage_plugin_settings', $settings)) {
      return;
    }
    $base_table = $settings['storage_plugin_settings']['base_table'];
    $prop_table = $base_table . 'prop';

    $chado = \Drupal::service('tripal_chado.database');
    $schema = $chado->schema();
    if ($schema->tableExists($prop_table)) {
      $form_state->setValue(['settings', 'storage_plugin_settings', 'prop_table'], $prop_table);
    }
    else {
      $form_state->setErrorByName('storage_plugin_settings][base_table',
          'The selected base table does not have an associated property table.');
    }
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
    $idSpace_manager = \Drupal::service('tripal.collection_plugin_manager.idspace');

    // Use the parent method to get a template values array.
    $prop_values = parent::tripalValuesTemplate($field_definition, $default_value);

    // Term: genus.
    $idSpace = $idSpace_manager->loadCollection('TAXRANK');
    $genus_term = $idSpace->getTerm('0000005');
    // Term: scientific name.
    $idSpace = $idSpace_manager->loadCollection('NCBITaxon');
    $sciename_term = $idSpace->getTerm('scientific_name');

    // FIX the type_id for both our properties using the terms above.
    foreach ($prop_values as $index => $prop_value) {
      if ($prop_value->getKey() == 'genus_type_id') {
        $prop_values[$index]->setValue($genus_term->getInternalId());
      }
      elseif ($prop_value->getKey() == 'sciname_type_id') {
        $prop_values[$index]->setValue($sciename_term->getInternalId());
      }
    }

    return $prop_values;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraints = parent::getConstraints();

    /**
     * Ensure that the value entered is not larger then the max length.
     * @code
     * if ($max_length = $this->getSetting('max_length')) {
     *   $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
     *   $constraints[] = $constraint_manager->create('ComplexData', [
     *     'value' => [
     *       'Length' => [
     *         'max' => $max_length,
     *         'maxMessage' => t('%name: may not be longer than @max characters.', [
     *           '%name' => $this
     *           ->getFieldDefinition()
     *           ->getLabel(),
     *           '@max' => $max_length,
     *         ]),
     *       ],
     *     ],
     *   ]);
     * }
     * @endcode
     */

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

}

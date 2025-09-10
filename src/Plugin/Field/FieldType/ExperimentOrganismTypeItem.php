<?php

namespace Drupal\trpcultivate\Plugin\Field\FieldType;

use Drupal\core\Field\FieldDefinitionInterface;
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
 * Plugin implementation of the 'experiment_organism' field type.
 */
#[TripalFieldType(
  id: 'experiment_organism',
  category: 'tripal_chado',
  label: new TranslatableMarkup('Experiment Species'),
  description: new TranslatableMarkup('Germplasm species being observed in this experiment.'),
  default_widget: 'experiment_organism_widget',
  default_formatter: 'experiment_organism_formatter',
  cardinality: 1,
)]
class ExperimentOrganismTypeItem extends ChadoFieldItemBase {

  /**
   * The unique identifier of this field.
   *
   * NOTE: must match the id in the Attribute.
   *
   * @var string
   */
  public static $id = "experiment_organism";

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings() {
    $settings = [];

    return $settings + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings() {
    $settings = parent::defaultStorageSettings();

    $settings['storage_plugin_settings']['prop_table'] = 'projectprop';
    $settings['storage_plugin_id'] = 'chado_storage';

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

    // Use Tripal DBX to determine the primary key for this base table.
    $base_pkey_col = 'project_id';

    return [
      // Add your chado property types here.
      // This is REQUIRED before you can test this field through the UI.
      new ChadoIntStoragePropertyType($entity_type_id, self::$id, 'record_id', self::$record_id_term, [
        'action' => 'store_id',
        'drupal_store' => TRUE,
        'path' => $base_table . '.' . $base_pkey_col,
      ]),
      new ChadoVarCharStoragePropertyType($entity_type_id, self::$id, 'value', self::$record_id_term, 100, [
        'action' => 'store',
        'path' => $base_table . '.name',
      ]),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $values = [];

    // Use something like the following to retrieve storage settings.
    // $max_length = $field_definition->getSetting('max_length')
    $max_length = 100;

    // Generate a random value to use as a sample.
    $random = new Random();
    $values['record_id'] = 1;
    $values['value'] = $random->word(mt_rand(1, $max_length));

    return $values;
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

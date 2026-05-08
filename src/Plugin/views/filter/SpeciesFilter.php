<?php

namespace Drupal\trpcultivate\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;

/**
 * Crop Search Views Filter.
 *
 * @ingroup views_filter_handlers.
 */
#[ViewsFilter("species_filter")]
class SpeciesFilter extends FilterPluginBase {

  /**
   * {@inheritdoc}
   */
  public function canExpose() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['value'] = [
      'contains' => [
        // 'crop' => ['default' => ''],
        'genus' => ['default' => ''],
        'species' => ['default' => ''],
      ],
    ];
    $options['organism_field'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $entity_field_manager = \Drupal::service('entity_field.manager');
    $fields_defs = $entity_field_manager->getFieldStorageDefinitions('tripal_entity');

    $fields = [];

    foreach ($fields_defs as $field_name => $definition) {
      if ($definition->getType() === 'chado_organism_type_default') {
        $fields[$field_name] = $field_name;
      }
    }

    $form['organism_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Organism field'),
      '#options' => $fields,
      '#default_value' => $this->options['organism_field'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function valueForm(&$form, FormStateInterface $form_state) {
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $bundle_key = $entity_type_manager
      ->getDefinition('tripal_entity')
      ->getKey('bundle');
    // $crop_options = $this->getCropOptions();
    // $form['crop'] = [
    //   '#type' => 'radios',
    //   '#title' => $this->t('Crop'),
    //   '#options' => $crop_options,
    // ];
    $genus_options = ['' => $this->t('- Select genus -')];
    $genus_results = $entity_type_manager
      ->getStorage('tripal_entity')
      ->getAggregateQuery()
      ->accessCheck(FALSE)
      ->condition($bundle_key, 'organism')
      ->condition('organism_genus.value', '', '<>')
      ->groupBy('organism_genus.value')
      ->execute();
    foreach ($genus_results as $row) {
      $genus = $row['organism_genus_value'];
      $genus_options[$genus] = $genus;
    }
    $form['value']['genus'] = [
      '#type' => 'select',
      '#title' => $this->t('Genus'),
      '#options' => $genus_options,
      '#default_value' => $this->value,
    ];
    // Species options (from genus).
    $species_options = ['' => $this->t('- Select species -')];
    $species_results = $entity_type_manager
      ->getStorage('tripal_entity')
      ->getAggregateQuery()
      ->accessCheck(FALSE)
      ->condition($bundle_key, 'organism')
      ->condition('organism_species.value', '', '<>')
      ->groupBy('organism_species.value')
      ->execute();
    foreach ($species_results as $row) {
      $species = $row['organism_species_value'];
      $species_options[$species] = $species;
    }
    $form['value']['species'] = [
      '#type' => 'select',
      '#title' => $this->t('Species'),
      '#options' => $species_options,
      '#default_value' => $this->value,
    ];
  }

  // /**
  //  * Build crop options.
  //  */
  // protected function getCropOptions() {
  //   $options = [];
  //   return $options;

  /**
   * {@inheritdoc}
   */
  public function adminSummary() {
    return $this->value['genus'] . ' ' . $this->value['species'];
  }

  /**
   * {@inheritdoc}
   */
  public function acceptExposedInput($input) {
    $this->value['genus'] = $input['genus'];

    $this->value['species'] = $input['species'];

    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * Apply species filter to the View query.
   */
  public function query() {
    // Ensure the base table (tripal_entity) is initialized.
    $this->ensureMyTable();

    // Get the selected field.
    $field = '';
    if ($this->options['organism_field'] !== '') {
      $field = $this->options['organism_field'];
    }
    // Build the query according to the selected field.
    if ($field) {
      $field_table_alias = $this->query->ensureTable("tripal_entity__$field", $this->relationship);

      // Use the alias to add your specific genus/species conditions.
      if (!empty($this->value['genus'])) {
        $this->query->addWhere($this->options['group'], "$field_table_alias.{$field}_organism_genus", $this->value['genus'], 'IN');
      }

      if (!empty($this->value['species'])) {
        $this->query->addWhere($this->options['group'], "$field_table_alias.{$field}_organism_species", $this->value['species'], 'IN');
      }
    }
  }

}

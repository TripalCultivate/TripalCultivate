<?php

namespace Drupal\trpcultivate\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
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
        'crop' => ['default' => ''],
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
    $crop_options = $this->buildCropImageOptions();

    $form['value']['crop'] = [
      '#type' => 'radios',
      '#title' => $this->t('Crop'),
      '#options' => $crop_options,
      '#default_value' => $this->value['crop'] ?? '',
    ];

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
      '#default_value' => '',
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
      '#default_value' => '',
    ];

  }

  /**
   * Get the crop options.
   */
  protected function getCropOptions() {

    $crop_options = [
      'Cicer' => [
        'title' => 'Chickpea',
        'genus' => 'Cicer',
        'crop-species' => 'arietinum',
        'image' => 'images/crops/chickpea.jpg',
      ],
      'Lens' => [
        'title' => 'Lentil',
        'genus' => 'Lens',
        'crop-species' => 'culinaris',
        'image' => 'images/crops/lentil.jpg',
      ],
      'Phaseolus' => [
        'title' => 'Dry Bean',
        'genus' => 'Phaseolus',
        'crop-species' => 'vulgaris',
        'image' => 'images/crops/drybean.jpg',
      ],
      'Vicia' => [
        'title' => 'Faba Bean',
        'genus' => 'Vicia',
        'crop-species' => 'faba',
        'image' => 'images/crops/faba.jpg',
      ],
      'Pisum' => [
        'title' => 'Field Pea',
        'genus' => 'Pisum',
        'crop-species' => 'sativum',
        'image' => 'images/crops/pea.jpg',
      ],
    ];

    return $crop_options;
  }

  /**
   * Build crop options.
   */
  protected function buildCropImageOptions() {
    $options = [];

    foreach ($this->getCropOptions() as $key => $crop) {
      $image_markup = '';

      if (!empty($crop['image'])) {
        $relative_path = 'modules/contrib/TripalCultivate/' . $crop['image'];
        $absolute_path = '/var/www/drupal/web/' . $relative_path;

        if (file_exists($absolute_path)) {
          $image_markup = '<img src="' . base_path() . $relative_path . '" alt="' . $crop['title'] . '" />';
        }
      }

      $options[$key] = Markup::create(
      '<div class="crop-option">
        ' . $image_markup . '
        <div class="crop-title">' . $crop['title'] . '</div>
       </div>'
      );
    }

    return $options;

  }

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
    $this->value['genus'] = '';
    $this->value['species'] = '';

    if (!empty($input['crop'])) {
      $crop_options = $this->getCropOptions();

      if (isset($crop_options[$input['crop']])) {
        $this->value['genus'] = $crop_options[$input['crop']]['genus'];
        $this->value['species'] = $crop_options[$input['crop']]['crop-species'];
      }
    }
    else {
      $this->value['genus'] = $input['genus'] ?? '';
      $this->value['species'] = $input['species'] ?? '';
    }

    return !empty($this->value['genus']) || !empty($this->value['species']);
  }

  /**
   * {@inheritdoc}
   *
   * Apply species filter to the View query.
   */
  public function query() {

    // Ensure the base table (tripal_entity) is initialized.
    $this->ensureMyTable();

    if (empty($this->options['organism_field'])) {
      return;
    }

    // Get the selected field.
    $field = $this->options['organism_field'];

    // Build the query according to the selected field.
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

<?php

namespace Drupal\trpcultivate\Plugin\views\filter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\views\Attribute\ViewsFilter;
use Drupal\views\Plugin\views\filter\FilterPluginBase;
use Drupal\file\Entity\File;

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

    $options['organism_field'] = ['default' => ''];
    $options['organism_image'] = ['default' => ''];

    $options['value'] = [
      'contains' => [
        'crop' => ['default' => NULL],
        'genus' => ['default' => ''],
        'species' => ['default' => ''],
      ],
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    // Hide the value form in the Views admin UI.
    if (isset($form['value'])) {
      $form['value']['#access'] = FALSE;
    }

    $entity_field_manager = \Drupal::service('entity_field.manager');
    $fields_defs = $entity_field_manager->getFieldStorageDefinitions('tripal_entity');

    // Get organism fields for the dropdown.
    $organism_fields = [];

    foreach ($fields_defs as $field_name => $definition) {
      if ($definition->getType() === 'chado_organism_type_default') {
        $organism_fields[$field_name] = $field_name;
      }
    }

    $form['organism_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Organism field'),
      '#options' => $organism_fields,
      '#default_value' => $this->options['organism_field'],
    ];

    // Get image fields for the image dropdown.
    $image_fields = [];

    foreach ($fields_defs as $field_name => $definition) {
      if ($definition->getType() === 'image') {
        $image_fields[$field_name] = $field_name;
      }
    }

    $form['organism_image'] = [
      '#type' => 'select',
      '#title' => $this->t('Organism Image'),
      '#options' => $image_fields,
      '#default_value' => $this->options['organism_image'],
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {
    $entity_type_manager = \Drupal::service('entity_type.manager');

    $form['#attached']['library'][] = 'trpcultivate/species_filter';
    $bundle_key = $entity_type_manager
      ->getDefinition('tripal_entity')
      ->getKey('bundle');
    $crop_options = $this->buildCropImageOptions();

    // Hidden element to keep track of the selection type.
    $form['crop_used'] = [
      '#type' => 'hidden',
      '#value' => '0',
    ];

    $form['crop'] = [
      '#type' => 'radios',
      '#title' => $this->t('Crop'),
      '#options' => $crop_options,
      '#default_value' => NULL,
      '#attributes' => [
        'class' => ['crop-radios'],
        'onchange' => 'this.form.crop_used.value = 1; this.form.submit();',
      ],
    ];

    $genus_options = ['' => $this->t('- Select genus -')];
    $genus_results = $entity_type_manager
      ->getStorage('tripal_entity')
      ->getAggregateQuery()
      ->accessCheck(FALSE)
      ->condition($bundle_key, 'organism', '=')
      ->condition('organism_genus.value', NULL, 'IS NOT NULL')
      ->condition('organism_genus.value', '', '<>')
      ->groupBy('organism_genus.value')
      ->execute();
    foreach ($genus_results as $row) {
      $genus = $row['organism_genus_value'];
      $genus_options[$genus] = $genus;
    }

    // Species options (from genus).
    $species_options = ['' => $this->t('- Select species -')];
    $species_results = $entity_type_manager
      ->getStorage('tripal_entity')
      ->getAggregateQuery()
      ->accessCheck(FALSE)
      ->condition($bundle_key, 'organism')
      ->condition('organism_species.value', NULL, 'IS NOT NULL')
      ->condition('organism_species.value', '', '<>')
      ->groupBy('organism_species.value')
      ->execute();
    foreach ($species_results as $row) {
      $species = $row['organism_species_value'];
      $species_options[$species] = $species;
    }

    $crop_options = $this->getCropOptions();
    $input = $form_state->getUserInput() ?? [];

    $selected_crop = $input['crop'] ?? NULL;
    $selected_genus = $input['genus'] ?? '';
    $selected_species = $input['species'] ?? '';
    $crop_used = $input['crop_used'] ?? '0';

    if (!empty($selected_crop) && $crop_used == '1') {

      if (isset($crop_options[$selected_crop])) {
        $selected_genus = $crop_options[$selected_crop]['genus'];
        $selected_species = $crop_options[$selected_crop]['crop-species'];

        $input['genus'] = $selected_genus;
        $input['species'] = $selected_species;
        if (!in_array($selected_genus, $genus_options)) {
          $input['genus'] = '';
        }
        if (!in_array($selected_species, $species_options)) {
          $input['species'] = '';
        }
      }
    }

    // Unset the existing crop image selection when dropdown is used to select
    // the species.
    elseif ($crop_used == '0') {
      unset($input['crop']);
    }

    $form_state->setUserInput($input);

    $form['genus'] = [
      '#type' => 'select',
      '#title' => $this->t('Genus'),
      '#options' => $genus_options,
      '#default_value' => $selected_genus,
    ];
    $form['species'] = [
      '#type' => 'select',
      '#title' => $this->t('Species'),
      '#options' => $species_options,
      '#default_value' => $selected_species,
    ];
  }

  /**
   * Get the crop options.
   */
  protected function getCropOptions() {

    // If no image field is selected, erturn an emptly array.
    if (empty($this->options['organism_image'])) {
      return [];
    }

    // Get the selected image field and build the crop options based on that.
    $image_field = $this->options['organism_image'];
    $crop_options = [];
    $ids = \Drupal::entityQuery('tripal_entity')
      ->condition('type', 'organism')
      ->exists('organism_common_name')
      ->accessCheck(FALSE)->execute();

    $entities = \Drupal::entityTypeManager()
      ->getStorage('tripal_entity')
      ->loadMultiple($ids);

    foreach ($entities as $entity) {
      $crop_options[$entity->get('organism_common_name')->value] = [
        'title' => $entity->get('organism_common_name')->value,
        'genus' => $entity->get('organism_genus')->value,
        'crop-species' => $entity->get('organism_species')->value,
      ];
      if ($entity->hasField($image_field) && !$entity->get($image_field)->isEmpty()) {
        $items = $entity->get($image_field)->getValue();
        $file = File::load($items[0]['target_id']);
        if ($file) {
          $uri = $file->getFileUri();
        }
        $crop_options[$entity->get('organism_common_name')->value]['image'] = $uri ?? '';
      }
    }
    return $crop_options;
  }

  /**
   * Build crop options.
   */
  protected function buildCropImageOptions() {
    $options = [];

    foreach ($this->getCropOptions() as $key => $crop) {
      $image_markup = '';

      if (!empty($crop['image']) && file_exists($crop['image'])) {
        $url = \Drupal::service('file_url_generator')
          ->generateString($crop['image']);
        $image_markup = '<img src="' . $url . '" alt="' . $crop['title'] . '" />';
      }

      $options[$key] = Markup::create(
      '<div class="crop-option">
        <div class="crop-image">' . $image_markup . '</div>
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

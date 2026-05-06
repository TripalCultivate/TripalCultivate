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
      'default' => [
        // 'crop' => '',
        'genus' => '',
        'species' => '',
      ],
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildExposedForm(&$form, FormStateInterface $form_state) {
    $entity_type_manager = \Drupal::service('entity_type.manager');

    $bundle_key = $entity_type_manager
      ->getDefinition('tripal_entity')
      ->getKey('bundle');

    // $crop_options = $this->getCropOptions();
    // $form['crop'] = [
    //   '#type' => 'radios',
    //   '#title' => $this->t('Crop'),
    //   '#options' => $crop_options,
    //   '#default_value' => $this->value,
    // ];
    // Genus options.
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

    $form['genus'] = [
      '#type' => 'select',
      '#title' => $this->t('Genus'),
      '#options' => $genus_options,
      '#default_value' => $this->value,
    ];

    // Species options.
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

    $form['species'] = [
      '#type' => 'select',
      '#title' => $this->t('Species'),
      '#options' => $species_options,
      '#default_value' => $this->value,
    ];
  }

  /**
   * Build crop options.
   */
  protected function getCropOptions() {
    $options = [];
    return $options;
  }

  /**
   * {@inheritdoc}
   *
   * Apply species filter to the View query.
   */
  public function query() {
    $this->ensureMyTable();

    $this->query->addWhere($this->options['group'], "tripal_entity__organism_genus.organism_genus_value", $this->value['genus'], '=');
    $this->query->addWhere($this->options['group'], "tripal_entity__organism_species.organism_species_value", $this->value['species'], '=');
  }

}

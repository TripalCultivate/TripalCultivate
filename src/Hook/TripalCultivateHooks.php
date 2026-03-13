<?php

namespace Drupal\trpcultivate\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\tripal\Services\TripalLogger;

/**
 * Implements hooks for Tripal Cultivate.
 */
class TripalCultivateHooks {
  use StringTranslationTrait;

  /**
   * The TripalLogger service.
   *
   * @var Drupal\tripal\Services\TripalLogger
   */
  protected $logger;

  /**
   * Constructs a TripalCultivateHooks object.
   *
   * @param Drupal\tripal\Services\TripalLogger $logger
   *   The TripalLogger service.
   */
  public function __construct(TripalLogger $logger) {
    $this->logger = $logger;
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    switch ($route_name) {
      // Provides the module overview in the help tab.
      case 'help.page.trpcultivate':
        $output = '';
        $output .= '<h3>' . $this->t('About') . '</h3>';

        $output .= '<p>' . $this->t('This module provides basic functionality shared by the entire Tripal Cultivate package of modules.') . '</p>';

        return $output;

      default:
    }
  }

  /**
   * Implements hook_config_schema_info_alter().
   *
   * Update the schema to support markup fields.
   */
  #[Hook('config_schema_info_alter')]
  public function configSchemaInfoAlter(&$definitions) {

    // Support for the Entity Reference Field being used on a TripalEntity.
    // -- field settings.
    if (array_key_exists('field.field_settings.entity_reference', $definitions)) {
      foreach ($definitions['field.field_settings.entity_reference']['mapping'] as $setting_key => $settings) {
        $definitions['field.field.tripal_entity.*.*']['mapping']['settings']['mapping'][$setting_key] = $settings;
      }
    }
    else {
      $this->logger->error("Tripal Cultivate requires the Entity Reference Field for it's content types but it seems to be missing as the 'field.field_settings.entity_reference' schema definition is unavailable.");
    }
    // -- field storage settings.
    if (array_key_exists('field.storage_settings.entity_reference', $definitions)) {
      foreach ($definitions['field.storage_settings.entity_reference']['mapping'] as $setting_key => $settings) {
        $definitions['field.storage.tripal_entity.*']['mapping']['settings']['mapping'][$setting_key] = $settings;
      }
    }
    else {
      $this->logger->error("Tripal Cultivate requires the Entity Reference Field for it's content types but it seems to be missing as the 'field.storage_settings.entity_reference' schema definition is unavailable.");
    }

    // Support for Third Party Tripal field settings being used on TripalEntity.
    // @todo this should likely be in tripal core.
    if (!array_key_exists('third_party_settings', $definitions['field.field.tripal_entity.*.*']['mapping'])) {
      $definitions['field.field.tripal_entity.*.*']['mapping']['third_party_settings'] = [
        'type' => 'mapping',
        'mapping' => [],
      ];
    }
    if (!array_key_exists('tripal', $definitions['field.field.tripal_entity.*.*']['mapping']['third_party_settings']['mapping'])) {
      $definitions['field.field.tripal_entity.*.*']['mapping']['third_party_settings']['mapping']['tripal'] = [
        'type' => 'mapping',
        'mapping' => [],
      ];
    }
    $definitions['field.field.tripal_entity.*.*']['mapping']['third_party_settings']['mapping']['tripal']['mapping']['termIdSpace'] = [
      'type' => 'string',
      'label' => 'Term ID Space',
      'nullable' => TRUE,
    ];
    $definitions['field.field.tripal_entity.*.*']['mapping']['third_party_settings']['mapping']['tripal']['mapping']['termAccession'] = [
      'type' => 'string',
      'label' => 'Term Accession',
      'nullable' => TRUE,
    ];
  }

}

<?php

namespace Drupal\trpcultivate\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Implements Theme hooks for Tripal Cultivate.
 */
class TripalCultivateThemeHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_theme().
   *
   *  @see /templates/importer
   */
  #[Hook('theme')]
  public function theme($existing, $type, $theme, $path) {
    return [
      // Theme importer describe header window.
      'describe_header_window' => [
        'variables' => [
          'data' => [
            'headers' => [],
            'template_file' => '#',
          ],
        ],
        'template' => 'importer/describe-header-window',
      ],

      // Theme importer validation result window.
      'validation_result_window' => [
        'variables' => [
          'data' => [],
        ],
        'template' => 'importer/validation-result-window',
      ],
    ];
  }

  /**
   * Implements hook_preprocess_page().
   */
  #[Hook('preprocess_page')]
  public function preprocessPage(&$variables) {
    // Get the route for the current page.
    $route_name = \Drupal::routeMatch()->getRouteName();

    // If this is a page related to listing of TripalEntityTypes then we want
    // to add the following CSS library.
    $tripal_entity_type_routes = ['entity.tripal_entity.add_page', 'entity.tripal_entity_type.collection'];
    if (in_array($route_name, $tripal_entity_type_routes)) {
      $variables['#attached']['library'][] = 'trpcultivate/tripal_entity_type';
    }

    // Make Font Awesome library available.
    $variables['#attached']['library'][] = 'trpcultivate/cdn-font-awesome';
  }

}

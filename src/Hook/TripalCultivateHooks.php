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

}

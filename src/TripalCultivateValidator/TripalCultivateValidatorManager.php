<?php

namespace Drupal\tripalcultivate\TripalCultivateValidator;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Validator Plugin Manager.
 */
class TripalCultivateValidatorManager extends DefaultPluginManager {

  /**
   * Constructs Validator Plugin Manager.
   *
   * (@todo update the following paths when validators get moved to
   * TripalCultivate Base)
   * NOTES:
   *   Instance of validator in Drupal/tripalcultivate/Plugin/Validator.
   *   Each instance is an implementation of Drupal\tripalcultivate\TripalCultivateValidator\TripalCultivateValidatorInterface.
   *   Use annotations defined by Drupal\tripalcultivate\TripalCultivateValidator\Annotation\TripalCultivateValidator.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct(
      'Plugin/Validators',
      $namespaces,
      $module_handler,
      'Drupal\tripalcultivate\TripalCultivateValidator\TripalCultivateValidatorInterface',
      'Drupal\tripalcultivate\TripalCultivateValidator\Annotation\TripalCultivateValidator'
    );

    // This is the hook name to alter information in this plugin.
    $this->alterInfo('tripalcultivate_validators_info');
    $this->setCacheBackend($cache_backend, 'tripalcultivate_validators');
  }

}

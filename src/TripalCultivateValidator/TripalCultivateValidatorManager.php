<?php

namespace Drupal\trpcultivate\TripalCultivateValidator;

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
   * NOTES:
   *   Instance of validator in Drupal/trpcultivate/Plugin/Validator.
   *   Each instance is an implementation of Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorInterface.
   *   Use annotations defined by Drupal\trpcultivate\TripalCultivateValidator\Annotation\TripalCultivateValidator.
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
      'Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorInterface',
      'Drupal\trpcultivate\TripalCultivateValidator\Attribute\TripalCultivateValidator',
      'Drupal\trpcultivate\TripalCultivateValidator\Annotation\TripalCultivateValidator'
    );

    // This is the hook name to alter information in this plugin.
    $this->alterInfo('trpcultivate_validators_info');
    $this->setCacheBackend($cache_backend, 'trpcultivate_validators');
  }

}

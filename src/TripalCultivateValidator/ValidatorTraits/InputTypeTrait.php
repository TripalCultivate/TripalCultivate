<?php

namespace Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits;

/**
 * Provides getters/setters regarding input type.
 */
trait InputTypeTrait {

  /**
   * Indicate the input type this instance will validate.
   *
   * NOTE: each instance can only validate a single input type. If you want
   * to validate both input types on the same importer, you should create two
   * instances and use them independently.
   *
   * @param string $input_type
   *   The input type that this instance will use.
   *
   * @throws \Exception
   *   - If this is called more then once for a single instance.
   *   - If $input_type does not match one supported by this validator.
   */
  public function setInputType(string $input_type): void {

    $context_key = 'input_type';
    // Check if $input_type is one of those in the attributes.
    if (!in_array($input_type, ['metadata', 'data-row'])) {
      throw new \Exception("Input type $input_type is not supported by this validator.");
    }
    // Check protected variable is not already set.
    if (isset($this->input_type)) {
      throw new \Exception('Input type has already been set for this instance of the validator. Each instance can only validate a single input type.');
    }
    // Set protected variable.
    $this->context[$context_key] = $input_type;
  }

  /**
   * Get the input type this instance is set to validate.
   *
   * @return string
   *   The input type this instance is set to validate.
   *
   * @throws \Exception
   *   If the input type has not yet been set for this instance.
   */
  public function getInputType(): string {

    $context_key = 'input_type';

    if (array_key_exists($context_key, $this->context)) {
      return $this->context[$context_key];
    }
    else {
      throw new \Exception('Input type has not yet been set for this instance of the validator.');
    }
  }

}

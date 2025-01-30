<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators;

use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\ValidValues;

/**
 * Fake Validator that does not implement any of its own methods.
 *
 * Used to test the ValidValues trait.
 *
 * @TripalCultivateValidator(
 * id = "validator_requiring_valid_values",
 * validator_name = @Translation("Validator Using ValidValues Trait"),
 * input_types = {"header-row", "data-row"}
 * )
 */
class ValidatorValidValues extends TripalCultivateValidatorBase {

  use ValidValues;

}

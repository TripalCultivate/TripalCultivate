<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators;

use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\Organism;

/**
 * Fake Validator that does not implement any of its own methods.
 *
 * Used to test the Organism trait.
 *
 * @TripalCultivateValidator(
 *   id = "validator_requiring_organism",
 *   validator_name = @Translation("Validator Using Organism Trait"),
 *   input_types = {"header-row", "data-row"}
 * )
 */
class ValidatorOrganism extends TripalCultivateValidatorBase {

  use Organism;

}

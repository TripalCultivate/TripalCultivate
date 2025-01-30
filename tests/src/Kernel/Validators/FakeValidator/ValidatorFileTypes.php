<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators;

use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\FileTypes;

/**
 * Fake Validator that does not implement any of its own methods.
 *
 * Used to test the FileTypes trait.
 *
 * @TripalCultivateValidator(
 *   id = "validator_requiring_filetypes",
 *   validator_name = @Translation("Validator Using File Types Trait"),
 *   input_types = {"file"}
 * )
 */
class ValidatorFileTypes extends TripalCultivateValidatorBase {

  use FileTypes;

}

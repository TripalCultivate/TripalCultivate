<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators;

use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;

/**
 * Fake Validator that does not implement any of its own methods.
 *
 * Used to test the base class.
 *
 * @TripalCultivateValidator(
 * id = "fake_basically_base",
 * validator_name = @Translation("Basically Base Validator"),
 * input_types = {"header-row", "data-row"}
 * )
 */
class BasicallyBase extends TripalCultivateValidatorBase {

}

<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\InputTypeTrait;
use Drupal\trpcultivate\TripalCultivateValidator\Attribute\TripalCultivateValidator;

/**
 * Fake Validator that does not implement any of its own methods.
 *
 * Used to test the Input Type trait.
 */
#[TripalCultivateValidator(
   id: 'validator_requiring_input_type',
   validator_name: new TranslatableMarkup('Validator Using Input Type Trait'),
   input_types: ['metadata', 'data-row']
 )]
class ValidatorInputType extends TripalCultivateValidatorBase {

  use InputTypeTrait;

}

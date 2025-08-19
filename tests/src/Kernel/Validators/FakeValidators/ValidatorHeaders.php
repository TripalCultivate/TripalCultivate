<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\Headers;
use Drupal\trpcultivate\TripalCultivateValidator\Attribute\TripalCultivateValidator;

/**
 * Fake Validator that does not implement any of its own methods.
 *
 * Used to test the Headers trait.
 */
#[TripalCultivateValidator(
   id: 'validator_requiring_headers',
   validator_name: new TranslatableMarkup('Validator Using Headers Trait'),
   input_types: ['header-row', 'data-row']
 )]
class ValidatorHeaders extends TripalCultivateValidatorBase {

  use Headers;

}

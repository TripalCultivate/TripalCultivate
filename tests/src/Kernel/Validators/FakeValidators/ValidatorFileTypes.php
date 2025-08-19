<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\FileTypes;
use Drupal\trpcultivate\TripalCultivateValidator\Attribute\TripalCultivateValidator;

/**
 * Fake Validator that does not implement any of its own methods.
 *
 * Used to test the FileTypes trait.
 */
#[TripalCultivateValidator(
   id: 'validator_requiring_filetypes',
   validator_name: new TranslatableMarkup('Validator Using File Types Trait'),
   input_types: ['file']
 )]
class ValidatorFileTypes extends TripalCultivateValidatorBase {

  use FileTypes;

}

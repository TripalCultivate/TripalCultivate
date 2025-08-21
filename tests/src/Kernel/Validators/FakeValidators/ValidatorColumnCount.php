<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators;

use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\ColumnCount;
use Drupal\trpcultivate\TripalCultivateValidator\Attribute\TripalCultivateValidator;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Fake Validator that does not implement any of its own methods.
 *
 * Used to test the ColumnCount trait.
 */
#[TripalCultivateValidator(
   id: 'validator_requiring_column_count',
   validator_name: new TranslatableMarkup('Validator Using Column Count Trait'),
   input_types: ['header-row']
 )]
class ValidatorColumnCount extends TripalCultivateValidatorBase {

  use ColumnCount;

}

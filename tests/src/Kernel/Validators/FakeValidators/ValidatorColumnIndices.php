<?php

namespace Drupal\Tests\trpcultivate\Kernel\Validators\FakeValidators;

use Drupal\trpcultivate\TripalCultivateValidator\TripalCultivateValidatorBase;
use Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits\ColumnIndices;
use Drupal\trpcultivate\TripalCultivateValidator\Attribute\TripalCultivateValidator;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Fake Validator that does not implement any of its own methods.
 *
 * Used to test the ColumnIndices trait.
 */
#[TripalCultivateValidator(
 id: 'validator_requiring_column_indices',
 validator_name: new TranslatableMarkup('Validator Using ColumnIndices Trait'),
 input_types: ['header-row', 'data-row']
)]
class ValidatorColumnIndices extends TripalCultivateValidatorBase {

  use ColumnIndices;

}

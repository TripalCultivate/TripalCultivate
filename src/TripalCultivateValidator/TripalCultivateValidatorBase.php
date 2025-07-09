<?php

namespace Drupal\trpcultivate\TripalCultivateValidator;

use Drupal\Component\Plugin\PluginBase;
use Drupal\tripal\Services\TripalLogger;

/**
 * The base class for validator plugins.
 */
abstract class TripalCultivateValidatorBase extends PluginBase implements TripalCultivateValidatorInterface {

  /**
   * A context array with key-value pairs set by ValidatorTraits.
   *
   * An associative array containing the needed context, which is dependant
   * on the validator. For example, instead of validating each cell by default,
   * a validator may need a list of indices corresponding to the columns in
   * the row that the validator should act on. This might look like:
   * $context['indices'] => [1,3,5]
   */
  protected array $context = [];

  /**
   * A mapping array that maps tokens to their details for a validator.
   *
   * This mapping starts with all of the potential cases for a validator,
   * followed by additional tokens which are substitutable within the message(s)
   * provided to the user when validation fails.
   *
   * For each case, the array keys are the substitutable tokens for the entire
   * case message, and MUST contain the prefix of 'case-', and may contain the
   * following key-value pairs:
   * - 'token': the same token (same as the parent key- this can helpful for
   *   code readability). Recall that it must contain the prefix 'case-'.
   * - 'dev-case': The short, developer-focussed string describing the case.
   * - 'default-msg': An informative message that gets displayed to the user
   *   when validation fails for this particular case. This can contain any
   *   number of smaller, non case-specific tokens contained in square brackets.
   *   For example:
   *     "The selected [project] does not exist. Please [contact-admin] to have
   *     this added." where both [project] and [contact-admin] are separate
   *     non case-specific tokens (see definition of remaining tokens below).
   *
   * NOTE: The token 'case-valid' is reserved for the valid case for this
   * validator, and does NOT have a corresponding default message. Although
   * uncommon, when there is more than one valid case, assign 'case-valid' to
   * the most common passed case, and suffix `case-valid' with more details for
   * the other(s).
   *
   * For all remaining tokens, the array key is the substitutable token, with
   * the following key-pairs:
   * - 'token': the substitutable text in a message. This text would become
   *   flanked by brackets within a message string. For eg. [token]
   * - 'default-msg': A string that would substitute the associated token
   *   elsewhere in the render array (e.g. within the case message).
   */
  protected static array $mapping = [];

  /**
   * The TripalLogger service.
   *
   * This is used to report status and errors to both site users and
   * administrators through the server log.
   *
   * @var Drupal\tripal\Services\TripalLogger
   */
  public TripalLogger $logger;

  /**
   * {@inheritdoc}
   */
  public function getValidatorName() {
    return $this->pluginDefinition['validator_name'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedInputTypes() {
    return $this->pluginDefinition['input_types'];
  }

  /**
   * {@inheritdoc}
   */
  public function checkInputTypeSupported(string $input_type) {
    $supported_types = $this->getSupportedInputTypes();

    if (in_array($input_type, $supported_types)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function validateMetadata(array $form_values) {
    $plugin_name = $this->getValidatorName();
    throw new \Exception("Method validateMetadata() from base class called for $plugin_name. If this plugin wants to support this type of validation then they need to override it.");
  }

  /**
   * {@inheritdoc}
   */
  public function validateFile(int|null $fid) {
    $plugin_name = $this->getValidatorName();
    throw new \Exception("Method validateFile() from base class called for $plugin_name. If this plugin wants to support this type of validation then they need to override it.");
  }

  /**
   * {@inheritdoc}
   */
  public function validateRow(array $row_values) {
    $plugin_name = $this->getValidatorName();
    throw new \Exception("Method validateRow() from base class called for $plugin_name. If this plugin wants to support this type of validation then they need to override it.");
  }

  /**
   * {@inheritdoc}
   */
  public function validateRawRow(string $raw_row) {
    $plugin_name = $this->getValidatorName();
    throw new \Exception("Method validateRawRow() from base class called for $plugin_name. If this plugin wants to support this type of validation then they need to override it.");
  }

  /**
   * {@inheritdoc}
   */
  public function checkIndices($row_values, $indices) {

    // Report if the indices array is empty.
    if (!$indices) {
      throw new \Exception(
        'An empty indices array was provided.'
      );
    }

    // Get the potential range by looking at $row_values.
    $num_values = count($row_values);
    // Count our indices array.
    $num_indices = count($indices);
    if ($num_indices > $num_values) {
      throw new \Exception(
        'Too many indices were provided (' . $num_indices . ') compared to the number of cells in the provided row (' . $num_values . ').'
      );
    }

    // Pull out just the keys from $row_values and compare with $indices.
    $row_keys = array_keys($row_values);
    $result = array_diff($indices, $row_keys);
    if ($result) {
      $invalid_indices = implode(', ', $result);
      throw new \Exception(
        'One or more of the indices provided (' . $invalid_indices . ') is not valid when compared to the indices of the provided row.'
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setLogger(TripalLogger $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function getLogger() {
    if (!empty($this->logger)) {
      return $this->logger;
    }
    else {
      throw new \Exception('Cannot retrieve the Tripal Logger property as one has not been set for this validator using the setLogger() method.');
    }
  }

}

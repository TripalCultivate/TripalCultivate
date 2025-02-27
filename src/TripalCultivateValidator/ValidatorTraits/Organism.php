<?php

namespace Drupal\trpcultivate\TripalCultivateValidator\ValidatorTraits;

use Drupal\tripal_chado\Database\ChadoConnection;

/**
 * Provides getters/setters regarding organism and genus.
 */
trait Organism {

  /**
   * A Database query interface for querying Chado using Tripal DBX.
   *
   * @var Drupal\tripal_chado\Database\ChadoConnection
   */
  protected ChadoConnection $chado_connection;

  /**
   * Sets a single organism from its ID.
   *
   * NOTE: This setter is generally for validators that need the exact organism
   * ID of the data type being inserted.
   *
   * @param int $organism_id
   *   A single organism id from the chado.organism table.
   *
   * @throws \Exception
   *   - If an instance of ChadoConnection is not accessible.
   */
  public function setOrganismID(int $organism_id) {
    if (!isset($this->chado_connection)) {
      throw new \Exception('Using setOrganismID() by the Organism Trait needs an instance of ChadoConnection (tripal_chado.database) injected via the create() and set to $this->chado_connection.');
    }
    // Query the organism ID in chado.
    $query = $this->chado_connection->select('1:organism', 'o')
      ->fields('o', ['organism_id'])
      ->condition('o.organism_id', $organism_id);
    $record = $query->execute()->fetchObject();
    if (is_object($record)) {
      $this->context['organism_ids'] = [$organism_id];
    }
    else {
      // Since this is a user-provided value, the error is going to be logged
      // instead of thrown as an exception and then checked by a validator so
      // that the error can be passed to the user in a friendly way.
      $this->logger->error("The organism ID '$organism_id' was not found in chado.organism.");
    }

  }

  /**
   * Sets all of the available organism IDs for a particular genus.
   *
   * NOTE: This setter is helpful for validators that only perform a lookup for
   * existing data types related to an organism (such as germplasm).
   *
   * @param string $genus
   *   The genus name.
   *
   * @throws \Exception
   *   - If an instance of ChadoConnection is not accessible.
   */
  public function setGenus(string $genus) {
    if (!isset($this->chado_connection)) {
      throw new \Exception('Using setGenus() by the Organism Trait needs an instance of ChadoConnection (tripal_chado.database) injected via the create() and set to $this->chado_connection.');
    }
    // Query the genus in chado.
    $query = $this->chado_connection->select('1:organism', 'o')
      ->fields('o', ['organism_id'])
      ->condition('o.genus', $genus);
    $record = $query->execute()->fetchCol();
    // If we have 1+ organisms with the genus, return as an array.
    if (count($record) > 0) {
      $this->context['organism_ids'] = $record;
    }
    else {
      // Since this is a user-provided value, the error is going to be logged
      // instead of thrown as an exception and then checked by a validator so
      // that the error can be passed to the user in a friendly way.
      $this->logger->error("Unable to find any organisms for the genus '$genus' in chado.organism.");
    }

  }

  /**
   * Returns the organism(s).
   *
   * @return array
   *   A list of organisms that were set.
   *
   * @throws \Exception
   *   - If an organism ID was not set by setOrganismID() or setGenus().
   */
  public function getOrganismIDs() {
    if (array_key_exists('organism_ids', $this->context)) {
      return $this->context['organism_ids'];
    }
    else {
      throw new \Exception("Cannot retrieve an array of organism IDs as one has not been set by either the setOrganismID() or setGenus() method.");
    }
  }

}

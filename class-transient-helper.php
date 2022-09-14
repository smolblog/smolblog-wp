<?php
/**
 * Attach the Smolblog transient model to WordPress' transient funcitons.
 *
 * @package Smolblog\WP
 */

// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

namespace Smolblog\WP;

use Smolblog\Core\{Model, ModelHelper};

/**
 * Helper class to link WordPress and Smolblog transients.
 */
class Transient_Helper implements ModelHelper {
	/**
	 * Get data from the persistent store for the given model.
	 *
	 * @param Model|null $forModel Model to get data for.
	 * @param mixed      $withId   Primary key(s) to search for in the persistent store; default none.
	 * @return array|null Associative array of the model's data; null if data is not in store.
	 */
	public function getData( Model $forModel = null, mixed $withId = null ): ?array {
		$data  = array( 'key' => $withId );
		$value = get_transient( $withId );

		if ( $value !== false ) {
			$data['value'] = $value;
		}

		return $data;
	}

	/**
	 * Save the given data from the given model to the persistent store.
	 *
	 * It is recommended that the implementing class throw a ModelException if there is an unexpected error.
	 *
	 * @param Model|null $model    Model to save data for.
	 * @param array      $withData Data from the model to save.
	 * @return boolean True if save was successful.
	 */
	public function save( Model $model = null, array $withData = array() ): bool {
		return set_transient( $withData['key'], $withData['value'], $withData['expires'] );
	}
}

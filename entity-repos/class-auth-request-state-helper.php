<?php
/**
 * Attach the Smolblog AuthRequestState model to WordPress' transient funcitons.
 *
 * @package Smolblog\WP
 */

// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

namespace Smolblog\WP;

use Smolblog\Core\Connector\{AuthRequestState, AuthRequestStateReader, AuthRequestStateWriter};

/**
 * Helper class to link WordPress and Smolblog transients.
 */
class Auth_Request_State_Helper implements AuthRequestStateReader, AuthRequestStateWriter {
	/**
	 * Check the repository for the object identified by $id.
	 *
	 * @param string|integer $id Unique identifier for the object.
	 * @return boolean True if the repository contains an object with the given $id.
	 */
	public function has( string|int $id ): bool {
		return get_transient( $id ) !== false;
	}

	/**
	 * Get the indicated AuthRequestState from the repository. Should return null if not found.
	 *
	 * @param string|integer $id Unique identifier for the object.
	 * @return Entity Object identified by $id; null if it does not exist.
	 */
	public function get( string|int $id ): AuthRequestState {
		$state = get_transient( $id );
		if ( ! is_array( $state ) ) {
			return null;
		}

		return new AuthRequestState( ...$state );
	}

	/**
	 * Save the given AuthRequestState
	 *
	 * @param AuthRequestState $state State to save.
	 * @return void
	 */
	public function save( AuthRequestState $state ): void {
		set_transient( $state->id, get_object_vars( $state ), 60 * 15 );
	}
}

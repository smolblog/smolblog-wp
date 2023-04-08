<?php
/**
 * Helper class for Connection Credentials
 *
 * @package Smolblog\WP
 */

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

namespace Smolblog\WP;

use Smolblog\Core\Connector\{Connection, ConnectionReader, ConnectionWriter};
use Smolblog\Framework\Messages\Listener;

/**
 * Helper class for Connection Credentials
 */
class Connection_Credential_Helper implements Listener {
	use Table_Backed;

	const TABLE = 'smolblog_connections';

	const FIELDS = <<<EOF
		`id` bigint(20) NOT NULL AUTO_INCREMENT,
		`guid` char(16) NOT NULL UNIQUE,
		`user_id` bigint(20) NOT NULL,
		`provider` varchar(50) NOT NULL,
		`provider_key` varchar(50) NOT NULL,
		`display_name` varchar(100) NOT NULL,
		`details` text NOT NULL, 
		PRIMARY KEY  (id)
	EOF;

	/**
	 * Check the repository for the object identified by $id.
	 *
	 * @param string|integer $id Unique identifier for the object.
	 * @return boolean True if the repository contains an object with the given $id.
	 */
	public function has( string|int $id ): bool {
		global $wpdb;
		$tablename = $wpdb->prefix . 'smolblog_connection';

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM $tablename WHERE `guid` = %s", //phpcs:ignore
				$id,
			)
		);

		return isset( $id );
	}

	/**
	 * Get the indicated Connection from the repository. Should return null if not found.
	 *
	 * @param string|integer $id Unique identifier for the object.
	 * @return Entity Object identified by $id; null if it does not exist.
	 */
	public function get( string|int $id ): Connection {
		global $wpdb;
		$tablename = $wpdb->prefix . 'smolblog_connection';

		$db_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $tablename WHERE `guid` = %s", //phpcs:ignore
				$id
			),
			ARRAY_A
		);
		if ( ! isset( $db_data ) ) {
			return null;
		}

		return $this->connection_from_row( $db_data );
	}

	/**
	 * Get the Connections that belong to the given User.
	 *
	 * @param string|integer $userId ID of the User to search on.
	 * @return array
	 */
	public function getConnectionsForUser( string|int $userId ): array {
		global $wpdb;
		$tablename = $wpdb->prefix . 'smolblog_connection';

		$db_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $tablename WHERE `user_id` = %d", //phpcs:ignore
				$userId
			),
			ARRAY_A
		);
		if ( ! isset( $db_data ) ) {
			return [];
		}

		return array_map( array( $this, 'connection_from_row' ), $db_data );
	}

	/**
	 * Save the given Connection
	 *
	 * @throws \Exception Throws database errors.
	 * @param Connection $connection State to save.
	 * @return void
	 */
	public function save( Connection $connection ): void {
		global $wpdb;
		$tablename = $wpdb->prefix . 'smolblog_connection';

		$data    = array(
			'guid'         => $connection->id,
			'user_id'      => $connection->userId,
			'provider'     => $connection->provider,
			'provider_key' => $connection->providerKey,
			'display_name' => $connection->displayName,
			'details'      => wp_json_encode( $connection->details ),
		);
		$formats = array( '%s', '%d', '%s', '%s', '%s', '%s' );

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM $tablename WHERE `guid` = %s", //phpcs:ignore
				$connection->id,
			)
		);
		if ( isset( $id ) ) {
			$data['id'] = $id;
			$formats[]  = '%d';
		}

		if ( false === $wpdb->replace( $tablename, $data, $formats ) ) {
			throw new \Exception( "Database error: $wpdb->last_error" );
		}
	}

	/**
	 * Create a Connection object from a database row.
	 *
	 * @param array $db_data Associative array of data.
	 * @return Connection
	 */
	private function connection_from_row( array $db_data ): Connection {
		return new Connection(
			userId: $db_data['user_id'],
			provider: $db_data['provider'],
			providerKey: $db_data['provider_key'],
			displayName: $db_data['display_name'],
			details: json_decode( $db_data['details'], true ),
		);
	}
}

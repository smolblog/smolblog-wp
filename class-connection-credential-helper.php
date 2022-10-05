<?php
/**
 * Helper class for Connection Credentials
 *
 * @package Smolblog\WP
 */

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

namespace Smolblog\WP;

use Smolblog\Core\Connector\{Connection, ConnectionReader, ConnectionWriter};

/**
 * Helper class for Connection Credentials
 */
class Connection_Credential_Helper implements ConnectionReader, ConnectionWriter {
	/**
	 * Check the schema version and update if needed.
	 *
	 * @return void
	 */
	public function update_schema(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'smolblog_connection_credential';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			`id` varchar(101) NOT NULL,
			`user_id` bigint(20) NOT NULL,
			`provider` varchar(50) NOT NULL,
			`provider_key` varchar(50) NOT NULL,
			`display_name` varchar(100) NOT NULL,
			`details` varchar(255) NOT NULL, 
			PRIMARY KEY  (id)
		) $charset_collate;";

		if ( md5( $sql ) === get_option( 'smolblog_schemaver_connection_credential', '' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'smolblog_schemaver_connection_credential', md5( $sql ) );
	}

	/**
	 * Check the repository for the object identified by $id.
	 *
	 * @param string|integer $id Unique identifier for the object.
	 * @return boolean True if the repository contains an object with the given $id.
	 */
	public function has( string|int $id ): bool {
		global $wpdb;
		$tablename = $wpdb->prefix . 'smolblog_connection_credential';

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM $tablename WHERE `id` = %s", //phpcs:ignore
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
		$tablename = $wpdb->prefix . 'smolblog_connection_credential';

		$db_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $tablename WHERE `id` = %s", //phpcs:ignore
				$id
			),
			ARRAY_A
		);
		if ( ! isset( $db_data ) ) {
			return null;
		}

		return new Connection(
			userId: $db_data['user_id'],
			provider: $db_data['provider'],
			providerKey: $db_data['provider_key'],
			displayName: $db_data['display_name'],
			details: json_decode( $db_data['details'], true ),
		);
	}

	/**
	 * Save the given Connection
	 *
	 * @param Connection $connection State to save.
	 * @return void
	 */
	public function save( Connection $connection ): void {
		global $wpdb;
		$tablename = $wpdb->prefix . 'smolblog_connection_credential';

		$wpdb->replace(
			$tablename,
			array(
				'id'           => $connection->id,
				'user_id'      => $connection->userId,
				'provider'     => $connection->provider,
				'provider_key' => $connection->providerKey,
				'display_name' => $connection->displayName,
				'details'      => wp_json_encode( $connection->details ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}
}

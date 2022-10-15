<?php
/**
 * Helper class for Connection Credentials
 *
 * @package Smolblog\WP
 */

// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

namespace Smolblog\WP;

use Smolblog\Core\Connector\{Connection, ChannelReader, ChannelWriter};

/**
 * Helper class for Connection Credentials
 */
class Channel_Helper implements ChannelReader, ChannelWriter {
	/**
	 * Check the schema version and update if needed.
	 *
	 * @return void
	 */
	public static function update_schema(): void {
		global $wpdb;

		$table_name      = $wpdb->prefix . 'smolblog_channels';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
			`id` bigint(20) NOT NULL AUTO_INCREMENT,
			`guid` varchar(152) NOT NULL UNIQUE,
			`connection_id` varchar(101) NOT NULL,
			`channel_key` varchar(50) NOT NULL,
			`display_name` varchar(100) NOT NULL,
			`details` text NOT NULL, 
			PRIMARY KEY  (id)
		) $charset_collate;";

		if ( md5( $sql ) === get_option( 'smolblog_schemaver_channels', '' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'smolblog_schemaver_channels', md5( $sql ) );
	}

	/**
	 * Check the repository for the object identified by $id.
	 *
	 * @param string|integer $id Unique identifier for the object.
	 * @return boolean True if the repository contains an object with the given $id.
	 */
	public function has( string|int $id ): bool {
		global $wpdb;
		$tablename = $wpdb->prefix . 'smolblog_channels';

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM $tablename WHERE `guid` = %s", //phpcs:ignore
				$id,
			)
		);

		return isset( $id );
	}

	/**
	 * Get the indicated Channel from the repository. Should return null if not found.
	 *
	 * @param string|integer $id Unique identifier for the object.
	 * @return Entity Object identified by $id; null if it does not exist.
	 */
	public function get( string|int $id ): Channel {
		global $wpdb;
		$tablename = $wpdb->prefix . 'smolblog_channels';

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

		return $this->channel_from_row( $db_data );
	}

	/**
	 * Get all channels for the given Connection.
	 *
	 * @param string $connectionId Connection to search by.
	 * @return Channel[] Array of Channels attached to this Connection.
	 */
	public function getChannelsForConnection( string $connectionId ): array {
		global $wpdb;
		$tablename = $wpdb->prefix . 'smolblog_channels';

		$db_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $tablename WHERE `connection_id` = %s", //phpcs:ignore
				$connectionId
			),
			ARRAY_A
		);
		if ( ! isset( $db_data ) ) {
			return null;
		}

		return array_map( array( $this, 'channel_from_row' ), $db_data );
	}

	/**
	 * Get all channels for all the given Connections.
	 *
	 * @param string[] $connectionIds Connections to search by.
	 * @return array[] Associative array of arrays of Channels keyed to their Connection.
	 */
	public function getChannelsForConnections( array $connectionIds ): array {
		global $wpdb;
		$tablename = $wpdb->prefix . 'smolblog_channels';

		$db_ready_ids = array_map( fn( $id) => $wpdb->prepare( '%s', $id ) );

		$db_data = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM $tablename WHERE `connection_id` in %s ORDER BY `connection_id`", //phpcs:ignore
				'(' . implode( ',', $db_ready_ids ) . ')'
			),
			ARRAY_A
		);
		if ( ! isset( $db_data ) ) {
			return null;
		}

		$channels = array();
		foreach ( $db_data as $row ) {
			$channels[ $row['connection_id'] ] ??= array();
			$channels[ $row['connection_id'] ][] = $this->channel_from_row( $row );
		}

		return $channels;
	}

	/**
	 * Save the given Channel
	 *
	 * @throws \Exception Throws database errors.
	 * @param Channel $channel State to save.
	 * @return void
	 */
	public function save( Channel $channel ): void {
		global $wpdb;
		$tablename = $wpdb->prefix . 'smolblog_channels';

		$data    = array(
			'guid'          => $channel->id,
			'connection_id' => $channel->connectionId,
			'channel_key'   => $channel->channelKey,
			'display_name'  => $channel->displayName,
			'details'       => wp_json_encode( $channel->details ),
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s' );

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM $tablename WHERE `guid` = %s", //phpcs:ignore
				$channel->id,
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
	 * Delete the indicated Channel from the repository.
	 *
	 * @param string $id ID of Channel to delete.
	 * @return void
	 */
	public function delete( string $id ): void {
		global $wpdb;
		$tablename = $wpdb->prefix . 'smolblog_channels';

		$wpdb->delete( $tablename, array( 'guid' => $id ), array( '%s' ) );
	}

	/**
	 * Create a Channel object from a database row.
	 *
	 * @param array $db_data Associative array from the database.
	 * @return Channel
	 */
	private function channel_from_row( array $db_data ): Channel {
		return new Channel(
			connectionId: $db_data['connection_id'],
			channelKey: $db_data['channel_key'],
			displayName: $db_data['display_name'],
			details: json_decode( $db_data['details'], true ),
		);
	}
}

<?php
/**
 * Helper class for Connection Credentials
 *
 * @package Smolblog\WP
 */

// phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

namespace Smolblog\WP;

use Smolblog\Core\{Model, ModelHelper};
use Smolblog\Core\Definitions\ModelField;

/**
 * Helper class for Connection Credentials
 */
class Connection_Credential_Helper implements ModelHelper {
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
			`id` bigint(20) NOT NULL AUTO_INCREMENT,
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
	 * Get data from the persistent store for the given model.
	 *
	 * @param Model|null $forModel Model to get data for.
	 * @param mixed      $withId   Primary key(s) to search for in the persistent store; default none.
	 * @return array|null Associative array of the model's data; null if data is not in store.
	 */
	public function getData( Model $forModel = null, mixed $withId = null ): ?array {
		global $wpdb;
		$tablename = $wpdb->prefix . 'smolblog_connection_credential';

		$db_data = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM $tablename WHERE `provider` = %s AND `provider_key` = %s", //phpcs:ignore
				$withId['provider'],
				$withId['providerKey']
			),
			ARRAY_A
		);

		if ( ! $db_data ) {
			return $withId;
		}

		return array(
			'userId'      => $db_data['user_id'],
			'displayName' => $db_data['display_name'],
			'details'     => $db_data['details'],
		);
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
		global $wpdb;
		$tablename = $wpdb->prefix . 'smolblog_connection_credential';

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT `id` FROM $tablename WHERE `provider` = %s AND `provider_key` = %s", //phpcs:ignore
				$withData['provider'],
				$withData['providerKey']
			)
		);

		$result = false;
		if ( $id ) {
			$result = $wpdb->update(
				$tablename,
			)
		} else {

		}
	}

	private function formats_from_model( Model $model ): array {
		$formats = array();
		foreach ( get_class( $model )::FIELDS as $field ) {
			switch ( $field ) {
				case ModelField::int:
					$formats[] = '%d';
					break;
				case ModelField::float:
					$formats[] = '%f';
					break;
				case ModelField::string:
					$formats[] = '%s';
					break;
			}
		}
		return $formats;
	}
}

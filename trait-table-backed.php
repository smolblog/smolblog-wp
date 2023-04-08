<?php

namespace Smolblog\WP;

trait Table_Backed {
	protected static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Check the schema version and update if needed.
	 *
	 * @return void
	 */
	public static function update_schema(): void {
		global $wpdb;

		$table_name      = self::table_name();
		$table_fields    = self::FIELDS;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name ($table_fields) $charset_collate;";

		if ( md5( $sql ) === get_option( self::TABLE . '_schemaver', '' ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::TABLE . '_schemaver', md5( $sql ) );
	}
}
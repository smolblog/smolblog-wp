<?php

namespace Smolblog\WP\Projections;

use Smolblog\Core\Connector\Entities\Connection;
use Smolblog\Core\Connector\Events\{ConnectionEstablished, ConnectionRefreshed, ConnectionDeleted};
use Smolblog\Core\Connector\Queries\{ConnectionById, ConnectionBelongsToUser, ConnectionsForUser};
use Smolblog\Framework\Messages\Projection;
use Smolblog\Framework\Objects\Identifier;
use Smolblog\WP\TableBacked;

class ConnectionProjection extends TableBacked implements Projection {
	const TABLE = 'connections';
	const FIELDS = <<<EOF
		`id` bigint(20) NOT NULL AUTO_INCREMENT,
		`connection_id` char(16) NOT NULL UNIQUE,
		`user_id` char(16) NOT NULL,
		`provider` varchar(50) NOT NULL,
		`provider_key` varchar(50) NOT NULL,
		`display_name` varchar(50) NOT NULL,
		`details` text,
		PRIMARY KEY (id)
	EOF;

	public function onConnectionEstablished(ConnectionEstablished $event) {
		$dbid = $this->dbid_for_uuid($event->connectionId);

		$values = array_filter( [
			'id' => $dbid,
			'connection_id' => $event->connectionId->toByteString(),
			'user_id' => $event->userId->toByteString(),
			'provider' => $event->provider,
			'provider_key' => $event->providerKey,
			'display_name' => $event->displayName,
			'details' => wp_json_encode( $event->details ),
		] );
		$formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%s'];

		if ( ! isset( $dbid ) ) {
			unset($values['id']);
			unset($formats[0]);
		}

		$this->db->replace(
			static::table_name(),
			$values,
			$formats,
		);
	}

	public function onConnectionRefreshed(ConnectionRefreshed $event) {
		$this->db->update(
			static::table_name(),
			[ 'details' => wp_json_encode( $event->details ) ],
			[ 'connection_id' => $event->connectionId->toByteString() ]
		);
	}

	public function onConnectionDeleted(ConnectionDeleted $event) {
		$this->db->delete(
			static::table_name(),
			[ 'connection_id' => $event->connectionId->toByteString() ]
		);
	}

	public function onConnectionById(ConnectionById $query) {
		$table      = static::table_name();
		$db_results = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM $table WHERE `connection_id` = %s",
				$query->connectionId->toByteString()
			),
			ARRAY_A
		);

		$query->results = $this->connection_from_row($db_results);
	}

	public function onConnectionsForUser(ConnectionsForUser $query) {
		$table      = static::table_name();
		$db_results = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM $table WHERE `user_id` = %s",
				$query->userId->toByteString()
			),
			ARRAY_A
		);

		$query->results = array_map( fn( $con ) => $this->connection_from_row( $con ), $db_results );
	}

	public function onConnectionBelongsToUser(ConnectionBelongsToUser $query) {
		$table = static::table_name();

		$query->results = $this->db->get_var(
			$this->db->prepare(
				"SELECT `id` FROM $table WHERE `connection_id` = %s AND `user_id` = %s",
				$query->connectionId->toByteString(),
				$query->userId->toByteString()
			)
		);
	}

	private function dbid_for_uuid(Identifier $uuid): ?int {
		$table = static::table_name();

		return $this->db->get_var(
			$this->db->prepare("SELECT `id` FROM $table WHERE `connection_id` = %s", $uuid->toByteString())
		);
	}

	private function connection_from_row(array $data): Connection {
		return new Connection(
			userId: $data['user_id'],
			provider: $data['provider'],
			providerKey: $data['provider_key'],
			displayName: $data['display_name'],
			details: json_decode( $data['details'], true ),
		);
	}
}

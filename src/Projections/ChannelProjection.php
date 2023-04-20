<?php

namespace	Smolblog\WP\Projections;

use Smolblog\Core\Connector\Entities\Channel;
use Smolblog\Core\Connector\Events\{ChannelDeleted, ChannelSaved};
use Smolblog\Core\Connector\Queries\{ChannelById, ChannelsForConnection};
use Smolblog\WP\TableBacked;
use Smolblog\Framework\Messages\Projection;
use Smolblog\Framework\Objects\Identifier;

class ChannelProjection extends TableBacked implements Projection {
	const TABLE = 'channels';
	const FIELDS = <<<EOF
		id bigint(20) NOT NULL AUTO_INCREMENT,
		channel_id varchar(40) NOT NULL UNIQUE,
		connection_id varchar(40) NOT NULL,
		channel_key varchar(50) NOT NULL,
		display_name varchar(100) NOT NULL,
		details text NOT NULL,
		PRIMARY KEY  (id)
	EOF;

	public function onChannelSaved(ChannelSaved $event) {
		$channel_id = Channel::buildId(
			connectionId: $event->connectionId,
			channelKey: $event->channelKey,
		);

		$dbid = $this->dbid_for_uuid($channel_id);

		$values = array_filter( [
			'id' => $dbid,
			'channel_id' => $channel_id->toString(),
			'connection_id' => $event->connectionId->toString(),
			'channel_key' => $event->channelKey,
			'display_name' => $event->displayName,
			'details' => wp_json_encode( $event->details ),
		] );
		$formats = ['%d', '%s', '%s', '%s', '%s', '%s'];

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

	public function onChannelDeleted(ChannelDeleted $event) {
		$this->db->delete(
			static::table_name(),
			Channel::buildId(
				connectionId: $event->connectionId,
				channelKey: $event->channelKey,
			)
		);
	}

	public function onChannelById(ChannelById $query) {
		$table      = static::table_name();
		$db_results = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM $table WHERE `channel_id` = %s",
				$query->channelId->toString()
			),
			ARRAY_A
		);

		$query->results = $this->channel_from_row($db_results);
	}

	public function onChannelsForConnection(ChannelsForConnection $query) {
		$table      = static::table_name();
		$db_results = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM $table WHERE `connection_id` = %s",
				$query->connectionId->toString()
			),
			ARRAY_A
		);

		$query->results = array_map( fn( $con ) => $this->channel_from_row( $con ), $db_results );
	}

	private function dbid_for_uuid(Identifier $uuid): ?int {
		$table = static::table_name();

		return $this->db->get_var(
			$this->db->prepare("SELECT `id` FROM $table WHERE `channel_id` = %s", $uuid->toString())
		);
	}

	public function channel_from_row(array $data): Channel {
		return new Channel(
			connectionId: Identifier::fromString($data['connection_id']),
			channelKey: $data['channel_key'],
			displayName: $data['display_name'],
			details: json_decode( $data['details'], true ),
		);
	}
}
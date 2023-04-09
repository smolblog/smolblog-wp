<?php

namespace Smolblog\WP\EventStreams;

use DateTimeInterface;
use Smolblog\Core\Connector\Events\ConnectorEvent;
use Smolblog\Framework\Messages\Attributes\EventStoreLayerListener;
use Smolblog\Framework\Messages\Listener;
use Smolblog\WP\TableBacked;

class ConnectorEventStream extends TableBacked implements Listener {
	const TABLE  = 'connector_events';
	const FIELDS = <<<EOF
		`id` bigint(20) NOT NULL AUTO_INCREMENT,
		`event_id` char(16) NOT NULL UNIQUE,
		`event_time` varchar(30) NOT NULL,
		`connection_id` char(16) NOT NULL,
		`user_id` char(16) NOT NULL,
		`event_type` varchar(50) NOT NULL,
		`payload` text,
		PRIMARY KEY (id)
	EOF;

	/**
	 * Save the given ConnectorEvent to the stream.
	 *
	 * @param ConnectorEvent $event Event to save.
	 * @return void
	 */
	#[EventStoreLayerListener()]
	public function onConnectorEvent(ConnectorEvent $event) {
		$this->db->insert(
			$this->table_name(),
			[
				'event_id' => $event->id->toByteString(),
				'event_time' => $event->timestamp->format(DateTimeInterface::RFC3339_EXTENDED),
				'connection_id' => $event->connectionId->toByteString(),
				'user_id' => $event->userId->toByteString(),
				'event_type' => get_class($event),
				'payload' => wp_json_encode($event->getPayload()),
			]
		);
	}
}
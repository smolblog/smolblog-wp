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
		event_id varchar(40) NOT NULL UNIQUE,
		event_time varchar(30) NOT NULL,
		connection_id varchar(40) NOT NULL,
		user_id varchar(40) NOT NULL,
		event_type varchar(255) NOT NULL,
		payload text,
	EOF;

	/**
	 * Save the given ConnectorEvent to the stream.
	 *
	 * @param ConnectorEvent $event Event to save.
	 * @return void
	 */
	#[EventStoreLayerListener()]
	public function onConnectorEvent(ConnectorEvent $event) {
		$data = [
			'event_id' => $event->id->toString(),
			'event_time' => $event->timestamp->format(DateTimeInterface::RFC3339_EXTENDED),
			'connection_id' => $event->connectionId->toString(),
			'user_id' => $event->userId->toString(),
			'event_type' => get_class($event),
			'payload' => wp_json_encode($event->getPayload()),
		];

		$result = $this->db->insert(
			$this->table_name(),
			$data
		);

		if (!$result) {
			$this->db->print_error();
			print_r($data);
		}
	}
}
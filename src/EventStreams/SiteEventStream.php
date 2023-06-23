<?php

namespace Smolblog\WP\EventStreams;

use DateTimeInterface;
use Smolblog\Core\Site\SiteEvent;
use Smolblog\Framework\Messages\Attributes\EventStoreLayerListener;
use Smolblog\Framework\Messages\Listener;
use Smolblog\WP\TableBacked;

class SiteEventStream extends TableBacked implements Listener {
	const TABLE  = 'site_events';
	const FIELDS = <<<EOF
		event_id varchar(40) NOT NULL UNIQUE,
		event_time varchar(30) NOT NULL,
		site_id varchar(40) NOT NULL,
		user_id varchar(40) NOT NULL,
		event_type varchar(50) NOT NULL,
		payload text,
	EOF;

	/**
	 * Save the given SiteEvent to the stream.
	 *
	 * @param SiteEvent $event Event to save.
	 * @return void
	 */
	#[EventStoreLayerListener()]
	public function onContentEvent(SiteEvent $event) {
		$this->db->insert(
			$this->table_name(),
			[
				'event_id' => $event->id->toString(),
				'event_time' => $event->timestamp->format(DateTimeInterface::RFC3339_EXTENDED),
				'site_id' => $event->siteId->toString(),
				'user_id' => $event->userId->toString(),
				'event_type' => get_class($event),
				'payload' => wp_json_encode($event->getPayload()),
			]
		);
	}
}

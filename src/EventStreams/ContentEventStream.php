<?php

namespace Smolblog\WP\EventStreams;

use DateTimeInterface;
use Smolblog\Core\Content\Events\ContentEvent;
use Smolblog\Framework\Messages\Attributes\EventStoreLayerListener;
use Smolblog\Framework\Messages\Listener;
use Smolblog\WP\TableBacked;

class ContentEventStream extends TableBacked implements Listener {
	const TABLE  = 'content_events';
	const FIELDS = <<<EOF
		event_id varchar(40) NOT NULL UNIQUE,
		event_time varchar(30) NOT NULL,
		content_id varchar(40) NOT NULL,
		site_id varchar(40) NOT NULL,
		user_id varchar(40) NOT NULL,
		event_type varchar(50) NOT NULL,
		payload text,
	EOF;

	/**
	 * Save the given ContentEvent to the stream.
	 *
	 * @param ContentEvent $event Event to save.
	 * @return void
	 */
	#[EventStoreLayerListener()]
	public function onContentEvent(ContentEvent $event) {
		$this->db->insert(
			$this->table_name(),
			[
				'event_id' => $event->id->toString(),
				'event_time' => $event->timestamp->format(DateTimeInterface::RFC3339_EXTENDED),
				'content_id' => $event->contentId->toString(),
				'site_id' => $event->siteId->toString(),
				'user_id' => $event->userId->toString(),
				'event_type' => get_class($event),
				'payload' => wp_json_encode($event->getPayload()),
			]
		);
	}
}

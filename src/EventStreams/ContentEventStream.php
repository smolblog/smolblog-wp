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
		`id` bigint(20) NOT NULL AUTO_INCREMENT,
		`event_id` char(16) NOT NULL UNIQUE,
		`event_time` varchar(30) NOT NULL,
		`content_id` char(16) NOT NULL,
		`site_id` char(16) NOT NULL,
		`user_id` char(16) NOT NULL,
		`event_type` varchar(50) NOT NULL,
		`payload` text,
		PRIMARY KEY (id)
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
				'event_id' => $event->id->toByteString(),
				'event_time' => $event->timestamp->format(DateTimeInterface::RFC3339_EXTENDED),
				'content_id' => $event->contentId->toByteString(),
				'site_id' => $event->siteId->toByteString(),
				'user_id' => $event->userId->toByteString(),
				'event_type' => get_class($event),
				'payload' => wp_json_encode($event->getPayload()),
			]
		);
	}
}

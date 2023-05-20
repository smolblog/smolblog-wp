<?php

namespace Smolblog\WP\Projections;

use Smolblog\Core\Content\Types\Status\Status;
use Smolblog\Core\Content\Types\Status\StatusBodyEdited;
use Smolblog\Core\Content\Types\Status\StatusBuilder;
use Smolblog\Core\Content\Types\Status\StatusCreated;
use Smolblog\Core\Content\Types\Status\StatusDeleted;
use Smolblog\Framework\Messages\Attributes\ContentBuildLayerListener;
use Smolblog\Framework\Messages\Projection;
use Smolblog\WP\TableBacked;

class StatusProjection extends TableBacked implements Projection {
	const TABLE = 'statuses';
	const FIELDS = <<<EOF
		content_id varchar(40) NOT NULL UNIQUE,
		markdown text NOT NULL,
		html text,
	EOF;

	public function onStatusCreated(StatusCreated $event) {
		$data = array_filter([
			'content_id' => $event->contentId->toString(),
			'markdown' => $event->text,
			'html' => $event->getNewBody(),
		]);

		$result = $this->db->insert(
			$this->table_name(),
			$data
		);

		if (!$result) {
			$this->db->print_error();
			print_r($data);
		}
	}

	public function onStatusBodyEdited(StatusBodyEdited $event) {
		$this->db->update(
			static::table_name(),
			[
				'markdown' => $event->text,
				'html' => $event->getNewBody(),
			],
			[ 'content_id' => $event->contentId->toString() ]
		);
	}

	public function onStatusDeleted(StatusDeleted $event) {
		$this->db->delete(
			static::table_name(),
			[ 'content_id' => $event->contentId->toString() ]
		);
	}

	#[ContentBuildLayerListener]
	public function buildStatus(StatusBuilder $message) {
		$table      = static::table_name();
		$db_results = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM $table WHERE `content_id` = %s",
				$message->getContentId()->toString()
			),
			ARRAY_A
		);

		$message->setContentType(new Status(
			text: $db_results['markdown'],
			rendered: $db_results['html'] ?? null,
		));
	}
}
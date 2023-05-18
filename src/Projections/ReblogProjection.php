<?php

namespace Smolblog\WP\Projections;

use Smolblog\Core\Content\Types\Reblog\ExternalContentInfo;
use Smolblog\Core\Content\Types\Reblog\Reblog;
use Smolblog\Core\Content\Types\Reblog\ReblogCommentChanged;
use Smolblog\Core\Content\Types\Reblog\ReblogCreated;
use Smolblog\Core\Content\Types\Reblog\ReblogDeleted;
use Smolblog\Core\Content\Types\Reblog\ReblogInfoChanged;
use Smolblog\Core\Content\Types\Reblog\ReblogBuilder;
use Smolblog\Framework\Messages\Attributes\ContentBuildLayerListener;
use Smolblog\Framework\Messages\Projection;
use Smolblog\WP\TableBacked;

class ReblogProjection extends TableBacked implements Projection {
	const TABLE = 'reblogs';
	const FIELDS = <<<EOF
		content_id varchar(40) NOT NULL UNIQUE,
		url varchar(255) NOT NULL,
		comment text,
		comment_html text,
		url_info text,
	EOF;

	public function onReblogCreated(ReblogCreated $event) {
		$data = array_filter([
			'content_id' => $event->contentId->toString(),
			'url' => $event->url,
			'comment' => $event->comment,
			'comment_html' => $event->getCommentHtml(),
			'url_info' => isset($event->info) ? json_encode($event->info) : null,
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

	public function onReblogInfoChanged(ReblogInfoChanged $event) {
		$this->db->update(
			static::table_name(),
			[
				'url' => $event->url,
				'url_info' => json_encode($event->info),
			],
			[ 'content_id' => $event->contentId->toString() ]
		);
	}

	public function onReblogCommentChanged(ReblogCommentChanged $event) {
		$this->db->update(
			static::table_name(),
			[
				'comment' => $event->comment,
				'comment_html' => $event->getCommentHtml(),
			],
			[ 'content_id' => $event->contentId->toString() ]
		);
	}

	public function onReblogDeleted(ReblogDeleted $event) {
		$this->db->delete(
			static::table_name(),
			[ 'content_id' => $event->contentId->toString() ]
		);
	}

	#[ContentBuildLayerListener]
	public function buildReblog(ReblogBuilder $message) {
		$table      = static::table_name();
		$db_results = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM $table WHERE `content_id` = %s",
				$message->getContentId()->toString()
			),
			ARRAY_A
		);

		$message->setContentType(new Reblog(
			url: $db_results['url'],
			comment: $db_results['comment'] ?? null,
			info: isset($db_results['url_info']) ? ExternalContentInfo::jsonDeserialize($db_results['url_info']) : null,
			commentHtml: $db_results['comment_html'] ?? null,
		));
	}
}
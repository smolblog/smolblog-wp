<?php

namespace Smolblog\WP\Projections;

use wpdb;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Smolblog\Core\Content\ContentVisibility;
use Smolblog\Core\Content\ContentBuilder;
use Smolblog\Core\Content\Events\{
	ContentBaseAttributeEdited,
	ContentBodyEdited,
	ContentCreated,
	ContentDeleted,
    ContentExtensionEdited,
    PermalinkAssigned,
	PublicContentChanged,
	PublicContentAdded,
	PublicContentRemoved,
};
use Smolblog\Core\Content\GenericContent;
use Smolblog\Core\Content\Queries\ContentList;
use Smolblog\Core\Content\Queries\ContentVisibleToUser;
use Smolblog\Core\Content\Queries\GenericContentById;
use Smolblog\Core\Content\Queries\UserCanEditContent;
use Smolblog\Core\Site\UserHasPermissionForSite;
use Smolblog\Framework\Messages\Attributes\ContentBuildLayerListener;
use Smolblog\Framework\Messages\Attributes\ExecutionLayerListener;
use Smolblog\Framework\Messages\MessageBus;
use Smolblog\Framework\Messages\Projection;
use Smolblog\Framework\Objects\Identifier;
use Smolblog\WP\Helpers\SiteHelper;
use Smolblog\WP\TableBacked;

class StandardContentProjection extends TableBacked implements Projection {
	const TABLE = 'standard_content';
	const FIELDS = <<<EOF
		content_id varchar(40) NOT NULL UNIQUE,
		type_class varchar(100) NOT NULL,
		title varchar(255),
		body text,
		site_uuid varchar(40) NOT NULL,
		author_id varchar(40) NOT NULL,
		permalink varchar(255),
		publish_timestamp varchar(50),
		visibility varchar(10) NOT NULL,
		extensions text NOT NULL,
	EOF;

	#[ExecutionLayerListener]
	public function onContentCreated(ContentCreated $event) {
		$data = array_filter([
			'content_id' => $event->contentId->toString(),
			'type_class' => $event->getContentType(),
			'title' => $event->getNewTitle(),
			'body' => $event->getNewBody(),
			'site_uuid' => $event->siteId->toString(),
			'author_id' => $event->authorId->toString(),
			'publish_timestamp' => $event->publishTimestamp?->format(DateTimeInterface::RFC3339_EXTENDED),
			'visibility' => ContentVisibility::Draft->value,
			'extensions' => '[]'
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

	#[ExecutionLayerListener]
	public function onContentBodyEdited(ContentBodyEdited $event) {
		$this->db->update(
			static::table_name(),
			[ 'title' => $event->getNewTitle(), 'body' => $event->getNewBody() ],
			[ 'content_id' => $event->contentId->toString() ]
		);
	}

	#[ExecutionLayerListener]
	public function onContentDeleted(ContentDeleted $event) {
		$this->db->delete(
			static::table_name(),
			[ 'content_id' => $event->contentId->toString() ]
		);
	}

	#[ExecutionLayerListener]
	public function onContentBaseAttributeEdited(ContentBaseAttributeEdited $event) {
		$this->db->update(
			static::table_name(),
			[
				'author_id' => $event->authorId?->toString() ?? null,
				'publish_timestamp' => $event->publishTimestamp?->format(DateTimeInterface::RFC3339_EXTENDED) ?? null,
			],
			[ 'content_id' => $event->contentId->toString() ]
		);
	}

	#[ExecutionLayerListener]
	public function onContentExtensionEdited(ContentExtensionEdited $event) {
		$table = static::table_name();

		$current_extension_json = $this->db->get_var(
			$this->db->prepare("SELECT `extensions` FROM $table WHERE `content_id` = %s", $event->contentId->toString())
		);
		if (!isset($current_extension_json)) {
			return;
		}
		$current = json_decode($current_extension_json, true);
		$ext = $event->getNewExtension();
		$current[get_class($ext)] = $ext->toArray();

		$this->db->update(
			static::table_name(),
			[ 'extensions' => wp_json_encode( $current ) ],
			[ 'content_id' => $event->contentId->toString() ]
		);
	}

	#[ExecutionLayerListener]
	public function onPermalinkAssigned(PermalinkAssigned $event) {
		$this->db->update(
			static::table_name(),
			[ 'permalink' => $event->permalink ],
			[ 'content_id' => $event->contentId->toString() ]
		);
	}

	#[ContentBuildLayerListener(earlier: 5)]
	public function onPublicContentAdded(PublicContentAdded $event) {
		$table = static::table_name();

		$pub_date = $this->db->get_var(
			$this->db->prepare(
				"SELECT `publish_timestamp` FROM $table WHERE `content_id` = %s",
				$event->contentId->toString()
			)
		);

		$update = [ 'visibility' => ContentVisibility::Published->value ];
		if (!isset($pub_date)) {
			$update['publish_timestamp'] = $event->timestamp->format(DateTimeInterface::RFC3339_EXTENDED);
		}

		$this->db->update(
			static::table_name(),
			$update,
			[ 'content_id' => $event->contentId->toString() ]
		);
	}

	#[ContentBuildLayerListener(earlier: 5)]
	public function onPublicContentRemoved(PublicContentRemoved $event) {
		$this->db->update(
			static::table_name(),
			[ 'visibility' => ContentVisibility::Draft->value ],
			[ 'content_id' => $event->contentId->toString() ]
		);
	}

	#[ContentBuildLayerListener(later:5)]
	public function onContentBuilder(ContentBuilder $message) {
		$table      = static::table_name();
		$db_results = $this->db->get_row(
			$this->db->prepare(
				"SELECT * FROM $table WHERE `content_id` = %s",
				$message->getContentId()->toString()
			),
			ARRAY_A
		);

		$message->setContentProperty(
			id: $message->getContentId(),
			siteId: isset($db_results['site_uuid']) ? Identifier::fromString($db_results['site_uuid']) : null,
			authorId: isset($db_results['author_id']) ? Identifier::fromString($db_results['author_id']) : null,
			permalink: $db_results['permalink'] ?? null,
			publishTimestamp: isset($db_results['publish_timestamp']) ?
				new DateTimeImmutable($db_results['publish_timestamp']): null,
			visibility: ContentVisibility::tryFrom($db_results['visibility'] ?? ''),
		);

		$extensions = json_decode($db_results['extensions'] ?? '[]', true);
		foreach($extensions as $ext_class => $ext_array) {
			$ext = $ext_class::fromArray($ext_array);
			$message->addContentExtension($ext);
		}
	}

	#[ContentBuildLayerListener]
	public function onGenericContentById(GenericContentById $query) {
		$table      = static::table_name();
		$db_results = $this->db->get_row(
			$this->db->prepare(
				"SELECT title, body FROM $table WHERE `content_id` = %s",
				$query->contentId->toString()
			),
			ARRAY_A
		);

		$query->setContentType(new GenericContent(title: $db_results['title'], body: $db_results['body']));
	}
}


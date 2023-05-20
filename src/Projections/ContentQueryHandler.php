<?php

namespace Smolblog\WP\Projections;

use DateTimeImmutable;
use Smolblog\Core\Content\Content;
use Smolblog\Core\Content\ContentVisibility;
use Smolblog\Core\Content\GenericContent;
use wpdb;
use Smolblog\Core\Content\Queries\ContentList;
use Smolblog\Core\Content\Queries\ContentVisibleToUser;
use Smolblog\Core\Content\Queries\UserCanEditContent;
use Smolblog\Core\Site\UserHasPermissionForSite;
use Smolblog\Framework\Messages\Listener;
use Smolblog\Framework\Messages\MessageBus;
use Smolblog\Framework\Objects\Identifier;

class ContentQueryHandler implements Listener {
	public function __construct(private wpdb $db, private MessageBus $bus) {
	}

	public function onContentVisibleToUser(ContentVisibleToUser $query) {
		$query = $this->checkContentPerm(contentId: $query->contentId, siteId: $query->siteId, userId: $query->userId);
	}

	public function onUserCanEditContent(UserCanEditContent $query) {
		$query = $this->checkContentPerm(contentId: $query->contentId, siteId: $query->siteId, userId: $query->userId);
	}

	public function onContentList(ContentList $query) {
		$isAdmin = isset($query->userId) && $this->bus->fetch(
			new UserHasPermissionForSite(siteId: $query->siteId, userId: $query->userId, mustBeAdmin: true)
		);
		$table = StandardContentProjection::table_name();

		$query_text = "SELECT * FROM $table WHERE site_uuid = %s";
		$query_vars = [ $query->siteId->toString() ];

		if (!$isAdmin) {
			$query_text .= ' AND (author_id = %s OR visibility = %s)';
			$query_vars[] = $query->userId?->toString() ?? 'x';
			$query_vars[] = ContentVisibility::Published->value;
		}

		if (isset($query->types)) {
			$query_text .= ' AND type_class IN (' . implode(',', array_fill(0, count($query->types), '%s')) . ')';
			$query_vars = array_merge($query_vars, $query->types);
		}
		if (isset($query->visibility)) {
			$query_text .= ' AND type_class IN (' . implode(',', array_fill(0, count($query->visibility), '%s')) . ')';
			$query_vars = array_merge($query_vars, array_map(fn($vis) => $vis->value, $query->visibility));
		}

		$query_text .= ' ORDER BY publish_timestamp DESC LIMIT %d,%d';
		$query_vars[] = ($query->page - 1) * $query->pageSize;
		$query_vars[] = $query->pageSize;

		$db_results = $this->db->get_results(
			$this->db->prepare( $query_text, $query_vars ),
			ARRAY_A
		);

		$query->results = array_map(
			fn($row) => new Content(
				id: Identifier::fromString($row['content_id']),
				type: new GenericContent(title: $row['title'], body: $row['body']),
				siteId: Identifier::fromString($row['site_uuid']),
				authorId: Identifier::fromString($row['author_id']),
				permalink: $row['permalink'] ?? null,
				publishTimestamp: isset($row['publish_timestamp']) ? new DateTimeImmutable($row['publish_timestamp']) : null,
				visibility: ContentVisibility::tryFrom($row['visibility']),
			), $db_results
		);
	}

	private function checkContentPerm(Identifier $contentId, Identifier $siteId, Identifier $userId): bool {
		$table      = StandardContentProjection::table_name();
		$db_results = $this->db->get_var(
			$this->db->prepare(
				"SELECT id FROM $table WHERE `content_id` = %s AND `site_uuid` = %s AND `author_id` = %s",
				$contentId->toString(),
				$siteId->toString(),
				$userId->toString(),
			)
		);
		return !empty($db_results) || $this->bus->fetch(
			new UserHasPermissionForSite(siteId: $siteId, userId: $userId, mustBeAdmin: true)
		);
	}
}
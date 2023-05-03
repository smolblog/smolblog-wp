<?php

namespace Smolblog\WP\Projections;

use DateTimeInterface;
use Smolblog\Core\Content\ContentVisibility;
use Smolblog\Core\Content\Events\{
	ContentBaseAttributeEdited,
	ContentBodyEdited,
	ContentCreated,
	ContentDeleted,
	ContentVisibilityChanged
};
use Smolblog\Core\Content\Types\Reblog\Reblog;
use Smolblog\Core\Content\Types\Status\Status;
use Smolblog\Framework\Messages\Attributes\ExecutionLayerListener;
use Smolblog\Framework\Messages\Projection;
use Smolblog\Framework\Objects\Identifier;
use Smolblog\WP\Helpers\SiteHelper;

class PostProjection implements Projection {
	#[ExecutionLayerListener(later: 5)]
	public function onContentCreated(ContentCreated $event) {
		$wp_site_id = SiteHelper::UuidToInt( $event->siteId );
		switch_to_blog( $wp_site_id );
		
		$wp_author_id = SiteHelper::UuidToInt( $event->authorId );
		$wp_post_id = wp_insert_post( [
			'post_author' => $wp_author_id,
			'post_date' => $event->publishTimestamp?->format( DateTimeInterface::ATOM ),
			'post_content' => $event->getNewBody(),
			'post_title' => $event->getNewTitle(),
			'post_status' => $this->visibilityToStatus( $event->visibility ),
			'post_type' => $this->typeToPostType( $event->getContentType() ),
			'meta_input' => [ 'smolblog_uuid' => $event->contentId->toString() ],
		], true );

		if (is_wp_error( $wp_post_id )) {
			wp_die($wp_post_id);
		}

		restore_current_blog();
	}

	#[ExecutionLayerListener(later: 5)]
	public function onContentBodyEdited(ContentBodyEdited $event) {
		$wp_site_id = SiteHelper::UuidToInt( $event->siteId );
		switch_to_blog( $wp_site_id );

		$args = [ 'ID' => self::UuidToInt($event->contentId) ];
		if ($event->getNewBody()) {
			$args['post_content'] = $event->getNewBody();
		}
		if ($event->getNewTitle()) {
			$args['post_title'] = $event->getNewTitle();
		}

		$results = wp_update_post( $args );
		if (is_wp_error( $results )) {
			wp_die($results);
		}

		restore_current_blog();
	}

	#[ExecutionLayerListener(later: 5)]
	public function onContentVisibilityChanged(ContentVisibilityChanged $event){}

	#[ExecutionLayerListener(later: 5)]
	public function onContentDeleted(ContentDeleted $event){}

	#[ExecutionLayerListener(later: 5)]
	public function onContentBaseAttributeEdited(ContentBaseAttributeEdited $event){}

	#[ExecutionLayerListener(later: 5)]
	public function onContentExtensionEdited(ContentBaseAttributeEdited $event){}

	/**
	 * Convert an Identifier to a WordPress Post ID. *Must be called within the blog the post belongs to!*
	 *
	 * @param Identifier $uuid ID for the post.
	 * @return int|null
	 */
	public static function UuidToInt(Identifier $uuid) {
		$results = get_posts( [
			'numberposts' => 1,
			'fields' => 'ids',
			'meta_key' => 'smolblog_uuid',
			'meta_value' => $uuid->toString(),
		] );

		return $results[0] ?? null;
	}

	private function visibilityToStatus(ContentVisibility $vis) {
		switch ($vis) {
			case ContentVisibility::Protected:
				return 'private';
			case ContentVisibility::Published:
				return 'publish';
			default:
				return 'draft';
		}
	}

	private function typeToPostType(string $contentType) {
		switch ($contentType) {
			case Status::class:
				return 'status';
			case Reblog::class:
				return 'reblog';
			default:
				return 'post';
		}
	}
}
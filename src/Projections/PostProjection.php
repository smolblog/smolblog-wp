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
use Smolblog\WP\Helpers\SiteHelper;

class PostProjection implements Projection {
	#[ExecutionLayerListener(later: 5)]
	public function onContentCreated(ContentCreated $event) {
		$wp_site_id = SiteHelper::UuidToInt( $event->siteId );
		$wp_author_id = SiteHelper::UuidToInt( $event->authorId );
		switch_to_blog( $wp_site_id );

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
	}

	#[ExecutionLayerListener(later: 5)]
	public function onContentBodyEdited(ContentBodyEdited $event){}

	#[ExecutionLayerListener(later: 5)]
	public function onContentVisibilityChanged(ContentVisibilityChanged $event){}

	#[ExecutionLayerListener(later: 5)]
	public function onContentDeleted(ContentDeleted $event){}

	#[ExecutionLayerListener(later: 5)]
	public function onContentBaseAttributeEdited(ContentBaseAttributeEdited $event){}

	#[ExecutionLayerListener(later: 5)]
	public function onContentExtensionEdited(ContentBaseAttributeEdited $event){}

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
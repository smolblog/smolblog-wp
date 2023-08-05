<?php

namespace Smolblog\WP\Helpers;

use Exception;
use Psr\Http\Message\UploadedFileInterface;
use Smolblog\Core\Content\Media\{MediaHandler, MediaFile};
use Smolblog\Framework\Objects\DateIdentifier;
use Smolblog\Framework\Objects\Identifier;

class MediaLibraryHelper implements MediaHandler {
	public static function getHandle(): string {
		return 'wordpress';
	}

	public function handleUploadedFile(
		UploadedFileInterface $file,
		?Identifier $userId = null,
		?Identifier $siteId = null
	): MediaFile {
		// These files need to be included as dependencies when on the front end.
		require_once( ABSPATH . 'wp-admin/includes/image.php' );
		require_once( ABSPATH . 'wp-admin/includes/file.php' );
		require_once( ABSPATH . 'wp-admin/includes/media.php' );

		$wp_id = media_handle_upload( $this->getFilesKeyForGivenPsrFile($file), 0 );

		if (is_wp_error($wp_id)) {
			throw new Exception('Error saving to library: ' . $wp_id->get_error_message());
		}

		return new MediaFile(
			id: new DateIdentifier(),
			handler: 'wordpress',
			mimeType: get_post( $wp_id )->post_mime_type,
			details: [ 'wp_id' => $wp_id ],
		);
	}

	public function getThumbnailUrlFor(MediaFile $file): string {
		$info = wp_get_attachment_image_src( $file->details['wp_id'], 'thumbnail', true );

		return $info[0];
	}

	public function getUrlFor(MediaFile $file, ?int $maxWidth = null, ?int $maxHeight = null, mixed ...$props): string {
		$size = isset($maxWidth) || isset($maxHeight) ? [$maxWidth ?? 9999, $maxHeight ?? 9999] : 'full';
		$info = wp_get_attachment_image_src( $file->details['wp_id'], $size, true );

		return $info[0];
	}

	public function getHtmlFor(MediaFile $file, ?int $maxWidth = null, ?int $maxHeight = null, mixed ...$props): string {
		return '';
	}

	private function getFilesKeyForGivenPsrFile(UploadedFileInterface $given): string|int|false {
		foreach ($_FILES as $key => $file) {
			if ($file['name'] === $given->getClientFilename() && $file['size'] === $given->getSize()) {
				return $key;
			}
			return false;
		}
	}
}
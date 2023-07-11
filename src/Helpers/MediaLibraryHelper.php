<?php

namespace Smolblog\WP\Helpers;

use Exception;
use Psr\Http\Message\UploadedFileInterface;
use Smolblog\Core\Content\Media\{Media, MediaHandler, UploadedMediaInfo};
use Smolblog\Framework\Objects\Identifier;

class MediaLibraryHelper implements MediaHandler {
	public static function getHandle(): string {
		return 'wordpress';
	}

	public function handleUploadedFile(
		UploadedFileInterface $file,
		Identifier $userId,
		Identifier $siteId
	): UploadedMediaInfo {
		throw new Exception(print_r(['$_FILES' => $_FILES, '$file' => $file], true));
	}

	public function getUrlFor(Media $media, ?int $maxWidth = null, ?int $maxHeight = null, array ...$props): string {
		return '';
	}

	public function getHtmlFor(Media $media, ?int $maxWidth = null, ?int $maxHeight = null, array ...$props): string {
		return '';
	}
}
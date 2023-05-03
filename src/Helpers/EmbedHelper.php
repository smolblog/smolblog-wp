<?php

namespace Smolblog\WP\Helpers;

use WP_oEmbed;
use Smolblog\Core\Content\Types\Reblog\ExternalContentInfo;
use Smolblog\Core\Content\Types\Reblog\ExternalContentService;

class EmbedHelper implements ExternalContentService {
	private WP_oEmbed $internal;

	public function __construct()
	{
		$this->internal = new WP_oEmbed();
	}

	public function getExternalContentInfo(string $url): ExternalContentInfo
	{
		$data = $this->internal->get_data($url);
		return new ExternalContentInfo(
			title: $data->title ?? ($data->author_name ? "$data->author_name on " : '') . $data->provider_name,
			embed: $this->internal->data2html($data, $url),
		);
	}
}
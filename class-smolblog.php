<?php

namespace Smolblog\WP;

use Smolblog\Api;
use Smolblog\Core;
use Smolblog\Framework\Infrastructure\AppKit;
use Smolblog\Framework\Infrastructure\ServiceRegistry;
use Smolblog\Framework\Objects\DomainModel;

class Smolblog
{
	use AppKit;

	public readonly ServiceRegistry $container;

	public function __construct(array $plugin_models = [])
	{
		$this->container = $this->buildDefaultContainer([
			Core\Model::class,
			Api\Model::class,
			$this->wordpress_model(),
			...$plugin_models,
		]);
	}

	private function wordpress_model(): string
	{
		$model = new class extends DomainModel
		{
			const SERVICES = [
				Core\Connector\Services\AuthRequestStateRepo::class => null,
				Api\ApiEnvironment::class => fn () => new class implements Api\ApiEnvironment
				{
					public function getApiUrl(string $endpoint = '/'): string
					{
						return \get_rest_url(null, '/smolblog/v2' . $endpoint);
					}
				},
			];
		};

		return get_class($model);
	}
}

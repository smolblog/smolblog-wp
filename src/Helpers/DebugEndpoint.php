<?php

namespace Smolblog\WP\Helpers;

use Smolblog\Api\Endpoint;
use Smolblog\Api\EndpointConfig;
use Smolblog\Api\GenericResponse;
use Smolblog\Framework\Objects\Identifier;

class DebugEndpoint implements Endpoint {
	public static function getConfiguration(): EndpointConfig
	{
		return new EndpointConfig(
			route: '/debug',
			requiredScopes: [],
		);
	}

	public function __construct(
		private array $depMap
	) {
	}

	public function run(?Identifier $userId, ?array $params, ?object $body): GenericResponse
	{
		return new GenericResponse(map: $this->depMap);
	}
}
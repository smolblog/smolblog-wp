<?php

namespace Smolblog\WP;

use Smolblog\Api\ApiEnvironment;
use Smolblog\Api\Endpoint;
use Smolblog\Api\EndpointConfig;
use Smolblog\Api\GenericResponse;
use Smolblog\Api\Model;
use Smolblog\Api\ParameterType;
use Smolblog\Framework\Objects\Identifier;
use Smolblog\Framework\Objects\Value;

class Doc_Endpoint implements Endpoint {
	public static function getConfiguration(): EndpointConfig {
		return new EndpointConfig(
			route: 'docs',
			responseShape: ParameterType::object(thing: ParameterType::boolean()),
			requiredScopes: [],
		);
	}

	public function __construct(private ApiEnvironment $env) {
	}

	public function run(?Identifier $userId = null, ?array $params = null, ?object $body = null): Value {
		ob_start();
		Model::generateOpenApiSpec();
		$specJson = ob_get_clean();
		$specArray = json_decode($specJson, associative: true);

		$specArray['servers'] = [
			['url' => $this->env->getApiUrl()],
		];

		return new GenericResponse(...$specArray);
	}
}
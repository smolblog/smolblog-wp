<?php
/**
 * Class to handle registering the Smolblog endpoints with WordPress.
 *
 * @package Smolblog\WP
 */

namespace Smolblog\WP;

use Exception;
use Psr\Container\ContainerInterface;
use Smolblog\Api\AuthScope;
use Smolblog\Api\Endpoint;
use Smolblog\Api\EndpointConfig;
use Smolblog\Api\Exceptions\ErrorResponse;
use Smolblog\Api\SuccessResponse;
use Smolblog\Api\RedirectResponse;
use Smolblog\Framework\Exceptions\MessageNotAuthorizedException;
use Smolblog\Framework\Infrastructure\Registry;
use Smolblog\Framework\Objects\Identifier;
use Smolblog\WP\Helpers\UserHelper;
use Throwable;
use \WP_REST_Request;
use \WP_REST_Response;

/**
 * Class to handle registering the Smolblog endpoints with WordPress.
 */
class EndpointRegistrar implements Registry
{
	public static function getInterfaceToRegister(): string
	{
		return Endpoint::class;
	}

	public function __construct(
		private ContainerInterface $container,
		private array $configuration
	){
	}

	public function init(): void {
		foreach ($this->configuration as $endpoint) {
			$this->processConfig($endpoint::getConfiguration(), $endpoint);
		}
	}

	/**
	 * Handle the configuration of the endpoint.
	 *
	 * @param EndpointConfig $config Configuration from the class.
	 * @param string $endpoint Endpoint class.
	 * @return void
	 */
	protected function processConfig(EndpointConfig $config, string $endpoint): void
	{
		$route =
			preg_replace_callback(
			'/\{([a-zA-Z]+)\}/',
				function( $param ) use ( $config ) {
				if (!isset($config->pathVariables[$param[1]])) {
						return $param[0];
					}

				$format = $this->process_pattern($config->pathVariables[$param[1]]->pattern) ?? '[a-zA-Z0-9-]+';

				return '(?P<' . $param[1] . '>' . $format . ')';
				},
				$config->route
			);

		if ($route === '/') {
			$route = '/hello';
		}

		register_rest_route(
			'smolblog/v2',
			$route,
			array(
				'methods'             => [$config->verb->value],
				'callback'            => $this->get_callback($config, $endpoint),
				'permission_callback' => $this->get_permission_callback($config->requiredScopes),
			),
		);
	}

	/**
	 * Remove terminators from the parameter pattern.
	 * 
	 * OpenAPI calls for ^ and $ to ensure that the regular expression matches the entire string. WordPress doesn't need
	 * this or even like it all that much.
	 *
	 * @param string|null $pattern Pattern value from the parameter.
	 * @return string|null Processed pattern value.
	 */
	private function process_pattern(?string $pattern): ?string {
		if (!isset($pattern)) {
			return null;
		}

		return ltrim(rtrim($pattern, '$'), '^');
	}

	/**
	 * Find out if the endpoint is public.
	 * 
	 * OAuth scopes do not match cleanly to the permission checks WordPress would handle here. Fine-grained security is
	 * handled at the Model level (by authorized queries).
	 *
	 * @param AuthScope[] $security Security level for this endpoint.
	 * @return callable Callback that checks for the analogous WordPress role.
	 */
	private function get_permission_callback(array $scopes): callable
	{
		if (empty($scopes)) {
			return '__return_true';
		}

		return fn() => current_user_can('read');
	}

	/**
	 * Create a callback function for this endpoint.
	 *
	 * @param string $route Route for the endpoint (to retrieve from library).
	 * @return callable Callback function that translates WordPress constructs and Smolblog constructs.
	 */
	private function get_callback( EndpointConfig $config, string $endpoint ): callable {
		return function( WP_REST_Request $incoming ) use ( $config, $endpoint ) {
			$outgoing = new WP_REST_Response();
			$wp_user_id = get_current_user_id();
			$smolblog_user_id = UserHelper::IntToUuid($wp_user_id);

			try {
				$body = null;
				if (class_exists( $config->bodyClass )) {
					$body = $config->bodyClass::fromArray($incoming->get_json_params());
				}

				$params = [];
				$debug = [];
				foreach ($incoming->get_params() as $key => $val) {
					$type = $config->pathVariables[$key] ?? $config->queryVariables[$key] ?? null;
					if (!isset($type)) {
						continue;
					}
					$debug[$key] = [
						'type' => $type->type,
						'format' => $type->format,
						'value' => $val,
					];

					if ($type->type == 'string' && $type->format == 'uuid') {
						$params[$key] = Identifier::fromString($val);
						continue;
					}
					if ($type->type == 'boolean') {
						$params[$key] = $val ? true : false;
						continue;
					}

					$params[$key] = $val;
				}

				$response = $this->container->get($endpoint)->run(
					userId: $smolblog_user_id,
					params: $params,
					body: $body,
				);

				switch (get_class($response)) {
					case SuccessResponse::class:
						$outgoing->set_status( 204 );
						$outgoing->set_data( null );
						break;
					
					case RedirectResponse::class:
						$outgoing->set_status( $response->permanent ? 301 : 302 );
						$outgoing->header( 'Location', $response->url );
						$outgoing->set_data( null );
						break;

					default:
						$outgoing->set_data($response);
						break;
				}
			} catch (ErrorResponse $ex) {
				$outgoing->set_data($ex);
				$outgoing->set_status($ex->getHttpCode());
			} catch (MessageNotAuthorizedException $ex) {
				$outgoing->set_data(['code' => 403, 'error' => $ex->getMessage(), 'trace' => $ex->getTraceAsString(), 'debug' => [
					'user' => [
						'wpid' => $wp_user_id,
						'uuid' => $smolblog_user_id->toString(),
					],
					'params' => $incoming->get_params(),
					'body' => $incoming->get_json_params(),
				]]);
				$outgoing->set_status( 403 );
			} catch (Throwable $ex) {
				$outgoing->set_data(['code' => 500, 'error' => $ex->getMessage(), 'debug' => [
					'user' => [
						'wpid' => $wp_user_id,
						'uuid' => $smolblog_user_id->toString(),
					],
					'params' => $incoming->get_params(),
					'body' => $incoming->get_json_params(),
				], 'file' => $ex->getFile(), 'line' => $ex->getLine(), 'trace' => $ex->getTraceAsString(),
			'previous' => $ex->getPrevious()?->getMessage()]);
				$outgoing->set_status( 500 );
			}

			return $outgoing;
		};
	}
}

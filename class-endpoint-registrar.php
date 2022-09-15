<?php
/**
 * Class to handle registering the Smolblog endpoints with WordPress.
 *
 * @package Smolblog\WP
 */

namespace Smolblog\WP;

use \WP_REST_Request;
use \WP_REST_Response;
use Smolblog\Core\{Endpoint, EndpointRegistrar, EndpointRequest};
use Smolblog\Core\Definitions\{HttpVerb, SecurityLevel};

/**
 * Class to handle registering the Smolblog endpoints with WordPress.
 */
class Endpoint_Registrar implements EndpointRegistrar {

	/**
	 * Hold the endpoints until we are ready to register them.
	 *
	 * @var array
	 */
	private $registry = array();

	/**
	 * Register the given endpoint with the REST API
	 *
	 * @param Endpoint $endpoint Endpoint to register.
	 *
	 * @return void
	 */
	public function registerEndpoint( Endpoint $endpoint ): void {
		$this->registry[] = $endpoint;
	}

	/**
	 * Register the endpoints we've been given
	 *
	 * @return void
	 */
	public function init_endpoints() : void {
		foreach ( $this->registry as $endpoint ) {
			$config = $endpoint->getConfig();
			$route  =
				( $config->route[0] === '/' ? '' : '/' ) .
				preg_replace_callback(
					'/\[([a-z]+)\]/',
					function( $param ) use ( $config ) {
						if ( ! isset( $config->params[ $param[1] ] ) ) {
							return $param[0];
						}

						return '(?P<' . $param[1] . '>' . $config->params[ $param[1] ] . ')';
					},
					$config->route
				);

			register_rest_route(
				'smolblog/v2',
				$route,
				array(
					'methods'             => $this->get_methods( $config->verbs ),
					'callback'            => $this->get_callback( array( $endpoint, 'run' ) ),
					'permission_callback' => $this->get_permission_callback( $config->security ),
				),
			);
		}
	}

	/**
	 * Translate array of Smolblog\Core\HttpVerb into strings.
	 *
	 * @param array $verbs HTTP methods for this endpoint.
	 * @return array HTTP methods for this endpoint as strings.
	 */
	private function get_methods( array $verbs ): array {
		return array_map(
			function ( $verb ) {
				return $verb->value;
			},
			$verbs
		);
	}

	/**
	 * Turn the SecurityLevel into a WordPress-friendly callback.
	 *
	 * @param SecurityLevel $security Security level for this endpoint.
	 * @return callable Callback that checks for the analogous WordPress role.
	 */
	private function get_permission_callback( SecurityLevel $security ): callable {
		switch ( $security ) {
			case SecurityLevel::Anonymous:
				return '__return_true';
			case SecurityLevel::Registered:
				return function () {
					return current_user_can( 'read' );
				};
			case SecurityLevel::Contributor:
				return function () {
					return current_user_can( 'publish_posts' );
				};
			case SecurityLevel::Moderator:
				return function () {
					return current_user_can( 'edit_others_posts' );
				};
			case SecurityLevel::Admin:
				return function () {
					return current_user_can( 'manage_options' );
				};
			case SecurityLevel::Root:
				return function () {
					return current_user_can( 'manage_sites' );
				};
		}
		return '__return_false';
	}

	/**
	 * Create a callback function for this endpoint.
	 *
	 * @param callable $run_endpoint Smolblog callback function for the endpoint.
	 * @return callable Callback function that translates WordPress constructs and Smolblog constructs.
	 */
	private function get_callback( callable $run_endpoint ): callable {
		return function( WP_REST_Request $incoming ) use ( $run_endpoint ) {
			$request = new EndpointRequest(
				userId: get_current_user_id(),
				siteId: get_current_blog_id(),
				params: $incoming->get_params(),
				json: $incoming->get_json_params() ?? false
			);

			$response = call_user_func( $run_endpoint, $request );

			$outgoing = new WP_REST_Response( $response->body );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$outgoing->set_status( $response->statusCode );

			return $outgoing;
		};
	}
}

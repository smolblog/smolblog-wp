<?php
/**
 * Class to handle registering the Smolblog endpoints with WordPress.
 *
 * @package Smolblog\WP
 */

namespace Smolblog\WP;

use \WP_REST_Request;
use \WP_REST_Response;
use Smolblog\Core\Endpoint\{Endpoint, EndpointRegistrar, EndpointRequest, HttpVerb, SecurityLevel};

/**
 * Class to handle registering the Smolblog endpoints with WordPress.
 */
class Endpoint_Registrar extends EndpointRegistrar {
	/**
	 * Handle the configuration of the endpoint. Should return the string key used to retrieve the class.
	 *
	 * @param mixed $config Configuration array from the class.
	 * @return string Key to retrieve the class with.
	 */
	protected function processConfig( mixed $config ): string {
		$route =
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
				'callback'            => $this->get_callback( $config->route ),
				'permission_callback' => $this->get_permission_callback( $config->security ),
			),
		);

		return $config->route;
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
	 * @param string $route Route for the endpoint (to retrieve from library).
	 * @return callable Callback function that translates WordPress constructs and Smolblog constructs.
	 */
	private function get_callback( string $route ): callable {
		return function( WP_REST_Request $incoming ) use ( $route ) {
			$request = new EndpointRequest(
				userId: get_current_user_id(),
				siteId: get_current_blog_id(),
				params: $incoming->get_params(),
				json: $incoming->get_json_params() ?? null,
			);

			$response = $this->get( key: $route )->run( request: $request );

			$outgoing = new WP_REST_Response( $response->body );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$outgoing->set_status( $response->statusCode );

			return $outgoing;
		};
	}
}

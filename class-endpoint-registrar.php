<?php

namespace Smolblog\WP;

use \WP_REST_Request;
use \WP_REST_Response;
use Smolblog\Core\{Endpoint, EndpointRequest};
use Smolblog\Core\Definitions\{HttpVerb, SecurityLevel};

class Endpoint_Registrar implements Smolblog\Core\EndpointRegistrar {

	private $registry = array();

	/**
	 * Register the given endpoint with the REST API
	 *
	 * @param Endpoint $endpoint Endpoint to register.
	 *
	 * @return void
	 */
	public function registerEndpoint( Endpoint $endpoint ): void {
		$registry[] = $endpoint;
	}

	public function init_endpoints() : void {
		foreach ( $registry as $endpoint ) {
			$config = $endpoint->getConfig();
			$route  = $config->route;

			register_rest_route(
				'smolblog/v1',
				$route,
				array(
					'methods'             => $this->get_methods( $config->verbs ),
					'callback'            => $this->get_callback( array( $endpoint, 'run' ) ),
					'permission_callback' => $this->get_permission_callback( $endpoint->security ),
				),
			);
		}
	}

	private function get_methods( array $verbs ): array {
		return array_map(
			function ( $verb ) {
				return $verb->value;
			},
			$verbs
		);
	}

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

	private function get_callback( callable $run_endpoint ): callable {
		return function( WP_REST_Request $incoming ) use ( $run_endpoint ) {
			$request = new EndpointRequest(
				userId: get_current_user_id(),
				siteId: get_current_blog_id(),
				params: $incoming->get_params(),
				json: $incoming->get_json_params()
			);

			$response = call_user_func( $run_endpoint, $request );

			$outgoing = new WP_REST_Response( $response->body );
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			$outgoing->set_status( $response->statusCode );

			return $outgoing;
		};
	}
}

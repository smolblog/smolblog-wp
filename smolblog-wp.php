<?php
/**
 * Smolblog-WP
 *
 * An interface between the core Smolblog library and WordPress.
 *
 * @package Smolblog\WP
 *
 * @wordpress-plugin
 * Plugin Name:       Smolblog WP
 * Plugin URI:        http://github.com/smolblog/smolblog-wp
 * Description:       WordPress + Smolblog
 * Version:           1.0.0
 * Author:            Smolblog
 * Author URI:        http://smolblog.org
 * License:           AGPL-3.0+
 * License URI:       https://www.gnu.org/licenses/agpl.html
 * Text Domain:       smolblog
 * Domain Path:       /languages
 */

namespace Smolblog\WP;

require_once 'vendor/autoload.php';
require_once 'class-endpoint-registrar.php';

use Smolblog\Core\{App, Environment};
use Smolblog\Core\Models\{ConnectionCredential, Transient};

add_action(
	'plugins_loaded',
	function() {
		// Load environment variables.
		$environment = new Environment( apiBase: get_rest_url( null, '/smolblog/v1' ) );

		// Create empty Endpoint Registrar and instantiate the App.
		$endpoint_registrar = new Endpoint_Registrar();
		$app                = new App(
			withEndpointRegistrar: $endpoint_registrar,
			withEnvironment: $environment
		);

		// Load the model helpers into the DI container.
		$app->container->add( Connection_Credential_Helper::class );
		$app->container->add( Transient_Helper::class );

		$app->container->extend( ConnectionCredentialFactory::class )->addArgument( Connection_Credential_Helper::class );
		$app->container->extend( TransientFactory::class )->addArgument( Transient_Helper::class );

		// Start Smolblog.
		$app->startup();

		// Register endpoints with WordPress.
		add_action( 'rest_api_init', array( $endpoint_registrar, 'init_endpoints' ) );
	}
);

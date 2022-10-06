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
require_once 'class-connection-credential-helper.php';
require_once 'class-auth-request-state-helper.php';

use Smolblog\Core\{App, Environment};
use Smolblog\Core\Connector\{AuthRequestStateReader, AuthRequestStateWriter, ConnectionReader, ConnectionWriter};
use Smolblog\Core\Endpoint\EndpointRegistrar;
use Smolblog\Twitter\TwitterConnector;
use Smolblog\OAuth2\Client\Provider\Twitter as TwitterOAuth;

// All of Smolblog Core is through REST endpoints, so load it on rest_api_init.
add_action(
	'rest_api_init',
	function() {
		// Load environment variables.
		$environment = new Environment(
			apiBase: get_rest_url( null, '/smolblog/v2' ),
			twitterAppId: 'mememe',
			twitterAppSecret: 'youyou',
		);

		// Create the app.
		$app = new App(
			withEnvironment: $environment,
			pluginClasses: array( Smolblog\Twitter\Plugin::class )
		);

		// Create the model helpers and load into the DI container.
		$state_helper = new Auth_Request_State_Helper();
		$cred_helper  = new Connection_Credential_Helper();
		$app->container->addShared( AuthRequestStateReader::class, fn() => $state_helper );
		$app->container->addShared( AuthRequestStateWriter::class, fn() => $state_helper );
		$app->container->addShared( ConnectionReader::class, fn() => $cred_helper );
		$app->container->addShared( ConnectionWriter::class, fn() => $cred_helper );

		// Create the Endpoint Registrar and load into the DI container.
		$endpoint_registrar = new Endpoint_Registrar();
		$app->container->addShared( EndpointRegistrar::class, fn() => $endpoint_registrar );

		// Start Smolblog.
		$app->startup();
	}
);

add_action(
	'wp_dashboard_setup',
	function() {
		wp_add_dashboard_widget(
			'smolblog-test',
			'Smolblog',
			function() {
				?>
			<p><a href="<?php echo esc_attr( get_rest_url( null, 'smolblog/v2/connect/init/twitter' ) ); ?>?_wpnonce=<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" class="button">Sign in with Twitter</a></p>

			<p><a href="<?php echo esc_attr( get_rest_url( null, 'smolblog/v2/admin/plugins' ) ); ?>?_wpnonce=<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>" class="button">See plugin info</a></p>
				<?php
			}
		);
	}
);

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

require_once 'env.php';
require_once 'vendor/autoload.php';
require_once 'class-endpoint-registrar.php';
require_once 'entity-repos/class-connection-credential-helper.php';
require_once 'entity-repos/class-auth-request-state-helper.php';

use Smolblog\Core\{App, Environment};
use Smolblog\Core\Connector\{AuthRequestStateReader, AuthRequestStateWriter, ConnectionReader, ConnectionWriter};
use Smolblog\Core\Endpoint\EndpointRegistrar;
use Smolblog\Twitter\TwitterConnector;
use Smolblog\OAuth2\Client\Provider\Twitter as TwitterOAuth;

Connection_Credential_Helper::update_schema();
Channel_Helper::update_schema();

// All of Smolblog Core is through REST endpoints, so load it on rest_api_init.
add_action(
	'rest_api_init',
	function() {
		// Load environment variables.
		$environment = new Environment(
			apiBase: get_rest_url( null, '/smolblog/v2' ),
			twitterAppId: SMOLBLOG_TWITTER_APPLICATION_KEY,
			twitterAppSecret: SMOLBLOG_TWITTER_APPLICATION_SECRET,
		);

		// Create the app.
		$app = new App(
			withEnvironment: $environment,
			pluginClasses: array( \Smolblog\Twitter\Plugin::class )
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
	'admin_enqueue_scripts',
	function() {
		// Register our script for enqueuing.
		$smolblog_asset_info =
		file_exists( plugin_dir_path( __FILE__ ) . 'build/index.asset.php' ) ?
		require plugin_dir_path( __FILE__ ) . 'build/index.asset.php' :
		array(
			'dependencies' => 'wp-element',
			'version'      => filemtime( 'js/index.js' ),
		);

		wp_register_script(
			'smolblog_admin',
			plugin_dir_url( __FILE__ ) . 'build/index.js',
			$smolblog_asset_info['dependencies'],
			$smolblog_asset_info['version'],
			true
		);
	},
	1,
	0
);

/**
 * Add page to admin
 */
function add_smolblog_page() {
	add_menu_page(
		'Smolblog Dashboard',
		'Smolblog',
		'read',
		'smolblog',
		__NAMESPACE__ . '\smolblog_admin',
		'dashicons-controls-repeat',
		3
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\add_smolblog_page' );

/**
 * Add the Smolblog admin javascript
 *
 * @param string $admin_page Current page.
 */
function enqueue_scripts( $admin_page ) {
	if ( $admin_page !== 'toplevel_page_smolblog' ) {
		return;
	}

	wp_enqueue_script( 'smolblog_admin' );
}
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts' );

/**
 * Output the Smolblog dashboard page
 */
function smolblog_admin() {
	?>
	<h1>Smolblog Admin</h1>
	<div id="smolblog-admin-app"></div>

	<?php
}

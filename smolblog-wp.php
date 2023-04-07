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

use Smolblog\Framework\Objects\Identifier;

require_once 'vendor/autoload.php';
require_once 'class-smolblog.php';
require_once 'class-endpoint-registrar.php';

require_once 'entity-repos/class-auth-request-state-helper.php';

$smolblog = new Smolblog();

add_action(
	'rest_api_init',
	function() {
		$app = new Smolblog();
		$endpoints = $app->container->get(Endpoint_Registrar::class);

		$endpoints->init();
	}
);

function get_current_user_uuid(): Identifier {
	$meta_value = get_user_meta( get_current_user_id(), 'smolblog_user_id', true );
	
	if (empty($meta_value)) {
		$new_id = Identifier::createRandom();
		update_user_meta( get_current_user_id(), 'smolblog_user_id', $new_id->toString() );

		return $new_id;
	}

	return Identifier::fromString( $meta_value );
}

function get_current_site_uuid(): Identifier {
	$meta_value = get_site_meta( get_current_site_id(), 'smolblog_site_id', true );
	
	if (empty($meta_value)) {
		$new_id = Identifier::createRandom();
		update_site_meta( get_current_site_id(), 'smolblog_site_id', $new_id->toString() );

		return $new_id;
	}

	return Identifier::fromString( $meta_value );
}
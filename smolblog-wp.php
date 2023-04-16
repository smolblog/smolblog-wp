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
use Smolblog\WP\Helpers\SiteHelper;
use Smolblog\WP\Helpers\UserHelper;

require_once __DIR__ . '/vendor/autoload.php';

$smolblog = new Smolblog();

add_action(
	'rest_api_init',
	function() {
		$app = new Smolblog();
		$endpoints = $app->container->get(EndpointRegistrar::class);

		$endpoints->init();
	}
);

function get_current_user_uuid(): Identifier {
	return UserHelper::IntToUuid(get_current_user_id());
}

function get_current_site_uuid(): Identifier {
	return SiteHelper::IntToUuid(get_current_site_id());
}
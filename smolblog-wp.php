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

use Smolblog\Framework\Messages\MessageBus;
use Smolblog\Framework\Objects\Identifier;
use Smolblog\WP\Helpers\SiteHelper;
use Smolblog\WP\Helpers\UserHelper;

require_once __DIR__ . '/vendor/autoload.php';

// Load Action Scheduler.
$smolblog_action_scheduler = __DIR__ . '/vendor/plugins/action-scheduler/action-scheduler.php';
if ( is_readable( $smolblog_action_scheduler ) ) {
	require_once $smolblog_action_scheduler;
}

foreach ([
	EventStreams\ConnectorEventStream::class,
	EventStreams\ContentEventStream::class,
	Projections\ChannelProjection::class,
	Projections\ChannelSiteLinkProjection::class,
	Projections\ConnectionProjection::class,
	Projections\ReblogProjection::class,
	Projections\StandardContentProjection::class,
	Projections\StatusProjection::class,
] as $proj) {
	$proj::update_schema();
}

$app = new Smolblog();

// Ensure the async hook is in place
add_action(
	'smolblog_async_dispatch',
	fn($msg) => $app->container->get(MessageBus::class)->dispatch($msg),
	10,
	1
);

add_action( 'rest_api_init', fn() => $app->container->get(EndpointRegistrar::class)->init() );

function get_current_user_uuid(): Identifier {
	return UserHelper::IntToUuid(get_current_user_id());
}

function get_current_site_uuid(): Identifier {
	return SiteHelper::IntToUuid(get_current_site_id());
}

$default_cpt_args = [
	'supports'              => array( 'editor', 'thumbnail', 'comments', 'custom-fields', 'page-attributes', 'post-formats' ),
	'taxonomies'            => array( 'category', 'post_tag' ),
	'public'                => true,
	'menu_position'         => 5,
	'has_archive'           => true,
	'show_in_rest'          => true,
];

add_action( 'init', fn() => register_post_type( 'status', [
	'label'                 => __( 'Status', 'smolblog' ),
	'description'           => __( 'A short text post', 'smolblog' ),
	...$default_cpt_args,
] ), 0 );

add_action( 'init', fn() => register_post_type( 'reblog', [
	'label'                 => __( 'Reblog', 'smolblog' ),
	'description'           => __( 'A webpage from off-site', 'smolblog' ),
	...$default_cpt_args,
] ), 0 );

add_action( 'pre_get_posts', function($query) {
	if ( ! is_admin() && $query->is_main_query() ) {
		$query->set( 'post_type', array( 'post', 'page', 'status', 'reblog' ) );
	}
});

add_filter( 'the_title_rss', function($title) {
	global $wp_query;
	$type = $wp_query->post->post_type;
	if (in_array($type, [ 'status', 'reblog' ])) {
		return null;
	}
	return $title;
});

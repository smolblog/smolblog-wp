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

foreach ([
	EventStreams\ConnectorEventStream::class,
	EventStreams\ContentEventStream::class,
	Projections\ChannelProjection::class,
	Projections\ChannelSiteLinkProjection::class,
	Projections\ConnectionProjection::class,
] as $proj) {
	$proj::update_schema();
}

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

// Register Custom Post Type
function generate_status_cpt() {

	$labels = array(
		'name'                  => _x( 'Statuses', 'Post Type General Name', 'smolblog' ),
		'singular_name'         => _x( 'Status', 'Post Type Singular Name', 'smolblog' ),
		'menu_name'             => __( 'Statuses', 'smolblog' ),
		'name_admin_bar'        => __( 'Status', 'smolblog' ),
		'archives'              => __( 'Status Archives', 'smolblog' ),
		'attributes'            => __( 'Status Attributes', 'smolblog' ),
		'parent_item_colon'     => __( 'Parent Status:', 'smolblog' ),
		'all_items'             => __( 'All Statuses', 'smolblog' ),
		'add_new_item'          => __( 'Add New Status', 'smolblog' ),
		'add_new'               => __( 'Add New', 'smolblog' ),
		'new_item'              => __( 'New Status', 'smolblog' ),
		'edit_item'             => __( 'Edit Status', 'smolblog' ),
		'update_item'           => __( 'Update Status', 'smolblog' ),
		'view_item'             => __( 'View Status', 'smolblog' ),
		'view_items'            => __( 'View Statuses', 'smolblog' ),
		'search_items'          => __( 'Search Status', 'smolblog' ),
		'not_found'             => __( 'Not found', 'smolblog' ),
		'not_found_in_trash'    => __( 'Not found in Trash', 'smolblog' ),
		'featured_image'        => __( 'Featured Image', 'smolblog' ),
		'set_featured_image'    => __( 'Set featured image', 'smolblog' ),
		'remove_featured_image' => __( 'Remove featured image', 'smolblog' ),
		'use_featured_image'    => __( 'Use as featured image', 'smolblog' ),
		'insert_into_item'      => __( 'Insert into item', 'smolblog' ),
		'uploaded_to_this_item' => __( 'Uploaded to this item', 'smolblog' ),
		'items_list'            => __( 'Items list', 'smolblog' ),
		'items_list_navigation' => __( 'Items list navigation', 'smolblog' ),
		'filter_items_list'     => __( 'Filter items list', 'smolblog' ),
	);
	$args = array(
		'label'                 => __( 'Status', 'smolblog' ),
		'description'           => __( 'A short text post', 'smolblog' ),
		'labels'                => $labels,
		'supports'              => array( 'title', 'editor', 'thumbnail', 'comments', 'custom-fields', 'page-attributes', 'post-formats' ),
		'taxonomies'            => array( 'category', 'post_tag' ),
		'hierarchical'          => false,
		'public'                => true,
		'show_ui'               => true,
		'show_in_menu'          => true,
		'menu_position'         => 5,
		'show_in_admin_bar'     => true,
		'show_in_nav_menus'     => true,
		'can_export'            => true,
		'has_archive'           => true,
		'exclude_from_search'   => false,
		'publicly_queryable'    => true,
		'capability_type'       => 'post',
		'show_in_rest'          => true,
	);
	register_post_type( 'status', $args );

}
add_action( 'init', __NAMESPACE__ . '\generate_status_cpt', 0 );
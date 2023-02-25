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

use Psr\Container\ContainerInterface;
use Smolblog\Framework\Infrastructure\DefaultMessageBus;
use Smolblog\Framework\Infrastructure\QueryMemoizationService;
use Smolblog\Framework\Infrastructure\SecurityCheckService;
use Smolblog\Framework\Infrastructure\ServiceRegistry;
use Smolblog\Framework\Messages\MessageBus;
use Smolblog\Markdown\SmolblogMarkdown;
use stdClass;

require_once 'vendor/autoload.php';
require_once 'class-smolblog.php';

// All of Smolblog Core is through REST endpoints, so load it on rest_api_init.
add_action(
	'rest_api_init',
	function() {
		$app = new Smolblog();
	}
);

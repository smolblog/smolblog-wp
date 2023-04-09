<?php

namespace Smolblog\WP;

use Psr\Container\ContainerInterface;
use Smolblog\Api;
use Smolblog\Core;
use Smolblog\Framework\Infrastructure\AppKit;
use Smolblog\Framework\Infrastructure\ServiceRegistry;
use Smolblog\Framework\Messages\MessageBus;
use Smolblog\Framework\Objects\DomainModel;

require_once __DIR__ . '/projections/class-channel-projection.php';
require_once __DIR__ . '/projections/class-connection-projection.php';

require_once __DIR__ . '/event-streams/class-connector-event-stream.php';
require_once __DIR__ . '/event-streams/class-content-event-stream.php';

class Smolblog {
	use AppKit;

	public readonly ServiceRegistry $container;

	public function __construct( array $plugin_models = [] ) {
		$this->container = $this->buildDefaultContainer( [
			Core\Model::class,
			Api\Model::class,
			$this->wordpress_model(),
			...$plugin_models,
		] );
	}

	private function wordpress_model(): string {
		$model = new class extends DomainModel {
			public static function getDependencyMap(): array {
				global $wpdb;

				return [
					Api\ApiEnvironment::class => fn() => new class implements Api\ApiEnvironment {
						public function getApiUrl( string $endpoint = '/' ): string {
							return get_rest_url( null, '/smolblog/v2' . $endpoint );
						}
					},
					wpdb::class => fn() => $wpdb,

					Core\Connector\Services\AuthRequestStateRepo::class => Auth_Request_State_Helper::class,

					EventStreams\Connector_Event_Stream::class => ['db' => wpdb::class],
					EventStreams\Content_Event_Stream::class => ['db' => wpdb::class],

					Projections\Channel_Projection::class => ['db' => wpdb::class],
					Projections\Channel_Site_Link_Projection::class => [
						'db' => wpdb::class,
						'channel_proj' => Projections\Channel_Projection::class,
						'bus' => MessageBus::class,
					],
					Projections\Connection_Projection::class => ['db' => wpdb::class],
					
					Helpers\Auth_Request_State_Helper::class => [],
					Endpoint_Registrar::class => [ 'container' => ContainerInterface::class ],
				];
			}
		};

		return get_class($model);
	}
}

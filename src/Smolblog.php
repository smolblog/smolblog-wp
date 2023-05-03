<?php

namespace Smolblog\WP;

use wpdb;
use Psr\Container\ContainerInterface;
use Smolblog\Api;
use Smolblog\Core;
use Smolblog\MicroBlog;
use Smolblog\Framework\Infrastructure\AppKit;
use Smolblog\Framework\Infrastructure\ServiceRegistry;
use Smolblog\Framework\Messages\MessageBus;
use Smolblog\Framework\Objects\DomainModel;

class Smolblog {
	use AppKit;

	public readonly ServiceRegistry $container;

	public function __construct( array $plugin_models = [] ) {
		$this->container = $this->buildDefaultContainer( [
			Core\Model::class,
			Api\Model::class,
			MicroBlog\Model::class,
			$this->wordpress_model(),
			...$plugin_models,
		] );
	}

	private function wordpress_model(): string {
		$model = new class extends DomainModel {
			public static function getDependencyMap(): array {
				global $wpdb;

				$wpdb->show_errors();
				define( 'DIEONDBERROR', true );

				return [
					Api\ApiEnvironment::class => fn() => new class implements Api\ApiEnvironment {
						public function getApiUrl( string $endpoint = '/' ): string {
							return get_rest_url( null, '/smolblog/v2' . $endpoint );
						}
					},
					EndpointRegistrar::class => [ 'container' => ContainerInterface::class ],
					wpdb::class => fn() => $wpdb,

					Core\Connector\Services\AuthRequestStateRepo::class => Helpers\AuthRequestStateHelper::class,

					EventStreams\ConnectorEventStream::class => ['db' => wpdb::class],
					EventStreams\ContentEventStream::class => ['db' => wpdb::class],

					Projections\ChannelProjection::class => ['db' => wpdb::class],
					Projections\ChannelSiteLinkProjection::class => [
						'db' => wpdb::class,
						'channel_proj' => Projections\ChannelProjection::class,
						'connection_proj' => Projections\ConnectionProjection::class,
						'bus' => MessageBus::class,
					],
					Projections\ConnectionProjection::class => ['db' => wpdb::class],
					Projections\PostProjection::class => [],
					
					Helpers\AuthRequestStateHelper::class => [],
					Helpers\SiteHelper::class => [],
					Helpers\UserHelper::class => [],
				];
			}
		};

		return get_class($model);
	}
}

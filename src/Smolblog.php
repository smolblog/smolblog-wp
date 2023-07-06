<?php

namespace Smolblog\WP;

use wpdb;
use Psr\Container\ContainerInterface;
use Psr\Http\Client\ClientInterface;
use Smolblog\Api;
use Smolblog\Core;
use Smolblog\MicroBlog;
use Smolblog\ActivityPub;
use Smolblog\IndieWeb;
use Smolblog\Framework\Infrastructure\AppKit;
use Smolblog\Framework\Infrastructure\DefaultModel;
use Smolblog\Framework\Infrastructure\ServiceRegistry;
use Smolblog\Framework\Messages\MessageBus;
use Smolblog\Framework\Objects\DomainModel;
use Smolblog\WP\Helpers\DebugEndpoint;

class Smolblog {
	use AppKit;

	public readonly ServiceRegistry $container;
	private array $depMap = [];

	public function __construct( array $plugin_models = [] ) {
		// $this->container = $this->buildDefaultContainer( [
		// 	Core\Model::class,
		// 	Api\Model::class,
		// 	MicroBlog\Model::class,
		// 	ActivityPub\Model::class,
		// 	$this->wordpress_model(),
		// 	...$plugin_models,
		// ] );

		$this->depMap = $this->buildDependencyMap([
			DefaultModel::class,
			Core\Model::class,
			Api\Model::class,
			MicroBlog\Model::class,
			ActivityPub\Model::class,
			IndieWeb\Model::class,
			$this->wordpress_model(),
			...$plugin_models,
		]);
		$this->depMap[DebugEndpoint::class]['depMap'] = fn() => $this->depMap;

		$this->container = new ServiceRegistry($this->depMap);
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

					ClientInterface::class => \GuzzleHttp\Client::class,
					\GuzzleHttp\Client::class => fn() => new \GuzzleHttp\Client(['verify' => false]),

					Core\Connector\Services\AuthRequestStateRepo::class => Helpers\AuthRequestStateHelper::class,
					Core\Content\Types\Reblog\ExternalContentService::class => Helpers\EmbedHelper::class,

					EventStreams\ConnectorEventStream::class => ['db' => wpdb::class],
					EventStreams\ContentEventStream::class => ['db' => wpdb::class],
					EventStreams\SiteEventStream::class => ['db' => wpdb::class],

					Projections\ChannelProjection::class => ['db' => wpdb::class],
					Projections\ChannelSiteLinkProjection::class => [
						'db' => wpdb::class,
						'channel_proj' => Projections\ChannelProjection::class,
						'connection_proj' => Projections\ConnectionProjection::class,
						'bus' => MessageBus::class,
					],
					Projections\ConnectionProjection::class => ['db' => wpdb::class],
					Projections\ContentQueryHandler::class => ['db' => wpdb::class, 'bus' => MessageBus::class],
					Projections\FollowerProjection::class => ['db' => wpdb::class],
					Projections\PostProjection::class => ['bus' => MessageBus::class],
					Projections\ReblogProjection::class => ['db' => wpdb::class],
					Projections\StandardContentProjection::class => ['db' => wpdb::class],
					Projections\StatusProjection::class => ['db' => wpdb::class],
					
					Helpers\AsyncHelper::class => [],
					Helpers\AuthRequestStateHelper::class => [],
					Helpers\SiteHelper::class => [],
					Helpers\UserHelper::class => [],
					Helpers\EmbedHelper::class => [],
					DebugEndpoint::class => ['depMap' => null],
				];
			}
		};

		return get_class($model);
	}
}

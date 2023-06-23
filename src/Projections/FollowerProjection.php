<?php

namespace	Smolblog\WP\Projections;

use Smolblog\Core\Connector\Entities\Channel;
use Smolblog\Core\Connector\Events\{ChannelDeleted, ChannelSaved};
use Smolblog\Core\Connector\Queries\{ChannelById, ChannelsForConnection};
use Smolblog\Core\Federation\Follower;
use Smolblog\Core\Federation\FollowerAdded;
use Smolblog\WP\TableBacked;
use Smolblog\Framework\Messages\Projection;
use Smolblog\Framework\Objects\Identifier;

class FollowerProjection extends TableBacked implements Projection {
	const TABLE = 'followers';
	const FIELDS = <<<EOF
		follower_id varchar(40) NOT NULL UNIQUE,
		site_id varchar(40) NOT NULL,
		provider varchar(50) NOT NULL,
		provider_key varchar(50) NOT NULL,
		display_name varchar(50) NOT NULL,
		details text,
	EOF;

	public function onFollowerAdded(FollowerAdded $event) {
		$follower = $event->getFollower();
		$dbid = $this->dbid_for_uuid($follower->id);

		$values = array_filter( [
			'id' => $dbid,
			'follower_id' => $follower->id->toString(),
			'site_id' => $event->siteId->toString(),
			'provider' => $follower->provider,
			'provider_key' => $follower->providerKey,
			'display_name' => $follower->displayName,
			'details' => wp_json_encode( $follower->data ),
		] );
		$formats = ['%d', '%s', '%s', '%s', '%s', '%s', '%s'];

		if ( ! isset( $dbid ) ) {
			unset($values['id']);
			unset($formats[0]);
		}

		$this->db->replace(
			static::table_name(),
			$values,
			$formats,
		);
	}

	private function dbid_for_uuid(Identifier $uuid): ?int {
		$table = static::table_name();

		return $this->db->get_var(
			$this->db->prepare("SELECT `id` FROM $table WHERE `follower_id` = %s", $uuid->toString())
		);
	}

	public function follower_from_row(array $data): Follower {
		return new Follower(
			siteId: Identifier::fromString( $data['site_id'] ),
			provider: $data['provider'],
			providerKey: $data['provider_key'],
			displayName: $data['display_name'],
			data: json_decode( $data['details'], true ),
		);
	}
}
<?php

namespace	Smolblog\WP\Projections;

use Smolblog\Core\Federation\Follower;
use Smolblog\Core\Federation\GetFollowersForSite;
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
		display_name varchar(100) NOT NULL,
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

		$res = $this->db->replace(
			static::table_name(),
			$values,
			$formats,
		);

		if (false === $res) {
			throw new \Exception($this->db->last_error . ' Event: ' . print_r([$event, $follower], true));
		}
	}

	public function onFollowersForSite(GetFollowersForSite $query) {
		$table = static::table_name();

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT * FROM $table WHERE `site_id` = %s",
				$query->siteId->toString()
			),
			ARRAY_A
		);

		return array_map(fn($row) => $this->follower_from_row($row), $results);
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
<?php

namespace Smolblog\WP\Projections;

use wpdb;
use Smolblog\Core\Connector\Entities\ChannelSiteLink;
use Smolblog\Core\Connector\Events\ChannelSiteLinkSet;
use Smolblog\Core\Connector\Queries\{ChannelsForSite, SiteHasPermissionForChannel, UserCanLinkChannelAndSite, ChannelsForAdmin};
use Smolblog\Core\Site\UserHasPermissionForSite;
use Smolblog\Framework\Messages\MessageBus;
use Smolblog\Framework\Messages\Projection;
use Smolblog\Framework\Objects\Identifier;
use Smolblog\WP\TableBacked;

class ChannelSiteLinkProjection extends TableBacked implements Projection {
	const TABLE = 'channel_site_links';
	const FIELDS = <<<EOF
		id bigint(20) NOT NULL AUTO_INCREMENT,
		link_id varchar(40) NOT NULL UNIQUE,
		channel_id varchar(40) NOT NULL,
		site_id varchar(40) NOT NULL,
		can_push bool NOT NULL,
		can_pull bool NOT NULL,
		PRIMARY KEY  (id)
	EOF;

	public function __construct(
		wpdb $db,
		private ChannelProjection $channel_proj,
		private ConnectionProjection $connection_proj,
		private MessageBus $bus,
	) {
		parent::__construct(db: $db);
	}

	public function onChannelSiteLinkSet(ChannelSiteLinkSet $event) {
		$link_id = ChannelSiteLink::buildId(channelId: $event->channelId, siteId: $event->siteId);

		$dbid = $this->dbid_for_uuid($link_id);

		$values = array_filter( [
			'id' => $dbid,
			'link_id' => $link_id->toString(),
			'channel_id' => $event->channelId->toString(),
			'site_id' => $event->siteId->toString(),
			'can_push' => $event->canPush,
			'can_pull' => $event->canPull,
		] );
		$formats = ['%d', '%s', '%s', '%s', '%d', '%d'];

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

	public function onChannelsForSite(ChannelsForSite $query) {
		$link_table    = static::table_name();
		$channel_table = ChannelProjection::table_name();

		$where  = '`links`.`site_id` = %s';
		$params = [$query->siteId->toString()];

		if (isset($query->canPull)) {
			$where .= ' AND `links`.`can_pull` = %s';
			$params[] = $query->canPull;
		}
		if (isset($query->canPush)) {
			$where .= ' AND `links`.`can_push` = %s';
			$params[] = $query->canPush;
		}

		$db_results = $this->db->get_results(
			$this->db->prepare(
				"SELECT `channels`.*
				FROM $link_table `links`
					INNER JOIN $channel_table `channels` ON `links`.`channel_id` = `channels`.`channel_id`
				WHERE $where",
				...$params
			),
			ARRAY_A
		);

		$query->results = array_map(fn($cha) => $this->channel_proj->channel_from_row($cha), $db_results);
	}

	public function onSiteHasPermissionForChannel(SiteHasPermissionForChannel $query) {
		$table = static::table_name();

		$link = $this->db->get_row(
			$this->db->prepare(
				"SELECT `id` FROM $table WHERE `site_id` = %s AND `channel_id` = %s",
				$query->siteId->toString(),
				$query->channelId->toString(),
			)
		);

		$query->results = (
			(!$query->mustPull || $link['can_pull']) &&
			(!$query->mustPush || $link['can_push'])
		);
	}

	public function onUserCanLinkChannelAndSite(UserCanLinkChannelAndSite $query) {
		$channel_table = ChannelProjection::table_name();
		$connect_table = ConnectionProjection::table_name();
		$owns_channel  = $this->db->get_var(
			$this->db->prepare(
				"SELECT `channel`.`id`
				FROM $channel_table `channel`
					INNER JOIN $connect_table `connection` ON `channel`.`connection_id` = `connection`.`connection_id`
				WHERE `channel`.`channel_id` = %s AND `connection`.`user_id` = %s",
				$query->channelId->toString(),
				$query->userId->toString(),
			),
		);

		if (!$owns_channel) {
			$query->results = false;
			return;
		}

		// It's not great form to dispatch another query to answer a query, but this *currently* prevents duplicate code
		// in checking user capabilities.
		$query->results = $this->bus->fetch(new UserHasPermissionForSite(
			userId: $query->userId,
			siteId: $query->siteId,
			mustBeAdmin: true,
		));
	}

	public function onChannelsForAdmin(ChannelsForAdmin $query) {
		$link_table = self::table_name();
		$channel_table = ChannelProjection::table_name();
		$connection_table = ConnectionProjection::table_name();

		$results = $this->db->get_results(
			$this->db->prepare(
				"SELECT
					`connections`.`connection_id`,
					`connections`.`user_id`,
					`connections`.`provider`,
					`connections`.`provider_key`,
					`connections`.`display_name` as `connection_display_name`,
					`connections`.`details` as `connection_details`,
					`channels`.`channel_id`,
					`channels`.`channel_key`,
					`channels`.`display_name` as `channel_display_name`,
					`channels`.`details` as `channel_details`,
					`links`.`site_id`,
					`links`.`can_pull`,
					`links`.`can_push`
				FROM $channel_table `channels`
				 	INNER JOIN $connection_table `connections` ON `connections`.`connection_id` = `channels`.`connection_id`
					LEFT JOIN (
						SELECT *
						FROM $link_table
						WHERE `site_id` = %s
					) AS `links` ON `channels`.`channel_id` = `links`.`channel_id`
				WHERE
					`connections`.`user_id` = %s OR
					`links`.`id` IS NOT NULL",
				$query->siteId->toString(),
				$query->userId->toString(),
			),
			ARRAY_A
		);

		$connections = [];
		$channels = [];
		$links = [];
		foreach ($results as $row) {
			if (!array_key_exists($row['connection_id'], $channels)) {
				$connections[$row['connection_id']] = $this->connection_proj->connection_from_row([
					...$row,
					'display_name' => $row['connection_display_name'],
					'details' => $row['connection_details'],
				]);
				$channels[$row['connection_id']] = [];
			}

			$channels[$row['connection_id']][] = $this->channel_proj->channel_from_row([
				...$row,
				'display_name' => $row['channel_display_name'],
				'details' => $row['channel_details'],
			]);

			if (isset($row['site_id'])) {
				$links[$row['channel_id']] = $this->link_from_row($row);
			}
		}

		$query->results = [
			'connections' => $connections,
			'channels' => $channels,
			'links' => $links,
		];
	}

	private function dbid_for_uuid(Identifier $uuid): ?int {
		$table = static::table_name();

		return $this->db->get_var(
			$this->db->prepare("SELECT `id` FROM $table WHERE `channel_id` = %s", $uuid->toString())
		);
	}

	private function link_from_row(array $data): ChannelSiteLink {
		return new ChannelSiteLink(
			channelId: Identifier::fromString($data['channel_id']),
			siteId: Identifier::fromString($data['site_id']),
			canPull: $data['can_pull'],
			canPush: $data['can_push'],
		);
	}
}
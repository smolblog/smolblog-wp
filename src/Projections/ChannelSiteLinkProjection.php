<?php

namespace Smolblog\WP\Projections;

use wpdb;
use Smolblog\Core\Connector\Entities\ChannelSiteLink;
use Smolblog\Core\Connector\Events\ChannelSiteLinkSet;
use Smolblog\Core\Connector\Queries\{ChannelsForSite, SiteHasPermissionForChannel, UserCanLinkChannelAndSite};
use Smolblog\Core\Site\UserHasPermissionForSite;
use Smolblog\Framework\Messages\MessageBus;
use Smolblog\Framework\Messages\Projection;
use Smolblog\Framework\Objects\Identifier;
use Smolblog\WP\TableBacked;

class ChannelSiteLinkProjection extends TableBacked implements Projection {
	const TABLE = 'channel_site_links';
	const FIELDS = <<<EOF
		`id` bigint(20) NOT NULL AUTO_INCREMENT,
		`link_id` char(16) NOT NULL UNIQUE,
		`channel_id` char(16) NOT NULL,
		`site_id` char(16) NOT NULL,
		`can_push` bool NOT NULL,
		`can_pull` bool NOT NULL,
		PRIMARY KEY (id)
	EOF;

	public function __construct(
		wpdb $db,
		private ChannelProjection $channel_proj,
		private MessageBus $bus,
	) {
		parent::__construct(db: $db);
	}

	public function onChannelSiteLinkSet(ChannelSiteLinkSet $event) {
		$link_id = ChannelSiteLink::buildId(channelId: $event->channelId, siteId: $event->siteId);

		$dbid = $this->dbid_for_uuid($link_id);

		$values = array_filter( [
			'id' => $dbid,
			'link_id' => $link_id->toByteString(),
			'channel_id' => $event->channelId->toByteString(),
			'site_id' => $event->siteId->toByteString(),
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
		$db_results    = $this->db->get_results(
			$this->db->prepare(
				"SELECT `channels`.*
				FROM $link_table `links`
					INNER JOIN $channel_table `channels` ON `links`.`channel_id` = `channels`.`channel_id`
				WHERE `links`.`site_id` = %s",
				$query->siteId->toByteString()
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
				$query->siteId->toByteString(),
				$query->channelId->toByteString(),
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
				"SELECT `link`.`id`
				FROM $channel_table `channel`
					INNER JOIN $connect_table `connection` ON `channel`.`connection_id` = `connection`.`connection_id`
				WHERE `link`.`channel_id` = %s AND `connection`.`user_id` = %s"
			),
			$query->channelId->toByteString(),
			$query->userId->toByteString(),
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

	private function dbid_for_uuid(Identifier $uuid): ?int {
		$table = static::table_name();

		return $this->db->get_var(
			$this->db->prepare("SELECT `id` FROM $table WHERE `channel_id` = %s", $uuid->toByteString())
		);
	}

	private function link_from_row(array $data): ChannelSiteLink {
		return new ChannelSiteLink(
			channelId: $data['channel_id'],
			siteId: $data['site_id'],
			canPull: $data['can_pull'],
			canPush: $data['can_push'],
		);
	}
}
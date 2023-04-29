<?php

namespace Smolblog\WP\Helpers;

use WP_Site_Query;
use Smolblog\Core\Site\{GetSiteSettings, LinkSiteAndUser, Site, SiteById, SiteSettings, SiteUsers, UpdateSettings, UserHasPermissionForSite};
use Smolblog\Core\User\User;
use Smolblog\Framework\Messages\Listener;
use Smolblog\Framework\Objects\Identifier;

class SiteHelper implements Listener {
	public function onGetSiteSettings(GetSiteSettings $query) {
		$site_id = self::UuidToInt($query->siteId);
		$site_info = get_site( $site_id );

		$query->results = new SiteSettings(
			siteId: $query->siteId,
			title: get_blog_option( $site_id, 'blogname', '' ),
			tagline: get_blog_option( $site_id, 'blogdescription', '' ),
		);
	}

	public function onLinkSiteAndUser(LinkSiteAndUser $command) {
		$user_id = UserHelper::UuidToInt($command->linkedUserId);
		$site_id = self::UuidToInt($command->siteId);
		
		$wp_role = 'subscriber';
		if ($command->isAuthor) {
			$wp_role = 'author';
		}
		if ($command->isAdmin) {
			$wp_role = 'administrator';
		}

		add_user_to_blog( $site_id, $user_id, $wp_role );
	}

	public function onSiteById(SiteById $query) {
		$site_id = self::UuidToInt($query->siteId);
		$query->results = self::SiteFromWpId($site_id, $query->siteId);
	}

	public function onSiteUsers(SiteUsers $query) {
		$site_id = self::UuidToInt($query->siteId);

		$query->results = array_map(
			fn($user) => UserHelper::UserFromWpUser($user),
			get_users( [ 'blog_id' => $site_id, 'role__not_in' => ['subscriber'] ] ),
		);
	}

	public function onUpdateSettings(UpdateSettings $command) {
		$site_id = self::UuidToInt($command->siteId);
		update_blog_option( $site_id, 'blogname', $command->siteName );
		update_blog_option( $site_id, 'blogdescription', $command->siteTagline );
	}

	public function onUserHasPermissionForSite(UserHasPermissionForSite $query) {
		$site_id = self::UuidToInt($query->siteId);
		$user_id = UserHelper::UuidToInt($query->userId);
		switch_to_blog( $site_id );

		$query->results = (
			(!$query->mustBeAuthor || user_can( $user_id, 'edit_posts' )) &&
			(!$query->mustBeAdmin || user_can( $user_id, 'activate_plugins'))
		);

		restore_current_blog();
	}

	public static function SiteFromWpId(int $site_id, ?Identifier $site_uuid = null): Site {
		$site_uuid ??= self::IntToUuid($site_id);
		$details   = get_blog_details( $site_id );

		return new Site(
			id: $site_uuid,
			handle: self::slugFromDomain($details->domain),
			displayName: $details->blogname,
			baseUrl: $details->home,
		);
	}

	public static function UuidToInt(Identifier $uuid) {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT blog_id FROM $wpdb->blogmeta WHERE meta_key = 'smolblog_site_id' AND meta_value = %s",
				$uuid->toString()
			)
		);
	}

	public static function IntToUuid(int $dbid) {
		$meta_value = get_site_meta( $dbid, 'smolblog_site_id', true );
	
		if (empty($meta_value)) {
			// If the site does not have an ID, give it one.
			$new_id = Identifier::createRandom();
			update_site_meta( $dbid, 'smolblog_site_id', $new_id->toString() );

			return $new_id;
		}

		return Identifier::fromString( $meta_value );
	}

	private static function slugFromDomain(string $domain) {
		$base = get_site( 1 )->domain;

		if ($domain === $base) {
			return 'root';
		}

		return str_replace(".$base", '', $domain);
	}
}
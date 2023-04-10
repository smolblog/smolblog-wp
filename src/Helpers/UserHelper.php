<?php

namespace Smolblog\WP\Helpers;

use Smolblog\Core\User\UpdateProfile;
use WP_User;
use WP_User_Query;
use Smolblog\Core\User\User;
use Smolblog\Core\User\UserById;
use Smolblog\Core\User\UserCanEditProfile;
use Smolblog\Core\User\UserSites;
use Smolblog\Framework\Messages\Listener;
use Smolblog\Framework\Objects\Identifier;

class UserHelper implements Listener {

	public function onUpdateProfile(UpdateProfile $command) {
		$user_id = self::UuidToInt($command->profileId);
		$user = get_user_by( 'id', $user_id );

		$user->user_login = $command->handle;
		$user->display_name = $command->displayName;
		$user->smolblog_pronouns = $command->pronouns;
	}

	public function onUserById(UserById $query) {
		$user_id = self::UuidToInt($query->userId);
		$query->results = self::UserFromWpUser(get_userdata( $user_id ));
	}

	public function onUserCanEditProfile(UserCanEditProfile $query) {
		if ($query->profileId == $query->userId) {
			$query->results = true;
		}

		$query->results = user_can( self::UuidToInt($query->userId), 'edit_users' );
	}

	public function onUserSites(UserSites $query) {
		$user_id = self::UuidToInt($query->userId);

		$query->results = array_map(
			fn($site_id) => SiteHelper::SiteFromWpId($site_id),
			get_blogs_of_user( $user_id )
		);
	}

	public static function UserFromWpUser(WP_User $wp_user): User {
		$user_id = self::IntToUuid( $wp_user->ID );

		return new User(
			id: $user_id,
			handle: $wp_user->user_login,
			displayName: $wp_user->display_name,
			pronouns: $wp_user->smolblog_pronouns ?? '',
			email: $wp_user->user_email,
		);
	}

	public static function UuidToInt(Identifier $uuid) {
		$user_query = new WP_User_Query( [
			'fields' => 'ids',
			'meta_query' => [
				'key' => 'smolblog_user_id',
				'value' => $uuid->toString(),
			],
		] );

		$ids = $user_query->get_users();
		return $ids[0];
	}

	public static function IntToUuid(int $dbid) {
		$meta_value = get_user_meta( $dbid, 'smolblog_user_id', true );
	
		if (empty($meta_value)) {
			$new_id = Identifier::createRandom();
			update_user_meta( $dbid, 'smolblog_user_id', $new_id->toString() );

			return $new_id;
		}

		return Identifier::fromString( $meta_value );
	}
}
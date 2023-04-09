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

	}

	public function onUserById(UserById $query) {

	}

	public function onUserCanEditProfile(UserCanEditProfile $query) {

	}

	public function onUserSites(UserSites $query) {

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
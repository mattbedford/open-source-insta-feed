<?php
/**
 * Sync class runs off schedule to fetch data from Instagram
 */

namespace osif;

defined( 'ABSPATH' ) || exit;

class Syncer {

	public static function getAccessToken(): ?string
	{
		$token = get_option( 'osif_access_token' );
		return $token ? (string) $token : null;
	}

	public static function refreshToken(): array
	{
		return [];
	}

	public static function sync(): array
	{
		return [];
	}

}
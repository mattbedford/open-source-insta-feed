<?php
/**
 * Sync class runs off schedule to fetch data from Instagram
 */

namespace osif;

defined( 'ABSPATH' ) || exit;

class Syncer {

	private const int DISPLAY_COUNT = 8;
	private const int BUFFER = 4;
	private const array FIELDS = [ 'id', 'caption', 'media_type', 'permalink', 'media_url' ];

	public static function getAccessToken(): ?string
	{
		$token = get_option( 'osif_access_token' );
		return $token ? (string) $token : null;
	}

	public static function refreshToken(): array
	{
		$token = self::getAccessToken();

		if ( null === $token ) {
			return [ 'success' => false, 'message' => 'No access token found to refresh.' ];
		}

		$client   = new Client( $token );
		$response = $client->refreshToken();

		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'message' => $response->get_error_message() ];
		}

		if ( ! isset( $response['access_token'] ) ) {
			return [ 'success' => false, 'message' => 'Refresh response did not contain an access token.' ];
		}

		update_option( 'osif_access_token', $response['access_token'] );
		wp_set_option_autoload( 'osif_access_token', false ); // Keep it non-autoloaded, per our decisions.

		return [
			'success'    => true,
			'expires_in' => $response['expires_in'] ?? null,
		];
	}

	public static function sync(): array
	{
		$token = self::getAccessToken();

		if ( null === $token ) {
			return [ 'success' => false, 'message' => 'No access token found. Cannot sync.' ];
		}

		$client = new Client( $token );
		$result = $client->fetchMedia( self::DISPLAY_COUNT + self::BUFFER, self::FIELDS );

		if ( is_wp_error( $result ) ) {
			return [ 'success' => false, 'message' => $result->get_error_message() ];
		}

		$posts = $result['data'] ?? [];

		// Images only. Videos and carousel albums are out of scope, per our decisions.
		$images = array_filter(
			$posts,
			static fn( array $post ): bool => ( $post['media_type'] ?? '' ) === 'IMAGE'
		);

		$seenIds = self::upsertPosts( $images );
		self::pruneRemoved( $seenIds );

		return [
			'success' => true,
			'synced'  => count( $seenIds ),
		];
	}

	private static function upsertPosts( array $posts ): array
	{
		global $wpdb;
		$table   = $wpdb->prefix . 'osif_photo_cache';
		$seenIds = [];

		foreach ( $posts as $post ) {
			$mediaId = $post['id'] ?? null;

			if ( null === $mediaId ) {
				continue;
			}

			$seenIds[] = (int) $mediaId;

			$existing = $wpdb->get_row(
				$wpdb->prepare( "SELECT id FROM {$table} WHERE media_id = %d", $mediaId )
			);

			if ( $existing ) {
				// Deliberately not touching `disabled` here. That's the admin's decision, not sync's.
				$wpdb->update(
					$table,
					[
						'caption'    => $post['caption'] ?? '',
						'permalink'  => $post['permalink'] ?? '',
						'media_type' => $post['media_type'] ?? '',
					],
					[ 'media_id' => $mediaId ],
					[ '%s', '%s', '%s' ],
					[ '%d' ]
				);

				continue;
			}

			$filename = null;

			if ( ! empty( $post['media_url'] ) ) {
				$filename = ImageCache::store( $post['media_url'], (string) $mediaId );
			}

			$wpdb->insert(
				$table,
				[
					'media_id'       => $mediaId,
					'caption'        => $post['caption'] ?? '',
					'permalink'      => $post['permalink'] ?? '',
					'media_type'     => $post['media_type'] ?? '',
					'local_filename' => $filename,
					'disabled'       => 0,
				],
				[ '%d', '%s', '%s', '%s', '%s', '%d' ]
			);
		}

		return $seenIds;
	}

	private static function pruneRemoved( array $seenIds ): void
	{
		global $wpdb;
		$table = $wpdb->prefix . 'osif_photo_cache';

		// Safety net: an empty fetch (API hiccup, everything filtered as video) should never mean
		// "delete every cached row". Only prune when we genuinely have a fresh list to diff against.
		if ( empty( $seenIds ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $seenIds ), '%d' ) );

		$toRemove = $wpdb->get_col(
			$wpdb->prepare( "SELECT media_id FROM {$table} WHERE media_id NOT IN ({$placeholders})", $seenIds )
		);

		foreach ( $toRemove as $mediaId ) {
			ImageCache::delete( (string) $mediaId );
		}

		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$table} WHERE media_id NOT IN ({$placeholders})", $seenIds )
		);
	}
}
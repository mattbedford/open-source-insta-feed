<?php
/**
 * Sync class runs off schedule to fetch data from Instagram
 */

namespace osif;

defined( 'ABSPATH' ) || exit;

class Syncer {

	/** What the block shows by default, and how many extra are fetched so hiding some never leaves it short. */
	public const int DISPLAY_COUNT = 8;
	public const int BUFFER = 4;

	/**
	 * How many pages to walk when the newest posts are mostly videos and carousels that
	 * cannot be shown. Enough to reach back through a run of them; small enough that a
	 * sync is never dozens of API calls.
	 */
	private const int MAX_PAGES = 5;

	/**
	 * `children` is asked for so a carousel can be shown by its first picture even when the
	 * album's own media_url points at a video. `thumbnail_url` is the poster frame of a video.
	 */
	private const array FIELDS = [
		'id', 'caption', 'media_type', 'permalink', 'media_url', 'thumbnail_url',
		'children{media_type,media_url,thumbnail_url}',
	];

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

		$posts = self::collectPosts( new Client( $token ) );

		if ( is_wp_error( $posts ) ) {
			return [ 'success' => false, 'message' => $posts->get_error_message() ];
		}

		$seenIds = self::upsertPosts( $posts );
		self::pruneRemoved( $seenIds );

		return [
			'success' => true,
			'synced'  => count( $seenIds ),
		];
	}

	/**
	 * The newest posts that can be shown as a picture, up to the display count plus the buffer.
	 *
	 * Instagram pages its results, and a page of the newest posts can be mostly reels and
	 * carousels. Rather than stop at one page and come up short, keep following the "next"
	 * link until the quota is filled, the account runs out, or MAX_PAGES is reached.
	 *
	 * @return list<array<string,mixed>>|\WP_Error Each post carries an `image_url` picked by imageUrl().
	 */
	public static function collectPosts( Client $client ): array|\WP_Error
	{
		$wanted  = self::DISPLAY_COUNT + self::BUFFER;
		$result  = $client->fetchMedia( $wanted, self::FIELDS );
		$posts   = [];
		$pages   = 1;

		while ( true ) {
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			foreach ( $result['data'] ?? [] as $post ) {
				$image = self::imageUrl( $post );

				if ( null === $image ) {
					continue;
				}

				$post['image_url'] = $image;
				$posts[]           = $post;

				if ( count( $posts ) >= $wanted ) {
					return $posts;
				}
			}

			$next = $result['paging']['next'] ?? null;

			if ( null === $next || $pages >= self::MAX_PAGES ) {
				return $posts;
			}

			$result = $client->fetchPage( $next );
			$pages++;
		}
	}

	/**
	 * The still picture that stands for a post on the site, or null if there is none.
	 *
	 * A photo is itself. A video or reel is its poster frame, which Instagram supplies as
	 * thumbnail_url. A carousel is its first slide, which may itself be a video, so the
	 * children are walked the same way. Nothing on the site ever plays; a click goes to
	 * Instagram, per our decisions.
	 */
	public static function imageUrl( array $post ): ?string
	{
		$type = $post['media_type'] ?? '';

		if ( 'IMAGE' === $type ) {
			return self::nonEmpty( $post['media_url'] ?? null );
		}

		if ( 'VIDEO' === $type ) {
			return self::nonEmpty( $post['thumbnail_url'] ?? null );
		}

		if ( 'CAROUSEL_ALBUM' !== $type ) {
			return null;
		}

		foreach ( $post['children']['data'] ?? [] as $child ) {
			$image = self::imageUrl( $child );

			if ( null !== $image ) {
				return $image;
			}
		}

		return self::nonEmpty( $post['thumbnail_url'] ?? null );
	}

	private static function nonEmpty( mixed $value ): ?string
	{
		return is_string( $value ) && '' !== $value ? $value : null;
	}

	/**
	 * @param list<array<string,mixed>> $posts Each with an `image_url`, as collectPosts() returns them.
	 * @return list<int> The media ids now in the cache.
	 */
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
				$wpdb->prepare( "SELECT id, local_filename FROM {$table} WHERE media_id = %d", $mediaId )
			);

			if ( $existing ) {
				// Deliberately not touching `disabled` here. That's the admin's decision, not sync's.
				// A row whose download failed last time gets another go, so a blip never leaves a
				// permanent gap in the feed.
				$fields  = [
					'caption'    => $post['caption'] ?? '',
					'permalink'  => $post['permalink'] ?? '',
					'media_type' => $post['media_type'] ?? '',
				];
				$formats = [ '%s', '%s', '%s' ];

				if ( empty( $existing->local_filename ) ) {
					$fields['local_filename'] = ImageCache::store( $post['image_url'], (string) $mediaId );
					$formats[]                = '%s';
				}

				$wpdb->update( $table, $fields, [ 'media_id' => $mediaId ], $formats, [ '%d' ] );

				continue;
			}

			$wpdb->insert(
				$table,
				[
					'media_id'       => $mediaId,
					'caption'        => $post['caption'] ?? '',
					'permalink'      => $post['permalink'] ?? '',
					'media_type'     => $post['media_type'] ?? '',
					'local_filename' => ImageCache::store( $post['image_url'], (string) $mediaId ),
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
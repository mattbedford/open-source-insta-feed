<?php
/**
 * The cached photos as the admin sees them: listing, hiding and showing.
 *
 * Hiding is the one editorial control the plugin offers. A hidden photo stays in the
 * cache so a re-sync does not resurrect it, and it is capped at the sync buffer: the
 * syncer always fetches that many beyond what the block shows, so hiding up to the
 * buffer never leaves the feed short.
 */

namespace osif;

defined( 'ABSPATH' ) || exit;

class Photos {

	/** How many photos may be hidden at once. */
	public const int HIDE_LIMIT = Syncer::BUFFER;

	/**
	 * Every cached photo, newest first, which is the order the block shows them in.
	 *
	 * @return list<object{media_id:string, caption:?string, permalink:string, local_filename:?string, disabled:string, created_at:string}>
	 */
	public static function all(): array
	{
		global $wpdb;
		$table = $wpdb->prefix . 'osif_photo_cache';

		return $wpdb->get_results(
			"SELECT media_id, caption, permalink, local_filename, disabled, created_at
			 FROM {$table}
			 ORDER BY created_at DESC, id DESC"
		) ?: [];
	}

	public static function hiddenCount(): int
	{
		global $wpdb;
		$table = $wpdb->prefix . 'osif_photo_cache';

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE disabled = 1" );
	}

	public static function canHideMore(): bool
	{
		return self::hiddenCount() < self::HIDE_LIMIT;
	}

	/** @return array{success:bool, message:string} */
	public static function hide( int $mediaId ): array
	{
		if ( ! self::canHideMore() ) {
			return [
				'success' => false,
				'message' => sprintf(
					/* translators: %d: the number of photos that may be hidden at once. */
					__( 'Up to %d photos can be hidden at a time. Show one before hiding another.', 'osif' ),
					self::HIDE_LIMIT
				),
			];
		}

		return self::setDisabled( $mediaId, true );
	}

	/** @return array{success:bool, message:string} */
	public static function show( int $mediaId ): array
	{
		return self::setDisabled( $mediaId, false );
	}

	/** @return array{success:bool, message:string} */
	private static function setDisabled( int $mediaId, bool $disabled ): array
	{
		global $wpdb;
		$table = $wpdb->prefix . 'osif_photo_cache';

		$exists = $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE media_id = %d", $mediaId )
		);

		if ( ! $exists ) {
			return [
				'success' => false,
				'message' => __( 'That photo is no longer in the cache. It may have been removed from Instagram.', 'osif' ),
			];
		}

		$updated = $wpdb->update(
			$table,
			[ 'disabled' => $disabled ? 1 : 0 ],
			[ 'media_id' => $mediaId ],
			[ '%d' ],
			[ '%d' ]
		);

		if ( false === $updated ) {
			return [ 'success' => false, 'message' => __( 'The photo could not be updated.', 'osif' ) ];
		}

		return [
			'success' => true,
			'message' => $disabled
				? __( 'Photo hidden. It will stay hidden until you show it again.', 'osif' )
				: __( 'Photo showing again.', 'osif' ),
		];
	}
}

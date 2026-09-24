<?php
/**
 * Registers and manages the WP-Cron schedule for syncing with Instagram.
 */

namespace osif;

defined( 'ABSPATH' ) || exit;

class Cron {

	private const string HOOK = 'osif_sync_event';

	public static function boot(): void
	{
		add_action( self::HOOK, [ Syncer::class, 'sync' ] );
	}

	public static function activate(): void
	{
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), 'twicedaily', self::HOOK );
		}
	}

	public static function deactivate(): void
	{
		wp_clear_scheduled_hook( self::HOOK );
	}
}

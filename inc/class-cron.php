<?php
/**
 * Registers and manages the WP-Cron schedules: a twice-daily media sync and a weekly token refresh.
 */

namespace osif;

defined( 'ABSPATH' ) || exit;

class Cron {

	private const string SYNC_HOOK    = 'osif_sync_event';
	private const string REFRESH_HOOK = 'osif_refresh_token_event';

	public static function boot(): void
	{
		add_action( self::SYNC_HOOK, [ Syncer::class, 'sync' ] );
		add_action( self::REFRESH_HOOK, [ self::class, 'refreshToken' ] );

		// Sites activated before the refresh event existed never ran activate() for it, so make sure
		// both events are on the schedule whenever the plugin loads.
		add_action( 'init', [ self::class, 'ensureScheduled' ] );
	}

	public static function activate(): void
	{
		self::ensureScheduled();
	}

	public static function deactivate(): void
	{
		wp_clear_scheduled_hook( self::SYNC_HOOK );
		wp_clear_scheduled_hook( self::REFRESH_HOOK );
	}

	public static function ensureScheduled(): void
	{
		if ( ! wp_next_scheduled( self::SYNC_HOOK ) ) {
			wp_schedule_event( time(), 'twicedaily', self::SYNC_HOOK );
		}

		// Long-lived tokens last 60 days and can only be refreshed once they are at least a day old.
		// Weekly keeps the token young without hammering the API; a few missed runs are still harmless.
		if ( ! wp_next_scheduled( self::REFRESH_HOOK ) ) {
			wp_schedule_event( time() + WEEK_IN_SECONDS, 'weekly', self::REFRESH_HOOK );
		}
	}

	public static function refreshToken(): void
	{
		$result = Syncer::refreshToken();

		if ( $result['success'] ) {
			return;
		}

		error_log( 'OSIF token refresh failed: ' . $result['message'] );
	}
}

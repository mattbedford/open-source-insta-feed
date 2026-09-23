<?php
/**
 * Plugin lifecycle: what happens at activation.
 */

namespace osif;

defined( 'ABSPATH' ) || exit;

class Plugin {

	public static function activate(): void {

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		dbDelta( "CREATE TABLE {$p}osif_photo_cache (
			id bigint(20) unsigned NOT NULL auto_increment,
			media_id bigint(20) NOT NULL,
			media_type varchar(255),
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			permalink varchar(255) NOT NULL,
			caption TEXT,
			disabled tinyint(1) default 0,
			PRIMARY KEY  (id),
			UNIQUE KEY  media_id (media_id)
		) {$charset};" );

	}

	public static function uninstall(): void
	{
		global $wpdb;

		$p = $wpdb->prefix;
		$table_name =  $p . 'osif_photo_cache';
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );
	}
}

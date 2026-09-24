<?php
/**
 * Downloads, stores, and deletes cached Instagram images.
 * Filesystem and image concerns only, deliberately kept out of Syncer.
 */

namespace osif;

defined( 'ABSPATH' ) || exit;

class ImageCache {

	private const string CACHE_DIR = 'osif-cache';
	private const int MAX_WIDTH = 640;

	public static function store( string $mediaUrl, string $mediaId ): ?string
	{
		$response = wp_remote_get( $mediaUrl );

		if ( is_wp_error( $response ) || '' === ( $body = wp_remote_retrieve_body( $response ) ) ) {
			return null;
		}

		$filename = $mediaId . '.' . self::extensionFromUrl( $mediaUrl );
		$dir      = self::cacheDir();

		if ( ! self::ensureDirExists( $dir ) ) {
			return null;
		}

		$path = trailingslashit( $dir ) . $filename;

		if ( false === file_put_contents( $path, $body ) ) {
			return null;
		}

		self::resize( $path );

		return $filename;
	}

	public static function delete( string $mediaId ): void
	{
		foreach ( glob( trailingslashit( self::cacheDir() ) . "{$mediaId}.*" ) ?: [] as $file ) {
			@unlink( $file );
		}
	}

	public static function url( string $filename ): string
	{
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['baseurl'] ) . self::CACHE_DIR . '/' . $filename;
	}

	private static function resize( string $path ): void
	{
		$editor = wp_get_image_editor( $path );

		if ( is_wp_error( $editor ) ) {
			return;
		}

		$editor->resize( self::MAX_WIDTH, null, false );
		$editor->save( $path );
	}

	private static function extensionFromUrl( string $url ): string
	{
		$path = wp_parse_url( $url, PHP_URL_PATH ) ?? '';
		return pathinfo( $path, PATHINFO_EXTENSION ) ?: 'jpg';
	}

	private static function cacheDir(): string
	{
		return wp_upload_dir()['basedir'] . '/' . self::CACHE_DIR;
	}

	private static function ensureDirExists( string $dir ): bool
	{
		if ( is_dir( $dir ) ) {
			return true;
		}

		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		file_put_contents( trailingslashit( $dir ) . 'index.php', '' ); // Block directory listing, per our decisions.

		return true;
	}
}
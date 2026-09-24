<?php
/**
 * Registers and renders the Instagram feed block.
 */

namespace osif;

defined( 'ABSPATH' ) || exit;

class Block {

	public static function boot(): void
	{
		add_action( 'init', [ self::class, 'register' ] );
	}

	public static function register(): void
	{
		register_block_type(
			OSIF_DIR . 'blocks/insta-feed',
			[ 'render_callback' => [ self::class, 'render' ] ]
		);
	}

	public static function render( array $attributes ): string
	{
		global $wpdb;
		$table = $wpdb->prefix . 'osif_photo_cache';
		$count = isset( $attributes['count'] ) ? (int) $attributes['count'] : 8;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT permalink, caption, local_filename FROM {$table}
				 WHERE disabled = 0 AND local_filename IS NOT NULL
				 ORDER BY created_at DESC LIMIT %d",
				$count
			)
		);

		if ( empty( $rows ) ) {
			return '';
		}

		$layout = isset( $attributes['layout'] ) ? sanitize_html_class( $attributes['layout'] ) : 'grid';

		ob_start();
		?>
		<div class="osif-feed osif-feed--<?php echo esc_attr( $layout ); ?>">
			<?php foreach ( $rows as $row ) : ?>
				<a class="osif-feed__item" href="<?php echo esc_url( $row->permalink ); ?>" target="_blank" rel="noopener noreferrer">
					<img src="<?php echo esc_url( ImageCache::url( $row->local_filename ) ); ?>"
					     alt="<?php echo esc_attr( wp_trim_words( $row->caption ?? '', 15 ) ); ?>"
					     loading="lazy" />
				</a>
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
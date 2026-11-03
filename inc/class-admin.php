<?php
/**
 * The admin screen, under Settings → Instagram Feed.
 *
 * Two things live here. The photo panel, where whoever looks after the account can
 * hide a cached photo from the site, show it again, and pull fresh posts without
 * waiting for the schedule. And the access token, which only administrators see.
 */

namespace osif;

defined( 'ABSPATH' ) || exit;

class Admin {

	private const string PAGE          = 'osif-feed';
	private const string OPTION_GROUP  = 'osif';
	private const string TOGGLE_ACTION = 'osif_toggle_photo';
	private const string SYNC_ACTION   = 'osif_sync_now';

	public static function boot(): void
	{
		add_action( 'admin_menu', [ self::class, 'addPage' ] );
		add_action( 'admin_init', [ self::class, 'registerSettings' ] );
		add_action( 'admin_post_' . self::TOGGLE_ACTION, [ self::class, 'handleToggle' ] );
		add_action( 'admin_post_' . self::SYNC_ACTION, [ self::class, 'handleSync' ] );
	}

	/**
	 * Who may curate the photos. Editors by default, so the people running the account
	 * need not be administrators. The token stays behind manage_options regardless.
	 */
	public static function capability(): string
	{
		return (string) apply_filters( 'osif_manage_capability', 'edit_others_posts' );
	}

	public static function addPage(): void
	{
		add_options_page(
			__( 'Instagram Feed', 'osif' ),
			__( 'Instagram Feed', 'osif' ),
			self::capability(),
			self::PAGE,
			[ self::class, 'renderPage' ]
		);
	}

	public static function registerSettings(): void
	{
		register_setting(
			self::OPTION_GROUP,
			'osif_access_token',
			[
				'type'              => 'string',
				'sanitize_callback' => [ self::class, 'sanitizeAccessToken' ],
				'default'           => '',
			]
		);

		add_settings_section(
			'osif_token_section',
			__( 'Access token', 'osif' ),
			'__return_false',
			self::PAGE
		);

		add_settings_field(
			'osif_access_token',
			__( 'Instagram API token', 'osif' ),
			[ self::class, 'renderAccessTokenField' ],
			self::PAGE,
			'osif_token_section'
		);
	}

	public static function sanitizeAccessToken( mixed $value ): string
	{
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			return (string) get_option( 'osif_access_token', '' );
		}

		return sanitize_text_field( $value );
	}

	public static function renderAccessTokenField(): void
	{
		$has_token = (bool) get_option( 'osif_access_token', '' );

		printf(
			'<input type="password" id="osif_access_token" name="osif_access_token" value="" autocomplete="new-password" placeholder="%s" class="regular-text" />',
			$has_token ? esc_attr( str_repeat( '•', 32 ) ) : ''
		);

		echo '<p class="description">';
		echo $has_token
			? esc_html__( 'A token is saved. Leave this blank to keep it.', 'osif' )
			: esc_html__( 'Paste the long-lived Instagram API token.', 'osif' );
		echo '</p>';
	}

	// ---------------------------------------------------------------- the page

	public static function renderPage(): void
	{
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the Instagram feed.', 'osif' ) );
		}

		self::printStyles();

		echo '<div class="wrap osif-admin">';
		echo '<h1>' . esc_html__( 'Instagram Feed', 'osif' ) . '</h1>';

		self::renderNotice();
		self::renderPhotos();

		if ( current_user_can( 'manage_options' ) ) {
			self::renderTokenForm();
		}

		echo '</div>';
	}

	private static function renderPhotos(): void
	{
		$photos    = Photos::all();
		$hidden    = Photos::hiddenCount();
		$canHide   = Photos::canHideMore();
		$showCount = Syncer::DISPLAY_COUNT;

		echo '<h2>' . esc_html__( 'Photos', 'osif' ) . '</h2>';

		echo '<p>' . esc_html( sprintf(
			/* translators: 1: photos shown on the site, 2: how many may be hidden. */
			__( 'The site shows the %1$d newest photos. Hide one and the next in reserve takes its place. Up to %2$d can be hidden at a time; a hidden photo stays hidden through every sync until you show it again.', 'osif' ),
			$showCount,
			Photos::HIDE_LIMIT
		) ) . '</p>';

		self::renderSyncForm();

		if ( [] === $photos ) {
			echo '<p>' . esc_html__( 'No photos are cached yet. Check Instagram now, or wait for the next scheduled sync.', 'osif' ) . '</p>';
			return;
		}

		printf(
			'<p class="osif-admin__count">%s</p>',
			esc_html( sprintf(
				/* translators: 1: number of cached photos, 2: number hidden, 3: hide limit. */
				__( '%1$d photos cached, %2$d of %3$d hidden.', 'osif' ),
				count( $photos ),
				$hidden,
				Photos::HIDE_LIMIT
			) )
		);

		echo '<table class="widefat striped osif-admin__table">';
		echo '<thead><tr>';
		echo '<th scope="col">' . esc_html__( 'Photo', 'osif' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Caption', 'osif' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Status', 'osif' ) . '</th>';
		echo '<th scope="col">' . esc_html__( 'Action', 'osif' ) . '</th>';
		echo '</tr></thead><tbody>';

		$position = 0;

		foreach ( $photos as $photo ) {
			$isHidden    = (bool) $photo->disabled;
			$hasImage    = ! empty( $photo->local_filename );
			$isShowing   = ! $isHidden && $hasImage && $position < $showCount;

			if ( ! $isHidden && $hasImage ) {
				$position++;
			}

			self::renderPhotoRow( $photo, $isHidden, $hasImage, $isShowing, $canHide );
		}

		echo '</tbody></table>';
	}

	private static function renderPhotoRow( object $photo, bool $isHidden, bool $hasImage, bool $isShowing, bool $canHide ): void
	{
		$status = match ( true ) {
			! $hasImage => __( 'Image not downloaded', 'osif' ),
			$isHidden   => __( 'Hidden', 'osif' ),
			$isShowing  => __( 'On the site', 'osif' ),
			default     => __( 'In reserve', 'osif' ),
		};

		$statusClass = match ( true ) {
			! $hasImage => 'missing',
			$isHidden   => 'hidden',
			$isShowing  => 'showing',
			default     => 'reserve',
		};

		echo '<tr class="osif-admin__row osif-admin__row--' . esc_attr( $statusClass ) . '">';

		echo '<td class="osif-admin__thumb">';
		if ( $hasImage ) {
			printf(
				'<img src="%s" alt="" width="96" height="96" loading="lazy" />',
				esc_url( ImageCache::url( $photo->local_filename ) )
			);
		}
		echo '</td>';

		echo '<td class="osif-admin__caption">';
		echo esc_html( wp_trim_words( $photo->caption ?? '', 20 ) );
		printf(
			'<br /><a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
			esc_url( $photo->permalink ),
			esc_html__( 'Open on Instagram', 'osif' )
		);
		echo '</td>';

		echo '<td><span class="osif-admin__status osif-admin__status--' . esc_attr( $statusClass ) . '">' . esc_html( $status ) . '</span></td>';

		echo '<td>';
		if ( $hasImage ) {
			self::renderToggleForm( (string) $photo->media_id, $isHidden, $canHide );
		}
		echo '</td>';

		echo '</tr>';
	}

	private static function renderToggleForm( string $mediaId, bool $isHidden, bool $canHide ): void
	{
		$disabled = ! $isHidden && ! $canHide;

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::TOGGLE_ACTION ) . '" />';
		echo '<input type="hidden" name="media_id" value="' . esc_attr( $mediaId ) . '" />';
		echo '<input type="hidden" name="do" value="' . ( $isHidden ? 'show' : 'hide' ) . '" />';
		wp_nonce_field( self::TOGGLE_ACTION );

		printf(
			'<button type="submit" class="button%s"%s%s>%s</button>',
			$isHidden ? ' button-primary' : '',
			$disabled ? ' disabled' : '',
			$disabled ? ' title="' . esc_attr( sprintf(
				/* translators: %d: hide limit. */
				__( 'Up to %d photos can be hidden at a time. Show one before hiding another.', 'osif' ),
				Photos::HIDE_LIMIT
			) ) . '"' : '',
			$isHidden ? esc_html__( 'Show', 'osif' ) : esc_html__( 'Hide', 'osif' )
		);

		echo '</form>';
	}

	private static function renderSyncForm(): void
	{
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="osif-admin__sync">';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::SYNC_ACTION ) . '" />';
		wp_nonce_field( self::SYNC_ACTION );
		submit_button( __( 'Check Instagram now', 'osif' ), 'secondary', 'submit', false );
		echo ' <span class="description">' . esc_html__( 'Otherwise new posts are picked up twice a day.', 'osif' ) . '</span>';
		echo '</form>';
	}

	private static function renderTokenForm(): void
	{
		echo '<form method="post" action="options.php">';
		settings_fields( self::OPTION_GROUP );
		do_settings_sections( self::PAGE );
		submit_button( __( 'Save token', 'osif' ) );
		echo '</form>';
	}

	private static function printStyles(): void
	{
		echo '<style>
			.osif-admin__table { max-width: 960px; margin-top: 1em; }
			.osif-admin__thumb { width: 96px; }
			.osif-admin__thumb img { display: block; width: 96px; height: 96px; object-fit: cover; border-radius: 2px; }
			.osif-admin__row--hidden .osif-admin__thumb img { opacity: .4; }
			.osif-admin__status { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 12px; background: #f0f0f1; }
			.osif-admin__status--showing { background: #d5e8d4; }
			.osif-admin__status--hidden { background: #f8d7da; }
			.osif-admin__sync { margin: 1em 0; }
			.osif-admin__count { margin-bottom: 0; }
		</style>';
	}

	// ------------------------------------------------------------- the handlers

	public static function handleToggle(): void
	{
		self::requirePermission( self::TOGGLE_ACTION );

		$mediaId = isset( $_POST['media_id'] ) ? (int) $_POST['media_id'] : 0;
		$hide    = isset( $_POST['do'] ) && 'hide' === $_POST['do'];

		$result = $hide ? Photos::hide( $mediaId ) : Photos::show( $mediaId );

		self::finish( $result );
	}

	public static function handleSync(): void
	{
		self::requirePermission( self::SYNC_ACTION );

		$result = Syncer::sync();

		if ( $result['success'] ) {
			$result['message'] = sprintf(
				/* translators: %d: number of photos found on Instagram. */
				__( 'Instagram checked: %d photos in the cache.', 'osif' ),
				$result['synced']
			);
		}

		self::finish( $result );
	}

	private static function requirePermission( string $action ): void
	{
		if ( ! current_user_can( self::capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the Instagram feed.', 'osif' ) );
		}

		check_admin_referer( $action );
	}

	/** @param array{success:bool, message:string} $result */
	private static function finish( array $result ): void
	{
		set_transient(
			self::noticeKey(),
			[ 'type' => $result['success'] ? 'success' : 'error', 'text' => $result['message'] ],
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect( add_query_arg( 'page', self::PAGE, admin_url( 'options-general.php' ) ) );
		exit;
	}

	private static function renderNotice(): void
	{
		$notice = get_transient( self::noticeKey() );

		if ( ! is_array( $notice ) ) {
			return;
		}

		delete_transient( self::noticeKey() );

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			'error' === $notice['type'] ? 'error' : 'success',
			esc_html( $notice['text'] )
		);
	}

	private static function noticeKey(): string
	{
		return 'osif_notice_' . get_current_user_id();
	}
}

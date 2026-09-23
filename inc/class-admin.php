<?php
/**
 * Creates two admin elements: a field to hold the instagram token
 * and a table to hold the instagram media with disabled editing
 */

namespace osif;

defined( 'ABSPATH' ) || exit;

class Admin {

	public function __construct() {
		$this->add_access_token();
		$this->add_media_table();
	}

	public static function boot(): void {
		new self();
	}

	private function add_access_token(): void {
		register_setting(
			'media',
			'osif_access_token',
			[
				'type'              => 'string',
				'sanitize_callback' => [ $this, 'sanitize_access_token' ],
				'default'           => '',
			]
		);

		add_settings_section(
			'osif_settings_section',
			__( 'Instagram Feed', 'osif' ),
			'__return_false', // no section description needed
			'media'
		);

		add_settings_field(
			'osif_access_token',
			__( 'Instagram API Token', 'osif' ),
			[ $this, 'render_access_token_field' ],
			'media',
			'osif_settings_section'
		);
	}

	public function sanitize_access_token( mixed $value ): string {
		$value = is_string( $value ) ? trim( $value ) : '';

		if ( '' === $value ) {
			return (string) get_option( 'osif_access_token', '' );
		}

		return sanitize_text_field( $value );
	}

	public function render_access_token_field(): void {
		$has_token = (bool) get_option( 'osif_access_token', '' );

		printf(
			'<input type="password" id="osif_access_token" name="osif_access_token" value="" autocomplete="new-password" placeholder="%s" />',
			$has_token ? esc_attr( str_repeat( '•', 32 ) ) : ''
		);

		if ( $has_token ) {
			echo '<p class="description">An access token is currently saved. Leave this field blank to keep it unchanged.</p>';
		} else {
			echo '<p class="description">Enter your Instagram API token.</p>';
		}
	}

	private function add_media_table() {
		//
	}

}
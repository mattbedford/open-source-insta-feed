<?php
/**
 * Client for calling Instagram
 */

namespace osif;

defined( 'ABSPATH' ) || exit;

class Client {

	private const string BASE_URL = 'https://graph.instagram.com';
	private const string MEDIA_ENDPOINT = '/me/media';
	private const string REFRESH_TOKEN_ENDPOINT = '/refresh_access_token';
	private const string GRANT_TYPE = 'ig_refresh_token';


	public function __construct(
		private readonly string $access_token
	)
	{}


	/**
	 * builds the /me/media URL with fields and limit as query params, calls the shared request helper,
	 * returns either the decoded data array from Instagram's response, or a WP_Error if anything went wrong
	 */
	public function fetchMedia(int $limit, array $fields): array|\WP_Error
	{
		$url = $this->buildUrl(
			self::MEDIA_ENDPOINT,
			[
				'fields'       => implode( ',', $fields ),
				'limit'        => $limit,
				'access_token' => $this->access_token,
			]
		);

		return $this->request( $url );

	}


	/**
	 * refreshToken(): array|\WP_Error — builds the /access_token URL with grant_type=ig_refresh_token
	 *
	 * @return array|\WP_Error
	 */
	public function refreshToken(): array|\WP_Error
	{
		$url = $this->buildUrl(
			self::REFRESH_TOKEN_ENDPOINT,
			[
				'grant_type'   => self::GRANT_TYPE,
				'access_token' => $this->access_token,
			]
		);

		return $this->request( $url );

	}


	/**
	 * Makes a request to the Instagram API.
	 * @param string $url The URL to request.
	 * @return array|\WP_Error
	 */
	private function request(string $url): array|\WP_Error {

		$response = wp_remote_get( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new \WP_Error( 'invalid_json', json_last_error_msg() );
		}

		if ( isset( $data['error'] ) ) {
			$message = $data['error']['message'] ?? 'Unknown Instagram API error';
			return new \WP_Error( 'instagram_error', $message, $data['error'] );
		}

		return $data;

	}


	private function buildUrl(string $endpoint, array $params): string
	{
		$url = self::BASE_URL . $endpoint;
		$query = http_build_query( $params );
		return $url . '?' . $query;
	}
}


<?php

namespace Hewo\WpAuth;

use Exception;
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Issuer\IssuerBuilder;
use Facile\OpenIDClient\Service\AuthorizationService;
use Facile\OpenIDClient\Service\Builder\AuthorizationServiceBuilder;
use Facile\OpenIDClient\Service\Builder\UserInfoServiceBuilder;
use Http\Discovery\Psr17Factory;
use Throwable;
use WP_REST_Request;
use WP_User;

final class OidcWrapper {

	private const META_KEY_SUB = 'hewo-auth-sub';

	private static $instance = null;

	private AuthorizationService $authorizationService;
	private ClientInterface $client;

	private bool $useSsl = true;

	public static function instance(): OidcWrapper {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		if ( ! defined( 'HEWO_WP_AUTH_ISSUER' ) )
			throw new Exception( 'Missing config: HEWO_WP_AUTH_ISSUER' );
		if ( ! defined( 'HEWO_WP_AUTH_CLIENT_ID' ) )
			throw new Exception( 'Missing config: HEWO_WP_AUTH_CLIENT_ID' );
		if ( ! defined( 'HEWO_WP_AUTH_CLIENT_SECRET' ) )
			throw new Exception( 'Missing config: HEWO_WP_AUTH_CLIENT_SECRET' );

		if ( defined( 'HEWO_WP_AUTH_USE_SSL' ) )
			$this->useSsl = HEWO_WP_AUTH_USE_SSL;

		$openidConfigurationUrl = HEWO_WP_AUTH_ISSUER . '/.well-known/openid-configuration';

		log( 'Fetch issuer details: ' . $openidConfigurationUrl );

		// TODO: cache?
		$issuer = ( new IssuerBuilder() )
			->build( $openidConfigurationUrl );

		log( 'Client ID: ' . HEWO_WP_AUTH_CLIENT_ID );

		$clientMetadata = ClientMetadata::fromArray( [
			'client_id' => HEWO_WP_AUTH_CLIENT_ID,
			'client_secret' => HEWO_WP_AUTH_CLIENT_SECRET,

			// TODO
			'token_endpoint_auth_method' => 'client_secret_basic',
			'redirect_uris' => [
				rest_url( Endpoint::instance()->getAuthCallbackPath() ),
			],
		] );

		$this->client = ( new ClientBuilder() )
			->setIssuer( $issuer )
			->setClientMetadata( $clientMetadata )
			->build();

		$this->authorizationService = ( new AuthorizationServiceBuilder() )
			->build();
	}

	public function getAuthorizationUrl(): string {
		return $this->authorizationService
			->getAuthorizationUri(
				$this->client,
			);
	}

	public function handleCallback( WP_REST_Request $request ) {
		log( 'Handle auth callback' );

		try {
			$serverRequest = ( new Psr17Factory() )
				->createServerRequest(
					$request->get_method(),
					rest_url( add_query_arg( $request->get_query_params() ) ),
					$_SERVER,
				);

			$callbackParams = $this->authorizationService
				->getCallbackParams( $serverRequest, $this->client );

			$tokenSet = $this->authorizationService
				->callback( $this->client, $callbackParams );

			$userInfo = ( new UserInfoServiceBuilder() )
				->build()
				->getUserInfo( $this->client, $tokenSet );

			log( $userInfo );

			$user = $this->getUser( $userInfo );

			wp_set_current_user( $user->ID, $user->user_login );
			wp_set_auth_cookie( $user->ID );
			do_action( 'wp_login', $user->user_login, $user );

			wp_safe_redirect( admin_url() );
			exit;

		} catch (Throwable $t) {
			log( 'Failed to handle auth callback: ' . $t->getMessage() );

			$home_url = home_url();
			$login_url = wp_login_url();
			wp_die(
				message: <<<HTML
					<h2>Auth Error</h2>
					<p>{$t->getMessage()}</p>
					<a href='$login_url'>Try again</a>
					or
					<a href='$home_url'>go back to home page</a>
				HTML,
				title: "Auth Error",
			);
		}
	}

	private function getUser( array $userInfo ): WP_User {
		if ( $userId = $this->findExistingUser( $userInfo ) ) {
			log( 'User exists with id: ' . $userId );

			$this->updateUser( $userId, $userInfo );
		} else {
			$userId = $this->createUser( $userInfo );
		}
		return get_user_by( 'ID', $userId );
	}

	private function findExistingUser( array $userInfo ): bool|int {
		$users = get_users( [
			'meta_key' => self::META_KEY_SUB,
			'meta_value' => $userInfo['sub'],
			'number' => 1,
			'count_total' => false,
			'fields' => 'ID',
		] );

		if ( ! empty( $users ) )
			return $users[0];

		$user = get_user_by( 'email', $userInfo['email'] );

		if ( $user !== false )
			return $user->ID;

		return false;
	}

	private function createUser( array $userInfo ): int {
		$userId = @wp_insert_user( $this->parseUserInfo( $userInfo ) );

		if ( is_wp_error( $userId ) ) {
			log( 'Failed to create new user' );
			log( $userId->get_error_messages() );

			$home_url = home_url();
			$login_url = wp_login_url();
			wp_die(
				message: <<<HTML
					<h2>Failed to create new user</h2>
					<p>Failed to create a new user. Check the server logs for more information.</p>
					<a href='$login_url'>Try again</a>
					or
					<a href='$home_url'>go back to home page</a>
				HTML,
				title: "Failed to create new user",
			);
		}

		log( 'Created new user with id: ' . $userId );

		return $userId;
	}

	private function updateUser( int $userId, array $userInfo ) {
		wp_update_user( [
			...$this->parseUserInfo( $userInfo ),
			'ID' => $userId,
		] );

		log( 'Updated details for user with id: ' . $userId );
	}

	private function parseUserInfo( array $userInfo ): array {
		return [
			'user_login' => $userInfo['preferred_username'],
			'user_nicename' => $userInfo['preferred_username'],
			'user_email' => $userInfo['email'],
			'display_name' => $userInfo['name'],
			'first_name' => $userInfo['given_name'],
			'last_name' => $userInfo['family_name'],
			'use_ssl' => $this->useSsl,
			'role' => $this->parseRole( $userInfo ),
			'locale' => $userInfo['locale'],
			'meta_input' => [
				self::META_KEY_SUB => $userInfo['sub'],
			],
		];
	}

	private function parseRole( array $userInfo ): string {
		// TODO
		return 'subscriber';
	}
}

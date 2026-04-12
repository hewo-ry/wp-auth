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
use Facile\OpenIDClient\Token\IdTokenVerifierBuilder;
use Http\Discovery\Psr17Factory;
use Throwable;
use WP_REST_Request;

final class OidcWrapper {

	private static $instance = null;

	private AuthorizationService $authorizationService;
	private ClientInterface $client;

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
}

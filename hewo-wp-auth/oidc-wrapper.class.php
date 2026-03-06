<?php

namespace Hewo\WpAuth;

use Exception;
use Facile\OpenIDClient\Client\ClientBuilder;
use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
use Facile\OpenIDClient\Issuer\IssuerBuilder;
use Facile\OpenIDClient\Service\Builder\AuthorizationServiceBuilder;

final class OidcWrapper {

	private static $instance = null;

	private ClientInterface $client;

	public static function instance() {
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
				'https://my-rp.com/callback',
			],
		] );

		$this->client = ( new ClientBuilder() )
			->setIssuer( $issuer )
			->setClientMetadata( $clientMetadata )
			->build();
	}

	public function getAuthorizationUrl(): string {
		return ( new AuthorizationServiceBuilder() )
			->build()
			->getAuthorizationUri(
				$this->client,
			);
		;
	}
}

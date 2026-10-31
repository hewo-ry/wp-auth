<?php

namespace Hewo\WpAuth\Tests;

use Facile\OpenIDClient\Client\ClientInterface;
use Facile\OpenIDClient\Client\Metadata\ClientMetadataInterface;
use Facile\OpenIDClient\Issuer\IssuerInterface;
use Facile\OpenIDClient\Issuer\Metadata\IssuerMetadataInterface;
use Facile\OpenIDClient\Service\AuthorizationService;
use Hewo\WpAuth\Endpoint;
use Hewo\WpAuth\OidcWrapper;
use Hewo\WpAuth\WordPressAuthSession;
use ReflectionClass;
use WP_UnitTestCase;

class OidcRegressionTest extends WP_UnitTestCase {

    private string $sessionCookie;

    protected function setUp(): void {
        parent::setUp();

        $this->sessionCookie = wp_generate_password( 32, false );
        $_COOKIE['hewo_wp_auth_session_id'] = $this->sessionCookie;
    }

    protected function tearDown(): void {
        delete_transient( 'hewo_wp_auth_session_' . $this->sessionCookie );
        unset( $_COOKIE['hewo_wp_auth_session_id'] );

        parent::tearDown();
    }

    public function test_authorization_url_includes_oidc_parameters_and_persists_session_values(): void {
        $wrapper = $this->createWrapper();

        $authorizationUrl = $wrapper->getAuthorizationUrl();
        $query = array();
        parse_str( (string) wp_parse_url( $authorizationUrl, PHP_URL_QUERY ), $query );

        $this->assertSame( 'https://identity.example/authorize', wp_parse_url( $authorizationUrl, PHP_URL_SCHEME ) . '://' . wp_parse_url( $authorizationUrl, PHP_URL_HOST ) . wp_parse_url( $authorizationUrl, PHP_URL_PATH ) );
        $this->assertSame( 'wp-client', $query['client_id'] );
        $this->assertSame( 'openid', $query['scope'] );
        $this->assertSame( 'code', $query['response_type'] );
        $this->assertSame( rest_url( 'hewo-auth/v0/auth/callback' ), $query['redirect_uri'] );
        $this->assertSame( 'example', $query['tenant'] );
        $this->assertSame( 32, strlen( $query['state'] ) );
        $this->assertSame( 32, strlen( $query['nonce'] ) );

        $session = $this->getAuthSession( $wrapper );
        $this->assertSame( $query['state'], $session->getState() );
        $this->assertSame( $query['nonce'], $session->getNonce() );
    }

    public function test_logout_url_uses_id_token_hint_and_expected_redirect(): void {
        $wrapper = $this->createWrapper();
        $userId = self::factory()->user->create();
        update_user_meta( $userId, 'oidc_id_token', 'stored-id-token' );

        $logoutUrl = $wrapper->getLogoutUrl( $userId );
        $query = array();
        parse_str( (string) wp_parse_url( $logoutUrl, PHP_URL_QUERY ), $query );

        $this->assertSame( 'https://identity.example/logout', wp_parse_url( $logoutUrl, PHP_URL_SCHEME ) . '://' . wp_parse_url( $logoutUrl, PHP_URL_HOST ) . wp_parse_url( $logoutUrl, PHP_URL_PATH ) );
        $this->assertSame( home_url( '/' ), $query['post_logout_redirect_uri'] );
        $this->assertSame( 'stored-id-token', $query['id_token_hint'] );
        $this->assertSame( 'example', $query['tenant'] );
    }

    public function test_allowed_hosts_include_oidc_logout_host(): void {
        $wrapper = $this->createWrapper();

        $this->assertSame( array( 'identity.example' ), $wrapper->getAllowedHosts() );
    }

    public function test_user_is_created_with_oidc_profile_and_first_valid_wordpress_role(): void {
        $wrapper = $this->createWrapper();
        $user = $this->invokePrivate( $wrapper, 'getUser', $this->userInfo( array(
            'resource_access' => array(
                'wp-client' => array(
                    'roles' => array( 'not-a-wordpress-role', 'editor' ),
                ),
            ),
        ) ) );

        $this->assertInstanceOf( \WP_User::class, $user );
        $this->assertSame( 'oidc-user', $user->user_login );
        $this->assertSame( 'oidc@example.test', $user->user_email );
        $this->assertSame( 'OIDC User', $user->display_name );
        $this->assertSame( 'Olivia', $user->first_name );
        $this->assertSame( 'Identity', $user->last_name );
        $this->assertSame( 'editor', $user->roles[0] );
        $this->assertSame( 'subject-123', get_user_meta( $user->ID, 'hewo-auth-sub', true ) );
    }

    public function test_existing_user_is_updated_by_oidc_subject(): void {
        $wrapper = $this->createWrapper();
        $userId = self::factory()->user->create( array(
            'user_login' => 'existing-account',
            'user_email' => 'old@example.test',
        ) );
        update_user_meta( $userId, 'hewo-auth-sub', 'subject-123' );

        $user = $this->invokePrivate( $wrapper, 'getUser', $this->userInfo( array(
            'email' => 'updated@example.test',
            'name' => 'Updated Name',
        ) ) );

        $this->assertSame( $userId, $user->ID );
        $this->assertSame( 'updated@example.test', $user->user_email );
        $this->assertSame( 'Updated Name', $user->display_name );
        $this->assertCount( 1, get_users( array( 'meta_key' => 'hewo-auth-sub', 'meta_value' => 'subject-123' ) ) );
    }

    public function test_existing_user_is_matched_by_email_when_subject_is_new(): void {
        $wrapper = $this->createWrapper();
        $userId = self::factory()->user->create( array(
            'user_login' => 'email-account',
            'user_email' => 'oidc@example.test',
        ) );

        $user = $this->invokePrivate( $wrapper, 'getUser', $this->userInfo( array(
            'sub' => 'new-subject',
        ) ) );

        $this->assertSame( $userId, $user->ID );
        $this->assertSame( 'new-subject', get_user_meta( $userId, 'hewo-auth-sub', true ) );
    }

    public function test_unknown_oidc_roles_fall_back_to_subscriber(): void {
        $wrapper = $this->createWrapper();
        $user = $this->invokePrivate( $wrapper, 'getUser', $this->userInfo( array(
            'resource_access' => array(
                'wp-client' => array(
                    'roles' => array( 'not-a-wordpress-role' ),
                ),
            ),
        ) ) );

        $this->assertSame( 'subscriber', $user->roles[0] );
    }

    public function test_auth_callback_route_is_registered_as_public_get_endpoint(): void {
        // Endpoint::init() resolves the OidcWrapper singleton, which would otherwise need real config and network.
        $instanceProperty = ( new ReflectionClass( OidcWrapper::class ) )->getProperty( 'instance' );
        $originalInstance = $instanceProperty->getValue();
        $instanceProperty->setValue( null, $this->createWrapper() );

        add_action( 'rest_api_init', array( Endpoint::instance(), 'init' ) );

        try {
            $routes = rest_get_server()->get_routes();
        } finally {
            $instanceProperty->setValue( null, $originalInstance );
        }

        $this->assertArrayHasKey( '/hewo-auth/v0/auth/callback', $routes );
        $route = $routes['/hewo-auth/v0/auth/callback'];

        $this->assertArrayHasKey( 'GET', $route[0]['methods'] );
        $this->assertSame( '__return_true', $route[0]['permission_callback'] );
        $this->assertSame( 'hewo-auth/v0/auth/callback', Endpoint::instance()->getAuthCallbackPath() );
    }

    public function test_auth_session_round_trips_oidc_state_and_clears_transient(): void {
        $session = new WordPressAuthSession();
        $session->setState( 'state-value' );
        $session->setNonce( 'nonce-value' );
        $session->setCodeVerifier( 'verifier-value' );
        $session->setCustoms( array( 'prompt' => 'login' ) );

        $reloadedSession = new WordPressAuthSession();
        $this->assertSame( 'state-value', $reloadedSession->getState() );
        $this->assertSame( 'nonce-value', $reloadedSession->getNonce() );
        $this->assertSame( 'verifier-value', $reloadedSession->getCodeVerifier() );
        $this->assertSame( array( 'prompt' => 'login' ), $reloadedSession->getCustoms() );

        $reloadedSession->clear();

        $this->assertFalse( get_transient( 'hewo_wp_auth_session_' . $this->sessionCookie ) );
        $this->expectException( \Exception::class );
        $reloadedSession->getState();
    }

    private function createWrapper(): OidcWrapper {
        $issuerMetadata = $this->createMock( IssuerMetadataInterface::class );
        $issuerMetadata->method( 'getAuthorizationEndpoint' )
            ->willReturn( 'https://identity.example/authorize?tenant=example' );
        $issuerMetadata->method( 'get' )
            ->willReturnMap( array(
                array( 'end_session_endpoint', 'https://identity.example/logout?tenant=example' ),
            ) );

        $issuer = $this->createMock( IssuerInterface::class );
        $issuer->method( 'getMetadata' )->willReturn( $issuerMetadata );

        $clientMetadata = $this->createMock( ClientMetadataInterface::class );
        $clientMetadata->method( 'getClientId' )->willReturn( 'wp-client' );
        $clientMetadata->method( 'getRedirectUris' )
            ->willReturn( array( rest_url( 'hewo-auth/v0/auth/callback' ) ) );
        $clientMetadata->method( 'getResponseTypes' )->willReturn( array( 'code' ) );

        $client = $this->createMock( ClientInterface::class );
        $client->method( 'getIssuer' )->willReturn( $issuer );
        $client->method( 'getMetadata' )->willReturn( $clientMetadata );

        $wrapper = ( new ReflectionClass( OidcWrapper::class ) )->newInstanceWithoutConstructor();
        $this->setPrivateProperty( $wrapper, 'client', $client );
        $this->setPrivateProperty(
            $wrapper,
            'authorizationService',
            ( new ReflectionClass( AuthorizationService::class ) )->newInstanceWithoutConstructor()
        );
        $this->setPrivateProperty( $wrapper, 'authSession', new WordPressAuthSession() );

        return $wrapper;
    }

    private function userInfo( array $overrides = array() ): array {
        return array_merge( array(
            'sub' => 'subject-123',
            'preferred_username' => 'oidc-user',
            'email' => 'oidc@example.test',
            'name' => 'OIDC User',
            'given_name' => 'Olivia',
            'family_name' => 'Identity',
            'locale' => 'en_US',
            'resource_access' => array(),
        ), $overrides );
    }

    private function invokePrivate( object $object, string $methodName, array $argument ): mixed {
        $method = ( new ReflectionClass( $object ) )->getMethod( $methodName );

        return $method->invoke( $object, $argument );
    }

    private function setPrivateProperty( object $object, string $propertyName, mixed $value ): void {
        $property = ( new ReflectionClass( $object ) )->getProperty( $propertyName );
        $property->setValue( $object, $value );
    }

    private function getAuthSession( OidcWrapper $wrapper ): WordPressAuthSession {
        $property = ( new ReflectionClass( $wrapper ) )->getProperty( 'authSession' );

        return $property->getValue( $wrapper );
    }
}

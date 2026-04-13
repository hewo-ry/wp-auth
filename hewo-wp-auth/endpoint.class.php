<?php

namespace Hewo\WpAuth;

use WP_REST_Request;

final class Endpoint {

	private const NAMESPACE = 'hewo-auth/v0';

	private const ROUTE_AUTH_CALLBACK = 'auth/callback';

	private static $instance = null;

	public static function instance(): Endpoint {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function init() {
		register_rest_route( self::NAMESPACE , self::ROUTE_AUTH_CALLBACK, array(
			'methods' => 'GET',
			'callback' => OidcWrapper::instance()->handleCallback( ... ),
			'permission_callback' => '__return_true',
		) );
	}

	public function getAuthCallbackPath(): string {
		return self::NAMESPACE . '/' . self::ROUTE_AUTH_CALLBACK;
	}
}

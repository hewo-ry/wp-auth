<?php

/**
 *
 * @package Hewo WP Auth
 * @author Ville Nupponen
 * @copyright 2026 Hewo Ry
 * @license MIT
 *
 * @wordpress-plugin
 * Plugin Name:        Hewo WP Auth
 * Description:        Replace WordPress's traditional login with OIDC-based authentication.
 * Version:            0.2.1
 * Requires at least:  7.1.3
 * Requires PHP:       8.5.12
 * Author:             Ville Nupponen
 * License:            MIT
 */

namespace Hewo\WpAuth;

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/endpoint.class.php';
require_once __DIR__ . '/oidc-wrapper.class.php';
require_once __DIR__ . '/utils.php';
require_once __DIR__ . '/wordpress-auth-session.php';

function init_plugin(): void {
    log( 'Init plugin' );

    if ( ! defined( 'HEWO_WP_AUTH_ISSUER' ) ) {
        log( 'HEWO_WP_AUTH_ISSUER is missing' );
        return;
    }
    if ( ! defined( 'HEWO_WP_AUTH_CLIENT_ID' ) ) {
        log( 'HEWO_WP_AUTH_CLIENT_ID is missing' );
        return;
    }
    if ( ! defined( 'HEWO_WP_AUTH_CLIENT_SECRET' ) ) {
        log( 'HEWO_WP_AUTH_CLIENT_SECRET is missing' );
        return;
    }

    add_action( 'admin_menu', 'Hewo\WpAuth\admin_menu' );
    add_action( 'login_url', 'Hewo\WpAuth\login_url' );
    // TODO: override wp-login.php ?

    add_action( 'rest_api_init', Endpoint::instance()->init( ... ) );

    add_filter( 'allowed_redirect_hosts', 'Hewo\WpAuth\allowed_redirect_hosts' );
    add_filter( 'logout_redirect', 'Hewo\WpAuth\logout_url', 10, 3 );
}

add_action( 'init', 'Hewo\WpAuth\init_plugin' );

function admin_menu(): void {
    log( 'Set up admin_menu' );

    remove_menu_page( 'users.php' );
}

function allowed_redirect_hosts( array $hosts ): array {
    return array_merge(
        $hosts,
        OidcWrapper::instance()->getAllowedHosts(),
    );
}

function login_url(): string {
    $login_url = OidcWrapper::instance()->getAuthorizationUrl();

    log( 'Created login url: ' . $login_url );

    return $login_url;
}

function logout_url( $redirect_to, $requested_redirect_to, $user ): string {
    $logout_url = OidcWrapper::instance()->getLogoutUrl( $user->ID );

    log( 'Created logout url:' . $logout_url );

    return $logout_url;
}

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
 * Description:        TODO
 * Version:            0.0.0
 * Requires at least:  6.9.1
 * Requires PHP:       8.3.30
 * Author:             Ville Nupponen
 * License:            MIT
 */

namespace Hewo\WpAuth;

require_once __DIR__ . '/vendor/autoload.php';

require_once __DIR__ . '/oidc-wrapper.class.php';
require_once __DIR__ . '/utils.php';

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
}

add_action( 'init', 'Hewo\WpAuth\init_plugin' );

function admin_menu(): void {
	log( 'Set up admin_menu' );

	remove_menu_page( 'users.php' );
}


function login_url() {
	$login_url = OidcWrapper::instance()->getAuthorizationUrl();

	log( 'Login url: ' . $login_url );

	return $login_url;
}


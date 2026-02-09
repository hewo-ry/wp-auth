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

include_once 'utils.php';

function init_plugin(): void {
	log( 'Init plugin' );
}

add_action( 'init', 'Hewo\WpAuth\init_plugin' );

function action_admin_menu(): void {
	log( 'Set up admin_menu' );

	remove_menu_page( 'users.php' );
}

add_action( 'admin_menu', 'Hewo\WpAuth\action_admin_menu' );

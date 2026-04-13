<?php
// https://developer.wordpress.org/advanced-administration/plugins/mu-plugins/#caveats
// WordPress only looks for PHP files right inside the mu-plugins directory, and (unlike for normal plugins) not for
// files in subdirectories. You may want to create a proxy PHP loader file inside the mu-plugins directory:

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
 * Version:            0.1.0
 * Requires at least:  6.9.1
 * Requires PHP:       8.3.30
 * Author:             Ville Nupponen
 * License:            MIT
 */

require WPMU_PLUGIN_DIR . '/hewo-wp-auth/hewo-wp-auth.php';

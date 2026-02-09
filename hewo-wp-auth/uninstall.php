<?php

namespace Hewo\WpAuth;

include_once 'utils.php';

// if uninstall.php is not called by WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

log( 'Uninstall plugin' );

<?php

namespace Hewo\WPAuth;

if ( ! defined( 'HEWO_WP_AUTH_DEBUG' ) ) {
	define( 'HEWO_WP_AUTH_DEBUG', false );
}

function log( mixed $message ): void {
	if ( HEWO_WP_AUTH_DEBUG === true ) {
		if ( is_array( $message ) || is_object( $message ) ) {
			error_log( '[' . __NAMESPACE__ . '] ' . print_r( $message, true ) );
		} else {
			error_log( '[' . __NAMESPACE__ . '] ' . $message );
		}
	}
}

<?php
defined( 'ABSPATH' ) || exit;

register_activation_hook( defined( 'WOOPQ_LITE' ) ? WOOPQ_LITE : WOOPQ_FILE, 'woopq_activate' );
register_deactivation_hook( defined( 'WOOPQ_LITE' ) ? WOOPQ_LITE : WOOPQ_FILE, 'woopq_deactivate' );
add_action( 'admin_init', 'woopq_check_version' );

function woopq_check_version() {
	if ( ! empty( get_option( 'woopq_version' ) ) && ( get_option( 'woopq_version' ) < WOOPQ_VERSION ) ) {
		wpc_log( 'woopq', 'upgraded' );
		update_option( 'woopq_version', WOOPQ_VERSION, false );
	}
}

function woopq_activate() {
	wpc_log( 'woopq', 'installed' );
	update_option( 'woopq_version', WOOPQ_VERSION, false );
}

function woopq_deactivate() {
	wpc_log( 'woopq', 'deactivated' );
}

if ( ! function_exists( 'wpc_log' ) ) {
	function wpc_log( $prefix, $action ) {
		$logs = get_option( 'wpc_logs', [] );
		$user = wp_get_current_user();

		if ( ! isset( $logs[ $prefix ] ) ) {
			$logs[ $prefix ] = [];
		}

		$logs[ $prefix ][] = [
			'time'   => current_time( 'mysql' ),
			'user'   => $user->display_name . ' (ID: ' . $user->ID . ')',
			'action' => $action
		];

		update_option( 'wpc_logs', $logs, false );
	}
}
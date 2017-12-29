<?php
/**
 * Compatibility with WP Multi Network plugin
 *
 * @package WPNetworkRoles
 * @since 1.0.0
 */

/**
 * Switches the available network roles when the network is switched.
 *
 * @since 1.0.0
 * @access private
 *
 * @param int $new_network_id ID of the network switched to.
 * @param int $old_network_id ID of the network switched from.
 */
function _nr_switched_network( $new_network_id, $old_network_id ) {
	if ( (int) $new_network_id === (int) $old_network_id ) {
		return;
	}

	$nr_current_user = nr_get_user_with_network_roles( wp_get_current_user() );

	wp_network_roles()->for_network( $new_network_id );
	$nr_current_user->for_network( $new_network_id );
}
add_action( 'switch_network', '_nr_switched_network', 10, 2 );

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
	if ( $new_network_id == $old_network_id ) {
		return;
	}

	wp_network_roles()->for_network( $new_network_id );
}
add_action( 'switch_network', '_nr_switched_network', 10, 2 );

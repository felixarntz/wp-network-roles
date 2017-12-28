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
 * @global array $wpnr_users_with_network_roles Internal storage for user objects with network roles.
 *
 * @param int $new_network_id ID of the network switched to.
 * @param int $old_network_id ID of the network switched from.
 */
function _nr_switched_network( $new_network_id, $old_network_id ) {
	global $wpnr_users_with_network_roles;

	if ( (int) $new_network_id === (int) $old_network_id ) {
		return;
	}

	$user_id = get_current_user_id();

	wp_network_roles()->for_network( $new_network_id );

	if ( ! isset( $wpnr_users_with_network_roles[ $user_id ] ) ) {
		$wpnr_users_with_network_roles[ $user_id ] = new WPNR_User_With_Network_Roles( $user_id, $new_network_id );
	} elseif ( $wpnr_users_with_network_roles[ $user_id ]->get_network_id() !== (int) $new_network_id ) {
		$wpnr_users_with_network_roles[ $user_id ]->for_network( $new_network_id );
	}
}
add_action( 'switch_network', '_nr_switched_network', 10, 2 );

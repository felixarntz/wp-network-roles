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

/**
 * Ensures the network administrator roles are set correctly when a new network is created with WPMN.
 *
 * @since 1.0.0
 *
 * @param int $network_id New network ID.
 */
function _nr_set_network_administrators_on_wpmn_add_network( $network_id ) {
	remove_filter( 'pre_site_option_site_admins', '_nr_filter_super_admins', 10 );
	$network_options = array(
		'site_admins' => get_network_option( $network_id, 'site_admins', array() ),
	);
	add_filter( 'pre_site_option_site_admins', '_nr_filter_super_admins', 10, 4 );

	_nr_set_network_administrators_on_new_network( $network_options, $network_id );
}
add_action( 'add_network', '_nr_set_network_administrators_on_wpmn_add_network', 10, 1 );

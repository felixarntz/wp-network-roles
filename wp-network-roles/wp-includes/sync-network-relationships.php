<?php
/**
 * Functions to synchronize the super admins network option with the Administrator network role.
 *
 * @package WPNetworkRoles
 * @since 1.0.0
 */

/**
 * Maybe adds a user to a network when they're added to a site.
 *
 * The user is only added to the site's network if they haven't been granted any capability on that network
 * already through another method.
 *
 * @since 1.0.0
 * @access private
 *
 * @param int    $user_id User ID.
 * @param string $role    New role on the site.
 * @param int    $site_id Site ID.
 */
function _nr_maybe_add_user_to_network( $user_id, $role, $site_id ) {
	if ( ! $site_id ) {
		return;
	}

	$site = get_site( $site_id );
	if ( ! $site ) {
		return;
	}

	$network_id = $site->network_id;

	$nr_user = nr_get_user_with_network_roles( get_userdata( $user_id ) );
	if ( $nr_user->get_network_id() !== $network_id ) {
		$nr_user->for_network( $network_id );
	}

	if ( empty( $nr_user->network_caps ) ) {
		$nr_user->set_network_role( 'member' );
	}
}
add_action( 'add_user_to_blog', '_nr_maybe_add_user_to_network', 10, 3 );

/**
 * Sets the initial network relationship on a new user.
 *
 * The user is added to the network of the current site.
 *
 * @since 1.0.0
 *
 * @param int $user_id User ID.
 */
function _nr_set_initial_network_relationship_on_new_user( $user_id ) {
	$site_id = get_current_blog_id();

	_nr_maybe_add_user_to_network( $user_id, '', $site_id );
}
add_action( 'user_register', '_nr_set_initial_network_relationship_on_new_user', 10, 1 );

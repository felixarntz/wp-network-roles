<?php
/**
 * Misc functions for the network user roles API.
 *
 * @package WordPress
 * @subpackage Users
 * @since 4.8.0
 */

/**
 * Retrieves the global WP_Network_Roles instance and instantiates it if necessary.
 *
 * @since 4.8.0
 *
 * @global WP_Network_Roles $wp_network_roles WP_Network_Roles global instance.
 *
 * @return WP_Network_Roles WP_Network_Roles global instance if not already instantiated.
 */
function wp_network_roles() {
	global $wp_network_roles;

	if ( ! isset( $wp_network_roles ) ) {
		$wp_network_roles = new WP_Network_Roles();
	}
	return $wp_network_roles;
}

/**
 * Retrieve network role object.
 *
 * @since 4.8.0
 *
 * @param string $role Network role name.
 * @return WP_Network_Role|null WP_Network_Role object if found, null if the role does not exist.
 */
function get_network_role( $role ) {
	return wp_network_roles()->get_role( $role );
}

/**
 * Add network role, if it does not exist.
 *
 * @since 4.8.0
 *
 * @param string $role Network role name.
 * @param string $display_name Display name for role.
 * @param array $capabilities List of capabilities, e.g. array( 'edit_posts' => true, 'delete_posts' => false );
 * @return WP_Network_Role|null WP_Network_Role object if role is added, null if already exists.
 */
function add_network_role( $role, $display_name, $capabilities = array() ) {
	if ( empty( $role ) ) {
		return;
	}
	return wp_network_roles()->add_role( $role, $display_name, $capabilities );
}

/**
 * Remove network role, if it exists.
 *
 * @since 4.8.0
 *
 * @param string $role Network role name.
 */
function remove_network_role( $role ) {
	wp_network_roles()->remove_role( $role );
}

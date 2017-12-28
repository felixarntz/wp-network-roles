<?php
/**
 * Core User Role & Capabilities API
 *
 * @package WPNetworkRoles
 * @since 1.0.0
 */

if ( ! function_exists( 'wp_network_roles' ) ) :

	/**
	 * Retrieves the global WP_Network_Roles instance and instantiates it if necessary.
	 *
	 * @since 1.0.0
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

endif;

if ( ! function_exists( 'get_network_role' ) ) :

	/**
	 * Retrieves a network role object.
	 *
	 * @since 1.0.0
	 *
	 * @param string $role Network role name.
	 * @return WP_Network_Role|null WP_Network_Role object if found, null if the role does not exist.
	 */
	function get_network_role( $role ) {
		return wp_network_roles()->get_role( $role );
	}

endif;

if ( ! function_exists( 'add_network_role' ) ) :

	/**
	 * Adds a network role, if it does not exist.
	 *
	 * @since 1.0.0
	 *
	 * @param string $role         Network role name.
	 * @param string $display_name Display name for role.
	 * @param array  $capabilities List of capabilities, e.g. array( 'edit_posts' => true, 'delete_posts' => false ).
	 * @return WP_Network_Role|null WP_Network_Role object if role is added, null if already exists.
	 */
	function add_network_role( $role, $display_name, $capabilities = array() ) {
		if ( empty( $role ) ) {
			return;
		}

		return wp_network_roles()->add_role( $role, $display_name, $capabilities );
	}

endif;

if ( ! function_exists( 'remove_network_role' ) ) :

	/**
	 * Removes a network role, if it exists.
	 *
	 * @since 1.0.0
	 *
	 * @param string $role Network role name.
	 */
	function remove_network_role( $role ) {
		wp_network_roles()->remove_role( $role );
	}

endif;

/**
 * Sets up the network roles global and populates roles if necessary.
 *
 * @since 1.0.0
 * @access private
 */
function _nr_setup_wp_network_roles() {
	$GLOBALS['wp_network_roles'] = new WP_Network_Roles();

	_nr_maybe_populate_roles();
}
add_action( 'setup_theme', '_nr_setup_wp_network_roles', 1 );

/**
 * Populates the available network roles if necessary.
 *
 * @since 1.0.0
 * @access private
 */
function _nr_maybe_populate_roles() {
	if ( get_network_role( 'administrator' ) ) {
		return;
	}

	$site_administrator = get_role( 'administrator' );

	$network_administrator_capabilities = array_fill_keys( array(
		'manage_network',
		'manage_sites',
		'manage_network_users',
		'manage_network_themes',
		'manage_network_plugins',
		'manage_network_options',
	), true );

	add_network_role( 'administrator', __( 'Network Administrator' ), $network_administrator_capabilities );
}

/**
 * Ensures user metadata is used to get the super admins.
 *
 * This function should be unhooked, if you consider super admins to actually
 * be super admins, not network administrators. This is not the common
 * understanding though.
 *
 * @since 1.0.0
 * @access private
 *
 * @param mixed  $pre        Value to override.
 * @param string $option     Option name.
 * @param int    $network_id Network ID.
 * @param mixed  $default    Default option value.
 * @return array Array of network administrator user names.
 */
function _nr_filter_super_admins( $pre, $option, $network_id, $default ) {
	$users = get_users( array(
		'blog_id'      => 0,
		'network_id'   => $network_id,
		'network_role' => 'administrator',
	) );

	if ( empty( $users ) ) {
		return $default;
	}

	return wp_list_pluck( $users, 'user_login' );
}
add_filter( 'pre_site_option_site_admins', '_nr_filter_super_admins', 10, 4 );

/**
 * Ensures granting super admin privileges adds the 'administrator' network role to that user.
 *
 * This function should be unhooked, if you consider super admins to actually
 * be super admins, not network administrators. This is not the common
 * understanding though.
 *
 * @since 1.0.0
 * @access private
 *
 * @param int $user_id User ID of the user who was granted super admin privileges.
 */
function _nr_grant_network_administrator( $user_id ) {
	nr_add_network_role_for_user( $user_id, 'administrator' );
}
add_action( 'granted_super_admin', 'wpnr_grant_network_administrator', 10, 1 );

/**
 * Ensures revoking super admin privileges removes the 'administrator' network role from that user.
 *
 * This function should be unhooked, if you consider super admins to actually
 * be super admins, not network administrators. This is not the common
 * understanding though.
 *
 * @since 1.0.0
 * @access private
 *
 * @param int $user_id User ID of the user whose super admin privileges were revoked.
 */
function _nr_revoke_network_administrator( $user_id ) {
	nr_remove_network_role_for_user( $user_id, 'administrator' );
}
add_action( 'revoked_super_admin', 'wpnr_revoke_network_administrator', 10, 1 );

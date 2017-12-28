<?php
/**
 * Functions to synchronize the super admins network option with the Administrator network role.
 *
 * @package WPNetworkRoles
 * @since 1.0.0
 */

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
 * @global array $wpnr_users_with_network_roles Internal storage for user objects with network roles.
 *
 * @param int $user_id User ID of the user who was granted super admin privileges.
 */
function _nr_grant_network_administrator( $user_id ) {
	global $wpnr_users_with_network_roles;

	if ( ! isset( $wpnr_users_with_network_roles[ $user_id ] ) ) {
		$wpnr_users_with_network_roles[ $user_id ] = new WPNR_User_With_Network_Roles( $user_id );
	}

	$wpnr_users_with_network_roles[ $user_id ]->add_network_role( 'administrator' );
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
 * @global array $wpnr_users_with_network_roles Internal storage for user objects with network roles.
 *
 * @param int $user_id User ID of the user whose super admin privileges were revoked.
 */
function _nr_revoke_network_administrator( $user_id ) {
	global $wpnr_users_with_network_roles;

	if ( ! isset( $wpnr_users_with_network_roles[ $user_id ] ) ) {
		$wpnr_users_with_network_roles[ $user_id ] = new WPNR_User_With_Network_Roles( $user_id );
	}

	$wpnr_users_with_network_roles[ $user_id ]->remove_network_role( 'administrator' );
}
add_action( 'revoked_super_admin', 'wpnr_revoke_network_administrator', 10, 1 );

/**
 * Ensures granting super admin users on a new network automatically receive the 'administrator' network role.
 *
 * @since 1.0.0
 * @access private
 *
 * @global array $wpnr_users_with_network_roles Internal storage for user objects with network roles.
 *
 * @param array $network_options All network options for the new network.
 * @param int   $network_id      ID of the new network.
 * @return array Unmodified network options.
 */
function _nr_set_network_administrators_on_new_network( $network_options, $network_id ) {
	global $wpnr_users_with_network_roles;

	if ( ! empty( $network_options['site_admins'] ) ) {
		$network_id = (int) $network_id;

		$users = get_users( array(
			'blog_id'   => 0,
			'login__in' => $network_options['site_admins'],
		) );

		foreach ( $users as $user ) {
			if ( ! isset( $wpnr_users_with_network_roles[ $user->ID ] ) ) {
				$wpnr_users_with_network_roles[ $user->ID ] = new WPNR_User_With_Network_Roles( $user->ID, $network_id );
			} elseif ( $wpnr_users_with_network_roles[ $user->ID ]->get_network_id() !== $network_id ) {
				$wpnr_users_with_network_roles[ $user->ID ]->for_network( $network_id );
			}

			$wpnr_users_with_network_roles[ $user->ID ]->add_network_role( 'administrator' );
		}
	}

	return $network_options;
}
add_filter( 'populate_network_meta', '_nr_set_network_administrators_on_new_network', 10, 2 );

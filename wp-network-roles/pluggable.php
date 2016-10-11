<?php
/**
 * Ugly overridden pluggable Core functions adjusted for the network user roles API.
 *
 * @package WordPress
 * @subpackage Users
 * @since 4.8.0
 */

function wp_set_current_user( $id, $name = '' ) {
	global $current_user;

	// If `$id` matches the user who's already current, there's nothing to do.
	if ( isset( $current_user )
		&& ( $current_user instanceof WP_User )
		&& ( $id == $current_user->ID )
		&& ( null !== $id )
	) {
		if ( ! $current_user instanceof WP_User_With_Network_Roles ) {
			$current_user = new WP_User_With_Network_Roles( $current_user );
		}
		return $current_user;
	}

	$current_user = new WP_User_With_Network_Roles( $id, $name );

	setup_userdata( $current_user->ID );

	/**
	 * Fires after the current user is set.
	 *
	 * @since 2.0.1
	 */
	do_action( 'set_current_user' );

	return $current_user;
}

function get_user_by( $field, $value ) {
	$userdata = WP_User::get_data_by( $field, $value );

	if ( ! $userdata ) {
		return false;
	}

	$user = new WP_User_With_Network_Roles;
	$user->init( $userdata );
	// The following is required to initialize extra network functionality.
	$user->for_network();

	return $user;
}

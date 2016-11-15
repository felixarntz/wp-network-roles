<?php
/**
 * Ugly overridden pluggable Core functions adjusted for the network user roles API.
 *
 * @package WordPress
 * @subpackage Users
 * @since 4.8.0
 */

if ( ! function_exists( 'wp_set_current_user' ) ) :
function wp_set_current_user( $id, $name = '' ) {
	global $current_user;

	$user_class_name = apply_filters( 'wpnr_user_class_name', 'WP_User_With_Network_Roles' );
	if ( ! is_subclass_of( $user_class_name, 'WP_User_With_Network_Roles' ) ) {
		$user_class_name = 'WP_User_With_Network_Roles';
	}

	// If `$id` matches the user who's already current, there's nothing to do.
	if ( isset( $current_user )
		&& ( $current_user instanceof WP_User )
		&& ( $id == $current_user->ID )
		&& ( null !== $id )
	) {
		if ( ! is_a( $current_user, $user_class_name ) ) {
			$current_user = new $user_class_name( $current_user );
		}
		return $current_user;
	}

	$current_user = new $user_class_name( $id, $name );

	setup_userdata( $current_user->ID );

	/**
	 * Fires after the current user is set.
	 *
	 * @since 2.0.1
	 */
	do_action( 'set_current_user' );

	return $current_user;
}
endif;

if ( ! function_exists( 'get_user_by' ) ) :
function get_user_by( $field, $value ) {
	$userdata = WP_User::get_data_by( $field, $value );

	if ( ! $userdata ) {
		return false;
	}

	$user_class_name = apply_filters( 'wpnr_user_class_name', 'WP_User_With_Network_Roles' );
	if ( ! is_subclass_of( $user_class_name, 'WP_User_With_Network_Roles' ) ) {
		$user_class_name = 'WP_User_With_Network_Roles';
	}

	$user = new $user_class_name;
	$user->init( $userdata );
	// The following is required to initialize extra network functionality.
	$user->for_network();

	return $user;
}
endif;

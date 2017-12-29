<?php
/**
 * Sets up the initial network role and migrates existing users to have their respective network roles.
 *
 * @package WPNetworkRoles
 * @since 1.0.0
 */

/**
 * Checks whether the migration of each existing user's network relationships is done.
 *
 * @since 1.0.0
 *
 * @return bool True if migration is done, false otherwise.
 */
function nr_is_user_network_migration_done() {
	return (bool) get_network_option( get_main_network_id(), '_nr_user_network_migration_done' );
}

/**
 * Gets the definition data of initial network roles defined by the plugin.
 *
 * @since 1.0.0
 *
 * @return array Array of associative network role definition arrays.
 */
function _nr_get_initial_network_roles() {
	return array(
		array(
			'role'         => 'administrator',
			'display_name' => __( 'Network Administrator' ),
			'capabilities' => array_fill_keys( array(
				'manage_network',
				'manage_sites',
				'manage_network_users',
				'manage_network_themes',
				'manage_network_plugins',
				'manage_network_options',
			), true ),
		),
	);
}

/**
 * Ensures the initial network roles are populated on a new network.
 *
 * @since 1.0.0
 * @access private
 *
 * @param array $network_options All network options for the new network.
 * @return array Modified network options including the network roles.
 */
function _nr_populate_network_roles_on_new_network( $network_options ) {
	$network_roles = _nr_get_initial_network_roles();

	$network_options['user_roles'] = array();

	foreach ( $network_roles as $network_role ) {
		$network_options['user_roles'][ $network_role['role'] ] = array(
			'display_name' => $network_role['display_name'],
			'capabilities' => $network_role['capabilities'],
		);
	}

	return $network_options;
}
add_filter( 'populate_network_meta', '_nr_populate_network_roles_on_new_network', 1, 1 );

/**
 * Maybe populates the initial network roles.
 *
 * @since 1.0.0
 * @access private
 */
function _nr_maybe_populate_initial_network_roles() {
	if ( ! is_user_logged_in() ) {
		return;
	}

	$network_roles = _nr_get_initial_network_roles();

	foreach ( $network_roles as $network_role ) {
		if ( get_network_role( $network_role['role'] ) ) {
			continue;
		}

		add_network_role( $network_role['role'], $network_role['display_name'], $network_role['capabilities'] );
	}
}
add_action( 'init', '_nr_maybe_populate_initial_network_roles', 1, 0 );

/**
 * Maybe migrates existing users and adds their network capabilities.
 *
 * Each super admin is given the 'administrator' role on the respective network, and all other users are added to each
 * network for which they have at least one site as a member without any actual role.
 *
 * @since 1.0.0
 * @access private
 */
function _nr_maybe_migrate_user_network_relationships() {
	global $wpdb;

	if ( ! is_user_logged_in() ) {
		return;
	}

	if ( nr_is_user_network_migration_done() ) {
		return;
	}

	$user_search = new WP_User_Query( array(
		'blog_id'     => 0,
		'count_total' => true,
		'number'      => 20,
		'meta_query'  => array(
			'relation' => 'AND',
			array(
				'key'     => '_nr_network_relationship_migrated',
				'compare' => 'NOT EXISTS',
			),
		),
	) );

	$users = $user_search->get_results();
	$total = $user_search->get_total();

	$network_super_admins = array();

	foreach ( $users as $user ) {
		$network_ids = array();

		$blogs = get_blogs_of_user( $user->ID );
		foreach ( $blogs as $blog ) {
			if ( in_array( (int) $blog->site_id, $network_ids, true ) ) {
				continue;
			}

			$network_ids[] = (int) $blog->site_id;
		}

		foreach ( $network_ids as $network_id ) {
			if ( ! isset( $network_super_admins[ $network_id ] ) ) {
				remove_filter( 'pre_site_option_site_admins', '_nr_filter_super_admins', 10 );
				$network_super_admins[ $network_id ] = get_network_option( $network_id, 'site_admins', array() );
				add_filter( 'pre_site_option_site_admins', '_nr_filter_super_admins', 10, 4 );
			}

			$network_cap_key = $wpdb->base_prefix . 'network_' . $network_id . '_capabilities';
			$network_caps    = array();
			if ( in_array( $user->user_login, $network_super_admins[ $network_id ], true ) ) {
				$network_caps['administrator'] = true;
			}

			update_user_meta( $user->ID, $network_cap_key, $network_caps ); // phpcs:ignore WordPress.VIP.RestrictedFunctions
		}

		update_user_meta( $user->ID, '_nr_network_relationship_migrated', '1' ); // phpcs:ignore WordPress.VIP.RestrictedFunctions
	}

	if ( 20 >= $total ) {
		update_network_option( get_main_network_id(), '_nr_user_network_migration_done', '1' );
	}
}
add_action( 'init', '_nr_maybe_migrate_user_network_relationships', 1, 0 );

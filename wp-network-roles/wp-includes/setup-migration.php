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
 * Maybe populates the initial network roles.
 *
 * @since 1.0.0
 * @access private
 */
function _nr_maybe_populate_initial_network_roles() {
	if ( ! is_user_logged_in() ) {
		return;
	}

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

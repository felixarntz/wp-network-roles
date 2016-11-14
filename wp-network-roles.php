<?php
/*
Plugin Name: WP Network Roles
Plugin URI:  https://github.com/felixarntz/wp-network-roles/
Description: Implements actual network-wide user roles in WordPress.
Version:     1.0.0
Author:      Felix Arntz
Author URI:  http://leaves-and-love.net
License:     GNU General Public License v2
License URI: http://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! is_multisite() ) {
	return;
}

/* Load plugin files. */

require_once dirname( __FILE__ ) . '/wp-network-roles/class-wp-network-role.php';
require_once dirname( __FILE__ ) . '/wp-network-roles/class-wp-network-roles.php';

// The following would be incorporated into the WP_User class if it was in Core.
require_once dirname( __FILE__ ) . '/wp-network-roles/class-wp-user-with-network-roles.php';

require_once dirname( __FILE__ ) . '/wp-network-roles/functions.php';

// The following file contains functions that override Core functions. Cheatin, huh?
require_once dirname( __FILE__ ) . '/wp-network-roles/pluggable.php';

/* Internal functions and hooks to bootstrap new functionality. */

function wpnr_add_hooks() {
	add_action( 'setup_theme', 'wpnr_setup_wp_network_roles', 1 );
	add_action( 'pre_user_query', 'wpnr_support_network_role_in_user_query', 10, 1 );
}
add_action( 'plugins_loaded', 'wpnr_add_hooks', 9 );

function wpnr_support_network_role_in_user_query( $query ) {
	global $wpdb;

	if ( empty( $query->query_vars['network_id'] ) ) {
		return;
	}

	$network_id = $query->query_vars['network_id'];

	$network_roles = array();
	if ( isset( $query->query_vars['network_role'] ) ) {
		if ( is_array( $query->query_vars['network_role'] ) ) {
			$network_roles = $query->query_vars['network_role'];
		} elseif ( is_string( $query->query_vars['network_role'] ) && ! empty( $query->query_vars['network_role'] ) ) {
			$network_roles = array_map( 'trim', explode( ',', $query->query_vars['network_role'] ) );
		}
	}

	$network_role__in = array();
	if ( isset( $query->query_vars['network_role__in'] ) ) {
		$network_role__in = (array) $query->query_vars['network_role__in'];
	}

	$network_role__not_in = array();
	if ( isset( $query->query_vars['network_role__not_in'] ) ) {
		$network_role__not_in = (array) $query->query_vars['network_role__not_in'];
	}

	//TODO: Instead of is_multisite(), it would be great if we could check for is_multinetwork() here.
	if ( $network_id && ( ! empty( $network_roles ) || ! empty( $network_role__in ) || ! empty( $network_role__not_in ) || is_multisite() ) ) {
		$network_role_queries = array();

		$network_roles_clauses = array( 'relation' => 'AND' );
		if ( ! empty( $network_roles ) ) {
			foreach ( $network_roles as $network_role ) {
				$network_roles_clauses[] = array(
					'key'     => $wpdb->base_prefix . 'network_' . $network_id . '_capabilities',
					'value'   => '"' . $network_role . '"',
					'compare' => 'LIKE',
				);
			}

			$network_role_queries[] = $network_roles_clauses;
		}

		$network_role__in_clauses = array( 'relation' => 'OR' );
		if ( ! empty( $network_role__in ) ) {
			foreach ( $network_role__in as $network_role ) {
				$network_role__in_clauses[] = array(
					'key'     => $wpdb->base_prefix . 'network_' . $network_id . '_capabilities',
					'value'   => '"' . $network_role . '"',
					'compare' => 'LIKE',
				);
			}

			$network_role_queries[] = $network_role__in_clauses;
		}

		$network_role__not_in_clauses = array( 'relation' => 'AND' );
		if ( ! empty( $network_role__not_in ) ) {
			foreach ( $network_role__not_in as $network_role ) {
				$network_role__not_in_clauses[] = array(
					'key'     => $wpdb->base_prefix . 'network_' . $network_id . '_capabilities',
					'value'   => '"' . $network_role . '"',
					'compare' => 'NOT LIKE',
				);
			}

			$network_role_queries[] = $network_role__not_in_clauses;
		}

		// If there are no specific roles named, make sure the user is a member of the site.
		if ( empty( $network_role_queries ) ) {
			$network_role_queries[] = array(
				'key' => $wpdb->base_prefix . 'network_' . $network_id . '_capabilities',
				'compare' => 'EXISTS',
			);
		}

		// Specify that role queries should be joined with AND.
		$network_role_queries['relation'] = 'AND';

		$old_clauses = false;
		if ( ! empty( $query->meta_query->queries ) ) {
			$old_clauses = $query->meta_query->get_sql( 'user', $wpdb->users, 'ID', $this );
		}

		if ( empty( $query->meta_query->queries ) ) {
			$query->meta_query->queries = $network_role_queries;
		} else {
			// Append the cap query to the original queries and reparse the query.
			$query->meta_query->queries = array(
				'relation' => 'AND',
				array( $query->meta_query->queries, $network_role_queries ),
			);
		}

		$query->meta_query->parse_query_vars( $query->meta_query->queries );

		if ( ! empty( $query->meta_query->queries ) ) {
			$clauses = $query->meta_query->get_sql( 'user', $wpdb->users, 'ID', $query );

			if ( $old_clauses ) {
				$query->query_from = str_replace( $old_clauses['join'], $clauses['join'], $query->query_from );
				$query->query_where = str_replace( $old_clauses['where'], $clauses['where'], $query->query_where );

				if ( $query->meta_query->has_or_relation() && false === strpos( $query->query_fields, 'DISTINCT ' ) ) {
					$query->query_fields = 'DISTINCT ' . $query->query_fields;
				}
			} else {
				$query->query_from .= $clauses['join'];
				$query->query_where .= $clauses['where'];

				if ( $query->meta_query->has_or_relation() ) {
					$query->query_fields = 'DISTINCT ' . $query->query_fields;
				}
			}
		}
	}
}

function wpnr_setup_wp_network_roles() {
	$GLOBALS['wp_network_roles'] = new WP_Network_Roles();

	// Only include custom network admin functionality if the global is not already overridden manually.
	if ( ! isset( $GLOBALS['super_admins'] ) ) {
		$GLOBALS['_wpnr_override_super_admins'] = true;

		wpnr_maybe_setup_and_migrate();
		wpnr_set_super_admins();
	} else {
		wpnr_maybe_setup_and_migrate();
	}

	// Hook from `wp-multi-network` plugin.
	add_action( 'switch_network', 'wpnr_switched_network', 10, 2 );
}

function wpnr_switched_network( $new_network_id, $old_network_id ) {
	if ( $new_network_id == $old_network_id ) {
		return;
	}

	wp_network_roles()->reinit();

	if ( isset( $GLOBALS['_wpnr_override_super_admins'] ) ) {
		wpnr_maybe_setup_and_migrate();
		wpnr_set_super_admins();
	}
}

function wpnr_set_super_admins() {
	$users = get_users( array(
		'blog_id'      => 0,
		'network_id'   => $GLOBALS['wpdb']->siteid,
		'network_role' => 'administrator',
	) );

	$GLOBALS['super_admins'] = wp_list_pluck( $users, 'user_login' );
}

function wpnr_maybe_setup_and_migrate() {
	$option = get_network_option( null, '_wpnr_migrated' );
	if ( $option ) {
		return;
	}

	wpnr_populate_roles();

	$network_admin_logins = get_network_option( null, 'site_admins', array( 'admin' ) );

	// TODO: We cannot adjust the return type of get_users() because there is no filter,
	// so it's always unenhanced WP_User objects.
	$network_admins = get_users( array(
		'blog_id'   => 0,
		'login__in' => $network_admin_logins,
	) );
	foreach ( $network_admins as $network_admin ) {
		$network_admin = new WP_User_With_Network_Roles( $network_admin );
		$network_admin->add_network_role( 'administrator' );
	}

	update_network_option( null, '_wpnr_migrated', '1' );
}

function wpnr_populate_roles() {
	add_network_role( 'administrator', __( 'Network Administrator' ) );

	$role = get_network_role( 'administrator' );
	//TODO: This list is definitely not complete.
	$role->add_cap( 'manage_network' );
	$role->add_cap( 'manage_sites' );
	$role->add_cap( 'manage_network_users' );
	$role->add_cap( 'manage_network_themes' );
	$role->add_cap( 'manage_network_plugins' );
	$role->add_cap( 'manage_network_options' );
}

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
Network:     True
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
require_once dirname( __FILE__ ) . '/wp-network-roles/user.php';

// The following file contains functions that override Core functions. Cheatin, huh?
require_once dirname( __FILE__ ) . '/wp-network-roles/pluggable.php';

/* Internal functions and hooks to bootstrap new functionality. */

function wpnr_add_hooks() {
	add_action( 'setup_theme', 'wpnr_setup_wp_network_roles', 1 );
	add_action( 'pre_user_query', 'wpnr_support_network_role_in_user_query', 10, 1 );
}
add_action( 'plugins_loaded', 'wpnr_add_hooks', 9 );

function wpnr_activate_everywhere( $plugins ) {
	if ( isset( $plugins['wp-network-roles/wp-network-roles.php'] ) ) {
		return $plugins;
	}

	$plugins['wp-network-roles/wp-network-roles.php'] = time();

	return $plugins;
}
if ( did_action( 'muplugins_loaded' ) ) {
	add_filter( 'pre_update_site_option_active_sitewide_plugins', 'wpnr_activate_everywhere', 10, 1 );
}

function wpnr_setup_wp_network_roles() {
	$GLOBALS['wp_network_roles'] = new WP_Network_Roles();

	wpnr_maybe_setup_and_migrate();

	//TODO: This can be removed once all the `is_super_admin()` checks are gone.
	add_filter( 'pre_site_option_site_admins', 'wpnr_get_network_administrator_logins', 10, 3 );

	//TODO: These are for support of current network administrator functionality. Can be removed at some point.
	add_action( 'granted_super_admin', 'wpnr_grant_network_administrator', 10, 1 );
	add_action( 'revoked_super_admin', 'wpnr_revoke_network_administrator', 10, 1 );

	// Hook from `wp-multi-network` plugin.
	add_action( 'switch_network', 'wpnr_switched_network', 10, 2 );
}

function wpnr_get_network_administrator_logins( $default, $option, $network_id ) {
	$users = get_users( array(
		'blog_id'      => 0,
		'network_id'   => $network_id,
		'network_role' => 'administrator',
	) );

	return wp_list_pluck( $users, 'user_login' );
}

function wpnr_grant_network_administrator( $user_id ) {
	$user = get_userdata( $user_id );
	$user->add_network_role( 'administrator' );
}

function wpnr_revoke_network_administrator( $user_id ) {
	$user = get_userdata( $user_id );
	$user->remove_network_role( 'administrator' );
}

function wpnr_switched_network( $new_network_id, $old_network_id ) {
	if ( $new_network_id == $old_network_id ) {
		return;
	}

	wp_network_roles()->reinit();

	wpnr_maybe_setup_and_migrate();
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
	if ( get_network_role( 'administrator' ) ) {
		return;
	}

	$site_administrator = get_role( 'administrator' );

	$network_administrator_capabilities = array_merge( $site_administrator->capabilities, array_fill_keys( array(
		'manage_network',
		'manage_sites',
		'manage_network_users',
		'manage_network_themes',
		'manage_network_plugins',
		'manage_network_options',
	), true ) );

	add_network_role( 'administrator', __( 'Network Administrator' ), $network_administrator_capabilities );
}

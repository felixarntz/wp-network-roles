<?php
/*
Plugin Name: WP Network Roles
Plugin URI:  https://github.com/felixarntz/wp-network-roles/
Description: Implements network-wide user roles in WordPress.
Version:     1.0.0
Author:      Felix Arntz
Author URI:  https://leaves-and-love.net
License:     GNU General Public License v3
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Text Domain: wp-network-roles
Network:     true
Tags:        network roles, network, multisite, multinetwork
 */
/**
 * Plugin initialization file
 *
 * @package WPNetworkRoles
 * @since 1.0.0
 */

if ( ! is_multisite() ) {
	return;
}

/**
 * Initializes the plugin.
 *
 * Loads the required files.
 *
 * @since 1.0.0
 */
function nr_init() {
	define( 'NR_PATH', plugin_dir_path( __FILE__ ) );
	define( 'NR_URL', plugin_dir_url( __FILE__ ) );

	require_once( NR_PATH . 'wp-network-roles/wp-includes/class-wp-network-role.php' );
	require_once( NR_PATH . 'wp-network-roles/wp-includes/class-wp-network-roles.php' );
	require_once( NR_PATH . 'wp-network-roles/wp-includes/capabilities.php' );
	require_once( NR_PATH . 'wp-network-roles/wp-includes/user.php' );

	if ( is_admin() ) {
		require_once( NR_PATH . 'wp-network-roles/wp-admin/includes/wp-ms-users-list-table-tweaks.php' );
	}

	if ( is_plugin_active( 'wp-multi-network/wpmn-loader.php' ) ) {
		require_once( NR_PATH . 'wp-network-roles/multi-network-compat.php' );
	}
}

/**
 * Shows an admin notice if the WordPress version installed is not supported.
 *
 * @since 1.0.0
 */
function nr_requirements_notice() {
	$plugin_file = plugin_basename( __FILE__ );
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<?php printf(
				__( 'Please note: WP Network Roles requires WordPress 4.9-beta or higher. <a href="%s">Deactivate plugin</a>.' ),
				wp_nonce_url(
					add_query_arg(
						array(
							'action'        => 'deactivate',
							'plugin'        => $plugin_file,
							'plugin_status' => 'all',
						),
						self_admin_url( 'plugins.php' )
					),
					'deactivate-plugin_' . $plugin_file
				)
			); ?>
		</p>
	</div>
	<?php
}

/**
 * Ensures that this plugin gets activated in every new network by filtering the `active_sitewide_plugins` option.
 *
 * @since 1.0.0
 *
 * @param array $plugins Array of plugin basenames as keys and time() as values.
 * @return array Modified plugins array.
 */
function nr_activate_everywhere( $plugins ) {
	if ( ! is_array( $plugins ) ) {
		$plugins = array();
	}

	if ( isset( $plugins['wp-network-roles/wp-network-roles.php'] ) ) {
		return $plugins;
	}

	$plugins['wp-network-roles/wp-network-roles.php'] = time();

	return $plugins;
}

if ( version_compare( $GLOBALS['wp_version'], '4.9-beta', '<' ) ) {
	add_action( 'admin_notices', 'nr_requirements_notice' );
	add_action( 'network_admin_notices', 'nr_requirements_notice' );
} else {
	add_action( 'plugins_loaded', 'nr_init' );

	if ( did_action( 'muplugins_loaded' ) ) {
		add_filter( 'site_option_active_sitewide_plugins', 'nr_activate_everywhere', 10, 1 );
		add_filter( 'pre_update_site_option_active_sitewide_plugins', 'nr_activate_everywhere', 10, 1 );
	}
}

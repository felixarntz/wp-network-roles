<?php
/**
 * WP-CLI: WP_CLI_Network_Capabilities_Command class
 *
 * @package WPNetworkRoles
 * @since 1.0.0
 */

if ( ! class_exists( 'WP_CLI_Network_Capabilities_Command' ) ) :

	/**
	 * Adds, removes, and lists capabilities of a user role.
	 *
	 * ## EXAMPLES
	 *
	 *     # Add 'spectate' capability to 'member' role.
	 *     $ wp network-cap add 'member' 'spectate'
	 *     Success: Added 1 capability to 'member' role.
	 *
	 *     # Add all caps from 'administrator' role to 'member' role.
	 *     $ wp network-cap list 'administrator' | xargs wp network-cap add 'member'
	 *     Success: Added 9 capabilities to 'member' role.
	 *
	 *     # Remove all caps from 'administrator' role that also appear in 'member' role.
	 *     $ wp network-cap list 'member' | xargs wp network-cap remove 'administrator'
	 *     Success: Removed 0 capabilities from 'administrator' role.
	 *
	 * @since 1.0.0
	 */
	class WP_CLI_Network_Capabilities_Command extends WP_CLI_Command {

		/**
		 * Available fields for the --fields argument.
		 *
		 * @since 1.0.0
		 * @var array
		 */
		private $fields = array( 'name' );

		/**
		 * Lists capabilities for a given network role.
		 *
		 * ## OPTIONS
		 *
		 * <role>
		 * : Key for the network role.
		 *
		 * [--format=<format>]
		 * : Render output in a particular format.
		 * ---
		 * default: list
		 * options:
		 *   - list
		 *   - table
		 *   - csv
		 *   - json
		 *   - count
		 *   - yaml
		 * ---
		 *
		 * ## EXAMPLES
		 *
		 *     # Display alphabetical list of Administrator capabilities.
		 *     $ wp network-cap list 'administrator' | sort
		 *     create_sites
		 *     delete_sites
		 *     manage_network
		 *     manage_network_options
		 *     manage_network_plugins
		 *     manage_network_themes
		 *     manage_network_users
		 *     manage_sites
		 *     upgrade_network
		 *
		 * @subcommand list
		 *
		 * @since 1.0.0
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function list_( $args, $assoc_args ) {
			$role_obj = self::get_role( $args[0] );

			if ( 'list' === $assoc_args['format'] ) {
				foreach ( array_keys( $role_obj->capabilities ) as $cap ) {
					WP_CLI::line( $cap );
				}
			} else {
				$output_caps = array();
				foreach ( array_keys( $role_obj->capabilities ) as $cap ) {
					$output_cap = new stdClass();

					$output_cap->name = $cap;

					$output_caps[] = $output_cap;
				}
				$formatter = new \WP_CLI\Formatter( $assoc_args, $this->fields );
				$formatter->display_items( $output_caps );
			}
		}

		/**
		 * Adds capabilities to a given network role.
		 *
		 * ## OPTIONS
		 *
		 * <role>
		 * : Key for the network role.
		 *
		 * <cap>...
		 * : One or more capabilities to add.
		 *
		 * ## EXAMPLES
		 *
		 *     # Add 'spectate' capability to 'member' network role.
		 *     $ wp network-cap add member spectate
		 *     Success: Added 1 capability to 'member' network role.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args Positional arguments.
		 */
		public function add( $args ) {
			self::persistence_check();

			$role = array_shift( $args );

			$role_obj = self::get_role( $role );

			$count = 0;

			foreach ( $args as $cap ) {
				if ( $role_obj->has_cap( $cap ) ) {
					continue;
				}

				$role_obj->add_cap( $cap );

				$count++;
			}

			$message = ( 1 === $count ) ? "Added %d capability to '%s' network role." : "Added %d capabilities to '%s' network role.";
			WP_CLI::success( sprintf( $message, $count, $role ) );
		}

		/**
		 * Removes capabilities from a given network role.
		 *
		 * ## OPTIONS
		 *
		 * <role>
		 * : Key for the network role.
		 *
		 * <cap>...
		 * : One or more capabilities to remove.
		 *
		 * ## EXAMPLES
		 *
		 *     # Remove 'spectate' capability from 'member' network role.
		 *     $ wp network-cap remove member spectate
		 *     Success: Removed 1 capability from 'member' network role.
		 *
		 * @since 1.0.0
		 *
		 * @param array $args Positional arguments.
		 */
		public function remove( $args ) {
			self::persistence_check();

			$role = array_shift( $args );

			$role_obj = self::get_role( $role );

			$count = 0;

			foreach ( $args as $cap ) {
				if ( ! $role_obj->has_cap( $cap ) ) {
					continue;
				}

				$role_obj->remove_cap( $cap );

				$count++;
			}

			$message = ( 1 === $count ) ? "Removed %d capability from '%s' network role." : "Removed %d capabilities from '%s' network role.";
			WP_CLI::success( sprintf( $message, $count, $role ) );
		}

		/**
		 * Gets a network role object, or prints a WP-CLI error if it cannot be found.
		 *
		 * @since 1.0.0
		 * @static
		 *
		 * @global WP_Network_Roles $wp_network_roles WP_Network_Roles global instance.
		 *
		 * @param string $role Network role name.
		 * @return WP_Network_Role|null WP_Network_Role object if found, null if the role does not exist.
		 */
		private static function get_role( $role ) {
			global $wp_network_roles;

			$role_obj = $wp_network_roles->get_role( $role );

			if ( ! $role_obj ) {
				WP_CLI::error( "'$role' network role not found." );
			}

			return $role_obj;
		}

		/**
		 * Prints a WP-CLI error if the database isn't being used to store network roles.
		 *
		 * @since 1.0.0
		 * @static
		 *
		 * @global WP_Network_Roles $wp_network_roles WP_Network_Roles global instance.
		 */
		private static function persistence_check() {
			global $wp_network_roles;

			if ( ! $wp_network_roles->use_db ) {
				WP_CLI::error( 'Network role definitions are not persistent.' );
			}
		}
	}

endif;

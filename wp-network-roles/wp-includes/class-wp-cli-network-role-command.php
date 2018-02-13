<?php
/**
 * WP-CLI: WP_CLI_Network_Role_Command class
 *
 * @package WPNetworkRoles
 * @since 1.0.0
 */

if ( ! class_exists( 'WP_CLI_Network_Role_Command' ) ) :

	/**
	 * Manages network user roles, including creating new roles and resetting to defaults.
	 *
	 * ## EXAMPLES
	 *
	 *     # List roles.
	 *     $ wp network-role list --fields=role --format=csv
	 *     role
	 *     administrator
	 *     member
	 *
	 *     # Check to see if a role exists.
	 *     $ wp network-role exists administrator
	 *     Success: Network role with ID 'administrator' exists.
	 *
	 *     # Create a new role.
	 *     $ wp network-role create approver Approver
	 *     Success: Network role with key 'approver' created.
	 *
	 *     # Delete an existing role.
	 *     $ wp network-role delete approver
	 *     Success: Network role with key 'approver' deleted.
	 *
	 *     # Reset existing roles to their default capabilities.
	 *     $ wp network-role reset administrator member
	 *     Success: Reset 2/2 network roles.
	 *
	 * @since 1.0.0
	 */
	class WP_CLI_Network_Role_Command extends WP_CLI_Command {

		/**
		 * Available fields for the --fields argument.
		 *
		 * @since 1.0.0
		 * @var array
		 */
		private $fields = array( 'name', 'role' );

		/**
		 * Lists all network roles.
		 *
		 * ## OPTIONS
		 *
		 * [--fields=<fields>]
		 * : Limit the output to specific object fields.
		 *
		 * [--format=<format>]
		 * : Render output in a particular format.
		 * ---
		 * default: table
		 * options:
		 *   - table
		 *   - csv
		 *   - json
		 *   - count
		 *   - yaml
		 * ---
		 *
		 * ## AVAILABLE FIELDS
		 *
		 * These fields will be displayed by default for each network role:
		 *
		 * * name
		 * * role
		 *
		 * There are no optional fields.
		 *
		 * ## EXAMPLES
		 *
		 *     # List network roles.
		 *     $ wp network-role list --fields=role --format=csv
		 *     role
		 *     administrator
		 *     member
		 *
		 * @subcommand list
		 *
		 * @since 1.0.0
		 *
		 * @global WP_Network_Roles $wp_network_roles WP_Network_Roles global instance.
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function list_( $args, $assoc_args ) {
			global $wp_network_roles;

			$output_roles = array();
			foreach ( $wp_network_roles->roles as $key => $role ) {
				$output_role = new stdClass();

				$output_role->name = $role['name'];
				$output_role->role = $key;

				$output_roles[] = $output_role;
			}

			$formatter = new \WP_CLI\Formatter( $assoc_args, $this->fields );
			$formatter->display_items( $output_roles );
		}

		/**
		 * Checks if a network role exists.
		 *
		 * Exits with return code 0 if the network role exists, 1 if it does not.
		 *
		 * ## OPTIONS
		 *
		 * <role-key>
		 * : The internal name of the role.
		 *
		 * ## EXAMPLES
		 *
		 *     # Check if a network role exists.
		 *     $ wp network-role exists administrator
		 *     Success: Network role with ID 'administrator' exists.
		 *
		 * @since 1.0.0
		 *
		 * @global WP_Network_Roles $wp_network_roles WP_Network_Roles global instance.
		 *
		 * @param array $args Positional arguments.
		 */
		public function exists( $args ) {
			global $wp_network_roles;

			if ( ! in_array( $args[0], array_keys( $wp_network_roles->roles ), true ) ) {
				WP_CLI::error( "Network role with ID '$args[0]' does not exist." );
			}

			WP_CLI::success( "Network role with ID '$args[0]' exists." );
		}

		/**
		 * Creates a new network role.
		 *
		 * ## OPTIONS
		 *
		 * <role-key>
		 * : The internal name of the network role.
		 *
		 * <role-name>
		 * : The publicly visible name of the network role.
		 *
		 * [--clone=<role>]
		 * : Clone capabilities from an existing network role.
		 *
		 * ## EXAMPLES
		 *
		 *     # Create network role for Approver.
		 *     $ wp network-role create approver Approver
		 *     Success: Network role with key 'approver' created.
		 *
		 *     # Create network role for User Administrator.
		 *     $ wp network-role create useradmin "User Administrator"
		 *     Success: Network role with key 'useradmin' created.
		 *
		 * @since 1.0.0
		 *
		 * @global WP_Network_Roles $wp_network_roles WP_Network_Roles global instance.
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function create( $args, $assoc_args ) {
			global $wp_network_roles;

			self::persistence_check();

			$role_key  = array_shift( $args );
			$role_name = array_shift( $args );

			if ( empty( $role_key ) || empty( $role_name ) ) {
				WP_CLI::error( "Can't create network role, insufficient information provided.");
			}

			$capabilities = false;
			if ( ! empty( $assoc_args['clone'] ) ) {
				$role_obj = $wp_network_roles->get_role( $assoc_args['clone'] );
				if ( ! $role_obj ) {
					WP_CLI::error( "'{$assoc_args['clone']}' role not found." );
				}
				$capabilities = array_keys( $role_obj->capabilities );
			}

			if ( add_network_role( $role_key, $role_name ) ) {
				if ( ! empty( $capabilities ) ) {
					$role_obj = $wp_network_roles->get_role( $role_key );
					foreach( $capabilities as $cap ) {
						$role_obj->add_cap( $cap );
					}
					WP_CLI::success( sprintf( "Network role with key '%s' created. Cloned capabilities from '%s'.", $role_key, $assoc_args['clone'] ) );
				} else {
					WP_CLI::success( sprintf( "Network role with key '%s' created.", $role_key ) );
				}
			} else {
				WP_CLI::error( "Network role couldn't be created." );
			}
		}

		/**
		 * Deletes an existing network role.
		 *
		 * ## OPTIONS
		 *
		 * <role-key>
		 * : The internal name of the network role.
		 *
		 * ## EXAMPLES
		 *
		 *     # Delete approver network role.
		 *     $ wp network-role delete approver
		 *     Success: Network role with key 'approver' deleted.
		 *
		 *     # Delete useradmin network role.
		 *     wp network-role delete useradmin
		 *     Success: Network role with key 'useradmin' deleted.
		 *
		 * @since 1.0.0
		 *
		 * @global WP_Network_Roles $wp_network_roles WP_Network_Roles global instance.
		 *
		 * @param array $args Positional arguments.
		 */
		public function delete( $args ) {
			global $wp_network_roles;

			self::persistence_check();

			$role_key = array_shift( $args );

			if ( empty( $role_key ) || ! isset( $wp_network_roles->roles[ $role_key ] ) ) {
				WP_CLI::error( 'Network role key not provided, or is invalid.' );
			}

			remove_network_role( $role_key );

			// Note: remove_network_role() doesn't indicate success or otherwise, so we have to check ourselves.
			if ( ! isset( $wp_network_roles->roles[ $role_key ] ) ) {
				WP_CLI::success( sprintf( "Network role with key '%s' deleted.", $role_key ) );
			} else {
				WP_CLI::error( sprintf( "Network role with key '%s' could not be deleted.", $role_key ) );
			}

		}

		/**
		 * Resets any default network role to default capabilities.
		 *
		 * ## OPTIONS
		 *
		 * [<role-key>...]
		 * : The internal name of one or more network roles to reset.
		 *
		 * [--all]
		 * : If set, all default network roles will be reset.
		 *
		 * ## EXAMPLES
		 *
		 *     # Reset network role.
		 *     $ wp network-role reset administrator member
		 *     Success: Reset 1/2 roles.
		 *
		 *     # Reset all default network roles.
		 *     $ wp network-role reset --all
		 *     Success: All default network roles reset.
		 *
		 * @since 1.0.0
		 *
		 * @global WP_Network_Roles $wp_network_roles WP_Network_Roles global instance.
		 *
		 * @param array $args       Positional arguments.
		 * @param array $assoc_args Associative arguments.
		 */
		public function reset( $args, $assoc_args ) {
			global $wp_network_roles;

			self::persistence_check();

			if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) && empty( $args ) ) {
				WP_CLI::error( 'Network role key not provided, or is invalid.' );
			}

			$all_roles     = array_keys( $wp_network_roles->roles );
			$preserve_args = $args;

			// Get our default network roles.
			$default_roles = array( 'administrator', 'member' );
			$preserve      = $default_roles;
			$before        = array();

			if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) ) {
				foreach ( $default_roles as $role ) {
					$before[ $role ] = get_network_role( $role );
					remove_network_role( $role );
					$args[] = $role;
				}

				$this->populate_roles();
				$not_affected_roles = array_diff( $all_roles, $default_roles );
				if ( ! empty( $not_affected_roles ) ) {
					foreach ( $not_affected_roles as $not_affected_role ) {
						WP_CLI::log( "Custom network role '{$not_affected_role}' not affected." );
					}
				}
			} else {
				foreach ( $args as $k => $role_key ) {
					$key = array_search( $role_key, $default_roles, true );
					if ( false !== $key ) {
						unset( $preserve[ $key ] );
						$before[ $role_key ] = get_network_role( $role_key );
						remove_network_role( $role_key );
					} else {
						unset( $args[ $k ] );
					}
				}

				$not_affected_roles = array_diff( $preserve_args, $default_roles );
				if ( ! empty( $not_affected_roles ) ) {
					foreach ( $not_affected_roles as $not_affected_role ) {
						WP_CLI::log( "Custom network role '{$not_affected_role}' not affected." );
					}
				}

				// No roles were unset, bail.
				if ( count( $default_roles ) === count( $preserve ) ) {
					WP_CLI::error( 'Must specify a default network role to reset.' );
				}

				// For the roles we're not resetting.
				foreach ( $preserve as $k => $role ) {
					$roleobj        = get_network_role( $role );
					$preserve[ $k ] = is_null( $roleobj ) ? $role : $roleobj;

					remove_network_role( $role );
				}

				// Put back all default network roles and capabilities.
				$this->populate_roles();

				// Restore the preserved roles.
				foreach ( $preserve as $k => $roleobj ) {
					// Re-remove after populating.
					if ( is_a( $roleobj, 'WP_Network_Role' ) ) {
						remove_network_role( $roleobj->name );
						add_network_role( $roleobj->name, ucwords( $roleobj->name ), $roleobj->capabilities );
					} else {
						// When not an object, that means the role didn't exist before.
						remove_network_role( $roleobj );
					}
				}
			}

			$num_reset    = 0;
			$args         = array_unique( $args );
			$num_to_reset = count( $args );

			foreach ( $args as $role_key ) {
				$after[ $role_key ] = get_network_role( $role_key );

				if ( $after[ $role_key ] !== $before[ $role_key ] ) {
					++$num_reset;

					$restored_cap = array_diff_key( $after[ $role_key ]->capabilities, $before[ $role_key ]->capabilities );
					$removed_cap  = array_diff_key( $before[ $role_key ]->capabilities, $after[ $role_key ]->capabilities );

					$restored_cap_count = count( $restored_cap );
					$removed_cap_count  = count( $removed_cap );

					$restored_text = ( 1 === $restored_cap_count ) ? '%d capability' : '%d capabilities';
					$removed_text  = ( 1 === $removed_cap_count ) ? '%d capability' : '%d capabilities';
					$message       = 'Restored ' . $restored_text . ' to and removed ' . $removed_text . " from '%s' role.";

					WP_CLI::log( sprintf( $message, $restored_cap_count, $removed_cap_count, $role_key ) );
				} else {
					WP_CLI::log( "No changes necessary for '{$role_key}' network role." );
				}
			}
			if ( $num_reset ) {
				if ( 1 === count( $args ) ) {
					WP_CLI::success( 'Network role reset.' );
				} else {
					WP_CLI::success( "{$num_reset} of {$num_to_reset} network roles reset." );
				}
			} else {
				if ( 1 === count( $args ) ) {
					WP_CLI::success( 'Network role didn\'t need resetting.' );
				} else {
					WP_CLI::success( 'No network roles needed resetting.' );
				}
			}
		}

		/**
		 * Populates the default network roles with their default capabilities.
		 *
		 * @since 1.0.0
		 */
		private function populate_roles() {
			$network_roles = _nr_get_initial_network_roles();

			foreach ( $network_roles as $network_role ) {
				if ( get_network_role( $network_role['role'] ) ) {
					continue;
				}

				add_network_role( $network_role['role'], $network_role['display_name'], $network_role['capabilities'] );
			}
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

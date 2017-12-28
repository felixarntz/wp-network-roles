<?php
/**
 * User API: WP_Network_Roles class
 *
 * @package WPNetworkRoles
 * @since 1.0.0
 */

if ( ! class_exists( 'WP_Network_Roles' ) ) :

	/**
	 * Core class used to implement a network user roles API.
	 *
	 * The role option is simple, the structure is organized by role name that store
	 * the name in value of the 'name' key. The capabilities are stored as an array
	 * in the value of the 'capability' key.
	 *
	 *     array (
	 *    		'rolename' => array (
	 *    			'name' => 'rolename',
	 *    			'capabilities' => array()
	 *    		)
	 *     )
	 *
	 * @since 1.0.0
	 */
	class WP_Network_Roles extends WP_Roles {
		/**
		 * The network ID the roles are initialized for.
		 *
		 * @since 1.0.0
		 * @var int
		 */
		protected $network_id = 0;

		/**
		 * Constructor
		 *
		 * @since 1.0.0
		 *
		 * @global array $wp_network_user_roles Used to set the 'roles' property value.
		 *
		 * @param int $network_id Network ID to initialize roles for. Default is the current network.
		 */
		public function __construct( $network_id = null ) {
			global $wp_network_user_roles;

			$this->use_db = empty( $wp_network_user_roles );

			$this->for_network( $network_id );
		}

		/**
		 * Set up the object properties.
		 *
		 * The role key is set to 'user_roles' and retrieved from the network options database table.
		 * If the $wp_network_user_roles global is set, then it will
		 * be used and the network role option will not be updated or used.
		 *
		 * @since 1.0.0
		 * @deprecated 1.0.0 Use WP_Network_Roles::for_network()
		 */
		protected function _init() {
			_deprecated_function( __METHOD__, '4.9.0', 'WP_Network_Roles::for_network()' );

			$this->for_network();
		}

		/**
		 * Reinitialize the object
		 *
		 * Recreates the network role objects. This is typically called only
		 * after switching wpdb to a new network ID.
		 *
		 * @since 1.0.0
		 * @deprecated 1.0.0 Use WP_Network_Roles::for_network()
		 */
		public function reinit() {
			_deprecated_function( __METHOD__, '4.7.0', 'WP_Network_Roles::for_network()' );

			$this->for_network();
		}

		/**
		 * Add role name with capabilities to list.
		 *
		 * Updates the list of roles, if the role doesn't already exist.
		 *
		 * The capabilities are defined in the following format `array( 'read' => true );`
		 * To explicitly deny a role a capability you set the value for that capability to false.
		 *
		 * @since 1.0.0
		 *
		 * @param string $role         Role name.
		 * @param string $display_name Role display name.
		 * @param array  $capabilities List of role capabilities in the above format.
		 * @return WP_Role|void WP_Role object, if role is added.
		 */
		public function add_role( $role, $display_name, $capabilities = array() ) {
			if ( empty( $role ) || isset( $this->roles[ $role ] ) ) {
				return;
			}

			$this->roles[ $role ] = array(
				'name'         => $display_name,
				'capabilities' => $capabilities,
			);
			if ( $this->use_db ) {
				update_network_option( $this->network_id, $this->role_key, $this->roles );
			}
			$this->role_objects[ $role ] = new WP_Network_Role( $role, $capabilities );
			$this->role_names[ $role ] = $display_name;

			return $this->role_objects[ $role ];
		}

		/**
		 * Remove role by name.
		 *
		 * @since 1.0.0
		 *
		 * @param string $role Role name.
		 */
		public function remove_role( $role ) {
			if ( ! isset( $this->role_objects[ $role ] ) ) {
				return;
			}

			unset( $this->role_objects[ $role ] );
			unset( $this->role_names[ $role ] );
			unset( $this->roles[ $role ] );

			if ( $this->use_db ) {
				update_network_option( $this->network_id, $this->role_key, $this->roles );
			}
		}

		/**
		 * Add capability to role.
		 *
		 * @since 1.0.0
		 *
		 * @param string $role  Role name.
		 * @param string $cap   Capability name.
		 * @param bool   $grant Optional. Whether role is capable of performing capability. Default true.
		 */
		public function add_cap( $role, $cap, $grant = true ) {
			if ( ! isset( $this->roles[ $role ] ) ) {
				return;
			}

			$this->roles[ $role ]['capabilities'][ $cap ] = $grant;
			if ( $this->use_db ) {
				update_network_option( $this->network_id, $this->role_key, $this->roles );
			}
		}

		/**
		 * Remove capability from role.
		 *
		 * @since 1.0.0
		 *
		 * @param string $role Role name.
		 * @param string $cap Capability name.
		 */
		public function remove_cap( $role, $cap ) {
			if ( ! isset( $this->roles[ $role ] ) ) {
				return;
			}

			unset( $this->roles[ $role ]['capabilities'][ $cap ] );
			if ( $this->use_db ) {
				update_network_option( $this->network_id, $this->role_key, $this->roles );
			}
		}

		/**
		 * Initializes all of the available roles.
		 *
		 * @since 1.0.0
		 */
		public function init_roles() {
			if ( empty( $this->roles ) ) {
				return;
			}

			$this->role_objects = array();
			$this->role_names =  array();
			foreach ( array_keys( $this->roles ) as $role ) {
				$this->role_objects[ $role ] = new WP_Network_Role( $role, $this->roles[ $role ]['capabilities'] );
				$this->role_names[ $role ] = $this->roles[ $role ]['name'];
			}

			/**
			 * After the network roles have been initialized, allow plugins to add their own roles.
			 *
			 * @since 1.0.0
			 *
			 * @param WP_Network_Roles $this A reference to the WP_Network_Roles object.
			 */
			do_action( 'wp_network_roles_init', $this );
		}

		/**
		 * Sets the network to operate on. Defaults to the current network.
		 *
		 * @since 1.0.0
		 *
		 * @param int $network_id Network ID to initialize roles for. Default is the current network.
		 */
		public function for_network( $network_id = null ) {
			if ( ! empty( $network_id ) ) {
				$this->network_id = absint( $network_id );
			} else {
				$this->network_id = get_current_network_id();
			}

			$this->role_key = 'user_roles';

			if ( ! empty( $this->roles ) && ! $this->use_db ) {
				return;
			}

			$this->roles = $this->get_roles_data();

			$this->init_roles();
		}

		/**
		 * Gets the ID of the network for which roles are currently initialized.
		 *
		 * @since 1.0.0
		 *
		 * @return int Network ID.
		 */
		public function get_network_id() {
			return $this->network_id;
		}

		/**
		 * Sets the site to operate on. Defaults to the current site.
		 *
		 * @since 1.0.0
		 *
		 * @param int $site_id Site ID to initialize roles for. Default is the current site.
		 */
		public function for_site( $site_id = null ) {
			// Empty method body.
		}

		/**
		 * Gets the ID of the site for which roles are currently initialized.
		 *
		 * @since 1.0.0
		 *
		 * @return int Site ID.
		 */
		public function get_site_id() {
			return 0;
		}

		/**
		 * Gets the available roles data.
		 *
		 * @since 1.0.0
		 *
		 * @global array $wp_network_user_roles Used to set the 'roles' property value.
		 *
		 * @return array Roles array.
		 */
		protected function get_roles_data() {
			global $wp_network_user_roles;

			if ( ! empty( $wp_network_user_roles ) ) {
				return $wp_network_user_roles;
			}

			return get_network_option( $this->network_id, $this->role_key, array() );
		}
	}

endif;

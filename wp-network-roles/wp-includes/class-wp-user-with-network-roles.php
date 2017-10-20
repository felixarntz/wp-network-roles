<?php
/**
 * User API: WP_User_With_Network_Roles class
 *
 * @package WordPress
 * @subpackage Users
 * @since 4.8.0
 */

/**
 * Core class used to implement the WP_User object with support for network roles.
 *
 * TODO: If it was in Core, the init() method would need to be adjusted to support $network_id as well.
 *
 * @since 4.8.0
 */
class WP_User_With_Network_Roles extends WP_User {
	/**
	 * The individual network capabilities the user has been given.
	 *
	 * @since 4.8.0
	 * @access public
	 * @var array
	 */
	public $network_caps = array();

	/**
	 * User metadata option name for network caps.
	 *
	 * @since 4.8.0
	 * @access public
	 * @var string
	 */
	public $network_cap_key;

	/**
	 * The network roles the user is part of.
	 *
	 * @since 4.8.0
	 * @access public
	 * @var array
	 */
	public $network_roles = array();

	/**
	 * Constructor.
	 *
	 * Retrieves the userdata and passes it to WP_User::init().
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int|string|stdClass|WP_User $id User's ID, a WP_User object, or a user object from the DB.
	 * @param string $name Optional. User's username
	 * @param int $blog_id Optional Site ID, defaults to current site.
	 */
	public function __construct( $id = 0, $name = '', $blog_id = '', $network_id = '' ) {
		parent::__construct( $id, $name, $blog_id );

		// By default, set the network ID to the network that matches the provided site ID.
		if ( empty( $network_id ) ) {
			if ( empty( $blog_id ) ) {
				$network_id = get_current_network_id();
			} else {
				$network_id = get_site( $blog_id )->network_id;
			}
		}

		$this->for_network( $network_id );
	}

	/**
	 * Set up capability object properties.
	 *
	 * Will set the value for the 'network_cap_key' property to the database table
	 * base prefix, followed by 'network', the current network ID and 'capabilities'.
	 * Will then check to see if the property matching the 'network_cap_key' exists
	 * and is an array. If so, it will be used.
	 *
	 * @since 4.8.0
	 * @access protected
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string $cap_key Optional capability key
	 */
	protected function _init_network_caps( $cap_key = '' ) {
		global $wpdb;

		if ( empty( $cap_key ) ) {
			$this->network_cap_key = $wpdb->base_prefix . 'network_' . $wpdb->siteid . '_capabilities';
		} else {
			$this->network_cap_key = $cap_key;
		}

		$this->network_caps = get_user_meta( $this->ID, $this->network_cap_key, true );

		if ( ! is_array( $this->network_caps ) )
			$this->network_caps = array();

		$this->get_role_caps();
	}

	/**
	 * Retrieve all of the role capabilities and merge with individual capabilities.
	 *
	 * All of the capabilities of the roles the user belongs to are merged with
	 * the users individual roles. This also means that the user can be denied
	 * specific roles that their role might have, but the specific user isn't
	 * granted permission to.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @return array List of all capabilities for the user.
	 */
	public function get_role_caps() {
		$this->allcaps = parent::get_role_caps();

		//TODO: There is a bug in Core in this function, and it is explicitly a bug here either.
		// For discussion about a fix, see https://core.trac.wordpress.org/ticket/36961
		// The fix here will happen after the fix is in place in Core.

		$wp_network_roles = wp_network_roles();

		//Filter out caps that are not role names and assign to $this->network_roles
		if ( is_array( $this->network_caps ) ) {
			$this->network_roles = array_filter( array_keys( $this->network_caps ), array( $wp_network_roles, 'is_role' ) );
		}

		//Build $allcaps from role caps, overlay user's $caps
		$this->allcaps = array();
		foreach ( (array) $this->network_roles as $role ) {
			$the_role = $wp_network_roles->get_role( $role );
			$this->allcaps = array_merge( (array) $this->allcaps, (array) $the_role->capabilities );
		}
		$this->allcaps = array_merge( (array) $this->allcaps, (array) $this->network_caps );

		return $this->allcaps;
	}

	/**
	 * Add network role to user.
	 *
	 * Updates the user's meta data option with capabilities and roles.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @param string $role Network role name.
	 */
	public function add_network_role( $role ) {
		if ( empty( $role ) ) {
			return;
		}

		$this->network_caps[ $role ] = true;
		update_user_meta( $this->ID, $this->network_cap_key, $this->network_caps );
		$this->get_role_caps();

		/**
		 * Fires immediately after the user has been given a new network role.
		 *
		 * @since 4.8.0
		 *
		 * @param int    $user_id The user ID.
		 * @param string $role    The new role.
		 */
		do_action( 'add_network_user_role', $this->ID, $role );
	}

	/**
	 * Remove network role from user.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @param string $role Network role name.
	 */
	public function remove_network_role( $role ) {
		if ( ! in_array( $role, $this->network_roles ) ) {
			return;
		}

		unset( $this->network_caps[ $role ] );
		update_user_meta( $this->ID, $this->network_cap_key, $this->network_caps );
		$this->get_role_caps();

		/**
		 * Fires immediately after a network role as been removed from a user.
		 *
		 * @since 4.8.0
		 *
		 * @param int    $user_id The user ID.
		 * @param string $role    The removed role.
		 */
		do_action( 'remove_network_user_role', $this->ID, $role );
	}

	/**
	 * Set the network role of the user.
	 *
	 * This will remove the previous network roles of the user and assign the user the
	 * new one. You can set the network role to an empty string and it will remove all
	 * of the network roles from the user.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @param string $role Network role name.
	 */
	public function set_network_role( $role ) {
		if ( 1 == count( $this->network_roles ) && $role == current( $this->network_roles ) ) {
			return;
		}

		foreach ( (array) $this->network_roles as $oldrole ) {
			unset( $this->network_caps[ $oldrole ] );
		}

		$old_roles = $this->network_roles;
		if ( ! empty( $role ) ) {
			$this->network_caps[ $role ] = true;
			$this->network_roles = array( $role => true );
		} else {
			$this->network_roles = false;
		}
		update_user_meta( $this->ID, $this->network_cap_key, $this->network_caps );
		$this->get_role_caps();

		/**
		 * Fires after the user's network role has changed.
		 *
		 * @since 4.8.0
		 *
		 * @param int    $user_id   The user ID.
		 * @param string $role      The new role.
		 * @param array  $old_roles An array of the user's previous roles.
		 */
		do_action( 'set_network_user_role', $this->ID, $role, $old_roles );
	}

	/**
	 * Add network capability and grant or deny access to capability.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @param string $cap Network capability name.
	 * @param bool $grant Whether to grant capability to user.
	 */
	public function add_network_cap( $cap, $grant = true ) {
		$this->network_caps[ $cap ] = $grant;
		update_user_meta( $this->ID, $this->network_cap_key, $this->network_caps );
		$this->get_role_caps();
	}

	/**
	 * Remove network capability from user.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @param string $cap Capability name.
	 */
	public function remove_network_cap( $cap ) {
		if ( ! isset( $this->network_caps[ $cap ] ) ) {
			return;
		}
		unset( $this->network_caps[ $cap ] );
		update_user_meta( $this->ID, $this->network_cap_key, $this->network_caps );
		$this->get_role_caps();
	}

	/**
	 * Remove all of the capabilities of the user.
	 *
	 * @since 4.8.0
	 * @access public
	 */
	public function remove_all_network_caps() {
		$this->network_caps = array();
		delete_user_meta( $this->ID, $this->network_cap_key );
		$this->get_role_caps();
	}

	/**
	 * Set the network to operate on. Defaults to the current network.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $network_id Optional. Network ID, defaults to current network.
	 */
	public function for_network( $network_id = '' ) {
		global $wpdb;

		if ( ! empty( $network_id ) ) {
			//TODO: There should be a method `wpdb::get_network_prefix()` in Core instead.
			$cap_key = $wpdb->base_prefix . 'network_' . $network_id . '_capabilities';
		} else {
			$cap_key = '';
		}

		$this->_init_network_caps( $cap_key );
	}
}

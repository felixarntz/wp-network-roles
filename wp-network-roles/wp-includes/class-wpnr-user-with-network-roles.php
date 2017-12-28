<?php
/**
 * WPNR_User_With_Network_Roles class
 *
 * @package WPNetworkRoles
 * @since 1.0.0
 */

/**
 * Class used to manage network roles of a user.
 *
 * @since 1.0.0
 */
class WPNR_User_With_Network_Roles {

	/**
	 * The user's ID.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	public $ID = 0;

	/**
	 * The individual capabilities the user has been given.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	public $network_caps = array();

	/**
	 * User metadata option name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	public $network_cap_key;

	/**
	 * The roles the user is part of.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	public $network_roles = array();

	/**
	 * All capabilities the user has, including individual and role based.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	public $network_allcaps = array();

	/**
	 * The network ID the capabilities of this user are initialized for.
	 *
	 * @since 1.0.0
	 * @var int
	 */
	private $network_id = 0;

	/**
	 * Constructor.
	 *
	 * Sets up the network roles for the user.
	 *
	 * @since 1.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $id         The user's ID.
	 * @param int $network_id Optional. Network ID, defaults to current site.
	 */
	public function __construct( $id = 0, $network_id = 0 ) {
		$this->ID = (int) $id;

		$this->for_network( $network_id );
	}

	/**
	 * Retrieve all of the network role capabilities and merge with individual capabilities.
	 *
	 * @since 1.0.0
	 *
	 * @return array List of all network capabilities for the user.
	 */
	public function get_network_role_caps() {
		$wp_network_roles = wp_network_roles();

		$switch_network = false;
		if ( is_multisite() && $wp_network_roles->get_network_id() !== $this->network_id ) {
			$switch_network = $wp_network_roles->get_network_id();

			$wp_network_roles->for_network( $this->network_id );
		}

		// Filter out caps that are not role names and assign to $this->network_roles.
		if ( is_array( $this->network_caps ) ) {
			$this->network_roles = array_filter( array_keys( $this->network_caps ), array( $wp_network_roles, 'is_role' ) );
		}

		// Build $allcaps from role caps, overlay user's $caps.
		$this->network_allcaps = array();
		foreach ( (array) $this->network_roles as $role ) {
			$the_role              = $wp_network_roles->get_role( $role );
			$this->network_allcaps = array_merge( (array) $this->network_allcaps, (array) $the_role->capabilities );
		}
		$this->network_allcaps = array_merge( (array) $this->network_allcaps, (array) $this->network_caps );

		if ( $switch_network ) {
			$wp_network_roles->for_network( $switch_network );
		}

		return $this->network_allcaps;
	}

	/**
	 * Add network role to user.
	 *
	 * Updates the user's meta data option with network capabilities and roles.
	 *
	 * @since 1.0.0
	 *
	 * @param string $role Network role name.
	 */
	public function add_network_role( $role ) {
		if ( empty( $role ) ) {
			return;
		}

		$this->network_caps[ $role ] = true;

		update_user_meta( $this->ID, $this->network_cap_key, $this->network_caps ); // phpcs:ignore WordPress.VIP.RestrictedFunctions

		$this->get_network_role_caps();

		/**
		 * Fires immediately after the user has been given a new role.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $user_id The user ID.
		 * @param string $role    The new role.
		 */
		do_action( 'add_network_user_role', $this->ID, $role );
	}

	/**
	 * Remove network role from user.
	 *
	 * @since 1.0.0
	 *
	 * @param string $role Network role name.
	 */
	public function remove_network_role( $role ) {
		if ( ! in_array( $role, $this->network_roles ) ) {
			return;
		}

		unset( $this->network_caps[ $role ] );

		update_user_meta( $this->ID, $this->network_cap_key, $this->network_caps ); // phpcs:ignore WordPress.VIP.RestrictedFunctions

		$this->get_network_role_caps();

		/**
		 * Fires immediately after a role as been removed from a user.
		 *
		 * @since 1.0.0
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
	 * new one. You can set the role to an empty string and it will remove all
	 * of the roles from the user.
	 *
	 * @since 1.0.0
	 *
	 * @param string $role Network role name.
	 */
	public function set_network_role( $role ) {
		if ( 1 === count( $this->network_roles ) && current( $this->network_roles ) === $role ) {
			return;
		}

		foreach ( (array) $this->network_roles as $oldrole ) {
			unset( $this->network_caps[ $oldrole ] );
		}

		$old_roles = $this->network_roles;
		if ( ! empty( $role ) ) {
			$this->network_caps[ $role ] = true;
			$this->network_roles         = array( $role => true );
		} else {
			$this->network_roles = false;
		}

		update_user_meta( $this->ID, $this->network_cap_key, $this->network_caps ); // phpcs:ignore WordPress.VIP.RestrictedFunctions

		$this->get_network_role_caps();

		/**
		 * Fires after the user's role has changed.
		 *
		 * @since 1.0.0
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
	 * @since 1.0.0
	 *
	 * @param string $cap   Network capability name.
	 * @param bool   $grant Whether to grant capability to user.
	 */
	public function add_network_cap( $cap, $grant = true ) {
		$this->network_caps[ $cap ] = $grant;

		update_user_meta( $this->ID, $this->network_cap_key, $this->network_caps ); // phpcs:ignore WordPress.VIP.RestrictedFunctions

		$this->get_network_role_caps();
	}

	/**
	 * Remove network capability from user.
	 *
	 * @since 1.0.0
	 *
	 * @param string $cap Network capability name.
	 */
	public function remove_network_cap( $cap ) {
		if ( ! isset( $this->network_caps[ $cap ] ) ) {
			return;
		}

		unset( $this->network_caps[ $cap ] );

		update_user_meta( $this->ID, $this->network_cap_key, $this->network_caps ); // phpcs:ignore WordPress.VIP.RestrictedFunctions

		$this->get_network_role_caps();
	}

	/**
	 * Remove all of the network capabilities of the user.
	 *
	 * @since 1.0.0
	 */
	public function remove_all_network_caps() {
		$this->network_caps = array();

		delete_user_meta( $this->ID, $this->network_cap_key ); // phpcs:ignore WordPress.VIP.RestrictedFunctions

		$this->get_network_role_caps();
	}

	/**
	 * Sets the network to operate on. Defaults to the current network.
	 *
	 * @since 1.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $network_id Network ID to initialize user capabilities for. Default is the current network.
	 */
	public function for_network( $network_id = '' ) {
		global $wpdb;

		if ( ! empty( $network_id ) ) {
			$this->network_id = absint( $network_id );
		} else {
			$this->network_id = get_current_network_id();
		}

		$this->network_cap_key = $wpdb->base_prefix . 'network_' . $this->network_id . '_capabilities';

		$this->network_caps = $this->get_network_caps_data();

		$this->get_network_role_caps();
	}

	/**
	 * Gets the ID of the network for which the user's capabilities are currently initialized.
	 *
	 * @since 1.0.0
	 *
	 * @return int Network ID.
	 */
	public function get_network_id() {
		return $this->network_id;
	}

	/**
	 * Gets the available user capabilities data.
	 *
	 * @since 1.0.0
	 *
	 * @return array User capabilities array.
	 */
	private function get_network_caps_data() {
		$network_caps = get_user_meta( $this->ID, $this->network_cap_key, true ); // phpcs:ignore WordPress.VIP.RestrictedFunctions

		if ( ! is_array( $network_caps ) ) {
			return array();
		}

		return $network_caps;
	}
}

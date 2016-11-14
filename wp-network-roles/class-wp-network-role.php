<?php
/**
 * User API: WP_Network_Role class
 *
 * @package WordPress
 * @subpackage Users
 * @since 4.8.0
 */

/**
 * Core class used to extend the network user roles API.
 *
 * @since 4.8.0
 */
class WP_Network_Role extends WP_Role {
	/**
	 * Assign network role a capability.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @param string $cap Capability name.
	 * @param bool $grant Whether network role has capability privilege.
	 */
	public function add_cap( $cap, $grant = true ) {
		$this->capabilities[$cap] = $grant;
		wp_network_roles()->add_cap( $this->name, $cap, $grant );
	}

	/**
	 * Removes a capability from a network role.
	 *
	 * This is a container for WP_Network_Roles::remove_cap() to remove the
	 * capability from the role. That is to say, that WP_Network_Roles::remove_cap()
	 * implements the functionality, but it also makes sense to use this class,
	 * because you don't need to enter the role name.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @param string $cap Capability name.
	 */
	public function remove_cap( $cap ) {
		unset( $this->capabilities[$cap] );
		wp_network_roles()->remove_cap( $this->name, $cap );
	}

	/**
	 * Determines whether the  network role has the given capability.
	 *
	 * The capabilities is passed through the {@see 'network_role_has_cap'} filter.
	 * The first parameter for the hook is the list of capabilities the class
	 * has assigned. The second parameter is the capability name to look for.
	 * The third and final parameter for the hook is the role name.
	 *
	 * @since 4.8.0
	 * @access public
	 *
	 * @param string $cap Capability name.
	 * @return bool True if the network role has the given capability. False otherwise.
	 */
	public function has_cap( $cap ) {
		/**
		 * Filters which capabilities a network role has.
		 *
		 * @since 4.8.0
		 *
		 * @param array  $capabilities Array of role capabilities.
		 * @param string $cap          Capability name.
		 * @param string $name         Network role name.
		 */
		$capabilities = apply_filters( 'network_role_has_cap', $this->capabilities, $cap, $this->name );

		if ( ! empty( $capabilities[$cap] ) ) {
			return $capabilities[$cap];
		}

		return false;
	}
}

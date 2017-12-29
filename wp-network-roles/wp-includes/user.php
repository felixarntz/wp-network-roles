<?php
/**
 * User functions for the network user roles API.
 *
 * @package WPNetworkRoles
 * @since 1.0.0
 */

if ( ! function_exists( 'get_networks_of_user' ) ) :

	/**
	 * Gets the networks a user belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int $user_id User ID
	 * @return array A list of the user's networks. An empty array if the user doesn't exist
	 *               or belongs to no networks.
	 */
	function get_networks_of_user( $user_id ) {
		global $wpdb;

		if ( ! is_multisite() ) {
			return array();
		}

		$user_id = (int) $user_id;

		// Logged out users can't have networks.
		if ( empty( $user_id ) ) {
			return array();
		}

		/**
		 * Filters the list of a user's networks before it is populated.
		 *
		 * Passing a non-null value to the filter will effectively short circuit
		 * get_networks_of_user(), returning that value instead.
		 *
		 * @since 1.0.0
		 *
		 * @param null|array $networks An array of network objects of which the user is a member.
		 * @param int        $user_id  User ID.
		 */
		$networks = apply_filters( 'pre_get_networks_of_user', null, $user_id );

		if ( null !== $networks ) {
			return $networks;
		}

		$keys = get_user_meta( $user_id ); // phpcs:ignore WordPress.VIP.RestrictedFunctions
		if ( empty( $keys ) ) {
			return array();
		}

		$keys = array_keys( $keys );

		$network_ids = array();
		foreach ( $keys as $key ) {
			if ( 'capabilities' !== substr( $key, -12 ) ) {
				continue;
			}

			if ( $wpdb->base_prefix && 0 !== strpos( $key, $wpdb->base_prefix . 'network_' ) ) {
				continue;
			}

			$network_id = str_replace( array( $wpdb->base_prefix . 'network_', '_capabilities' ), '', $key );
			if ( ! is_numeric( $network_id ) ) {
				continue;
			}

			$network_ids[] = (int) $network_id;
		}

		$networks = array();

		if ( ! empty( $network_ids ) ) {
			$networks = get_networks( array(
				'number'      => '',
				'network__in' => $network_ids,
			) );
		}

		/**
		 * Filters the list of networks a user belongs to.
		 *
		 * @since 1.0.0
		 *
		 * @param array $networks An array of network objects belonging to the user.
		 * @param int   $user_id  User ID.
		 */
		return apply_filters( 'get_networks_of_user', $networks, $user_id );
	}

endif;

if ( ! function_exists( 'count_network_users' ) ) :

	/**
	 * Counts number of network users who have each of the user roles.
	 *
	 * Assumes there are neither duplicated nor orphaned capabilities meta_values.
	 * Assumes role names are unique phrases. Same assumption made by WP_User_Query::prepare_query()
	 * Using $strategy = 'time' this is CPU-intensive and should handle around 10^7 users.
	 * Using $strategy = 'memory' this is memory-intensive and should handle around 10^5 users, but see WP Bug #12257.
	 *
	 * @since 1.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string   $strategy   Optional. The computational strategy to use when counting the users.
	 *                             Accepts either 'time' or 'memory'. Default 'time'.
	 * @param int|null $network_id Optional. The network ID to count users for. Defaults to the current network.
	 * @return array Includes a grand total and an array of counts indexed by role strings.
	 */
	function count_network_users( $strategy = 'time', $network_id = null ) {
		global $wpdb;

		if ( ! $network_id ) {
			$network_id = get_current_network_id();
		}

		$network_prefix = $wpdb->base_prefix . 'network_' . $network_id . '_';
		$result = array();

		if ( 'time' === $strategy ) {
			$original_network_id = wp_network_roles()->get_network_id();
			if ( is_multisite() && $original_network_id !== (int) $network_id ) {
				wp_network_roles()->for_network( $network_id );
				$avail_roles = wp_network_roles()->get_names();
				wp_network_roles()->for_network( $original_network_id );
			} else {
				$avail_roles = wp_network_roles()->get_names();
			}

			$select_count = array();
			foreach ( $avail_roles as $slug => $name ) {
				$select_count[] = $wpdb->prepare( 'COUNT(NULLIF(`meta_value` LIKE %s, false))', '%' . $wpdb->esc_like( '"' . $slug . '"' ) . '%' );
			}
			$select_count[] = "COUNT(NULLIF(`meta_value` = 'a:0:{}', false))";
			$select_count = implode( ', ', $select_count );

			$row = $wpdb->get_row( "SELECT $select_count, COUNT(*) FROM $wpdb->usermeta WHERE meta_key = '{$network_prefix}capabilities'", ARRAY_N ); // WPCS: db call ok.

			$col = 0;
			$role_counts = array();
			foreach ( $avail_roles as $slug => $name ) {
				$count = (int) $row[ $col++ ];
				if ( $count > 0 ) {
					$role_counts[ $slug ] = $count;
				}
			}

			$role_counts['none'] = (int) $row[ $col++ ];

			$result['total_users'] = (int) $row[ $col ];
			$result['avail_roles'] =& $role_counts;
		} else {
			$avail_roles = array( 'none' => 0 );

			$users_of_network = $wpdb->get_col( "SELECT meta_value FROM $wpdb->usermeta WHERE meta_key = '{$network_prefix}capabilities'" ); // WPCS: db call ok.

			foreach ( $users_of_network as $caps_meta ) {
				$network_roles = maybe_unserialize( $caps_meta );
				if ( ! is_array( $network_roles ) ) {
					continue;
				}

				if ( empty( $network_roles ) ) {
					$avail_roles['none']++;
				}

				foreach ( $network_roles as $network_role => $val ) {
					if ( isset( $avail_roles[ $network_role ] ) ) {
						$avail_roles[ $network_role ]++;
					} else {
						$avail_roles[ $network_role ] = 1;
					}
				}
			}

			$result['total_users'] = count( $users_of_network );
			$result['avail_roles'] =& $avail_roles;
		}

		return $result;
	}

endif;

if ( ! function_exists( 'wp_get_users_with_no_network_role' ) ) :

	/**
	 * Gets the user IDs of all users with no role on this network.
	 *
	 * @since 1.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param int|null $network_id Optional. The network ID to get users with no network role for. Defaults to the current network.
	 * @return array Array of user IDs.
	 */
	function wp_get_users_with_no_network_role( $network_id = null ) {
		global $wpdb;

		if ( ! $network_id ) {
			$network_id = get_current_network_id();
		}

		$network_prefix = $wpdb->base_prefix . 'network_' . $network_id . '_';

		$original_network_id = wp_network_roles()->get_network_id();
		if ( is_multisite() && $original_network_id !== (int) $network_id ) {
			wp_network_roles()->for_network( $network_id );
			$role_names = wp_network_roles()->get_names();
			wp_network_roles()->for_network( $original_network_id );
		} else {
			$role_names = wp_network_roles()->get_names();
		}

		$regex = implode( '|', array_keys( $role_names ) );
		$regex = preg_replace( '/[^a-zA-Z_\|-]/', '', $regex );

		$users = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = '{$network_prefix}capabilities' AND meta_value NOT REGEXP %s", $regex ) ); // WPCS: db call ok.

		return $users;
	}

endif;

if ( ! function_exists( 'translate_network_user_role' ) ) :

	/**
	 * Translates a network role name.
	 *
	 * Since the role names are in the database and not in the source there
	 * are dummy gettext calls to get them into the POT file and this function
	 * properly translates them back.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name The network role name.
	 * @return string Translated network role name on success, original name on failure.
	 */
	function translate_network_user_role( $name ) {
		return translate_with_gettext_context( before_last_bar( $name ), 'Network user role' );
	}

endif;

/**
 * Gets the user with network roles instance for a regular user instance.
 *
 * @since 1.0.0
 *
 * @param WP_User $user User instance.
 * @return WPNR_User_With_Network_Roles User with network roles instance.
 */
function nr_get_user_with_network_roles( $user ) {
	if ( ! isset( $user->network_roles ) ) {
		$site = get_site( $user->get_site_id() );
		if ( $site ) {
			$network_id = $site->network_id;
		} else {
			$network_id = get_current_network_id();
		}

		$user->network_roles = new WPNR_User_With_Network_Roles( $user->ID, $network_id );
	}

	return $user->network_roles;
}

/**
 * Adds the network capabilities to a user's regular capabilities.
 *
 * @since 1.0.0
 * @access private
 *
 * @param array   $allcaps Array of all the user's capabilities.
 * @param array   $caps    Actual capabilities for meta capability.
 * @param array   $args    Optional parameters passed to has_cap().
 * @param WP_User $user    User object.
 * @return array $allcaps including network capabilities.
 */
function _nr_filter_user_has_cap( $allcaps, $caps, $args, $user ) {
	$nr_user = nr_get_user_with_network_roles( $user );

	return array_merge( $allcaps, $nr_user->network_allcaps );
}
add_filter( 'user_has_cap', '_nr_filter_user_has_cap', 1, 4 );

/**
 * Adds support for querying users by network ID and network role.
 *
 * @since 1.0.0
 * @access private
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param WP_User_Query $query User query instance.
 */
function _nr_support_network_role_in_user_query( $query ) {
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
				'key'     => $wpdb->base_prefix . 'network_' . $network_id . '_capabilities',
				'compare' => 'EXISTS',
			);
		}

		// Specify that role queries should be joined with AND.
		$network_role_queries['relation'] = 'AND';

		$old_clauses = false;
		if ( ! empty( $query->meta_query->queries ) ) {
			$old_clauses = $query->meta_query->get_sql( 'user', $wpdb->users, 'ID', $this ); // phpcs:ignore WordPress.VIP.RestrictedVariables
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
			$clauses = $query->meta_query->get_sql( 'user', $wpdb->users, 'ID', $query ); // phpcs:ignore WordPress.VIP.RestrictedVariables

			if ( $old_clauses ) {
				$query->query_from  = str_replace( $old_clauses['join'], $clauses['join'], $query->query_from );
				$query->query_where = str_replace( $old_clauses['where'], $clauses['where'], $query->query_where );

				if ( $query->meta_query->has_or_relation() && false === strpos( $query->query_fields, 'DISTINCT ' ) ) {
					$query->query_fields = 'DISTINCT ' . $query->query_fields;
				}
			} else {
				$query->query_from  .= $clauses['join'];
				$query->query_where .= $clauses['where'];

				if ( $query->meta_query->has_or_relation() ) {
					$query->query_fields = 'DISTINCT ' . $query->query_fields;
				}
			}
		}
	}
}
add_action( 'pre_user_query', '_nr_support_network_role_in_user_query', 10, 1 );

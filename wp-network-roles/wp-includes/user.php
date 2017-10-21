<?php
/**
 * User functions for the network user roles API.
 *
 * @package WPNetworkRoles
 * @since 1.0.0
 */

/**
 * Gets the network roles for a user.
 *
 * @since 1.0.0
 *
 * @param int $user_id    User ID.
 * @param int $network_id Optional. Network ID. Default is the current network ID.
 */
function nr_get_network_roles_for_user( $user_id, $network_id = 0 ) {
	global $_nr_network_role_data;

	if ( ! $network_id ) {
		$network_id = get_current_network_id();
	}

	if ( ! isset( $_nr_network_role_data[ $user_id ][ $network_id ] ) ) {
		_nr_get_network_role_caps_for_user( $user_id, $network_id );
	}

	return $_nr_network_role_data[ $user_id ][ $network_id ]['roles'];
}

/**
 * Adds a network role to a user.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int    $user_id    User ID.
 * @param string $role       Role to add to the user.
 * @param int    $network_id Optional. Network ID. Default is the current network ID.
 */
function nr_add_network_role_for_user( $user_id, $role, $network_id = 0 ) {
	global $wpdb;

	if ( ! $network_id ) {
		$network_id = $wpdb->siteid;
	}

	$network_cap_key = $wpdb->base_prefix . 'network_' . $network_id . '_capabilities';

	$network_roles = get_user_meta( $user_id, $network_cap_key, true );
	if ( ! is_array( $network_roles ) ) {
		$network_roles = array();
	}

	$network_roles[ $role ] = true;
	update_user_meta( $user_id, $network_cap_key, $network_roles );

	_nr_get_network_role_caps_for_user( $user_id, $network_id );

	/**
	 * Fires immediately after the user has been given a new network role.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id The user ID.
	 * @param string $role    The new role.
	 */
	do_action( 'add_network_user_role', $user_id, $role );
}

/**
 * Removes a network role from a user.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int    $user_id    User ID.
 * @param string $role       Role to remove from the user.
 * @param int    $network_id Optional. Network ID. Default is the current network ID.
 */
function nr_remove_network_role_for_user( $user_id, $role, $network_id = 0 ) {
	global $wpdb;

	if ( ! $network_id ) {
		$network_id = $wpdb->siteid;
	}

	$network_cap_key = $wpdb->base_prefix . 'network_' . $network_id . '_capabilities';

	$network_roles = get_user_meta( $user_id, $network_cap_key, true );
	if ( ! is_array( $network_roles ) ) {
		return;
	}

	if ( ! isset( $network_roles[ $role ] ) ) {
		return;
	}

	unset( $network_roles[ $role ] );
	update_user_meta( $user_id, $network_cap_key, $network_roles );

	_nr_get_network_role_caps_for_user( $user_id, $network_id );

	/**
	 * Fires immediately after a network role as been removed from a user.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id The user ID.
	 * @param string $role    The removed role.
	 */
	do_action( 'remove_network_user_role', $user_id, $role );
}

/**
 * Sets a network role to a user.
 *
 * @since 1.0.0
 *
 * @global wpdb $wpdb WordPress database abstraction object.
 *
 * @param int    $user_id    User ID.
 * @param string $role       Role to add to the user.
 * @param int    $network_id Optional. Network ID. Default is the current network ID.
 */
function nr_set_network_role_for_user( $user_id, $role, $network_id = 0 ) {
	global $wpdb;

	if ( ! $network_id ) {
		$network_id = $wpdb->siteid;
	}

	$network_cap_key = $wpdb->base_prefix . 'network_' . $network_id . '_capabilities';

	$old_roles = get_user_meta( $user_id, $network_cap_key, true );

	update_user_meta( $user_id, $network_cap_key, array( $role => true ) );

	_nr_get_network_role_caps_for_user( $user_id, $network_id );

	/**
	 * Fires after the user's network role has changed.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user_id   The user ID.
	 * @param string $role      The new role.
	 * @param array  $old_roles An array of the user's previous roles.
	 */
	do_action( 'set_network_user_role', $user_id, $role, $old_roles );
}

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
 * @param string $strategy 'time' or 'memory'
 * @return array Includes a grand total and an array of counts indexed by role strings.
 */
function count_network_users( $strategy = 'time' ) {
	global $wpdb;

	$id = get_current_network_id();
	$network_prefix = $wpdb->base_prefix . 'network_' . $id . '_';
	$result = array();

	if ( 'time' === $strategy ) {
		$avail_roles = wp_network_roles()->get_names();

		$select_count = array();
		foreach ( $avail_roles as $slug => $name ) {
			$select_count[] = $wpdb->prepare( "COUNT(NULLIF(`meta_value` LIKE %s, false))", '%' . $wpdb->esc_like( '"' . $slug . '"' ) . '%' );
		}
		$select_count[] = "COUNT(NULLIF(`meta_value` = 'a:0:{}', false))";
		$select_count = implode( ', ', $select_count );

		$row = $wpdb->get_row( "SELECT $select_count, COUNT(*) FROM $wpdb->usermeta WHERE meta_key = '{$network_prefix}capabilities'", ARRAY_N );

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

		$users_of_network = $wpdb->get_col( "SELECT meta_value FROM $wpdb->usermeta WHERE meta_key = '{$network_prefix}capabilities'" );

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
 * @return array Array of user IDs.
 */
function wp_get_users_with_no_network_role() {
	global $wpdb;

	$network_prefix = $wpdb->base_prefix . 'network_' . get_current_network_id() . '_';

	$regex = implode( '|', array_keys( wp_network_roles()->get_names() ) );
	$regex = preg_replace( '/[^a-zA-Z_\|-]/', '', $regex );

	$users = $wpdb->get_col( $wpdb->prepare( "SELECT user_id FROM $wpdb->usermeta WHERE meta_key = '{$network_prefix}capabilities' AND meta_value NOT REGEXP %s", $regex ) );

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
 * Builds the network roles and capabilities data for a user.
 *
 * @since 1.0.0
 * @access private
 *
 * @param int $user_id    User ID.
 * @param int $network_id Optional. Network ID. Default is the current network ID.
 */
function _nr_get_network_role_caps_for_user( $user_id, $network_id = 0 ) {
	global $wpdb, $_nr_network_role_data;

	if ( ! $network_id ) {
		$network_id = $wpdb->siteid;
	}

	$network_cap_key = $wpdb->base_prefix . 'network_' . $network_id . '_capabilities';

	if ( ! isset( $_nr_network_role_data ) ) {
		$_nr_network_role_data = array();
	}

	if ( ! isset( $_nr_network_role_data[ $user_id ][ $network_id ] ) ) {
		$_nr_network_role_data[ $user_id ][ $network_id ] = array();
	}

	$_nr_network_role_data[ $user_id ][ $network_id ]['caps'] = get_user_meta( $user_id, $network_cap_key, true );
	if ( ! is_array( $_nr_network_role_data[ $user_id ][ $network_id ]['caps'] ) ) {
		$_nr_network_role_data[ $user_id ][ $network_id ]['caps'] = array();
	}

	$wp_network_roles = wp_network_roles();

	$original_network_id = 0;
	if ( (int) $network_id !== $wp_network_roles->get_network_id() ) {
		$original_network_id = $wp_network_roles->get_network_id();

		$wp_network_roles->for_network( $network_id );
	}

	$_nr_network_role_data[ $user_id ][ $network_id ]['roles'] = array_filter( array_keys( $_nr_network_role_data[ $user_id ][ $network_id ]['caps'] ), array( $wp_network_roles, 'is_role' ) );

	// Build $allcaps from role caps, overlay user's $caps
	$_nr_network_role_data[ $user_id ][ $network_id ]['allcaps'] = array();
	foreach ( (array) $_nr_network_role_data[ $user_id ][ $network_id ]['roles'] as $role ) {
		$the_role = $wp_network_roles->get_role( $role );
		$_nr_network_role_data[ $user_id ][ $network_id ]['allcaps'] = array_merge( (array) $_nr_network_role_data[ $user_id ][ $network_id ]['allcaps'], (array) $the_role->capabilities );
	}
	$_nr_network_role_data[ $user_id ][ $network_id ]['allcaps'] = array_merge( (array) $_nr_network_role_data[ $user_id ][ $network_id ]['allcaps'], (array) $_nr_network_role_data[ $user_id ]['caps'] );

	if ( ! empty( $original_network_id ) ) {
		$wp_network_roles->for_network( $original_network_id );
	}
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
	global $_nr_network_role_data;

	$site = get_site( $user->get_site_id() );
	if ( ! $site ) {
		return $allcaps;
	}

	$network_id = $site->network_id;

	if ( ! isset( $_nr_network_role_data[ $user->ID ][ $network_id ] ) ) {
		_nr_get_network_role_caps_for_user( $user->ID, $network_id );
	}

	return array_merge( $allcaps, $_nr_network_role_data[ $user->ID ][ $network_id ]['allcaps'] );
}
add_filter( 'user_has_cap', '_nr_filter_user_has_cap', 1, 4 );

/**
 * Adds support for querying users by network ID and network role.
 *
 * @since 1.0.0
 * @access private
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
add_action( 'pre_user_query', '_nr_support_network_role_in_user_query', 10, 1 );

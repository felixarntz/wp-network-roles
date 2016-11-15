<?php
/**
 * User functions for the network user roles API.
 *
 * @package WordPress
 * @subpackage Users
 * @since 4.8.0
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

function wpnr_support_network_role_in_user_query( $query ) {
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

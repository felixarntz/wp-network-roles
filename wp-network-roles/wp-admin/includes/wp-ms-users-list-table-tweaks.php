<?php
/**
 * Adjustments for the MS Users list table.
 *
 * @package WordPress
 * @subpackage Users
 * @since 4.8.0
 */

function wpnr_ms_users_override_query_args( $args ) {
	global $role;

	if ( ! is_network_admin() ) {
		return $args;
	}

	if ( 'none' === $role ) {
		$args['include'] = wp_get_users_with_no_network_role();
	} else {
		$args['network_role'] = $role;
	}

	return $args;
}
add_filter( 'users_list_table_query_args', 'wpnr_ms_users_override_query_args' );

function wpnr_ms_users_override_columns( $columns ) {
	$first_columns = array_slice( $columns, 0, 4 );

	$last_columns = array_diff_key( $columns, $first_columns );

	return array_merge( $first_columns, array( 'role' => __( 'Network Role' ) ), $last_columns );
}
add_filter( 'wpmu_users_columns', 'wpnr_ms_users_override_columns' );

function wpnr_ms_users_column_role( $output, $column_name, $user_id ) {
	if ( ! is_network_admin() ) {
		return $output;
	}

	if ( 'role' !== $column_name ) {
		return $output;
	}

	$user = get_userdata( $user_id );

	$user_role_names = wpnr_ms_users_get_role_list( $user );

	return implode( ', ', $user_role_names );
}
add_filter( 'manage_users_custom_column', 'wpnr_ms_users_column_role', 10, 3 );

function wpnr_ms_users_get_role_list( $user_object ) {
	$wp_network_roles = wp_network_roles();

	$role_list = array();

	foreach ( $user_object->roles as $role ) {
		if ( isset( $wp_network_roles->role_names[ $role ] ) ) {
			$role_list[ $role ] = translate_network_user_role( $wp_network_roles->role_names[ $role ] );
		}
	}

	if ( empty( $role_list ) ) {
		$role_list['none'] = _x( 'None', 'no user roles' );
	}

	return $role_list;
}

function wpnr_ms_users_override_views( $views ) {
	global $role;

	$wp_network_roles = wp_network_roles();

	$url = 'users.php';
	$users_of_network = count_network_users();

	$total_users = $users_of_network['total_users'];
	$avail_roles =& $users_of_network['avail_roles'];

	unset( $users_of_network );

	$class = empty( $role ) ? ' class="current"' : '';

	$role_links = array();
	$role_links['all'] = "<a href='$url'$class>" . sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_users, 'network users' ), number_format_i18n( $total_users ) ) . '</a>';
	foreach ( $wp_network_roles->get_names() as $slug => $name ) {
		if ( ! isset( $avail_roles[ $slug ] ) ) {
			continue;
		}

		$class = $slug === $role ? ' class="current"' : '';

		$name = translate_network_user_role( $name );
		/* translators: User role name with count */
		$name = sprintf( __( '%1$s <span class="count">(%2$s)</span>' ), $name, number_format_i18n( $avail_roles[ $slug ] ) );
		$role_links[ $slug ] = "<a href='" . esc_url( add_query_arg( 'role', $slug, $url ) ) . "'$class>$name</a>";
	}

	if ( ! empty( $avail_roles['none'] ) ) {
		$class = 'none' === $role ? ' class="current"' : '';

		$name = __( 'No role' );
		/* translators: User role name with count */
		$name = sprintf( __( '%1$s <span class="count">(%2$s)</span>' ), $name, number_format_i18n( $avail_roles['none' ] ) );
		$role_links['none'] = "<a href='" . esc_url( add_query_arg( 'role', 'none', $url ) ) . "'$class>$name</a>";
	}

	return $role_links;
}
add_filter( 'views_users-network', 'wpnr_ms_users_override_views' );

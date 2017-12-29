[![Build Status](https://api.travis-ci.org/felixarntz/wp-network-roles.png?branch=master)](https://travis-ci.org/felixarntz/wp-network-roles)
[![Code Climate](https://codeclimate.com/github/felixarntz/wp-network-roles/badges/gpa.svg)](https://codeclimate.com/github/felixarntz/wp-network-roles)
[![Test Coverage](https://codeclimate.com/github/felixarntz/wp-network-roles/badges/coverage.svg)](https://codeclimate.com/github/felixarntz/wp-network-roles/coverage)
[![Latest Stable Version](https://poser.pugx.org/felixarntz/wp-network-roles/version)](https://packagist.org/packages/felixarntz/wp-network-roles)
[![License](https://poser.pugx.org/felixarntz/wp-network-roles/license)](https://packagist.org/packages/felixarntz/wp-network-roles)

# WP Network Roles

Implements network-wide user roles in WordPress.

## What it does

* introduces classes `WP_Network_Role` and `WP_Network_Roles` for managing network-wide roles
* introduces internal class `WPNR_User_With_Network_Roles` that stores network roles and capabilities for a user
* introduces relationships between users and networks
* allows to query users for specific network roles or whether they are part of a network
* uses user meta to store network-wide roles and capabilities, in a similar fashion like WordPress core stores site roles and capabilities)
* introduces two initial network roles "administrator" (similar to Super Admin for a network) and "member" (no capabilities, simply a member of a network)
* synchronizes and migrates super admins to the network role "administrator"
* adds network relationships to existing users by assigning them the "member" network role
* supports the WP Multi Network plugin

## How to install

The plugin can either be installed as a network-wide regular plugin or alternatively as a must-use plugin.

## Recommendations

* Do not set any of the following global variables:
    * `$super_admins` (WordPress core)
    * `$wp_user_roles` (WordPress core)
    * `$wp_network_roles`
    * `$wp_network_user_roles`
* Do not rely on `is_super_admin()`, but rather on actual capabilities
* While it is a best practice to prefix plugin functions and classes, this plugin is a proof-of-concept for WordPress core, and several functions may end up there eventually. This plugin only prefixes functions and classes that are specific to the plugin, internal helper functions for itself or hooks. Non-prefixed functions and classes are wrapped in a conditional so that, if WordPress core adapts them, their core variant will be loaded instead. Therefore, do not define any of the following functions or classes:
  * `wp_network_roles()`
  * `get_network_role()`
  * `add_network_role()`
  * `remove_network_role()`
  * `get_networks_of_user()`
  * `count_network_users()`
  * `wp_get_users_with_no_network_role()`
  * `WP_Network_Role`
  * `WP_Network_Roles`

## Usage

### Managing Network Roles

* Function: `wp_network_roles(): WP_Network_Roles`
* Function: `get_network_role( string $role ): WP_Network_Role|null`
* Function: `add_network_role( string $role, string $display_name, array $capabilities )`
* Function: `remove_network_role( string $role )`

### Managing User Network Roles

* Function: `nr_get_user_with_network_roles( WP_User $user ): WPNR_User_With_Network_Roles` -- *Since it is impossible to extend the `WP_User` class in a reliable way in a plugin, this function must be used to retrieve an instance of a special class to manage network roles of the user object passed. That instance will be internally cached inside of the original `WP_User` instance.*
* Method: `WPNR_User_With_Network_Roles::add_network_role( string $role )`
* Method: `WPNR_User_With_Network_Roles::remove_network_role( string $role )`
* Method: `WPNR_User_With_Network_Roles::set_network_role( string $role )`
* Method: `WPNR_User_With_Network_Roles::for_network( int $network_id )` -- *Initializes the instance for a specific network.*
* Method: `WPNR_User_With_Network_Roles::get_network_id()` -- *Gets the ID of the network for which the instance is currently initialized.*

### Hooks

* Action: `wp_network_roles_init`
* Action: `add_network_user_role`
* Action: `remove_network_user_role`
* Action: `set_network_user_role`
* Filter: `network_role_has_cap`
* Filter: `pre_get_networks_of_user`
* Filter: `get_networks_of_user`

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
* introduces one initial network role "administrator"
* synchronizes and migrates super admins to the network role "administrator"
* adds network relationships to existing users
* supports the WP Multi Network plugin

## How to install

The plugin can either be installed as a network-wide regular plugin or alternatively as a must-use plugin.

## Recommendations

* Do not set any of the following global variables: `$super_admins`, `$wp_user_roles`, `$wp_network_user_roles`
* Do not rely on `is_super_admin()`, but rather on actual capabilities

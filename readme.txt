=== WP Network Roles ===

Plugin Name:       WP Network Roles
Plugin URI:        https://github.com/felixarntz/wp-network-roles
Author:            Felix Arntz
Author URI:        https://leaves-and-love.net
Contributors:      flixos90
Requires at least: 4.9
Tested up to:      4.9
Stable tag:        1.0.0-beta.1
Version:           1.0.0-beta.1
License:           GNU General Public License v2 (or later)
License URI:       http://www.gnu.org/licenses/gpl-2.0.html
Tags:              network roles, network, multisite, multinetwork

Implements network-wide user roles in WordPress.

== Description ==

= Features =

* introduces classes `WP_Network_Role` and `WP_Network_Roles` for managing network-wide roles
* introduces internal class `WPNR_User_With_Network_Roles` that stores network roles and capabilities for a user
* introduces relationships between users and networks
* allows to query users for specific network roles or whether they are part of a network
* uses user meta to store network-wide roles and capabilities, in a similar fashion like WordPress core stores site roles and capabilities)
* introduces two initial network roles "administrator" (similar to Super Admin for a network) and "member" (no capabilities, simply a member of a network)
* synchronizes and migrates super admins to the network role "administrator"
* adds network relationships to existing users by assigning them the "member" network role
* includes `wp network-role` and `wp network-cap` commands for WP-CLI
* supports the WP Multi Network plugin

== Installation ==

1. Upload the entire `wp-network-roles` folder to the `/wp-content/plugins/` directory or download it through the WordPress backend.
2. Activate the plugin through the 'Plugins' menu in WordPress.

== Frequently Asked Questions ==

= Where should I submit my support request? =

I preferably take support requests as [issues on GitHub](https://github.com/felixarntz/wp-network-roles/issues), so I would appreciate if you created an issue for your request there. However, if you don't have an account there and do not want to sign up, you can of course use the [wordpress.org support forums](https://wordpress.org/support/plugin/wp-network-roles) as well.

= How can I contribute to the plugin? =

If you're a developer and you have some ideas to improve the plugin or to solve a bug, feel free to raise an issue or submit a pull request in the [GitHub repository for the plugin](https://github.com/felixarntz/wp-network-roles).

You can also contribute to the plugin by translating it. Simply visit [translate.wordpress.org](https://translate.wordpress.org/projects/wp-plugins/wp-network-roles) to get started.

== Changelog ==

= 1.0.0 =
* First stable version

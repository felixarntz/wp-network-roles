# WP Network Roles

Proof-of-Concept for implementing a real network-wide role system in WordPress Core.

## What it does

* introduces classes `WP_Network_Role` and `WP_Network_Roles` for managing network-wide roles
* enhances the `WP_User` class (by providing a `WP_User_With_Network_Roles` class which would become part of `WP_User` if the functionality was in Core)
* uses user meta to store network-wide roles and capabilities (in a similar fashion like it stores site roles and capabilities)
* allows to query users for specific network roles or whether they are part of a network
* introduces one initial network role "administrator"
* automatically migrates the "super admins" stored in the network option into the user meta-based system
* supports the WP Multi Network plugin when switching networks

## How to install

It's recommended to use this as a must-use plugin. Download the ZIP and extract its contents directly into the `wp-content/mu-plugins` directory.

## Some background information

The long-term plan is to only use the currently existing super-admin functionality for special access to everything (by providing the super admins with the `$super_admins` global). For network administrators (and possibly global administrators) dedicated role systems, similar to how they exist for site administrators, should be implemented. This plugin is a first take on this.

See https://wordpress.slack.com/archives/core-multisite/p1470762377000454 for some discussion on the topic

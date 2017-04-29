<?php
/**
 * Plugin Name:     BP Emails for BBP
 * Plugin URI:      https://github.com/thebrandonallen/bp-emails-for-bbp
 * Description:     Send bbPress forum and topic subscription emails using Buddypress' email API.
 * Author:          Brandon Allen
 * Author URI:      https://github.com/thebrandonallen
 * Text Domain:     bp-emails-for-bbp
 * Domain Path:     /languages
 * Version:         0.2.0
 *
 * @package         BP_Emails_For_BBP
 */

/*
	Copyright (C) 2016-2017  Brandon Allen  (email : plugins ([at]) brandonallen ([dot]) me)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

	https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

// Exit if access directly.
defined( 'ABSPATH' ) || exit;

/**
 * Loads BP Emails for BBP if the minimum requirements are met.
 *
 * @since 0.2.0
 *
 * @return void
 */
function bp_emails_for_bbp_loader() {

	// Check for compatible versions of BuddyPress and bbPress.
	if ( bpebbp_compatability_check() ) {
		add_action( 'admin_notices', 'bpebbp_admin_notices' );
		return;
	}

	// Include the necessary files.
	bpebbp_includes();

	// Initialize BP Emails for BBP.
	BP_Emails_For_BBP::get_instance();

	// Maybe load the admin.
	bpebbp_load_admin();
}
add_action( 'plugins_loaded', 'bp_emails_for_bbp_loader', 20 );

/**
 * Check if the current install meets the minimum requirements.
 *
 * @since 0.2.0
 *
 * @return array
 */
function bpebbp_compatability_check() {

	static $errors;

	if ( null !== $errors ) {
		return $errors;
	}

	// Set some default variables.
	$errors = array();

	// Check for bbPress 2.5+.
	if ( ! function_exists( 'bbp_get_version' ) || ! version_compare( bbp_get_version(), '2.5', '>=' ) ) {
		$errors[] = esc_html__( 'You must be using bbPress 2.5 or greater.', 'bp-emails-for-bbp' );
	}

	// Check for BuddyPress 2.5+.
	if ( ! function_exists( 'bp_send_email' ) ) {
		$errors[] = esc_html__( 'You must be using BuddyPress 2.5 or greater.', 'bp-emails-for-bbp' );
	}

	return $errors;
}

/**
 * Output admin notices.
 *
 * @since 0.2.0
 *
 * @return void
 */
function bpebbp_admin_notices() {

	// Bail if there are no error messages.
	$errors = bpebbp_compatability_check();
	if ( ! $errors ) {
		return;
	}

	// Bail if the user can't install plugins.
	if ( ! current_user_can( 'install_plugins' ) ) {
		return;
	}

	// Set up the message.
	$messages  = esc_html__( "Yikes! Looks like some things aren't right. You should deactivate BP Emails for BBP, or fix the following issues.", 'bp-emails-for-bbp' );
	$messages .= ' <strong><em>' . implode( '</em></strong>  <strong><em>', $errors ) . '</em></strong>';

	// Output the message.
	printf( '<div id="message" class="notice notice-error"><p>%s</p></div>', $messages );
}

/**
 * Include our base files.
 *
 * @since 0.2.0
 */
function bpebbp_includes() {
	$dir = dirname( __FILE__ );
	require $dir . '/classes/class-bp-emails-for-bbp.php';
	require $dir . '/classes/class-wp-async-task.php';
	require $dir . '/classes/class-bpebbp-async-new-reply-email.php';
	require $dir . '/classes/class-bpebbp-async-new-topic-email.php';
}

/**
 * Conditionally loads the admin.
 *
 * @since 0.2.0
 *
 * @return void
 */
function bpebbp_load_admin() {

	// Bail if we're not in the admin.
	if ( ! is_admin() ) {
		return;
	}

	require dirname( __FILE__ ) . '/class-bpebbp-admin.php';
	BPEBBP_Admin::get_instance();
}

/**
 * Load the text domain.
 *
 * @since 0.2.0
 */
function bpebbp_load_textdomain() {
	load_plugin_textdomain( 'bp-emails-for-bbp', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'bpebbp_load_textdomain' );

<?php
/**
 * Uninstalls BP Emails for BBP.
 *
 * @package    BP_Emails_For_BBP
 * @subpackage Uninstall
 */

// Exit if access directly.
defined( 'ABSPATH' ) || exit;

/**
 * The BP Emails for BBP uninstall routine.
 *
 * Removes the BP Emails for BBP situation terms, and any emails with the
 * corresponding email id in meta.
 *
 * @since 0.1.0
 */
function bpebbp_uninstall() {

	// Attempt to remove our database version.
	if ( function_exists( 'bp_delete_option' ) ) {
		bp_delete_option( '_bpebbp_db_version' );
	} else {
		delete_option( '_bpebbp_db_version' );
	}

	// We can't assume that BuddyPress is installed, but we should make every
	// effort to get the correct taxonomy type.
	$tax_type = apply_filters( 'bp_email_tax_type', 'bp-email-type' );
	$tax_type = apply_filters( 'bp_get_email_tax_type', $tax_type );

	$email_ids = array( 'bpebbp-new-forum-topic', 'bpebbp-new-forum-reply' );

	foreach ( $email_ids as $email_id ) {

		$term = term_exists( $email_id, $tax_type );
		if ( ! empty( $term['term_id'] ) ) {

			// The situation exists, so check for attached emails.
			$posts = get_objects_in_term( $term['term_id'], $tax_type );

			// Delete our emails, as they are no longer needed.
			if ( ! is_wp_error( $posts ) && ! empty( $posts ) ) {
				foreach ( $posts as $post_id ) {
					$meta = get_post_meta( $post_id, 'bpebbp_email_id', true );
					if ( $email_id === $meta ) {
						wp_trash_post( $post_id );
					}
				}
			}

			// Delete the situation.
			wp_delete_term( $term['term_id'], $tax_type );
		}
	}
}
bpebbp_uninstall();

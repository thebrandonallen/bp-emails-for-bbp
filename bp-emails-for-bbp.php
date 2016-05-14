<?php
/**
 * Plugin Name:     BP Emails for BBP
 * Plugin URI:      https://github.com/thebrandonallen/bp-emails-for-bbp
 * Description:     Send bbPress forum and topic subscription emails using Buddypress' email API.
 * Author:          Brandon Allen
 * Author URI:      https://github.com/thebrandonallen
 * Text Domain:     bp-emails-for-bbp
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         BP_Emails_For_BBP
 */

// Exit if access directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BP_Emails_For_BBP' ) ) {

	/**
	 * The BP Emails for BBP class.
	 *
	 * @since 0.1.0
	 */
	class BP_Emails_For_BBP {

		/**
		 * BP Emails for BBP constructor.
		 *
		 * @since 0.1.0
		 */
		public function __construct() {

			// Maybe install emails on activation.
			register_activation_hook( __FILE__, array( $this, 'install_emails' ) );

			// Load the rest of the plugin.
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		/**
		 * Initialize the plugin.
		 *
		 * @since 0.1.0
		 *
		 * @return bool|void
		 */
		public function init() {

			load_plugin_textdomain( 'bp-emails-for-bbp', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );

			// Bail early if dependencies aren't met.
			$errors = $this->check_for_errors();
			if ( ! empty( $errors ) ) {
				return add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			}

			$this->includes();
			$this->setup_actions();
		}

		/**
		 * Include our files.
		 *
		 * @since 0.1.0
		 */
		private function includes() {

			// Yay, for micro-optimizations!
			$dir = dirname( __FILE__ );

			// Load the WP Async Tasks classes.
			require $dir . '/classes/class-wp-async-task.php';
			require $dir . '/classes/class-bpebbp-async-new-reply-email.php';
			require $dir . '/classes/class-bpebbp-async-new-topic-email.php';
		}

		/**
		 * Add our actions and filters.
		 *
		 * @since 0.1.0
		 */
		private function setup_actions() {

			// Install our emails during BP's reinstall routine.
			add_action( 'bp_core_install_emails', array( $this, 'install_emails' ) );

			// Load the forum notifications task, and remove the bbPress action.
			new BPEBBP_Async_New_Topic_Email;
			remove_action( 'bbp_new_topic', 'bbp_notify_forum_subscribers', 11, 4 );
			add_action( 'wp_async_bbp_new_topic', array( $this, 'notify_forum_subscribers' ), 10, 4 );

			// Load the topic notifications task, and remove the bbPress action.
			new BPEBBP_Async_New_Reply_Email;
			remove_action( 'bbp_new_reply', 'bbp_notify_subscribers', 11, 5 ); // Pre-2.5.6.
			remove_action( 'bbp_new_reply', 'bbp_notify_topic_subscribers', 11, 5 );
			add_action( 'wp_async_bbp_new_reply', array( $this, 'notify_topic_subscribers' ), 10, 5 );

			// Filter the unsubscribe link.
			add_filter( 'bp_email_get_property', array( $this, 'filter_unsubscribe_url' ), 10, 4 );
		}

		/**
		 * Checks for dependency errors. BP Emails for BBP requires bbPress and
		 * BuddyPress, and requires that they are both at version 2.5+.
		 *
		 * @since 0.1.0
		 *
		 * @return array
		 */
		private function check_for_errors() {

			// Set some default variables.
			$errors = array();

			// Check for bbPress 2.5+.
			if ( function_exists( 'bbp_get_version' ) ) {

				if ( ! version_compare( bbp_get_version(), '2.5', '>=' ) ) {
					$errors[] = esc_html__( 'You must be using bbPress 2.5 or greater.', 'bp-emails-for-bbp' );
				}
			} else {
				$errors[] = esc_html__( 'bbPress must be installed.', 'bp-emails-for-bbp' );
			}

			// Check for BuddyPress 2.5+.
			if ( function_exists( 'bp_get_version' ) ) {

				if ( ! version_compare( bp_get_version(), '2.5', '>=' ) ) {
					$errors[] = esc_html__( 'You must be using BuddyPress 2.5 or greater.', 'bp-emails-for-bbp' );
				}
			} else {
				$errors[] = esc_html__( 'BuddyPress must be installed.', 'bp-emails-for-bbp' );
			}

			return $errors;
		}

		/**
		 * Outputs the admin notice if all conditions are met.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function admin_notices() {

			// Bail if there are no error messages.
			$errors = $this->check_for_errors();
			if ( empty( $errors ) ) {
				return;
			}

			// Bail if the user can't install plugins.
			if ( ! current_user_can( 'install_plugins' ) ) {
				return;
			}

			// Set up the notice message classes.
			$classes = 'error fade';
			if ( version_compare( $GLOBALS['wp_version'], '4.2', '>=' ) ) {
				$classes = 'notice notice-error';
			}

			// Set up the message.
			$messages  = esc_html__( "Yikes! Looks like some things aren't right. You should deactivate BP Emails for BBP, or fix the following issues.", 'bp-emails-for-bbp' );
			$messages .= ' <strong><em>' . implode( '</em></strong>  <strong><em>', $errors ) . '</em></strong>';

			// Output the message.
			printf( '<div id="message" class="%1$s"><p>%2$s</p></div>', $classes, $messages );
		}

		/**
		 * Sends notification emails for new topics to forum subscribers.
		 *
		 * @since 0.1.0
		 *
		 * @param int   $topic_id       The topic id.
		 * @param int   $forum_id       The forum id.
		 * @param array $anonymous_data Array of anonymous user data.
		 * @param int   $topic_author   The topic author id.
		 *
		 * @return bool True on success, false on failure.
		 */
		public function notify_forum_subscribers( $topic_id = 0, $forum_id = 0, $anonymous_data = false, $topic_author = 0 ) {

			// Bail if subscriptions are turned off.
			if ( ! bbp_is_subscriptions_active() ) {
				return false;
			}

			/* Validation *****************************************************/

			$topic_id = bbp_get_topic_id( $topic_id );
			$forum_id = bbp_get_forum_id( $forum_id );

			/* Topic **********************************************************/

			// Bail if topic is not published.
			if ( ! bbp_is_topic_published( $topic_id ) ) {
				return false;
			}

			// Get topic subscribers and bail if empty.
			$user_ids = bbp_get_forum_subscribers( $forum_id, true );

			// Dedicated filter to manipulate user ID's to send emails to.
			$user_ids = (array) apply_filters( 'bbp_forum_subscription_user_ids', $user_ids );
			if ( empty( $user_ids ) ) {
				return false;
			}

			// Get the topic author id.
			$topic_author = (int) $topic_author;
			if ( empty( $topic_author ) ) {
				$topic_author = bbp_get_topic_author_id( $topic_id );
			}

			// Remove the topic author from the user id list.
			$key = array_search( $topic_author, $user_ids );
			if ( ! empty( $key ) ) {
				unset( $user_ids[ $key ] );
			}

			// Bail if the user ids array is now empty.
			if ( empty( $user_ids ) ) {
				return false;
			}

			// Poster name.
			$topic_author_name = bbp_get_topic_author_display_name( $topic_id );

			/* Mail ***********************************************************/

			// Remove filters from reply content to prevent content from being
			// encoded with HTML entities, wrapped in paragraph tags, etc...
			remove_all_filters( 'bbp_get_topic_content' );

			// Strip tags from text and setup mail data.
			$forum_title   = wp_strip_all_tags( get_post_field( 'post_title', $forum_id ) );
			$forum_url     = esc_url( bbp_get_forum_permalink( $forum_id ) );
			$topic_title   = wp_strip_all_tags( get_post_field( 'post_title', $topic_id ) );
			$topic_url     = esc_url( bbp_get_topic_permalink( $topic_id ) );
			$topic_content = wp_strip_all_tags( bbp_get_topic_content( $topic_id ) );

			$args = array(
				'tokens' => array(
					'forum.title'   => $forum_title,
					'forum.url'     => $forum_url,
					'topic.title'   => $topic_title,
					'topic.url'     => $topic_url,
					'topic.content' => $topic_content,
					'poster.name'   => $topic_author_name,
				),
			);

			// Loop through users.
			foreach ( $user_ids as $user_id ) {

				// Send notification email.
				bp_send_email( 'bpebbp-new-forum-topic', (int) $user_id, $args );
			}

			return true;
		}

		/**
		 * Sends notification emails for new replies to topic subscribers.
		 *
		 * @since 0.1.0
		 *
		 * @param int   $reply_id       The reply id.
		 * @param int   $topic_id       The topic id.
		 * @param int   $forum_id       The forum id.
		 * @param array $anonymous_data Array of anonymous user data.
		 * @param int   $reply_author   The reply author id.
		 *
		 * @return bool True on success, false on failure.
		 */
		public function notify_topic_subscribers( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = false, $reply_author = 0 ) {

			// Bail if subscriptions are turned off.
			if ( ! bbp_is_subscriptions_active() ) {
				return false;
			}

			/* Validation *****************************************************/

			$reply_id = bbp_get_reply_id( $reply_id );
			$topic_id = bbp_get_topic_id( $topic_id );
			$forum_id = bbp_get_forum_id( $forum_id );

			/* Topic **********************************************************/

			// Bail if topic is not published.
			if ( ! bbp_is_topic_published( $topic_id ) ) {
				return false;
			}

			/* Reply **********************************************************/

			// Bail if reply is not published.
			if ( ! bbp_is_reply_published( $reply_id ) ) {
				return false;
			}

			// Get topic subscribers and bail if empty.
			$user_ids = bbp_get_topic_subscribers( $topic_id, true );

			// Dedicated filter to manipulate user ID's to send emails to.
			$user_ids = (array) apply_filters( 'bbp_topic_subscription_user_ids', $user_ids );
			if ( empty( $user_ids ) ) {
				return false;
			}

			// Get the reply author id.
			$reply_author = (int) $reply_author;
			if ( empty( $reply_author ) ) {
				$reply_author = bbp_get_reply_author_id( $reply_id );
			}

			// Remove the reply author from the user id list.
			$key = array_search( $reply_author, $user_ids );
			if ( ! empty( $key ) ) {
				unset( $user_ids[ $key ] );
			}

			// Bail if the user ids array is now empty.
			if ( empty( $user_ids ) ) {
				return false;
			}

			// Poster name.
			$reply_author_name = bbp_get_reply_author_display_name( $reply_id );

			/* Mail ***********************************************************/

			// Remove filters from reply content and topic title to prevent
			// content from being encoded with HTML entities, wrapped in
			// paragraph tags, etc...
			remove_all_filters( 'bbp_get_reply_content' );

			// Strip tags from text and setup mail data.
			$forum_title   = wp_strip_all_tags( get_post_field( 'post_title', $forum_id ) );
			$forum_url     = esc_url( bbp_get_forum_permalink( $forum_id ) );
			$topic_title   = wp_strip_all_tags( get_post_field( 'post_title', $topic_id ) );
			$topic_url     = esc_url( bbp_get_topic_permalink( $topic_id ) );
			$reply_content = wp_strip_all_tags( bbp_get_reply_content( $reply_id ) );
			$reply_url     = esc_url( bbp_get_reply_url( $reply_id ) );

			$args = array(
				'tokens' => array(
					'forum.title'   => $forum_title,
					'forum.url'     => $forum_url,
					'topic.title'   => $topic_title,
					'topic.url'     => $topic_url,
					'reply.url'     => $reply_url,
					'reply.content' => $reply_content,
					'poster.name'   => $reply_author_name,
				),
			);

			// Loop through users.
			foreach ( $user_ids as $user_id ) {

				// Send notification email.
				bp_send_email( 'bpebbp-new-forum-reply', (int) $user_id, $args );
			}

			return true;
		}

		/**
		 * Get a list of emails for populating the email post type.
		 *
		 * @since 0.1.0
		 *
		 * @return array
		 */
		public function get_bp_email_schema() {
			return array(
				'bpebbp-new-forum-topic' => array(
					/* translators: do not remove {} brackets or translate its contents. */
					'post_title' => __( '[{{{site.name}}}] {{topic.title}}', 'bp-emails-for-bbp' ),
					/* translators: do not remove {} brackets or translate its contents. */
					'post_content' => __( "{{poster.name}} started a new topic <a href=\"{{topic.url}}\">{{topic.title}}</a> in the forum <a href=\"{{forum.url}}\">{{forum.title}}</a>:\n\n<blockquote>{{topic.content}}</blockquote>", 'bp-emails-for-bbp' ),
					/* translators: do not remove {} brackets or translate its contents. */
					'post_excerpt' => __( "{{poster.name}} started a new topic {{topic.title}} in the forum {{forum.title}}:\n\n{{topic.content}}\n\nTopic Link: {{topic.url}}", 'bp-emails-for-bbp' ),
				),
				'bpebbp-new-forum-reply' => array(
					/* translators: do not remove {} brackets or translate its contents. */
					'post_title' => __( '[{{{site.name}}}] Re: {{topic.title}}', 'bp-emails-for-bbp' ),
					/* translators: do not remove {} brackets or translate its contents. */
					'post_content' => __( "{{poster.name}} replied to the topic <a href=\"{{topic.url}}\">{{topic.title}}</a> in the forum <a href=\"{{forum.url}}\">{{forum.title}}</a>:\n\n<blockquote>{{reply.content}}</blockquote>", 'bp-emails-for-bbp' ),
					/* translators: do not remove {} brackets or translate its contents. */
					'post_excerpt' => __( "{{poster.name}} replied to the topic {{topic.title}} in the forum {{forum.title}}:\n\n{{reply.content}}\n\nPost Link: {{reply.url}}", 'bp-emails-for-bbp' ),
				),
			);
		}

		/**
		 * Get a list of emails for populating email type taxonomy terms.
		 *
		 * @since 0.1.0
		 *
		 * @return array
		 */
		public function get_bp_email_type_schema() {
			return array(
				'bpebbp-new-forum-topic' => __( 'A user creates a new forum topic. (bbPress)', 'bp-emails-for-bbp' ),
				'bpebbp-new-forum-reply' => __( 'A user replies to a forum topic. (bbPress)', 'bp-emails-for-bbp' ),
			);
		}

		/**
		 * Adds the BP Emails for BBP default emails.
		 *
		 * @since 0.1.0
		 *
		 * @return void
		 */
		public function install_emails() {

			// Bail if errors exist on installation.
			if ( 'activate_' . plugin_basename( __FILE__ ) === current_filter() ) {

				$errors = $this->check_for_errors();
				if ( ! empty( $errors ) ) {
					return;
				}
			}

			$defaults = array(
				'post_status' => 'publish',
				'post_type' => bp_get_email_post_type(),
			);

			$emails       = $this->get_bp_email_schema();
			$descriptions = $this->get_bp_email_type_schema();

			$tax_type = bp_get_email_tax_type();

			// Add these emails to the database.
			foreach ( $emails as $id => $email ) {

				// If the situation email exists, move on.
				if ( $this->situation_maybe_exists( $id ) ) {
					continue;
				}

				$post_id = wp_insert_post( bp_parse_args( $email, $defaults, 'install_email_' . $id ) );
				if ( ! $post_id ) {
					continue;
				}

				// Breadcrumb to find our way back home.
				update_post_meta( $post_id, 'bpebbp_email_id', $id );

				$tt_ids = wp_set_object_terms( $post_id, $id, $tax_type );
				foreach ( $tt_ids as $tt_id ) {
					$term = get_term_by( 'term_taxonomy_id', (int) $tt_id, $tax_type );
					wp_update_term( (int) $term->term_id, $tax_type, array(
						'description' => $descriptions[ $id ],
					) );
				}
			}

			/**
			 * Fires after BP Emails for BBP adds the posts for its emails.
			 *
			 * @since 0.1.0
			 */
			do_action( 'bpebbp_install_emails' );
		}

		/**
		 * Checks if the passed situation has already been added.
		 *
		 * The cheapest method to check for the existence of our emails is to
		 * first check for the existence of our situation terms. We then check
		 * to see if an email is attached to that term. While it's unlikely that
		 * one will exist without the other, they are not mutually exclusive.
		 *
		 * This method allows `BP_Emails_For_BBP::install_emails` to perform
		 * double duty, and operate on plugin activation and during BP's email
		 * reinstall routine.
		 *
		 * @since 0.1.0
		 *
		 * @param string $email_id The email situation id.
		 *
		 * @return bool
		 */
		private function situation_maybe_exists( $email_id = '' ) {

			// Default to not installed.
			$retval = false;

			if ( in_array( $email_id, array( 'bpebbp-new-forum-topic', 'bpebbp-new-forum-reply' ), true ) ) {

				$tax_type = bp_get_email_tax_type();

				// Check for the existence of the supplied situation.
				$term = term_exists( $email_id, $tax_type );

				// If the situation exists, check for attached emails.
				if ( ! empty( $term['term_id'] ) ) {
					$posts  = get_objects_in_term( $term['term_id'], $tax_type );
					$retval = ( ! is_wp_error( $posts ) && ! empty( $posts ) );
				}
			}

			return $retval;
		}

		/**
		 * Filters the BP email tokens to set the `unsubscribe` token to the
		 * recipient's forum/topic unsubscribe link.
		 *
		 * @since 0.1.0
		 *
		 * @param array    $tokens        The BP Email tokens array.
		 * @param string   $property_name The BP Email property name.
		 * @param string   $transform     The BP Email property transform type.
		 * @param BP_Email $bp_email      The BP_Email object.
		 *
		 * @return array
		 */
		public function filter_unsubscribe_url( $tokens = array(), $property_name = '', $transform = '', $bp_email = null ) {

			// Only filter the `tokens` property.
			if ( 'tokens' !== $property_name ) {
				return $tokens;
			}

			// Bail if we don't have a valid `BP_Email` instance.
			if ( ! $bp_email instanceof BP_Email ) {
				return $tokens;
			}

			// Attempt to retrieve an accurate unsubscribe url.
			if ( isset( $tokens['reply.url'] ) && ! empty( $tokens['topic.url'] ) ) {
				$tokens['unsubscribe'] = $tokens['topic.url'];
			} elseif ( ! empty( $tokens['forum.url'] ) ) {
				$tokens['unsubscribe'] = $tokens['forum.url'];
			} else {

				$recipient = $bp_email->get_to();
				if ( $recipient ) {
					$recipient = array_shift( $recipient );
					$user_obj  = $recipient->get_user( 'search-email' );

					if ( ! $user_obj && $tokens['recipient.email'] ) {
						$user_obj = get_user_by( 'email', $tokens['recipient.email'] );
					}

					if ( $user_obj ) {
						// Unsubscribe link.
						$tokens['unsubscribe'] = esc_url( bbp_get_subscriptions_permalink( $user_obj->ID ) );
					}
				}
			}

			return $tokens;
		}
	}

	// Initialize the BP Emails for BBP class.
	new BP_Emails_For_BBP;
} // End class_exists check.

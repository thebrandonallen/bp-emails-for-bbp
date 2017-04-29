<?php
/**
 * The Main BP Emails for BBP class.
 *
 * @package BP_Emails_For_BBP
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
		 * The BP Emails for BBP version.
		 *
		 * @since 0.2.0
		 *
		 * @var string
		 */
		const VERSION = '0.2.0';

		/**
		 * The BP Emails for BBP database version.
		 *
		 * @since 0.2.0
		 *
		 * @var int
		 */
		const DB_VERSION = 20;

		/**
		 * The BP Emails for BBP instance.
		 *
		 * @since 0.2.0
		 *
		 * @var BP_Emails_For_BBP
		 */
		protected static $instance;

		/**
		 * Provides access to a single instance of `BP_Emails_For_BBP` using the
		 * singleton pattern.
		 *
		 * @since 0.2.0
		 *
		 * @return BP_Emails_For_BBP
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self;
			}
			return self::$instance;
		}

		/**
		 * BP Emails for BBP constructor.
		 *
		 * @since 0.1.0
		 */
		public function __construct() {
			$this->setup_actions();
		}

		/**
		 * Add our actions and filters.
		 *
		 * @since 0.1.0
		 */
		private function setup_actions() {

			// Load the forum notifications task, and remove the bbPress action.
			new BPEBBP_Async_New_Topic_Email();
			remove_action( 'bbp_new_topic', 'bbp_notify_forum_subscribers', 11, 4 );
			add_action( 'wp_async_bbp_new_topic', array( $this, 'notify_forum_subscribers' ), 10, 4 );

			// Load the topic notifications task, and remove the bbPress action.
			new BPEBBP_Async_New_Reply_Email();
			remove_action( 'bbp_new_reply', 'bbp_notify_subscribers', 11, 5 ); // Pre-2.5.6.
			remove_action( 'bbp_new_reply', 'bbp_notify_topic_subscribers', 11, 5 );
			add_action( 'wp_async_bbp_new_reply', array( $this, 'notify_topic_subscribers' ), 10, 5 );

			// Filter the unsubscribe link.
			add_filter( 'bp_email_get_property', array( $this, 'filter_unsubscribe_url' ), 10, 4 );
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
			$user_ids = array_filter( array_map( 'intval', $user_ids ) );
			if ( empty( $user_ids ) ) {
				return false;
			}

			// Get the topic author id.
			$topic_author = (int) $topic_author;
			if ( empty( $topic_author ) ) {
				$topic_author = bbp_get_topic_author_id( $topic_id );
			}

			// Remove the topic author from the user id list.
			$key = array_search( $topic_author, $user_ids, true );
			if ( false !== $key ) {
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
			$user_ids = array_filter( array_map( 'intval', $user_ids ) );
			if ( empty( $user_ids ) ) {
				return false;
			}

			// Get the reply author id.
			$reply_author = (int) $reply_author;
			if ( empty( $reply_author ) ) {
				$reply_author = bbp_get_reply_author_id( $reply_id );
			}

			// Remove the reply author from the user id list.
			$key = array_search( $reply_author, $user_ids, true );
			if ( false !== $key ) {
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
} // End if().

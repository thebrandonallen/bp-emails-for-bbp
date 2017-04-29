<?php
/**
 * The BP Emails for BBP Admin class.
 *
 * @package BP_Emails_For_BBP
 * @subpackage Admin
 */

// Exit if access directly.
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'BPEBBP_Admin' ) ) {

	/**
	 * The BP Emails for BBP class.
	 *
	 * @since 0.2.0
	 */
	class BPEBBP_Admin {

		/**
		 * The BP Emails for BBP instance.
		 *
		 * @since 0.2.0
		 *
		 * @var BP_Emails_For_BBP
		 */
		protected static $instance;

		/**
		 * Whether the current request needs emails installed.
		 *
		 * @since 0.2.0
		 *
		 * @var bool
		 */
		public $install = false;

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
		 * BP Emails for BBP Admin constructor.
		 *
		 * @since 0.2.0
		 */
		public function __construct() {
			$this->setup_actions();
		}

		/**
		 * Add our actions and filters.
		 *
		 * @since 0.2.0
		 */
		private function setup_actions() {

			// Install emails on activation.
			add_action( 'admin_init', array( $this, 'install_emails' ) );

			// Install our emails during BP's reinstall routine.
			add_action( 'bp_core_install_emails', array( $this, 'reinstall_emails' ) );
		}

		/**
		 * Adds the BP Emails for BBP default emails.
		 *
		 * @since 0.2.0
		 *
		 * @return void
		 */
		public function install_emails() {

			if ( ! $this->is_install() ) {
				return;
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
				if ( $this->situation_exists( $id ) ) {
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

			// Store the new database version.
			bp_update_option( '_bpebbp_version', BP_Emails_For_BBP::DB_VERSION );

			/**
			 * Fires after BP Emails for BBP adds the posts for its emails.
			 *
			 * @since 0.1.0
			 */
			do_action( 'bpebbp_install_emails' );
		}

		/**
		 * Reinstalls emails when a user requests reinstallation in Tools > BuddyPress.
		 *
		 * @since 0.2.0
		 */
		public function reinstall_emails() {

			// Set the install variable to true, to force install.
			$this->install = true;

			// Install the emails.
			$this->install_emails();
		}

		/**
		 * Checks if we're in install mode.
		 *
		 * @since 0.2.0
		 *
		 * @return bool
		 */
		public function is_install() {

			// Default to false.
			$retval = false;

			// If the install property is true, or if the version is higher.
			if ( $this->install
				|| ( BP_Emails_For_BBP::DB_VERSION >= (int) bp_get_option( '_bpebbp_version' ) ) ) {

				$retval = true;
			}

			return $retval;
		}

		/**
		 * Get a list of emails for populating the email post type.
		 *
		 * @since 0.2.0
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
		 * @since 0.2.0
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
		 * @since 0.2.0
		 *
		 * @param string $email_id The email situation id.
		 *
		 * @return bool
		 */
		protected function situation_exists( $email_id = '' ) {

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
	}
} // End if().

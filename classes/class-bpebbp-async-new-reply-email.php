<?php
/**
 * The Async New Reply Email Class.
 *
 * @package    BP_Email_For_BBP
 * @subpackage Classes
 *
 * @since 0.1.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WP_Async_Task' ) ) {

	/**
	 * The New Reply Async Email Task.
	 */
	class BPEBBP_Async_New_Reply_Email extends WP_Async_Task {

		/**
		 * This is the argument count for the main action set in the constructor.
		 * It is set to an arbitrarily high value of twenty, but can be
		 * overridden if necessary.
		 *
		 * @var int
		 */
		protected $argument_count = 5;

		/**
		 * Priority to fire intermediate action.
		 *
		 * @var int
		 */
		protected $priority = 11;

		/**
		 * The action.
		 *
		 * @var string
		 */
		protected $action = 'bbp_new_reply';

		/**
		 * Prepare any data to be passed to the asynchronous postback
		 *
		 * The array this function receives will be a numerically keyed array from
		 * func_get_args(). It is expected that you will return an associative array
		 * so that the $_POST values used in the asynchronous call will make sense.
		 *
		 * The array you send back may or may not have anything to do with the data
		 * passed into this method. It all depends on the implementation details and
		 * what data is needed in the asynchronous postback.
		 *
		 * Do not set values for 'action' or '_nonce', as those will get overwritten
		 * later in launch().
		 *
		 * @throws Exception If the postback should not occur for any reason.
		 *
		 * @param array $data The raw data received by the launch method.
		 *
		 * @return array The prepared data.
		 */
		protected function prepare_data( $data ) {
			return array(
				'reply_id'       => isset( $data[0] ) ? $data[0] : 0,
				'topic_id'       => isset( $data[1] ) ? $data[1] : 0,
				'forum_id'       => isset( $data[2] ) ? $data[2] : 0,
				'anonymous_data' => isset( $data[3] ) ? $data[3] : array(),
				'reply_author'   => isset( $data[4] ) ? $data[4] : 0,
			);
		}

		/**
		 * Run the do_action function for the asynchronous postback.
		 *
		 * This method needs to fetch and sanitize any and all data from the
		 * $_POST superglobal and provide them to the do_action call.
		 *
		 * The action should be constructed as "wp_async_task_$this->action".
		 */
		protected function run_action() {
			$reply_id       = ( isset( $_POST['reply_id'] ) && is_numeric( $_POST['reply_id'] ) )
							  ? absint( $_POST['reply_id'] )
							  : 0;
			$topic_id       = ( isset( $_POST['topic_id'] ) && is_numeric( $_POST['topic_id'] ) )
							  ? absint( $_POST['topic_id'] )
							  : 0;
			$forum_id       = ( isset( $_POST['forum_id'] ) && is_numeric( $_POST['forum_id'] ) )
							  ? absint( $_POST['forum_id'] )
							  : 0;
			$anonymous_data = isset( $_POST['anonymous_data'] )
							  ? (array) $_POST['anonymous_data']
							  : array();
			$reply_author   = ( isset( $_POST['reply_author'] ) && is_numeric( $_POST['reply_author'] ) )
							  ? absint( $_POST['reply_author'] )
							  : 0;

			do_action( "wp_async_{$this->action}", $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author );
		}
	}
} // End if().

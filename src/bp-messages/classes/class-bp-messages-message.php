<?php
/**
 * BuddyBoss Messages Classes.
 *
 * @package BuddyBoss\Messages\Classes
 * @since   BuddyPress 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Single message class.
 */
class BP_Messages_Message {

	public static $last_inserted_id;
	/**
	 * ID of the message.
	 *
	 * @var int
	 */
	public $id;

	/**
	 * ID of the message thread.
	 *
	 * @var int
	 */
	public $thread_id;

	/**
	 * ID of the sender.
	 *
	 * @var int
	 */
	public $sender_id;

	/**
	 * Subject line of the message.
	 *
	 * @var string
	 */
	public $subject;

	/**
	 * Content of the message.
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Date the message was sent.
	 *
	 * @var string
	 */
	public $date_sent;

	/**
	 * Thread is hidden.
	 *
	 * @var bool
	 */
	public $is_hidden;

	/**
	 * Mark thread to visible for other participants.
	 *
	 * @var bool
	 */
	public $mark_visible;

	/**
	 * Message recipients.
	 *
	 * @var bool|array
	 */
	public $recipients = false;

	/**
	 * Constructor.
	 *
	 * @param int|null $id Optional. ID of the message.
	 */
	public function __construct( $id = null ) {
		$this->date_sent = bp_core_current_time();
		$this->sender_id = bp_loggedin_user_id();

		if ( ! empty( $id ) ) {
			$this->populate( $id );
		}
	}

	/**
	 * Set up data related to a specific message object.
	 *
	 * @param int $id ID of the message.
	 */
	public function populate( $id ) {

		$message = self::get(
			array(
				'include' => array( $id ),
			)
		);

		$fetched_message = ( isset( $message['messages'][0] ) && is_object( $message['messages'][0] ) ) ? $message['messages'][0] : (object) array();

		if ( $fetched_message ) {
			$this->id        = (int) $fetched_message->id;
			$this->thread_id = (int) $fetched_message->thread_id;
			$this->sender_id = (int) $fetched_message->sender_id;
			$this->subject   = $fetched_message->subject;
			$this->message   = $fetched_message->message;
			$this->date_sent = $fetched_message->date_sent;
		}
	}

	/**
	 * Send a message.
	 *
	 * @return int|bool ID of the newly created message on success, false on failure.
	 */
	public function send() {
		global $wpdb;

		$bp = buddypress();

		$this->sender_id    = apply_filters( 'messages_message_sender_id_before_save', $this->sender_id, $this->id );
		$this->thread_id    = apply_filters( 'messages_message_thread_id_before_save', $this->thread_id, $this->id );
		$this->subject      = apply_filters( 'messages_message_subject_before_save', $this->subject, $this->id );
		$this->message      = apply_filters( 'messages_message_content_before_save', $this->message, $this->id );
		$this->date_sent    = apply_filters( 'messages_message_date_sent_before_save', $this->date_sent, $this->id );
		$this->is_hidden    = apply_filters( 'messages_message_is_hidden_before_save', $this->is_hidden, $this->id );
		$this->mark_visible = apply_filters( 'messages_message_mark_visible_before_save', $this->mark_visible, $this->id );

		/**
		 * Fires before the current message item gets saved.
		 *
		 * Please use this hook to filter the properties above. Each part will be passed in.
		 *
		 * @since BuddyPress 1.0.0
		 *
		 * @param BP_Messages_Message $this Current instance of the message item being saved. Passed by reference.
		 */
		do_action_ref_array( 'messages_message_before_save', array( &$this ) );

		// Make sure we have at least one recipient before sending.
		if ( empty( $this->recipients ) ) {
			return false;
		}

		$new_thread = false;

		// If we have no thread_id then this is the first message of a new thread.
		if ( empty( $this->thread_id ) ) {
			$max_thread      = self::get(
				array(
					'fields'   => 'thread_ids',
					'per_page' => 1,
					'page'     => 1,
				)
			);
			$this->thread_id = ( isset( $max_thread['messages'][0] ) && is_numeric( $max_thread['messages'][0] ) ) ? (int) $max_thread['messages'][0] + 1 : 0 + 1;
			$new_thread      = true;
		}

		// First insert the message into the messages table.
		if ( ! $wpdb->query( $wpdb->prepare( "INSERT INTO {$bp->messages->table_name_messages} ( thread_id, sender_id, subject, message, date_sent ) VALUES ( %d, %d, %s, %s, %s )", $this->thread_id, $this->sender_id, $this->subject, $this->message, $this->date_sent ) ) ) {
			return false;
		}

		static::$last_inserted_id = $this->id = $wpdb->insert_id;

		$recipient_ids = array();

		if ( $new_thread ) {
			// Add an recipient entry for all recipients.
			foreach ( (array) $this->recipients as $recipient ) {
				$wpdb->query( $wpdb->prepare( "INSERT INTO {$bp->messages->table_name_recipients} ( user_id, thread_id, unread_count ) VALUES ( %d, %d, 1 )", $recipient->user_id, $this->thread_id ) );
				$recipient_ids[] = $recipient->user_id;
			}

			// Add a sender recipient entry if the sender is not in the list of recipients.
			if ( ! in_array( $this->sender_id, $recipient_ids ) ) {
				$wpdb->query( $wpdb->prepare( "INSERT INTO {$bp->messages->table_name_recipients} ( user_id, thread_id ) VALUES ( %d, %d )", $this->sender_id, $this->thread_id ) );
			}

			// Mark Hidden thread for sender if `is_hidden` passed.
			if ( true === $this->is_hidden ) {
				$wpdb->query( $wpdb->prepare( "UPDATE {$bp->messages->table_name_recipients} SET is_hidden = %d WHERE thread_id = %d AND user_id = %d", 1, $this->thread_id, $this->sender_id ) );
			}

		} else {
			// Update the unread count for all recipients.
			$wpdb->query( $wpdb->prepare( "UPDATE {$bp->messages->table_name_recipients} SET unread_count = unread_count + 1, is_deleted = 0 WHERE thread_id = %d AND user_id != %d", $this->thread_id, $this->sender_id ) );

			if ( true === $this->mark_visible ) {
				// Mark the thread to visible for all recipients.
				$wpdb->query( $wpdb->prepare( "UPDATE {$bp->messages->table_name_recipients} SET is_hidden = %d WHERE thread_id = %d AND user_id != %d", 0, $this->thread_id, $this->sender_id ) );
			}
		}

		messages_remove_callback_values();

		// Removed message meta while sending a new message.
		if ( ! empty( $this->id ) && ! is_wp_error( $this->id ) ) {
			bp_messages_delete_meta( $this->id );
		}

		/**
		 * Fires after the current message item has been saved.
		 *
		 * @since BuddyPress 1.0.0
		 *
		 * @param BP_Messages_Message $this Current instance of the message item being saved. Passed by reference.
		 */
		do_action_ref_array( 'messages_message_after_save', array( &$this ) );

		return $this->id;
	}

	/**
	 * Get a list of recipients for a message.
	 *
	 * @return object $value List of recipients for a message.
	 */
	public function get_recipients() {
		global $wpdb;

		$bp = buddypress();

		return $wpdb->get_results( $wpdb->prepare( "SELECT user_id FROM {$bp->messages->table_name_recipients} WHERE thread_id = %d", $this->thread_id ) );
	}

	/** Static Functions **************************************************/

	/**
	 * Get list of recipient IDs from their usernames.
	 *
	 * @param array $recipient_usernames Usernames of recipients.
	 *
	 * @return bool|array $recipient_ids Array of Recepient IDs.
	 */
	public static function get_recipient_ids( $recipient_usernames ) {
		$recipient_ids = false;

		if ( ! $recipient_usernames ) {
			return $recipient_ids;
		}

		if ( is_array( $recipient_usernames ) ) {
			$rec_un_count = count( $recipient_usernames );

			for ( $i = 0, $count = $rec_un_count; $i < $count; ++ $i ) {
				if ( $rid = bp_core_get_userid( trim( $recipient_usernames[ $i ] ) ) ) {
					$recipient_ids[] = $rid;
				}
			}
		}

		/**
		 * Filters the array of recipients IDs.
		 *
		 * @since BuddyPress 2.8.0
		 *
		 * @param array $recipient_ids       Array of recipients IDs that were retrieved based on submitted usernames.
		 * @param array $recipient_usernames Array of recipients usernames that were submitted by a user.
		 */
		return apply_filters( 'messages_message_get_recipient_ids', $recipient_ids, $recipient_usernames );
	}

	/**
	 * Get the ID of the message last sent by the logged-in user for a given thread.
	 *
	 * @param int $thread_id ID of the thread.
	 *
	 * @return int|null ID of the message if found, otherwise null.
	 */
	public static function get_last_sent_for_user( $thread_id ) {

		$query = self::get(
			array(
				'fields'          => 'ids',
				'user_id'         => bp_loggedin_user_id(),
				'include_threads' => array( $thread_id ),
				'page'            => 1,
				'per_page'        => 1,
			)
		);

		return ( isset( $query['messages'][0] ) && is_numeric( $query['messages'][0] ) ) ? (int) $query['messages'][0] : null;
	}

	/**
	 * Check whether a user is the sender of a message.
	 *
	 * @param int $user_id    ID of the user.
	 * @param int $message_id ID of the message.
	 *
	 * @return int|null Returns the ID of the message if the user is the
	 *                  sender, otherwise null.
	 */
	public static function is_user_sender( $user_id, $message_id ) {

		$query = self::get(
			array(
				'fields'  => 'ids',
				'user_id' => $user_id,
				'include' => array( $message_id ),
			)
		);

		return is_numeric( $query['messages'][0] ) ? (int) $query['messages'][0] : $query['messages'][0];
	}

	/**
	 * Get the ID of the sender of a message.
	 *
	 * @param int $message_id ID of the message.
	 *
	 * @return int|null The ID of the sender if found, otherwise null.
	 */
	public static function get_message_sender( $message_id ) {
		global $wpdb;

		$bp = buddypress();

		$query = $wpdb->get_var( $wpdb->prepare( "SELECT sender_id FROM {$bp->messages->table_name_messages} WHERE id = %d", $message_id ) );

		return is_numeric( $query ) ? (int) $query : $query;
	}

	/**
	 * Delete all the message send by user
	 *
	 * @BuddyBoss 1.0.0
	 *
	 * @param int $user_id user id whom message should get deleted
	 */
	public static function delete_user_message( $user_id ) {
		global $wpdb;

		$bp = buddypress();

		// Get the message ids in order to delete their metas.
		$messages_arr = BP_Messages_Message::get(
			array(
				'fields'  => 'ids',
				'user_id' => $user_id,
				'order'   => 'ASC',
			)
		);
		$message_ids  = $messages_arr['messages'];

		// Get the all thread ids for unread messages
		$threads_arr = BP_Messages_Message::get(
			array(
				'fields'  => 'thread_ids',
				'user_id' => $user_id,
				'order'   => 'ASC',
			)
		);
		$thread_ids  = $threads_arr['messages'];

		$subject_deleted_text = apply_filters( 'delete_user_message_subject_text', 'Deleted' );
		$message_deleted_text = '<p> </p>';

		// Delete message meta.
		foreach ( $message_ids as $message_id ) {
			$query = $wpdb->prepare( "UPDATE {$bp->messages->table_name_messages} SET subject= '%s', message= '%s' WHERE id = %d", $subject_deleted_text, $message_deleted_text, $message_id );
			$wpdb->query( $query ); // db call ok; no-cache ok;
			// bp_messages_delete_meta( $message_id );
			bp_messages_update_meta( $message_id, '_gif_raw_data', '' );
			bp_messages_update_meta( $message_id, '_gif_data', '' );
			bp_messages_update_meta( $message_id, 'bp_media_ids', '' );
			bp_messages_update_meta( $message_id, 'bp_messages_deleted', 'yes' );
		}
		// unread theread message.
		if ( ! empty( $thread_ids ) ) {
			$thread_ids = implode( ',', $thread_ids );

			$wpdb->query( "UPDATE {$bp->messages->table_name_recipients} SET unread_count = 0 WHERE thread_id IN ({$thread_ids})" );
		}

		// Delete the thread of user.
		if ( bp_has_message_threads( array( 'user_id' => $user_id, ) ) ) {
			while ( bp_message_threads() ) :
				bp_message_thread();
				$thread_id = bp_get_message_thread_id();
				messages_delete_thread( $thread_id, $user_id );
			endwhile;
		}

		// delete all the meta recipients from user table.
		// $wpdb->query( $wpdb->prepare( "DELETE FROM {$bp->messages->table_name_recipients} WHERE user_id = %d", $user_id ) );
	}

	/**
	 * Get existsing thread which matches the recipients
	 *
	 * @since BuddyBoss 1.0.0
	 *
	 * @param array   $recipient_ids
	 * @param integer $sender
	 */
	public static function get_existing_thread( $recipient_ids, $sender = 0 ) {
		global $wpdb;

		$bp = buddypress();

		// add the sender into the recipient list and order by id ascending
		$recipient_ids[] = $sender;
		$recipient_ids   = array_filter( array_unique( array_values( $recipient_ids ) ) );
		sort( $recipient_ids );

		$results = $wpdb->get_results(
			$sql = $wpdb->prepare(
				"SELECT
				r.thread_id as thread_id,
				GROUP_CONCAT(DISTINCT user_id ORDER BY user_id separator ',') as recipient_list,
				MAX(m.date_sent) AS date_sent
			FROM {$bp->messages->table_name_recipients} r
			INNER JOIN {$bp->messages->table_name_messages} m ON m.thread_id = r.thread_id
			GROUP BY r.thread_id
			HAVING recipient_list = %s
			ORDER BY date_sent DESC
			LIMIT 1
			",
				implode( ',', $recipient_ids )
			)
		);

		if ( ! $results ) {
			return null;
		}

		$thread_id = $results[0]->thread_id;

		if ( ! $is_active_recipient = BP_Messages_Thread::is_thread_recipient( $thread_id, $sender ) ) {
			return null;
		}

		return $thread_id;
	}

	/**
	 * Get existsing threads which matches the recipients
	 *
	 * @since BuddyBoss 1.2.9
	 *
	 * @param array   $recipient_ids
	 * @param integer $sender
	 */
	public static function get_existing_threads( $recipient_ids, $sender = 0 ) {
		global $wpdb;

		$bp = buddypress();

		// add the sender into the recipient list and order by id ascending
		$recipient_ids[] = $sender;
		$recipient_ids   = array_filter( array_unique( array_values( $recipient_ids ) ) );
		sort( $recipient_ids );

		$results = $wpdb->get_results(
			$sql = $wpdb->prepare(
				"SELECT
				r.thread_id as thread_id,
				GROUP_CONCAT(DISTINCT user_id ORDER BY user_id separator ',') as recipient_list,
				MAX(m.date_sent) AS date_sent
			FROM {$bp->messages->table_name_recipients} r
			INNER JOIN {$bp->messages->table_name_messages} m ON m.thread_id = r.thread_id
			GROUP BY r.thread_id
			HAVING recipient_list = %s
			ORDER BY date_sent DESC
			",
				implode( ',', $recipient_ids )
			)
		);

		if ( ! $results ) {
			return null;
		}

		return $results;
	}

	/**
	 * Function to get messages
	 *
	 * @param array $args arguments array.
	 */
	public static function get( $args = array() ) {
		global $wpdb;

		$bp = buddypress();

		$defaults = array(
			'orderby'           => 'date_sent',
			'order'             => 'DESC',
			'per_page'          => null,
			'page'              => null,
			'user_id'           => 0,
			'meta_query'        => false,
			'include'           => false,
			'exclude'           => false,
			'include_threads'   => false,
			'exclude_threads'   => false,
			'meta_key__in'      => false,
			'meta_key__not_in'  => false,
			'update_meta_cache' => true,
			'fields'            => 'all',
			'group_by'          => '',
			'subject'           => '',
			'count_total'       => false,
		);

		$r = bp_parse_args( $args, $defaults, 'bp_messages_message_get' );

		$sql = array(
			'select'     => 'SELECT DISTINCT m.id',
			'from'       => "{$bp->messages->table_name_messages} m",
			'where'      => '',
			'orderby'    => '',
			'pagination' => '',
		);

		if ( 'thread_ids' === $r['fields'] ) {
			$sql['select'] = 'SELECT DISTINCT m.thread_id';
		}

		if ( 'sender_ids' === $r['fields'] ) {
			$sql['select'] = 'SELECT DISTINCT m.sender_id';
		}

		$where_conditions = array();

		$meta_query_sql = self::get_meta_query_sql( $r['meta_query'] );

		if ( ! empty( $meta_query_sql['join'] ) ) {
			$sql['from'] .= $meta_query_sql['join'];
		}

		if ( ! empty( $meta_query_sql['where'] ) ) {
			$where_conditions['meta'] = $meta_query_sql['where'];
		}

		/**
		 * Meta key IN and NOT IN query
		 */

		if ( ! empty( $r['meta_key__in'] ) || ! empty( $r['meta_key__not_in'] ) ) {
			$sql['from'] .= ", {$bp->messages->table_name_meta} mm";
		}

		if ( ! empty( $r['meta_key__in'] ) ) {
			$meta_key_in                 = implode( "','", wp_parse_slug_list( $r['meta_key__in'] ) );
			$where_conditions['meta_in'] = "mm.meta_key IN ('{$meta_key_in}')";
		}

		if ( ! empty( $r['meta_key__not_in'] ) ) {
			$meta_key_not_in                 = implode( "','", wp_parse_slug_list( $r['meta_key__not_in'] ) );
			$where_conditions['meta_not_in'] = "mm.meta_key NOT IN ('{$meta_key_not_in}')";
		}

		if ( ! empty( $r['user_id'] ) ) {
			$where_conditions['user'] = $wpdb->prepare( 'm.sender_id = %d', $r['user_id'] );
		}

		if ( ! empty( $r['include'] ) ) {
			$include                     = implode( ',', wp_parse_id_list( $r['include'] ) );
			$where_conditions['include'] = "m.id IN ({$include})";
		}

		if ( ! empty( $r['exclude'] ) ) {
			$exclude                     = implode( ',', wp_parse_id_list( $r['exclude'] ) );
			$where_conditions['exclude'] = "m.id NOT IN ({$exclude})";
		}

		if ( ! empty( $r['include_threads'] ) ) {
			$include_threads                     = implode( ',', wp_parse_id_list( $r['include_threads'] ) );
			$where_conditions['include_threads'] = "m.thread_id IN ({$include_threads})";
		}

		if ( ! empty( $r['exclude_threads'] ) ) {
			$exclude_threads                     = implode( ',', wp_parse_id_list( $r['exclude_threads'] ) );
			$where_conditions['exclude_threads'] = "m.thread_id NOT IN ({$exclude_threads})";
		}

		// Get the message with not matching subject.
		if ( ! empty( $r['subject'] ) ) {
			$where_conditions['subject'] = $wpdb->prepare( 'm.subject != %s', $r['subject'] );
		}

		/* Order/orderby ********************************************/

		$order   = $r['order'];
		$orderby = $r['orderby'];

		// Sanitize 'order'.
		$order = bp_esc_sql_order( $order );

		/**
		 * Filters the converted 'orderby' term.
		 *
		 * @since BuddyPress 2.1.0
		 *
		 * @param string $value   Converted 'orderby' term.
		 * @param string $orderby Original orderby value.
		 * @param string $value   Parsed 'type' value for the get method.
		 */
		$orderby = apply_filters( 'bp_messages_get_orderby_converted_by_term', self::convert_orderby_to_order_by_term( $orderby ), $orderby );

		$sql['orderby'] = "ORDER BY {$orderby} {$order}";

		if ( ! empty( $r['per_page'] ) && ! empty( $r['page'] ) && - 1 !== $r['per_page'] ) {
			$sql['pagination'] = $wpdb->prepare( 'LIMIT %d, %d', intval( ( $r['page'] - 1 ) * $r['per_page'] ), intval( $r['per_page'] ) );
		}

		/**
		 * Filters the Where SQL statement.
		 *
		 * @since BuddyBoss 1.5.4
		 *
		 * @param array $r                Array of parsed arguments for the get method.
		 * @param array $where_conditions Where conditions SQL statement.
		 */
		$where_conditions = apply_filters( 'bp_messages_get_where_conditions', $where_conditions, $r );

		$where = '';
		if ( ! empty( $where_conditions ) ) {
			$sql['where'] = implode( ' AND ', $where_conditions );
			$where        = "WHERE {$sql['where']}";
		}

		/**
		 * Filters the From SQL statement.
		 *
		 * @since BuddyBoss 1.5.4
		 *
		 * @param array  $r   Array of parsed arguments for the get method.
		 * @param string $sql From SQL statement.
		 */
		$sql['from'] = apply_filters( 'bp_messages_get_join_sql', $sql['from'], $r );

		$paged_messages_sql = "{$sql['select']} FROM {$sql['from']} {$where} {$sql['orderby']} {$sql['pagination']}";


		/**
		 * Filters the pagination SQL statement.
		 *
		 * @since BuddyPress 1.5.0
		 *
		 * @param string $value Concatenated SQL statement.
		 * @param array  $sql   Array of SQL parts before concatenation.
		 * @param array  $r     Array of parsed arguments for the get method.
		 */
		$paged_messages_sql = apply_filters( 'bp_messages_get_paged_messages_sql', $paged_messages_sql, $sql, $r );

		$paged_message_ids = $wpdb->get_col( $paged_messages_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( 'ids' === $r['fields'] || 'thread_ids' === $r['fields'] || 'sender_ids' === $r['fields'] ) {
			// We only want the IDs.
			$paged_messages = array_map( 'intval', $paged_message_ids );
		} else {
			$message_ids_sql = implode( ',', array_map( 'intval', $paged_message_ids ) );
			$paged_messages  = $wpdb->get_results( "SELECT m.* FROM {$bp->messages->table_name_messages} m WHERE m.id IN ({$message_ids_sql})" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$retval = array(
			'messages' => $paged_messages,
			'total'    => 0,
		);

		if ( ! empty( $r['count_total'] ) ) {
			// Find the total number of groups in the results set.
			$total_messages_sql = "SELECT COUNT(DISTINCT m.id) FROM {$sql['from']} $where";

			/**
			 * Filters the SQL used to retrieve total group results.
			 *
			 * @since BuddyPress 1.5.0
			 *
			 * @param string $t_sql     Concatenated SQL statement used for retrieving total group results.
			 * @param array  $total_sql Array of SQL parts for the query.
			 * @param array  $r         Array of parsed arguments for the get method.
			 */
			$total_messages_sql = apply_filters( 'bp_messages_get_total_groups_sql', $total_messages_sql, $sql, $r );

			$total_messages  = (int) $wpdb->get_var( $total_messages_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$retval['total'] = $total_messages;
		}

		return $retval;
	}

	/**
	 * Get the SQL for the 'meta_query' param in BP_Messages_Message::get()
	 *
	 * We use WP_Meta_Query to do the heavy lifting of parsing the
	 * meta_query array and creating the necessary SQL clauses.
	 *
	 * @since BuddyPress 1.8.0
	 *
	 * @param array $meta_query An array of meta_query filters. See the
	 *                          documentation for {@link WP_Meta_Query} for details.
	 *
	 * @return array $sql_array 'join' and 'where' clauses.
	 */
	protected static function get_meta_query_sql( $meta_query = array() ) {
		global $wpdb;

		$sql_array = array(
			'join'  => '',
			'where' => '',
		);

		if ( ! empty( $meta_query ) ) {
			$message_meta_query = new WP_Meta_Query( $meta_query );

			// WP_Meta_Query expects the table name at
			// $wpdb->group.
			$wpdb->messagemeta = buddypress()->messages->table_name_meta;

			$meta_sql           = $message_meta_query->get_sql( 'message', 'm', 'id' );
			$sql_array['join']  = $meta_sql['join'];
			$sql_array['where'] = self::strip_leading_and( $meta_sql['where'] );
		}

		return $sql_array;
	}

	/**
	 * Convert the 'orderby' param into a proper SQL term/column.
	 *
	 * @since BuddyPress 1.8.0
	 *
	 * @param string $orderby Orderby term as passed to get().
	 *
	 * @return string $order_by_term SQL-friendly orderby term.
	 */
	protected static function convert_orderby_to_order_by_term( $orderby ) {
		$order_by_term = '';

		switch ( $orderby ) {
			case 'date_sent':
			default:
				$order_by_term = 'm.date_sent';
				break;
		}

		return $order_by_term;
	}

	/**
	 * Strips the leading AND and any surrounding whitespace from a string.
	 *
	 * Used here to normalize SQL fragments generated by `WP_Meta_Query` and
	 * other utility classes.
	 *
	 * @since BuddyPress 2.7.0
	 *
	 * @param string $s String.
	 *
	 * @return string
	 */
	protected static function strip_leading_and( $s ) {
		return preg_replace( '/^\s*AND\s*/', '', $s );
	}
}

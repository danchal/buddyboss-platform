<?php
/**
 * Connections: User's "Connections" screen handler
 *
 * @package BuddyBoss
 * @subpackage ConnectionsScreens
 * @since BuddyPress 3.0.0
 */

/**
 * Catch and process the Mutual Connections page.
 *
 * @since BuddyBoss 1.0.0
 */
function friends_screen_mutual_friends() {

	/**
	 * Fires before the loading of template for the Mutual Connections page.
	 *
	 * @since BuddyBoss 1.0.0
	 */
	do_action( 'friends_screen_mutual_friends' );

	/**
	 * Filters the template used to display the Mutual Connections page.
	 *
	 * @since BuddyBoss 1.0.0
	 *
	 * @param string $template Path to the mutual connections template to load.
	 */
	bp_core_load_template( apply_filters( 'friends_template_mutual_friends', 'members/single/home' ) );
}
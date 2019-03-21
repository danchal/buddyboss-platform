<?php
/**
 * Memberpress integration helpers
 *
 * @package BuddyBoss\Memberpress
 * @since BuddyBoss 1.0.0
 */

// Exit if accessed directly.
defined('ABSPATH') || exit;

/**
 * Output View Logs link for Integrations
 *
 * @param  {string}  $integration Name of integration to be used in url-param
 * @return  {html}
 */
function mpRenderAnchor($integration) {

	echo wpautop(
		__("<a href=\"admin.php?page=bp-memberships-log&integration=$integration\" style=\"float:right\">View Logs</a>", 'buddyboss')
	);
}

/**
 * Display information when LearnDash is not installed/activated
 *
 * @return {html}
 */
function mpNoLearnDashText($integration) {
	echo sprintf(
		__("BuddyBoss Platform has integration settings for LearnDash-%s. If using LearnDash we add the ability to sync LearnDash groups with social groups, to generate course reports within social groups, and more. If using our BuddyBoss Theme we also include styling for LearnDash.", 'buddyboss'), $integration);

}
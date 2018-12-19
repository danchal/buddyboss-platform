<?php
/**
 * BuddyBoss Core Toolbar.
 *
 * Handles the core functions related to the WordPress Toolbar.
 *
 * @package BuddyBoss
 * @subpackage Core
 * @since BuddyPress 1.0.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Add the secondary BuddyPress area to the my-account menu.
 *
 * @since BuddyPress 1.6.0
 *
 * @global WP_Admin_Bar $wp_admin_bar.
 */
function bp_admin_bar_my_account_root() {
	global $wp_admin_bar;

	// Bail if this is an ajax request.
	if ( !bp_use_wp_admin_bar() || defined( 'DOING_AJAX' ) )
		return;

	// Only add menu for logged in user.
	if ( is_user_logged_in() ) {

		// Add secondary parent item for all BuddyPress components.
		$wp_admin_bar->add_menu( array(
			'parent'    => 'my-account',
			'id'        => 'my-account-buddypress',
			'title'     => __( 'My Account', 'buddyboss' ),
			'group'     => true,
			'meta'      => array(
				'class' => 'ab-sub-secondary'
			)
		) );
	}
}
add_action( 'admin_bar_menu', 'bp_admin_bar_my_account_root', 100 );

/**
 * Handle the Toolbar/BuddyBar business.
 *
 * @since BuddyPress 1.2.0
 */
function bp_core_load_admin_bar() {

	// Show the Toolbar for logged out users.
	if ( ! is_user_logged_in() && (int) bp_get_option( 'hide-loggedout-adminbar' ) != 1 ) {
		show_admin_bar( true );
	}

	// Hide the WordPress Toolbar and show the BuddyBar.
	if ( ! bp_use_wp_admin_bar() ) {
		_doing_it_wrong( __FUNCTION__, __( 'The BuddyBar is no longer supported. Please migrate to the WordPress toolbar as soon as possible.', 'buddyboss' ), '2.1.0' );

		// Load deprecated code if not available.
		if ( ! function_exists( 'bp_core_admin_bar' ) ) {
			require buddypress()->plugin_dir . 'bp-core/deprecated/2.1.php';
		}

		// Keep the WP Toolbar from loading.
		show_admin_bar( false );

		// Actions used to build the BP Toolbar.
		add_action( 'bp_adminbar_logo',  'bp_adminbar_logo'               );
		add_action( 'bp_adminbar_menus', 'bp_adminbar_login_menu',    2   );
		add_action( 'bp_adminbar_menus', 'bp_adminbar_account_menu',  4   );
		add_action( 'bp_adminbar_menus', 'bp_adminbar_thisblog_menu', 6   );
		add_action( 'bp_adminbar_menus', 'bp_adminbar_random_menu',   100 );

		// Actions used to append BP Toolbar to footer.
		add_action( 'wp_footer',    'bp_core_admin_bar', 8 );
		add_action( 'admin_footer', 'bp_core_admin_bar'    );
	}
}
add_action( 'init', 'bp_core_load_admin_bar', 9 );

/**
 * Handle the enqueueing of toolbar CSS.
 *
 * This function exists mostly for backwards compatibility reasons, so anyone
 * previously unhooking this function can continue to do so. It's hooked to
 * the `bp_init` action in `bp-core-actions.php`.
 *
 * @since BuddyPress 1.5.0
 */
function bp_core_load_admin_bar_css() {
	add_action( 'bp_enqueue_scripts',       'bp_core_enqueue_admin_bar_css', 1 );
	add_action( 'bp_admin_enqueue_scripts', 'bp_core_enqueue_admin_bar_css', 1 );
}

/**
 * Enqueue supplemental WordPress Toolbar styling.
 *
 * @since BuddyPress 2.1.0
 *
 * @see bp_core_register_common_styles()
 * @see bp_core_load_admin_bar_css()
 */
function bp_core_enqueue_admin_bar_css() {

	// Bail if not using WordPress's admin bar or it's not showing on this
	// page request.
	if ( ! bp_use_wp_admin_bar() || ! is_admin_bar_showing() ) {
		return;
	}

	// Enqueue the additional adminbar css.
	wp_enqueue_style( 'bp-admin-bar' );
}


/**
 * Replace admin bar "Howdy" text
 * Use proper display name and mention
 *
 * @since BuddyBoss 1.0.0
 */
function bp_core_fix_admin_bar_names( $wp_admin_bar ) {
	$user_id      = get_current_user_id();
	$current_user = wp_get_current_user();

	if ( ! $user_id ) {
		return;
	}

	$avatar = get_avatar( $user_id, 26 );

	// my account
    $wp_admin_bar->add_node( [
        'id' => 'my-account',
        'title' => '<span class="display-name">' . bp_custom_display_name_format( $current_user->display_name, $user_id ) . $avatar . '</span>'
    ] );

	// user info
	$user_info  = get_avatar( $user_id, 64 );
	$user_info .= "<span class='display-name'>". bp_custom_display_name_format( $current_user->display_name, $user_id ) . "</span>";
	$user_info .= "<span class='username'>" . bp_activity_get_user_mentionname( $user_id ) . "</span>";

	$wp_admin_bar->add_menu([
		'id' => 'user-info',
		'title'  => $user_info
	]);
}
add_filter( 'admin_bar_menu', 'bp_core_fix_admin_bar_names', 25 );

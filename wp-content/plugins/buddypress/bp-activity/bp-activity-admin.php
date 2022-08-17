<?php
/**
 * BuddyPress Activity component admin screen.
 *
 * Props to WordPress core for the Comments admin screen, and its contextual
 * help text, on which this implementation is heavily based.
 *
 * @package BuddyPress
 * @since BuddyPress (1.6.0)
 * @subpackage Activity
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

// Include WP's list table class
if ( !class_exists( 'WP_List_Table' ) ) require( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );

// per_page screen option. Has to be hooked in extremely early.
if ( is_admin() && ! empty( $_REQUEST['page'] ) && 'bp-activity' == $_REQUEST['page'] )
	add_filter( 'set-screen-option', 'bp_activity_admin_screen_options', 10, 3 );

/**
 * Register the Activity component admin screen.
 *
 * @since BuddyPress (1.6)
 */
function bp_activity_add_admin_menu() {

	// Add our screen
	$hook = add_menu_page(
		_x( 'Activity', 'Admin Dashbord SWA page title', 'buddypress' ),
		_x( 'Activity', 'Admin Dashbord SWA menu', 'buddypress' ),
		'bp_moderate',
		'bp-activity',
		'bp_activity_admin',
		'div'
	);

	// Hook into early actions to load custom CSS and our init handler.
	add_action( "load-$hook", 'bp_activity_admin_load' );
}
add_action( bp_core_admin_hook(), 'bp_activity_add_admin_menu' );

/**
 * Add activity component to custom menus array.
 *
 * Several BuddyPress components have top-level menu items in the Dashboard,
 * which all appear together in the middle of the Dashboard menu. This function
 * adds the Activity page to the array of these menu items.
 *
 * @since BuddyPress (1.7.0)
 *
 * @param array $custom_menus The list of top-level BP menu items.
 * @return array $custom_menus List of top-level BP menu items, with Activity added
 */
function bp_activity_admin_menu_order( $custom_menus = array() ) {
	array_push( $custom_menus, 'bp-activity' );
	return $custom_menus;
}
add_filter( 'bp_admin_menu_order', 'bp_activity_admin_menu_order' );

/**
 * AJAX receiver for Activity replies via the admin screen.
 *
 * Processes requests to add new activity comments, and echoes HTML for a new
 * table row.
 *
 * @since BuddyPress (1.6.0)
 */
function bp_activity_admin_reply() {
	// Check nonce
	check_ajax_referer( 'bp-activity-admin-reply', '_ajax_nonce-bp-activity-admin-reply' );

	$parent_id = ! empty( $_REQUEST['parent_id'] ) ? (int) $_REQUEST['parent_id'] : 0;
	$root_id   = ! empty( $_REQUEST['root_id'] )   ? (int) $_REQUEST['root_id']   : 0;

	// $parent_id is required
	if ( empty( $parent_id ) )
		die( '-1' );

	// If $root_id not set (e.g. for root items), use $parent_id
	if ( empty( $root_id ) )
		$root_id = $parent_id;

	// Check that a reply has been entered
	if ( empty( $_REQUEST['content'] ) )
		die( __( 'ERROR: Please type a reply.', 'buddypress' ) );

	// Check parent activity exists
	$parent_activity = new BP_Activity_Activity( $parent_id );
	if ( empty( $parent_activity->component ) )
		die( __( 'ERROR: The item you are trying to reply to cannot be found, or it has been deleted.', 'buddypress' ) );

	// @todo: Check if user is allowed to create new activity items
	// if ( ! current_user_can( 'bp_new_activity' ) )
	if ( ! current_user_can( 'bp_moderate' ) )
		die( '-1' );

	// Add new activity comment
	$new_activity_id = bp_activity_new_comment( array(
		'activity_id' => $root_id,              // ID of the root activity item
		'content'     => $_REQUEST['content'],
		'parent_id'   => $parent_id,            // ID of a parent comment
	) );

	// Fetch the new activity item, as we need it to create table markup to return
	$new_activity = new BP_Activity_Activity( $new_activity_id );

	// This needs to be set for the BP_Activity_List_Table constructor to work
	set_current_screen( 'toplevel_page_bp-activity' );

	// Set up an output buffer
	ob_start();
	$list_table = new BP_Activity_List_Table();
	$list_table->single_row( (array) $new_activity );

	// Get table markup
	$response =  array(
		'data'     => ob_get_contents(),
		'id'       => $new_activity_id,
		'position' => -1,
		'what'     => 'bp_activity',
	);
	ob_end_clean();

	// Send response
	$r = new WP_Ajax_Response();
	$r->add( $response );
	$r->send();

	exit();
}
add_action( 'wp_ajax_bp-activity-admin-reply', 'bp_activity_admin_reply' );

/**
 * Handle save/update of screen options for the Activity component admin screen.
 *
 * @since BuddyPress (1.6.0)
 *
 * @param string $value Will always be false unless another plugin filters it
 *        first.
 * @param string $option Screen option name.
 * @param string $new_value Screen option form value.
 * @return string Option value. False to abandon update.
 */
function bp_activity_admin_screen_options( $value, $option, $new_value ) {
	if ( 'toplevel_page_bp_activity_per_page' != $option && 'toplevel_page_bp_activity_network_per_page' != $option )
		return $value;

	// Per page
	$new_value = (int) $new_value;
	if ( $new_value < 1 || $new_value > 999 )
		return $value;

	return $new_value;
}

/**
 * Hide the advanced edit meta boxes by default, so we don't clutter the screen.
 *
 * @since BuddyPress (1.6.0)
 *
 * @param WP_Screen $screen Screen identifier.
 * @return array Hidden Meta Boxes.
 */
function bp_activity_admin_edit_hidden_metaboxes( $hidden, $screen ) {
	if ( empty( $screen->id ) || 'toplevel_page_bp-activity' != $screen->id && 'toplevel_page_bp-activity_network' != $screen->id )
		return $hidden;

	// Hide the primary link meta box by default
	$hidden  = array_merge( (array) $hidden, array( 'bp_activity_itemids', 'bp_activity_link', 'bp_activity_type', 'bp_activity_userid', ) );

	/**
	 * Filters default hidden metaboxes so plugins can alter list.
	 *
	 * @since BuddyPress (1.6.0)
	 *
	 * @param array     $hidden Default metaboxes to hide.
	 * @param WP_Screen $screen Screen identifier.
	 */
	return apply_filters( 'bp_hide_meta_boxes', array_unique( $hidden ), $screen );
}
add_filter( 'default_hidden_meta_boxes', 'bp_activity_admin_edit_hidden_metaboxes', 10, 2 );

/**
 * Set up the Activity admin page.
 *
 * Does the following:
 *   - Register contextual help and screen options for this admin page.
 *   - Enqueues scripts and styles
 *   - Catches POST and GET requests related to Activity
 *
 * @since BuddyPress (1.6.0)
 *
 * @global object $bp BuddyPress global settings.
 * @global BP_Activity_List_Table $bp_activity_list_table Activity screen list table.
 */
function bp_activity_admin_load() {
	global $bp_activity_list_table;

	$bp = buddypress();

	// Decide whether to load the dev version of the CSS and JavaScript
	$min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : 'min.';

	$doaction = bp_admin_list_table_current_bulk_action();

	/**
	 * Fires at top of Activity admin page.
	 *
	 * @since BuddyPress (1.6.0)
	 *
	 * @param string $doaction Current $_GET action being performed in admin screen.
	 */
	do_action( 'bp_activity_admin_load', $doaction );

	// Edit screen
	if ( 'edit' == $doaction && ! empty( $_GET['aid'] ) ) {
		// columns screen option
		add_screen_option( 'layout_columns', array( 'default' => 2, 'max' => 2, ) );

		get_current_screen()->add_help_tab( array(
			'id'      => 'bp-activity-edit-overview',
			'title'   => __( 'Overview', 'buddypress' ),
			'content' =>
				'<p>' . __( 'You edit activities made on your site similar to the way you edit a comment. This is useful if you need to change which page the activity links to, or when you notice that the author has made a typographical error.', 'buddypress' ) . '</p>' .
				'<p>' . __( 'The two big editing areas for the activity title and content are fixed in place, but you can reposition all the other boxes using drag and drop, and can minimize or expand them by clicking the title bar of each box. Use the Screen Options tab to unhide more boxes (Primary Item/Secondary Item, Link, Type, Author ID) or to choose a 1- or 2-column layout for this screen.', 'buddypress' ) . '</p>' .
				'<p>' . __( 'You can also moderate the activity from this screen using the Status box, where you can also change the timestamp of the activity.', 'buddypress' ) . '</p>'
		) );

		get_current_screen()->add_help_tab( array(
			'id'      => 'bp-activity-edit-advanced',
			'title'   => __( 'Item, Link, Type', 'buddypress' ),
			'content' =>
				'<p>' . __( '<strong>Primary Item/Secondary Item</strong> - These identify the object that created the activity. For example, the fields could reference a comment left on a specific site. Some types of activity may only use one, or none, of these fields.', 'buddypress' ) . '</p>' .
				'<p>' . __( '<strong>Link</strong> - Used by some types of activity (e.g blog posts and comments, and forum topics and replies) to store a link back to the original content.', 'buddypress' ) . '</p>' .
				'<p>' . __( '<strong>Type</strong> - Each distinct kind of activity has its own type. For example, <code>created_group</code> is used when a group is created and <code>joined_group</code> is used when a user joins a group.', 'buddypress' ) . '</p>' .
				'<p>' . __( 'For information about when and how BuddyPress uses all of these settings, see the Managing Activity link in the panel to the side.', 'buddypress' ) . '</p>'
		) );

		// Help panel - sidebar links
		get_current_screen()->set_help_sidebar(
			'<p><strong>' . __( 'For more information:', 'buddypress' ) . '</strong></p>' .
			'<p>' . __( '<a href="http://codex.buddypress.org/buddypress-site-administration/managing-activity/">Managing Activity</a>', 'buddypress' ) . '</p>' .
			'<p>' . __( '<a href="http://buddypress.org/support/">Support Forums</a>', 'buddypress' ) . '</p>'
		);

		// Register metaboxes for the edit screen.
		add_meta_box( 'submitdiv',           _x( 'Status', 'activity admin edit screen', 'buddypress' ), 'bp_activity_admin_edit_metabox_status', get_current_screen()->id, 'side', 'core' );
		add_meta_box( 'bp_activity_itemids', _x( 'Primary Item/Secondary Item', 'activity admin edit screen', 'buddypress' ), 'bp_activity_admin_edit_metabox_itemids', get_current_screen()->id, 'normal', 'core' );
		add_meta_box( 'bp_activity_link',    _x( 'Link', 'activity admin edit screen', 'buddypress' ), 'bp_activity_admin_edit_metabox_link', get_current_screen()->id, 'normal', 'core' );
		add_meta_box( 'bp_activity_type',    _x( 'Type', 'activity admin edit screen', 'buddypress' ), 'bp_activity_admin_edit_metabox_type', get_current_screen()->id, 'normal', 'core' );
		add_meta_box( 'bp_activity_userid',  _x( 'Author ID', 'activity admin edit screen', 'buddypress' ), 'bp_activity_admin_edit_metabox_userid', get_current_screen()->id, 'normal', 'core' );

		// Enqueue javascripts
		wp_enqueue_script( 'postbox' );
		wp_enqueue_script( 'dashboard' );
		wp_enqueue_script( 'comment' );

	// Index screen
	} else {
		// Create the Activity screen list table
		$bp_activity_list_table = new BP_Activity_List_Table();

		// per_page screen option
		add_screen_option( 'per_page', array( 'label' => _x( 'Activity', 'Activity items per page (screen options)', 'buddypress' )) );

		// Help panel - overview text
		get_current_screen()->add_help_tab( array(
			'id'      => 'bp-activity-overview',
			'title'   => __( 'Overview', 'buddypress' ),
			'content' =>
				'<p>' . __( 'You can manage activities made on your site similar to the way you manage comments and other content. This screen is customizable in the same ways as other management screens, and you can act on activities using the on-hover action links or the Bulk Actions.', 'buddypress' ) . '</p>' .
				'<p>' . __( 'There are many different types of activities. Some are generated automatically by BuddyPress and other plugins, and some are entered directly by a user in the form of status update. To help manage the different activity types, use the filter dropdown box to switch between them.', 'buddypress' ) . '</p>'
		) );

		// Help panel - moderation text
		get_current_screen()->add_help_tab( array(
			'id'		=> 'bp-activity-moderating',
			'title'		=> __( 'Moderating Activity', 'buddypress' ),
			'content'	=>
				'<p>' . __( 'In the <strong>Activity</strong> column, above each activity it says &#8220;Submitted on,&#8221; followed by the date and time the activity item was generated on your site. Clicking on the date/time link will take you to that activity on your live site. Hovering over any activity gives you options to reply, edit, spam mark, or delete that activity.', 'buddypress' ) . '</p>' .
				'<p>' . __( "In the <strong>
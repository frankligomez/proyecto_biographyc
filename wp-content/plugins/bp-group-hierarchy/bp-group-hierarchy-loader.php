<?php

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

class BP_Groups_Hierarchy_Component extends BP_Groups_Component {
	
	function __construct() {
		parent::start(
			'groups',
			__( 'User Groups', 'buddypress' ),
			BP_PLUGIN_DIR
		);
	}
	
	/**
	 * In BP 1.5, stub the includes function to prevent re-including files
	 * In BP 1.6, call it since we've suppressed the parent invocation
	 */
	function includes( $includes = array() ) {
		
		if( floatval( bp_get_version() ) >= 1.6 ) {
		
			$includes = array(
				'cache',
				'forums',
				'actions',
				'filters',
				'screens',
				'classes',
				'widgets',
				'activity',
				'template',
				'buddybar',
				'adminbar',
				'functions',
				'notifications'
			);
			parent::includes( $includes );
		}
		
	}
		
	/**
	 * A hierarchy-aware copy of the setup_globals function from BP_Groups_Component
	 */
	function setup_globals( $args = array() ) {
		global $bp;

		// Define a slug, if necessary
		if ( !defined( 'BP_GROUPS_SLUG' ) )
			define( 'BP_GROUPS_SLUG', $this->id );

		// Global tables for messaging component
		$global_tables = array(
			'table_name'           => $bp->table_prefix . 'bp_groups',
			'table_name_members'   => $bp->table_prefix . 'bp_groups_members',
			'table_name_groupmeta' => $bp->table_prefix . 'bp_groups_groupmeta'
		);

		// Metadata tables for groups component
		$meta_tables = array(
			'group' => $bp->table_prefix . 'bp_groups_groupmeta',
		);

		// All globals for messaging component.
		// Note that global_tables is included in this array.
		$globals = array(
			'path'                  => BP_PLUGIN_DIR,
			'slug'                  => BP_GROUPS_SLUG,
			'root_slug'             => isset( $bp->pages->groups->slug ) ? $bp->pages->groups->slug : BP_GROUPS_SLUG,
			'has_directory'         => true,
			'notification_callback' => 'groups_format_notifications',
			'search_string'         => __( 'Search Groups...', 'buddypress' ),
			'global_tables'         => $global_tables,
			'meta_tables'           => $meta_tables,
		);
		
		call_user_func(array(get_parent_class(get_parent_class($this)),'setup_globals'), $globals );

		/** Single Group Globals **********************************************/

		// Are we viewing a single group?
		if ( bp_is_groups_component() && $group_id = BP_Groups_Hierarchy::group_exists( bp_current_action() ) ) {
			
			$bp->is_single_item  = true;
			$current_group_class = apply_filters( 'bp_groups_current_group_class', 'BP_Groups_Hierarchy' );

			if ( 'BP_Groups_Hierarchy' == $current_group_class ) {
				$this->current_group = new BP_Groups_Hierarchy( $group_id, 0, array( 'populate_extras' => true ) );
			} else {
				$this->current_group = apply_filters( 'bp_groups_current_group_object', new $current_group_class( $group_id ) );
			}

			// When in a single group, the first action is bumped down one because of the
			// group name, so we need to adjust this and set the group name to current_item.
			$bp->current_item   = bp_current_action();
			$bp->current_action = bp_action_variable( 0 );
			array_shift( $bp->action_variables );

			// Using "item" not "group" for generic support in other components.
			if ( is_super_admin() || ( function_exists( 'bp_current_user_can' ) && bp_current_user_can( 'bp_moderate' ) ) )
				bp_update_is_item_admin( true, 'groups' );
			else
				bp_update_is_item_admin( groups_is_user_admin( bp_loggedin_user_id(), $this->current_group->id ), 'groups' );

			// If the user is not an admin, check if they are a moderator
			if ( !bp_is_item_admin() )
				bp_update_is_item_mod  ( groups_is_user_mod  ( bp_loggedin_user_id(), $this->current_group->id ), 'groups' );

			// Is the logged in user a member of the group?
			if ( ( is_user_logged_in() && groups_is_user_member( bp_loggedin_user_id(), $this->current_group->id ) ) )
				$this->current_group->is_user_member = true;
			else
				$this->current_group->is_user_member = false;

			// Should this group be visible to the logged in user?
			if ( 'public' == $this->current_group->status || $this->current_group->is_user_member )
				$this->current_group->is_visible = true;
			else
				$this->current_group->is_visible = false;

			// If this is a private or hidden group, does the user have access?
			if ( 'private' == $this->current_group->status || 'hidden' == $this->current_group->status ) {
				if ( $this->current_group->is_user_member && is_user_logged_in() || is_super_admin() || ( function_exists( 'bp_current_user_can' ) && bp_current_user_can( 'bp_moderate' ) ) )
					$this->current_group->user_has_access = true;
				else
					$this->current_group->user_has_access = false;
			} else {
				$this->current_group->user_has_access = true;
			}

		// Set current_group to 0 to prevent debug errors
		} else {
			$this->current_group = 0;
		}

		// Illegal group names/slugs
		$this->forbidden_names = apply_filters( 'groups_forbidden_names', array(
			'my-groups',
			'create',
			'invites',
			'send-invites',
			'forum',
			'delete',
			'add',
			'admin',
			'request-membership',
			'members',
			'settings',
			'avatar',
			$this->slug,
			$this->root_slug,
		) );

		// If the user was attempting to access a group, but no group by that name was found, 404
		if ( bp_is_groups_component() && empty( $this->current_group ) && bp_current_action() && ! in_array( bp_current_action(), $this->forbidden_names ) ) {
			bp_do_404();
			return;
		}

		if ( bp_is_groups_component() && !empty( $this->current_group ) ) {

			$this->default_extension = apply_filters( 'bp_groups_default_extension', defined( 'BP_GROUPS_DEFAULT_EXTENSION' ) ? BP_GROUPS_DEFAULT_EXTENSION : 'home' );

			if ( !bp_current_action() ) {
				$bp->current_action = $this->default_extension;
			}

			// Prepare for a redirect to the canonical URL
			$bp->canonical_stack['base_url'] = bp_get_group_permalink( $this->current_group );

			if ( bp_current_action() ) {
				$bp->canonical_stack['action'] = bp_current_action();
			}

			if ( !empty( $bp->action_variables ) ) {
				$bp->canonical_stack['action_variables'] = bp_action_variables();
			}

			// When viewing the default extension, the canonical URL should not have
			// that extension's slug, unless more has been tacked onto the URL via
			// action variables
			if ( bp_is_current_action( $this->default_extension ) && empty( $bp->action_variables ) )  {
				unset( $bp->canonical_stack['action'] );
			}

		}

		// Group access control
		if ( bp_is_groups_component() && !empty( $this->current_group ) ) {
			if ( !$this->current_group->user_has_access ) {

				// Hidden groups should return a 404 for non-members.
				// Unset the current group so that you're not redirected
				// to the default group tab
				if ( 'hidden' == $this->current_group->status ) {
					$this->current_group = 0;
					$bp->is_single_item  = false;
					bp_do_404();
					return;

				// Skip the no_access check on home and membership request pages
				} elseif ( ! in_array( bp_current_action(), apply_filters( 'bp_group_hierarchy_allow_anon_access', array( 'home', 'request-membership', BP_GROUP_HIERARCHY_SLUG ) ) ) ) {
					
					// Off-limits to this user. Throw an error and redirect to the group's home page
					if ( is_user_logged_in() ) {
						bp_core_no_access( array(
							'message'  => __( 'You do not have access to this group.', 'buddypress' ),
							'root'     => bp_get_group_permalink( $bp->groups->current_group ),
							'redirect' => false
						) );

					// User does not have access, and does not get a message
					} else {
						bp_core_no_access();
					}
				}
			}

			// Protect the admin tab from non-admins
			if ( bp_is_current_action( 'admin' ) && !bp_is_item_admin() ) {
				bp_core_no_access( array(
					'message'  => __( 'You are not an admin of this group.', 'buddypress' ),
					'root'     => bp_get_group_permalink( $bp->groups->current_group ),
					'redirect' => false
				) );
			}
		}

		// Preconfigured group creation steps
		$this->group_creation_steps = apply_filters( 'groups_create_group_steps', array(
			'group-details'  => array(
				'name'       => __( 'Details',  'buddypress' ),
				'position'   => 0
			),
			'group-settings' => array(
				'name'       => __( 'Settings', 'buddypress' ),
				'position'   => 10
			)
		) );

		// If avatar uploads are not disabled, add avatar option
		if ( !(int)bp_get_option( 'bp-disable-avatar-uploads' ) ) {
			$this->group_creation_steps['group-avatar'] = array(
				'name'     => __( 'Avatar',   'buddypress' ),
				'position' => 20
			);
		}

		// If friends component is active, add invitations
		if ( bp_is_active( 'friends' ) ) {
			$this->group_creation_steps['group-invites'] = array(
				'name'     => __( 'Invites', 'buddypress' ),
				'position' => 30
			);
		}

		// Groups statuses
		$this->valid_status = apply_filters( 'groups_valid_status', array(
			'public',
			'private',
			'hidden'
		) );

		// Auto join group when non group member performs group activity
		$this->auto_join = defined( 'BP_DISABLE_AUTO_GROUP_JOIN' ) && BP_DISABLE_AUTO_GROUP_JOIN ? false : true;
	}
}

/**
 * BP 1.6-style init function -- see -filters file for how it's used in BP 1.5
 */
function bp_setup_groups_hierarchy() {
	global $bp;
	$bp->groups = new BP_Groups_Hierarchy_Component();
}

?>
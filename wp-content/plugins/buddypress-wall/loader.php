<?php
/*
Plugin Name: BuddyPress Wall
Plugin URI: 
Description: Turn your Buddypress Activity Component to a Facebook-style Wall.
Profiles with Facebook-style walls
Version: 0.9.4
Requires at least:  WP 3.4, BuddyPress 1.5
Tested up to: Wordpress 4.1.1 BuddyPress 2.2.1
License: GNU General Public License 2.0 (GPL) http://www.gnu.org/licenses/gpl.html
Author: Meg@Info
Author URI: http://www.ibuddypress.net
Network: true
*/

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/*************************************************************************************************************
 --- BuddyPress Wall 0.9.4 ---
 *************************************************************************************************************/

// Define a constant that can be checked to see if the component is installed or not.
define( 'BP_WALL_IS_INSTALLED', 1 );

// Define a constant that will hold the current version number of the component
// This can be useful if you need to run update scripts or do compatibility checks in the future
define( 'BP_WALL_VERSION', '0.9.4' );

// Define a constant that we can use to construct file paths throughout the component
define( 'BP_WALL_PLUGIN_DIR', dirname( __FILE__ ) );

// Define a constant that we can use to construct the welcome page
define( 'BP_WALL_PLUGIN_FILE_LOADER',  __FILE__ );

// Define a constant that we can use as plugin url
define( 'BP_WALL_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

define ( 'BP_WALL_DB_VERSION', '1.0' );

// Define a constant that we can use as plugin basename
define( 'BP_WALL_PLUGIN_BASENAME',  plugin_basename( __FILE__ ) );

define( 'BP_WALL_PLUGIN_DIR_PATH',  plugin_dir_path( __FILE__ ) );


/**
 * textdomain loader.
 *
 * Checks WP_LANG_DIR for the .mo file first, then the plugin's language folder.
 * Allows for a custom language file other than those packaged with the plugin.
 *
 * @uses load_textdomain() Loads a .mo file into WP
 * @uses load_plugin_textdomain() Loads a .mo file into languages folder on plugin
 */ 
function bp_wall_load_textdomain() {
	$mofile		= sprintf( 'buddypress-wall-%s.mo', get_locale() );
	
	$mofile_global	= trailingslashit( WP_LANG_DIR ) . $mofile;
	$mofile_local	= BP_WALL_PLUGIN_DIR_PATH . 'languages/' . $mofile;

	if ( is_readable( $mofile_global ) ) {
		return load_textdomain( 'bp-wall', $mofile_global );
	} elseif ( is_readable( $mofile_local ) ){
		//return load_plugin_textdomain( 'bp-activity-privacy', false, $mofile_local );
		return load_textdomain( 'bp-wall', $mofile_local );
	}
	else
		return false;
}
add_action( 'plugins_loaded', 'bp_wall_load_textdomain' );


/**
 * Check the config for multisite and activity streams component
 */
function bp_wall_check_config() {
	global $bp;

	$config = array(
		'blog_status'    => false, 
		'network_active' => false, 
		'network_status' => true 
	);
	if ( get_current_blog_id() == bp_get_root_blog_id() ) {
		$config['blog_status'] = true;
	}
	
	$network_plugins = get_site_option( 'active_sitewide_plugins', array() );

	// No Network plugins
	if ( empty( $network_plugins ) )

	// Looking for BuddyPress and bp-activity plugin
	$check[] = $bp->basename;
	$check[] = BP_WALL_PLUGIN_BASENAME;

	// Are they active on the network ?
	$network_active = array_diff( $check, array_keys( $network_plugins ) );
	
	// If result is 1, your plugin is network activated
	// and not BuddyPress or vice & versa. Config is not ok
	if ( count( $network_active ) == 1 )
		$config['network_status'] = false;

	// We need to know if the plugin is network activated to choose the right
	// notice ( admin or network_admin ) to display the warning message.
	$config['network_active'] = isset( $network_plugins[ BP_WALL_PLUGIN_BASENAME ] );

	// if BuddyPress config is different than bp-activity plugin or Activity component is disabled
	if ( !$config['blog_status'] || !$config['network_status'] || !bp_is_active( 'activity' ) ) {

		$warnings = array();

		if ( !bp_core_do_network_admin() && !$config['blog_status'] ) {
			$warnings[] = __( 'Buddypress Activity Streams Component requires to be activated.', 'bp-wall' );
		}

		if ( !bp_core_do_network_admin() && !$config['blog_status'] ) {
			$warnings[] = __( 'Buddypress Wall requires to be activated on the blog where BuddyPress is activated.', 'bp-wall' );
		}

		if ( bp_core_do_network_admin() && !$config['network_status'] ) {
			$warnings[] = __( 'Buddypress Wall and BuddyPress need to share the same network configuration.', 'bp-wall' );
		}

		if ( ! empty( $warnings ) ) :
		?>
		<div id="message" class="error">
			<?php foreach ( $warnings as $warning ) : ?>
				<p><?php echo esc_html( $warning ) ; ?></p>
			<?php endforeach ; ?>
		</div>
		<?php
		endif;

		// Display a warning message in network admin or admin
		add_action( $config['network_active'] ? 'network_admin_notices' : 'admin_notices', $warning );
		
		return false;
	} 
	return true;
}


/* Only load the component if BuddyPress is loaded and initialized. */
function bp_wall_init() {
	//only load the plugin if check config return true
	if( bp_wall_check_config() )
		require( dirname( __FILE__ ) . '/includes/bp-wall-loader.php' );

}
add_action( 'bp_include', 'bp_wall_init' );

/* Put setup procedures to be run when the plugin is activated in the following function */
function bp_wall_activate() {
	// check if buddypress is active
	if ( ! defined( 'BP_VERSION' ) ) {
		//deactivate_plugins( basename( __FILE__ ) ); // Deactivate this plugin
		die( _e( 'You cannot enable BuddyPress Wall, <strong>BuddyPress</strong> is not active. Please install and activate BuddyPress before trying to activate Buddypress Wall.' , 'bp-wall' ) );
	}	
	
	// Add the transient to redirect
	set_transient( '_bp_wall_activation_redirect', true, 30 );
	do_action( 'bp_wall_activation' );
}
register_activation_hook( __FILE__, 'bp_wall_activate' );

/* On deacativation, clean up anything your component has added. */
function bp_wall_deactivate() {
	/* You might want to delete any options or tables that your component created. */
	do_action( 'bp_wall_deactivation' );
}
register_deactivation_hook( __FILE__, 'bp_wall_deactivate' );


function bp_wall_template_filter_init() {
	add_action( 'bp_template_content', 'bp_wall_filter_template_content' );
	add_filter( 'bp_get_template_part', 'bp_wall_template_part_filter', 10, 3 );
 
}
add_action('bp_init', 'bp_wall_template_filter_init');
 
function bp_wall_template_part_filter( $templates, $slug, $name ) {
	if ( 'activity/index' == $slug  ) {
		//return bp_buffer_template_part( 'activity/index-wall' );
		$templates[0] = 'activity/index-wall.php';
	}
	elseif ( 'members/single/home' == $slug  ) {
		$templates[0] = 'members/single/home-wall.php';
		//return bp_buffer_template_part( 'members/single/home-wall' );
	}
	elseif ( 'groups/single/home' == $slug  ) {
		$templates[0] = 'groups/single/home-wall.php';
		//return bp_buffer_template_part( 'members/single/home-wall' );
	}

	return $templates;
	//return bp_get_template_part( 'members/single/plugins' );
  
}
 
function bp_wall_filter_template_content() {
   // bp_buffer_template_part( 'activity/index-wall' );
}
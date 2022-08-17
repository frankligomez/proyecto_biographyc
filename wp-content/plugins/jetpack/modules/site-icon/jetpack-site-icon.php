<?php

/*
Plugin Name: Site Icon
Plugin URL: http://wordpress.com/
Description:  Add a site icon for your website.
Version: 0.1
Author: Automattic

Released under the GPL v.2 license.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/

class Jetpack_Site_Icon {

	public $module      = 'site-icon';
	public static $version     = 1;
	public static $assets_version = 2;

	public static $min_size  = 512; // the minimum size of the blavatar, 512 is the same as wp.com can be overwritten by SITE_ICON_MIN_SIZE
	public static $page_crop = 512; // the size to which to crop the image so that we can display it in the UI nicely

	public static $accepted_file_types = array(
		'image/jpg',
		'image/jpeg',
		'image/gif',
		'image/png'
	);

	public static $site_icon_sizes = array(
		256,
		128,
		80,
		64,
		32,
		16,
	);

	static $instance = false;

	/**
	 * Singleton
	 */
	public static function init() {
		if ( ! self::$instance ){
			self::$instance = new Jetpack_Site_Icon;
			self::$instance->register_hooks();
		}

		return self::$instance;
	}

	private function __construct() {
		self::$min_size = ( defined( 'SITE_ICON_MIN_SIZE' ) && is_int( SITE_ICON_MIN_SIZE ) ) ? SITE_ICON_MIN_SIZE : self::$min_size;
	}

	/**
	 * Register our actions and filters
	 * @return null
	 */
	public function register_hooks(){
		add_action( 'jetpack_modules_loaded', array( $this, 'jetpack_modules_loaded' ) );
		add_action( 'admin_menu',             array( $this, 'admin_menu_upload_site_icon' ) );
		add_filter( 'display_media_states',   array( $this, 'add_media_state' ) );
		add_action( 'admin_init',             array( $this, 'admin_init' ) );
		add_action( 'admin_init',             array( $this, 'delete_site_icon_hook' ) );
		add_action( 'atom_head', array( $this, 'atom_icon' ) );
		add_action( 'rss2_head', array( $this, 'rss2_icon' ) );

		add_action( 'admin_print_styles-options-general.php', array( $this, 'add_general_options_styles' ) );

		// Add the favicon to the front end and backend
		add_action( 'wp_head',           array( $this, 'site_icon_add_meta' ) );
		add_action( 'admin_head',        array( $this, 'site_icon_add_meta' ) );

		add_action( 'delete_option',     array( 'Jetpack_Site_Icon', 'delete_temp_data' ), 10, 1); // used to clean up after itself.
		add_action( 'delete_attachment', array( 'Jetpack_Site_Icon', 'delete_attachment_data' ), 10, 1); // in case user deletes the attachment via
		add_filter( 'get_post_metadata', array( 'Jetpack_Site_Icon', 'delete_attachment_images' ), 10, 4 );
	}

	/**
	 * After all modules have been loaded.
	 */
	public function jetpack_modules_loaded() {
		Jetpack::enable_module_configurable( $this->module );
		Jetpack::module_configuration_load( $this->module, array( $this, 'jetpack_configuration_load' ) );
	}

	/**
	 * Add meta elements to a blog header to light up Blavatar icons recognized by user agents.
	 * @link http://www.whatwg.org/specs/web-apps/current-work/multipage/links.html#rel-icon HTML5 specification link icon
	 *
	 */
	public function site_icon_add_meta() {
		/**
		 * Toggles the Favicon meta elements from being loaded.
		 *
		 * @since 3.2.0
		 *
		 * @param bool Output Site Icon Meta Elements.
		 */
		if ( apply_filters( 'site_icon_has_favicon', false ) ) {
			return;
		}

		$url_114 = jetpack_site_icon_url( null,  114 );
		$url_72  = jetpack_site_icon_url( null,  72 );
		$url_32  = jetpack_site_icon_url( null,  32 );
		if( $url_32 ) {
			echo '<link rel="icon" href="'.esc_url( $url_32 ) .'" sizes="32x32" />' . "\n";
			echo '<link rel="apple-touch-icon-precomposed" href="'. esc_url( $url_114 ) .'">' . "\n";
			// windows tiles
			echo '<meta name="msapplication-TileImage" content="' . esc_url( $url_114 ) . '"/>' . "\n";
		}

	}
	/**
	 * Display icons in RSS2.
	 */
	public function rss2_icon() {
		/** This filter is documented in modules/site-icon/jetpack-site-icon.php */
		if ( apply_filters( 'site_icon_has_favicon', false ) ) {
			return;
		}

		$rss_title = get_wp_title_rss();
		if ( empty( $rss_title ) ) {
			$rss_title = get_bloginfo_rss( 'name' );
		}

		$icon  = jetpack_site_icon_url( null,  32 );
		if( $icon  ) {
			echo '
	<image>
		<url>' . convert_chars( $icon ) . '</url>
		<title>' . $rss_title . '</title>
		<link>' .  get_bloginfo_rss( 'url' ) . '</link>
		<width>32</width>
		<height>32</height>
	</image> '."\n";
		}
	}

	/**
	 * Display icons in atom feeds.
	 *
	 */
	public function atom_icon() {
		/** This filter is documented in modules/site-icon/jetpack-site-icon.php */
		if ( apply_filters( 'site_icon_has_favicon', false ) ) {
			return;
		}

		$url  = jetpack_site_icon_url( null,  32 );
		if( $url  ) {
			echo '
	<icon>' . $url . '</icon> '."\n";
		}
	}

	/**
	 * Add a hidden upload page from people
	 */
	public function admin_menu_upload_site_icon() {
 		$page_hook = add_submenu_page(
 			null,
 			__( 'Site Icon Upload', 'jetpack' ),
 			'',
 			'manage_options',
 			'jetpack-site_icon-upload',
 			array( $this, 'upload_site_icon_page' )
 		);

 		add_action( "admin_head-$page_hook", array( $this, 'upload_balavatar_head' ) );
	}


	/**
	 * Add styles to the General Settings Screen
	 */
	public function add_general_options_styles() {
		wp_enqueue_style( 'site-icon-admin' );
	}
	/**
	 * Add Styles to the Upload UI Page
	 *
	 */
	public function upload_balavatar_head() {

		wp_register_script( 'site-icon-crop',  plugin_dir_url( __FILE__ ). "js/site-icon-crop.js"  , array( 'jquery', 'jcrop' ) ,  self::$assets_version, false);
		if ( isset( $_REQUEST['step'] )  && $_REQUEST['step'] == 2 ) {
			wp_enqueue_script( 'site-icon-crop' );
			wp_enqueue_style( 'jcrop' );
		}
		wp_enqueue_style( 'site-icon-admin' );
	}

	public function add_media_state( $media_states ) {

		if ( jetpack_has_site_icon() ) {
			global $post;

			if( $post->ID == Jetpack_Options::get_option( 'site_icon_id' ) ) {
				$media_states[] = __( 'Site Icon', 'jetpack' );
			}

		}
		return $media_states;
	}

	/**
	 * Direct the user to the Settings -> General
	 */
	public static function jetpack_configuration_load() {
		wp_safe_redirect( admin_url( 'options-general.php#site-icon' ) );
		exit;
	}

	/**
	 * Load on when the admin is initialized
	 */
	public function admin_init() {
		/* register the styles and scripts */
		wp_register_style( 'site-icon-admin' , plugin_dir_url( __FILE__ ). "css/site-icon-admin.css", array(), self::$assets_version );
		// register the settings
		add_settings_section(
		  $this->module,
		  '',
		  array( $this, 'site_icon_settings' ),
		  'general'
		);

		// We didn't have site_icon_url in 3.2 // this could potentially be removed in a year
		if( get_option( 'site_icon_id' ) && ! Jetpack_Options::get_option( 'site_icon_url' ) ) {
			Jetpack_Options::update_option( 'site_icon_id', get_option( 'site_icon_id' ) );
			Jetpack_Options::update_option( 'site_icon_url', jetpack_site_icon_url( get_current_blog_id(), 512 ) );
			delete_option( 'site_icon_id' );
		}
	}

	/**
	 * Checks for permission to delete the site_icon
	 */
	public function delete_site_icon_hook() {
		// Delete the site_icon
		if ( isset( $GLOBALS['plugin_page'] ) && 'jetpack-site_icon-upload' == $GLOBALS['plugin_page'] ) {
			if ( isset( $_GET['action'] )
					&& 'remove' == $_GET['action']
					&& isset( $_GET['nonce'] )
					&& wp_verify_nonce( $_GET['nonce'], 'remove_site_icon' ) ) {

				$site_icon_id = Jetpack_Options::get_option( 'site_icon_id' );
				// Delete the previous Blavatar
				self::delete_site_icon( $site_icon_id, true );
				wp_safe_redirect( admin_url( 'options-general.php#site-icon' ) );
			}
		}
	}

	/**
	 * Add HTML to the General Settings
	 */
	public function site_icon_settings() {
		$upload_blavatar_url = admin_url( 'options-general.php?page=jetpack-site_icon-upload' );

		// lets delete the temp data that we might he holding on to
		self::delete_temporay_data();

		?>
		<div id="site-icon" class="site-icon-shell">
			<h3><?php echo esc_html_e( 'Site Icon', 'jetpack' ); ?></h3>
			<div class="site-icon-content postbox">
				<div class="site-icon-image">
				<?php if( jetpack_has_site_icon() ) {
					echo jetpack_get_site_icon( null, 128 );
					} ?>
				</div>
				<div class="site-icon-meta">

				<?php if ( jetpack_has_site_icon() ) {
					$remove_blavatar_url = admin_url( 'options-general.php?page=jetpack-site_icon-upload' )."&action=remove&nonce=".wp_create_nonce( 'remove_site_icon' ); // this could be an ajax url
					?>
					<p><a href="<?php echo esc_url( $upload_blavatar_url ); ?>" id="site-icon-update" class="button"><?php echo esc_html_e( 'Update Site Icon', 'jetpack'  ); ?></a>
					<a href="<?php echo esc_url( $remove_blavatar_url ); ?>" id="site-icon-remove" ><?php echo esc_html_e( 'Remove Icon', 'jetpack'  ); ?></a> </p>

				<?php } else { ?>

					<a href="<?php echo esc_url( $upload_blavatar_url ); ?>" id="site-icon-update" class="button"><?php echo esc_html_e( 'Add a Site Icon', 'jetpack' ); ?></a>

				<?php } ?>

					<div class="site-icon-info">
						<p><?php echo esc_html_e( 'Site Icon creates a favicon for your site and more.', 'jetpack' ); ?></p>
					</div>

				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Hidden Upload Blavatar page for people that don't like modals
	 */
	public function upload_site_icon_page() { ?>
		<div class="wrap">
			<?php require_once( dirname( __FILE__ ) . '/upload-site-icon.php' ); ?>
		</div>
		<?php
	}

	/**
	 * Select a file admin view
	 */
	public static function select_page() {
		// Display the site_icon form to upload the image
		 ?>
		<form action="<?php echo esc_url( admin_url( 'options-general.php?page=jetpack-site_icon-upload' ) ); ?>" method="post" enctype="multipart/form-data">

			<h2 class="site-icon-title">
			<?php if( jetpack_has_site_icon() ) {
				esc_html_e( 'Update Site Icon', 'jetpack' );
			} else {
				esc_html_e( 'Add Site Icon', 'jetpack' );
			} ?> <span class="small"><?php esc_html_e( 'select a file', 'jetpack' ); ?></span></h2>
			<p><?php esc_html_e( 'Upload a image that you want to use as your site icon. You will be asked to crop it in the next step.', 'jetpack' ); ?></p>


			<p><input name="site-iconfile" id="site-iconfile" type="file" /></p>
			<p><?php esc_html_e( 'The image needs to be at least', 'jetpack' ); ?> <strong><?php echo self::$min_size; ?>px</strong> <?php esc_html_e( 'in both width and height.', 'jetpack' ); ?></p>
			<p class="submit site-icon-submit-form">
				<input name="submit" value="<?php esc_attr_e( 'Upload Image' , 'jetpack' ); ?>" type="submit" class="button button-primary button-large" /><?php printf( __( ' or <a href="%s">Cancel</a> and go back to the settings.' , 'jetpack' ), esc_url( admin_url( 'options-general.php' ) ) ); ?>
				<input name="step" value="2" type="hidden" />

				<?php wp_nonce_field( 'update-site_icon-2', '_nonce' ); ?>
			</p>
		</form>
		<?php
	}

	/**
	 * Crop a the image admin view
	 */
	public static function crop_page() {
		// handle the uploaded image
		$image = self::handle_file_upload( $_FILES['site-iconfile'] );

		// display the image image croppping funcunality
		if( is_wp_error( $image ) ) { ?>
			<div id="message" class="updated error below-h2"><p><?php echo esc_html( $image->get_error_message() ); ?></p></div>
			<?php
			// back to step one
			$_POST = array();
			self::delete_temporay_data();
			self::select_page();
			return;
		}

		$crop_data = get_option( 'site_icon_temp_data' );
		$crop_ration = $crop_data['large_image_data'][0] / $crop_data['resized_image_data'][0]; // always bigger then 1

		// lets make sure that the Javascript ia also loaded
		wp_localize_script( 'site-icon-crop', 'Site_Icon_Crop_Data', self::initial_crop_data( $crop_data['large_image_data'][0] , $crop_data['large_image_data'][1], $crop_data['resized_image_data'][0], $crop_data['resized_image_data'][1] ) );
		?>

		<h2 class="site-icon-title"><?php esc_html_e( 'Site Icon', 'jetpack' ); ?> <span class="small"><?php esc_html_e( 'crop the image', 'jetpack' ); ?></span></h2>
		<div class="site-icon-crop-shell">
			<form action="" method="post" enctype="multipart/form-data">
			<p class="site-icon-submit-form"><input name="submit" value="<?php esc_attr_e( 'Crop Image', 'jetpack'
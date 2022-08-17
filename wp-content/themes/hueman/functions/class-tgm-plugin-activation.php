<?php
/**
 * Plugin installation and activation for WordPress themes.
 *
 * @package   TGM-Plugin-Activation
 * @version   2.4.0
 * @author    Thomas Griffin <thomasgriffinmedia.com>
 * @author    Gary Jones <gamajo.com>
 * @copyright Copyright (c) 2012, Thomas Griffin
 * @license   http://opensource.org/licenses/gpl-2.0.php GPL v2 or later
 * @link      https://github.com/thomasgriffin/TGM-Plugin-Activation
 */

/*
    Copyright 2014 Thomas Griffin (thomasgriffinmedia.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

if ( ! class_exists( 'TGM_Plugin_Activation' ) ) {
    /**
     * Automatic plugin installation and activation library.
     *
     * Creates a way to automatically install and activate plugins from within themes.
     * The plugins can be either pre-packaged, downloaded from the WordPress
     * Plugin Repository or downloaded from a private repository.
     *
     * @since 1.0.0
     *
     * @package TGM-Plugin-Activation
     * @author  Thomas Griffin <thomasgriffinmedia.com>
     * @author  Gary Jones <gamajo.com>
     */
    class TGM_Plugin_Activation {

        /**
         * Holds a copy of itself, so it can be referenced by the class name.
         *
         * @since 1.0.0
         *
         * @var TGM_Plugin_Activation
         */
        public static $instance;

        /**
         * Holds arrays of plugin details.
         *
         * @since 1.0.0
         *
         * @var array
         */
        public $plugins = array();

        /**
         * Name of the querystring argument for the admin page.
         *
         * @since 1.0.0
         *
         * @var string
         */
        public $menu = 'tgmpa-install-plugins';

        /**
         * Default absolute path to folder containing pre-packaged plugin zip files.
         *
         * @since 2.0.0
         *
         * @var string Absolute path prefix to packaged zip file location. Default is empty string.
         */
        public $default_path = '';

        /**
         * Flag to show admin notices or not.
         *
         * @since 2.1.0
         *
         * @var boolean
         */
        public $has_notices = true;

        /**
         * Flag to determine if the user can dismiss the notice nag.
         *
         * @since 2.4.0
         *
         * @var boolean
         */
        public $dismissable = true;

        /**
         * Message to be output above nag notice if dismissable is false.
         *
         * @since 2.4.0
         *
         * @var string
         */
        public $dismiss_msg = '';

        /**
         * Flag to set automatic activation of plugins. Off by default.
         *
         * @since 2.2.0
         *
         * @var boolean
         */
        public $is_automatic = false;

        /**
         * Optional message to display before the plugins table.
         *
         * @since 2.2.0
         *
         * @var string Message filtered by wp_kses_post(). Default is empty string.
         */
        public $message = '';

        /**
         * Holds configurable array of strings.
         *
         * Default values are added in the constructor.
         *
         * @since 2.0.0
         *
         * @var array
         */
        public $strings = array();

        /**
         * Holds the version of WordPress.
         *
         * @since 2.4.0
         *
         * @var int
         */
        public $wp_version;

        /**
         * Adds a reference of this object to $instance, populates default strings,
         * does the tgmpa_init action hook, and hooks in the interactions to init.
         *
         * @since 1.0.0
         *
         * @see TGM_Plugin_Activation::init()
         */
        public function __construct() {

            self::$instance = $this;

            $this->strings = array(
                'page_title'                     => __( 'Install Required Plugins', 'tgmpa' ),
                'menu_title'                     => __( 'Install Plugins', 'tgmpa' ),
                'installing'                     => __( 'Installing Plugin: %s', 'tgmpa' ),
                'oops'                           => __( 'Something went wrong.', 'tgmpa' ),
                'notice_can_install_required'    => _n_noop( 'This theme requires the following plugin: %1$s.', 'This theme requires the following plugins: %1$s.' ),
                'notice_can_install_recommended' => _n_noop( 'This theme recommends the following plugin: %1$s.', 'This theme recommends the following plugins: %1$s.' ),
                'notice_cannot_install'          => _n_noop( 'Sorry, but you do not have the correct permissions to install the %s plugin. Contact the administrator of this site for help on getting the plugin installed.', 'Sorry, but you do not have the correct permissions to install the %s plugins. Contact the administrator of this site for help on getting the plugins installed.' ),
                'notice_can_activate_required'   => _n_noop( 'The following required plugin is currently inactive: %1$s.', 'The following required plugins are currently inactive: %1$s.' ),
                'notice_can_activate_recommended'=> _n_noop( 'The following recommended plugin is currently inactive: %1$s.', 'The following recommended plugins are currently inactive: %1$s.' ),
                'notice_cannot_activate'         => _n_noop( 'Sorry, but you do not have the correct permissions to activate the %s plugin. Contact the administrator of this site for help on getting the plugin activated.', 'Sorry, but you do not have the correct permissions to activate the %s plugins. Contact the administrator of this site for help on getting the plugins activated.' ),
                'notice_ask_to_update'           => _n_noop( 'The following plugin needs to be updated to its latest version to ensure maximum compatibility with this theme: %1$s.', 'The following plugins need to be updated to their latest version to ensure maximum compatibility with this theme: %1$s.' ),
                'notice_cannot_update'           => _n_noop( 'Sorry, but you do not have the correct permissions to update the %s plugin. Contact the administrator of this site for help on getting the plugin updated.', 'Sorry, but you do not have the correct permissions to update the %s plugins. Contact the administrator of this site for help on getting the plugins updated.' ),
                'install_link'                   => _n_noop( 'Begin installing plugin', 'Begin installing plugins' ),
                'activate_link'                  => _n_noop( 'Begin activating plugin', 'Begin activating plugins' ),
                'return'                         => __( 'Return to Required Plugins Installer', 'tgmpa' ),
                'dashboard'                      => __( 'Return to the dashboard', 'tgmpa' ),
                'plugin_activated'               => __( 'Plugin activated successfully.', 'tgmpa' ),
                'activated_successfully'         => __( 'The following plugin was activated successfully:', 'tgmpa' ),
                'complete'                       => __( 'All plugins installed and activated successfully. %1$s', 'tgmpa' ),
                'dismiss'                        => __( 'Dismiss this notice', 'tgmpa' ),
            );

            // Set the current WordPress version.
            global $wp_version;
            $this->wp_version = $wp_version;

            // Announce that the class is ready, and pass the object (for advanced use).
            do_action_ref_array( 'tgmpa_init', array( $this ) );

            // When the rest of WP has loaded, kick-start the rest of the class.
            add_action( 'init', array( $this, 'init' ) );

        }

        /**
         * Initialise the interactions between this class and WordPress.
         *
         * Hooks in three new methods for the class: admin_menu, notices and styles.
         *
         * @since 2.0.0
         *
         * @see TGM_Plugin_Activation::admin_menu()
         * @see TGM_Plugin_Activation::notices()
         * @see TGM_Plugin_Activation::styles()
         */
        public function init() {

            do_action( 'tgmpa_register' );
            // After this point, the plugins should be registered and the configuration set.

            // Proceed only if we have plugins to handle.
            if ( $this->plugins ) {
                $sorted = array();

                foreach ( $this->plugins as $plugin ) {
                    $sorted[] = $plugin['name'];
                }

                array_multisort( $sorted, SORT_ASC, $this->plugins );

                add_action( 'admin_menu', array( $this, 'admin_menu' ) );
                add_action( 'admin_head', array( $this, 'dismiss' ) );
                add_filter( 'install_plugin_complete_actions', array( $this, 'actions' ) );
                add_action( 'switch_theme', array( $this, 'flush_plugins_cache' ) );

                // Load admin bar in the header to remove flash when installing plugins.
                if ( $this->is_tgmpa_page() ) {
                    remove_action( 'wp_footer', 'wp_admin_bar_render', 1000 );
                    remove_action( 'admin_footer', 'wp_admin_bar_render', 1000 );
                    add_action( 'wp_head', 'wp_admin_bar_render', 1000 );
                    add_action( 'admin_head', 'wp_admin_bar_render', 1000 );
                }

                if ( $this->has_notices ) {
                    add_action( 'admin_notices', array( $this, 'notices' ) );
                    add_action( 'admin_init', array( $this, 'admin_init' ), 1 );
                    add_action( 'admin_enqueue_scripts', array( $this, 'thickbox' ) );
                    add_action( 'switch_theme', array( $this, 'update_dismiss' ) );
                }

                // Setup the force activation hook.
                foreach ( $this->plugins as $plugin ) {
                    if ( isset( $plugin['force_activation'] ) && true === $plugin['force_activation'] ) {
                        add_action( 'admin_init', array( $this, 'force_activation' ) );
                        break;
                    }
                }

                // Setup the force deactivation hook.
                foreach ( $this->plugins as $plugin ) {
                    if ( isset( $plugin['force_deactivation'] ) && true === $plugin['force_deactivation'] ) {
                        add_action( 'switch_theme', array( $this, 'force_deactivation' ) );
                        break;
                    }
                }
            }

        }

        /**
         * Handles calls to show plugin information via links in the notices.
         *
         * We get the links in the admin notices to point to the TGMPA page, rather
         * than the typical plugin-install.php file, so we can prepare everything
         * beforehand.
         *
         * WP doesn't make it easy to show the plugin information in the thickbox -
         * here we have to require a file that includes a function that does the
         * main work of displaying it, enqueue some styles, set up some globals and
         * finally call that function before exiting.
         *
         * Down right easy once you know how...
         *
         * @since 2.1.0
         *
         * @global string $tab Used as iframe div class names, helps with styling
         * @global string $body_id Used as the iframe body ID, helps with styling
         * @return null Returns early if not the TGMPA page.
         */
        public function admin_init() {

    
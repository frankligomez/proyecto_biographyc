<?php
/*
Plugin Name: Redirect All Types
Plugin Author: Kyle G.
Plugin URI: http://www.refactord.com/plugins/redirect-all-types
Author URI: http://www.refactord.com
Version: 1.5
Description: This plugin will give you the ability to redirect not only posts and pages, but all custom post types.  It adds a meta box to all post, page, and all custom post types with a field to input a redirect.
*/
if(!class_exists('redirect_all_types')){

    class Redirect_all_types{

        function  __construct() {
            add_action("admin_init", array($this, 'add_meta_boxes'));
            add_action('save_post', array($this, 'save'));
            add_action('get_header', array($this, 'redirect'));
        }

        function add_meta_boxes(){
            global $wpdb;

            $dont_include = array('revision', 'attachment', 'nav_menu_item');

            $post_types = get_post_types();

            foreach($post_types as $type){
                if(!in_array($type['post_type'], $dont_include)){
                    add_meta_box('redirection_meta', 'Redirect', array($this, 'meta_options'), $type, "normal");
                }
            }
        }

        function meta_options(){
            // Use nonce for verification
            wp_nonce_field( plugin_basename( __FILE__ ), 'redirect_all_types' );

            global $post;

            $redirect = get_post_meta($post->ID, '_redirect_url', true);

            echo "<p><label for='redirect'>Redirect URL</label><input style='width:99%;' type='text' name='_redirect_url' value='{$redirect}' /></p>";
        }

        function save(){
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
              return;

            if ( !wp_verify_nonce( $_POST['redirect_all_types'], plugin_basename( __FILE__ ) ) )
              return;

            if ( 'page' == $_POST['post_type'] )
            {
                if ( !current_user_can( 'edit_page', $post_id ) )
                    return;
            }
            else
            {
                if ( !current_user_can( 'edit_post', $post_id ) )
                    return;
            }

            $redirect = $_POST['_redirect_url'];
            $post_id = $_POST['ID'];

            update_post_meta($post_id, '_redirect_url', $redirect, get_post_meta($post_id, '_redirect_url', true));

            return $redirect;
        }

        function redirect(){
            global $post;

            $redirect = get_post_meta($post->ID, '_redirect_url', true);

            if (!empty($redirect) && ( is_single() || is_page() ) ) {
                header('Location: ' . $redirect);
            }
        }
    }
    $redirect_all_types = new Redirect_all_types();
}

<?php
/*
Plugin Name: WP-Members
Plugin URI:  http://rocketgeek.com
Description: WP access restriction and user registration.  For more information on plugin features, refer to <a href="http://rocketgeek.com/plugins/wp-members/users-guide/">the online Users Guide</a>. A <a href="http://rocketgeek.com/plugins/wp-members/quick-start-guide/">Quick Start Guide</a> is also available. WP-Members(tm) is a trademark of butlerblog.com.
Version:     3.0.0.2
Author:      Chad Butler
Author URI:  http://butlerblog.com/
License:     GPLv2
*/


/*  
	Copyright (c) 2006-2015  Chad Butler

	The name WP-Members(tm) is a trademark of butlerblog.com

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

	You may also view the license here:
	http://www.gnu.org/licenses/gpl.html
*/


/*
	A NOTE ABOUT LICENSE:

	While this plugin is freely available and open-source under the GPL2
	license, that does not mean it is "public domain." You are free to modify
	and redistribute as long as you comply with the license. Any derivative 
	work MUST be GPL licensed and available as open source.  You also MUST give 
	proper attribution to the original author, copyright holder, and trademark
	owner.  This means you cannot change two lines of code and claim copyright 
	of the entire work as your own.  The GPL2 license requires that if you
	modify this code, you must clearly indicate what section(s) you have
	modified and you may only claim copyright of your modifications and not
	the body of work.  If you are unsure or have questions about how a 
	derivative work you are developing complies with the license, copyright, 
	trademark, or if you do not understand the difference between
	open source and public domain, contact the original author at:
	http://rocketgeek.com/contact/.


	INSTALLATION PROCEDURE:
	
	For complete installation and usage instructions,
	visit http://rocketgeek.com
*/


// Initialize constants.
define( 'WPMEM_VERSION', '3.0.0.2' );
define( 'WPMEM_DEBUG', false );
define( 'WPMEM_DIR',  plugin_dir_url ( __FILE__ ) );
define( 'WPMEM_PATH', plugin_dir_path( __FILE__ ) );

// Localization.
add_action( 'plugins_loaded', 'wpmem_load_textdomain' );

// Initialize the plugin.
add_action( 'after_setup_theme', 'wpmem_init', 10 );

// Install the plugin.
register_activation_hook( __FILE__, 'wpmem_install' );


/**
 * Initialize WP-Members.
 *
 * The initialization function contains much of what was previously just
 * loaded in the main plugin file. It has been moved into this function
 * in order to allow action hooks for loading the plugin and initializing
 * its features and options.
 *
 * @since 2.9.0
 */
function wpmem_init() {

	// Set the object as global.
	global $wpmem;

	/**
	 * Fires before initialization of plugin options.
	 *
	 * @since 2.9.0
	 */
	do_action( 'wpmem_pre_init' );

	// Load WP_Members class.
	include_once( WPMEM_PATH . 'inc/class-wp-members.php' );
	$wpmem = new WP_Members();

	/**
	 * Fires after main settings are loaded.
	 *
	 * @since 3.0
	 */
	do_action( 'wpmem_settings_loaded' );

	/**
	 * Filter the location and name of the pluggable file.
	 *
	 * @since 2.9.0
	 *
	 * @param string The path to wp-members-pluggable.php.
	 */
	$wpmem_pluggable = apply_filters( 'wpmem_plugins_file', WP_PLUGIN_DIR . '/wp-members-pluggable.php' );

	// Preload any custom functions, if available.
	if ( file_exists( $wpmem_pluggable ) ) {
		include( $wpmem_pluggable );
	}

	// Preload the expiration module, if available.
	$exp_module = ( in_array( 'wp-members-expiration/module.php', get_option( 'active_plugins' ) ) ) ? true : false;
	define( 'WPMEM_EXP_MODULE', $exp_module ); 

	// Load core file.
	include_once( WPMEM_PATH . 'inc/core.php' );

	// Load actions and filters.
	$wpmem->load_hooks();

	// Load shortcodes.
	$wpmem->load_shortcodes();

	// Load fields.
	$wpmem->load_fields();
	
	// Load contants.
	$wpmem->load_constants();

	/**
	 * Fires after initialization of plugin options.
	 *
	 * @since 2.9.0
	 */
	do_action( 'wpmem_after_init' );
}


/**
 * Scripts for admin panels.
 *
 * Determines which scripts to load and actions to use based on the 
 * current users capabilities.
 *
 * @since 2.5.2
 */
function wpmem_chk_admin() {

	/**
	 * Fires before initialization of admin options.
	 *
	 * @since 2.9.0
	 */
	do_action( 'wpmem_pre_admin_init' );

	if ( is_multisite() && current_user_can( 'edit_theme_options' ) ) {
		require_once(  WPMEM_PATH . 'admin/admin.php' );
	}

	/**
	 * If user has a role that can edit users, load the admin functions,
	 * otherwise, load profile actions for non-admins.
	 */
	if ( current_user_can( 'edit_users' ) ) { 
		require_once( WPMEM_PATH . 'admin/admin.php' );
		require_once( WPMEM_PATH . 'admin/users.php' );
		include_once( WPMEM_PATH . 'admin/user-profile.php' );
	} else {
		require_once( WPMEM_PATH . 'inc/users.php' );
		add_action( 'show_user_profile', 'wpmem_user_profile'   );
		add_action( 'edit_user_profile', 'wpmem_user_profile'   );
		add_action( 'profile_update',    'wpmem_profile_update' );
	}

	/**
	 * If user has a role that can edit posts, add the block/unblock
	 * meta boxes and custom post/page columns.
	 */
	if ( current_user_can( 'edit_posts' ) ) {
		include_once( WPMEM_PATH . 'admin/post.php' );
		add_action( 'add_meta_boxes',             'wpmem_block_meta_add' );
		add_action( 'save_post',                  'wpmem_block_meta_save' );
		add_filter( 'manage_posts_columns',       'wpmem_post_columns' );
		add_action( 'manage_posts_custom_column', 'wpmem_post_columns_content', 10, 2 );
		add_filter( 'manage_pages_columns',       'wpmem_post_columns' );
		add_action( 'manage_pages_custom_column', 'wpmem_post_columns_content', 10, 2 );
	}

	/**
	 * Fires after initialization of admin options.
	 *
	 * @since 2.9.0
	 */
	do_action( 'wpmem_after_admin_init' );
}


/**
 * Adds the plugin options page and JavaScript.
 *
 * @since 2.5.2
 */
function wpmem_admin_options() {
	if ( ! is_multisite() || ( is_multisite() && current_user_can( 'edit_theme_options' ) ) ) {
		$plugin_page = add_options_page ( 'WP-Members', 'WP-Members', 'manage_options', 'wpmem-settings', 'wpmem_admin' );
		add_action( 'load-'.$plugin_page, 'wpmem_load_admin_js' ); // enqueues javascript for admin
	}
}


/**
 * Install the plugin options.
 *
 * @since 2.5.2
 */
function wpmem_install() {
	require_once( WPMEM_PATH . 'wp-members-install.php' );
	if ( is_multisite() ) {
		// if it is multisite, install options for each blog
		global $wpdb;
		$blogs = $wpdb->get_results(
			"SELECT blog_id
			FROM {$wpdb->blogs}
			WHERE site_id = '{$wpdb->siteid}'
			AND spam = '0'
			AND deleted = '0'
			AND archived = '0'"
		);
		$original_blog_id = get_current_blog_id();   
		foreach ( $blogs as $blog_id ) {
			switch_to_blog( $blog_id->blog_id );
			wpmem_do_install();
		}
		switch_to_blog( $original_blog_id );
	} else {
		// normal single install
		wpmem_do_install();
	}
}


add_action( 'wpmu_new_blog', 'wpmem_mu_new_site', 10, 6 );
/**
 * Install default plugin options for a newly added blog in multisite.
 *
 * @since 2.9.3
 *
 * @param $blog_id
 * @param $user_id
 * @param $domain
 * @param $path
 * @param $site_id
 * @param $meta
 */
function wpmem_mu_new_site( $blog_id, $user_id, $domain, $path, $site_id, $meta ) {
	require_once( WPMEM_PATH . 'wp-members-install.php' );
	switch_to_blog( $blog_id );
	wpmem_do_install();
	restore_current_blog();
}


/**
 * Loads translation files.
 *
 * @since 3.0.0
 */
function wpmem_load_textdomain() {
	
	/**
	 * Filter translation file.
	 *
	 * @since 3.0.0
	 *
	 * @param string $file The translation file to load.
	 */
	$file = apply_filters( 'wpmem_localization_file', dirname( plugin_basename( __FILE__ ) ) . '/lang/' );
	
	// Load the localization file.
	load_plugin_textdomain( 'wp-members', false, $file );
	
	return;
}

// End of File.
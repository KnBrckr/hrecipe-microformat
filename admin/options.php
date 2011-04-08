<?php
/**
 * Class to manage options
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2011 Kenneth J. Brucker (email: ken@pumastudios.com)
 * 
 * This file is part of hRecipe Microformat, a plugin for Wordpress.
 *
 * Copyright 2011  Kenneth J. Brucker  (email : ken@pumastudios.com)
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as 
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 **/

class hrecipe_microformat_options
{
	/**
	 * Define some shorthand
	 */
	const p = 'hrecipe-microformat';  // Plugin name
	const prefix = 'hrecipe_';				// prefix for ids, names, etc.
	const post_type = 'hrecipe_recipe';				// Post Type
	const settings = 'hrecipe_microformat_settings';

	/**
	 * When errors are detected in the module, this variable will contain a text description
	 *
	 * @var string Error Message
	 * @access public
	 **/
	protected $error;
	
	/**
	 * Indicate whether or not recipes should be included in the home page
	 *
	 * @var boolean True if recipes should be displayed in the home page
	 * @access public
	 **/
	protected $display_in_home;
	
	/**
	 * Indicate whether or not recipes should be included in the main feed
	 *
	 * @var boolean True if recipes should be displayed in the main feed
	 * @access public
	 **/
	protected $display_in_feed;

	/**
	 * Class Constructor function
	 *
	 * Setup plugin defaults and register with WordPress for use in Admin screens
	 **/
	function __constructor()
	{
		// Retrieve Plugin Options
		$options = get_option(self::settings);
		
		// Display Recipes on home page? -- Default to true
		$this->display_in_home = array_key_exists('display_in_home', $options) ? $options['display_in_home'] : true;
		
		// Display Recipes in main feed?  -- Default to true
		$this->display_in_feed = array_key_exists('display_in_feed', $options) ? $options['display_in_feed'] : true;
		
		// // Init value for debug log
		// $this->debug_log_enabled = $options['debug_log_enabled'] ? $options['debug_log_enabled'] : 0;
		// $this->debug_log = $options['debug_log'] ? $options['debug_log'] : array();
		
		// When displaying admin screens ...
		// if ( is_admin() ) {
		// 	add_action( 'admin_init', array( &$this, 'admin_init' ) );
		// 	
		// 	// Add section for reporting configuration errors
		// 	add_action('admin_footer', array( &$this, 'admin_notice'));			
		//}

		// // If logging is enabled, setup save in the footers.
		// if ($this->debug_log_enabled) {
		// 	add_action('admin_footer', array( &$this, 'save_debug_log'));
		// 	add_action('wp_footer', array( &$this, 'save_debug_log'));				
		// }
	}
		
	/**
	 * Register the plugin settings options when running admin_screen
	 **/
	function admin_init ()
	{
		// Create the sub-menu item in the Settings section
		add_options_page(
			__('hRecipe Microformat Plugin Settings', self::p), 
			__('hRecipe Microformat', self::p), 
			'manage_options', 
			self::settings, 
			array(&$this, 'options_page_html'));	
			
		// Add section controlling where recipes are displayed
		add_settings_section( 
				self::settings . '_section', 
				'Hrecipe Microformat Settings', 
				array( &$this, 'settings_section_html'), 
				'plugins' );
		
		// // Add slug name field to the plugin admin settings section
		// add_settings_field( 
		// 		'pau_plugin_settings[slug]', 
		// 		'Slug', 
		// 		array( &$this, 'slug_html' ), 
		// 		'media', 
		// 		'pau_settings_section' );
		// 
		// // Add Plugin Error Logging
		// add_settings_field( 
		// 		'pau_plugin_settings[debug_log_enabled]', 
		// 		'Enable Debug Log', 
		// 		array( &$this, 'debug_log_enabled_html'), 
		// 		'media', 
		// 		'pau_settings_section' );
		// add_settings_field(
		// 		'pau_plugin_settings[debug_log]',
		// 		array( &$this, 'debug_log_html'),
		// 		'media',
		// 		'pau_settings_section' );
		// 
		// // Register the slug name setting;
		// register_setting( 'media', 'pau_plugin_settings', array (&$this, 'sanitize_settings') );
		
		// TODO Need an unregister_setting routine for de-install of plugin
	}
	
	/**
	 * Display Notice messages at head of admin screen
	 *
	 * @return void
	 **/
	function admin_notice()
	{
		if ( $this->debug_log_enabled ) {
			echo '<div class="error"><p>';
			printf(__('%s logging is enabled.  If left enabled, this can affect database performance.', self::p),'<a href="options.php?page=' . self::settings . '">' . self::p . '</a>');
			echo '</p></div>';
		}
	}
	
	/**
	 * Sanitize the Plugin Options received from the user
	 *
	 * @return hash Sanitized hash of plugin options
	 **/
	function sanitize_settings($options)
	{
		// Cleanup error log if it's disabled
		if ( ! $options['debug_log_enabled'] ) {
			$options['debug_log'] = array();
		}

		return $options;
	}
	
	/**
	 * Emit HTML to create the Plugin settings page
	 *
	 * @return void
	 **/
	function options_page_html()
	{
		echo '<h2>' . self::$p . ' Settings</h2>';
	}
	
	/**
	 * Emit HTML to create a settings section for the plugin in admin screen.
	 **/
	function settings_section_html()
	{	
		echo '<p>';
			echo 'Settings go here';
		echo '</p>';
	}
	
	/**
	 * Emit HTML to create form field for slug name
	 **/
	function slug_html()
	{ 
		echo '<input type="text" name="pau_plugin_settings[slug]" value="' . $this->slug . '" />';
		echo '<p>';
		_e('Set the slug used by the plugin.  Only alphanumeric, dash (-) and underscore (_) characters are allowed.  White space will be converted to dash, illegal characters will be removed.', 'picasa-album-uploader');
		echo '<br />';
		_e('When the slug name is changed, a new button must be installed in Picasa to match the new setting.', 'picasa-album-uploader');
		echo '</p>';
	}
	
	/**
	 * Emit HTML to create form field used to enable/disable Debug Logging
	 **/
	function debug_log_enabled_html()
	{ 
		global $pau_versions;
		
		$checked = $this->debug_log_enabled ? "checked" : "" ;
		echo '<input type="checkbox" name="pau_plugin_settings[debug_log_enabled]" value="1" ' . $checked . '>';
		_e('Enable Plugin Debug Logging. When enabled, log will display below.', 'picasa-album-uploader');
		if ( $this-> debug_log_enabled ) {
			echo '<dl class=pau-debug-log>';
			echo '<dt>Versions: ';
			foreach ($pau_versions as $line) {
				echo '<dd>' . esc_attr($line);
			}
			echo '<dt>Plugin Slug: <dd>' . $this->slug;
			echo '<dt>Permalink Structure: <dd>' . get_option('permalink_structure');
			echo '<dt>Button HTML: <dd>' . esc_attr( do_shortcode( "[picasa_album_uploader_button]" ) );
			echo '<dt>Log:';
			foreach ($this->debug_log as $line) {
				echo '<dd>' . esc_attr($line);
			}
			echo '</dl>';
		}
	}
	
	/**
	 * Log an error message for display
	 **/
	function debug_log($msg)
	{
		if ( $this->debug_log_enabled )
			array_push($this->debug_log, date("Y-m-d H:i:s") . " " . $msg);
	}
	
	/**
	 * Save the error log if it's enabled
	 **/
	function save_debug_log()
	{
		if ( $this->debug_log_enabled ) {
			$options = get_option(self::settings);
			$options['debug_log'] = $this->debug_log;
			update_option(self::settings, $options);
		}
	}
	
	/**
	 * Log errors to server log and debug log
	 *
	 * @return void
	 * @author Kenneth J. Brucker <ken@pumastudios.com>
	 **/
	function p_error_log($msg)
	{
		error_log(self::p . ": " . $msg);
		$this->debug_log($msg);
	}
} // END class 
?>

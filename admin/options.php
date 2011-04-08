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
	const settings_page = 'hrecipe_microformat_settings_page';

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
	function __construct()
	{
		
		// Retrieve Plugin Options
		$options = get_option(self::settings);
		if (! is_array($options)) $options = array();
		
		
		// Display Recipes on home page? -- Default to true
		$this->display_in_home = array_key_exists('display_in_home', $options) ? $options['display_in_home'] : false;
		
		// Display Recipes in main feed?  -- Default to true
		$this->display_in_feed = array_key_exists('display_in_feed', $options) ? $options['display_in_feed'] : false;
		
		// Init value for debug log
		$this->debug_log_enabled = array_key_exists('debug_log_enabled', $options) ? $options['debug_log_enabled'] : false;
		$this->debug_log = array_key_exists('debug_log',$options) ? $options['debug_log'] : array();
		
		// When displaying admin screens ...
		if ( is_admin() ) {
			add_action('admin_init', array( &$this, 'admin_init'));
			add_action('admin_menu', array(&$this, 'admin_menu'));

		// 	// Add section for reporting configuration errors
		// 	add_action('admin_footer', array( &$this, 'admin_notice'));			
		}

		// // If logging is enabled, setup save in the footers.
		// if ($this->debug_log_enabled) {
		// 	add_action('admin_footer', array( &$this, 'save_debug_log'));
		// 	add_action('wp_footer', array( &$this, 'save_debug_log'));				
		// }
	}
	
	/**
	 * Create admin menu object
	 *
	 * @return void
	 **/
	function admin_menu()
	{	
		// Create the sub-menu item in the Settings section
		add_options_page(
			__('hRecipe Microformat Plugin Settings', self::p), 
			__('hRecipe Microformat', self::p), 
			'manage_options', 
			self::settings, 
			array(&$this, 'options_page_html'));	
			
	}

	/**
	 * Register the plugin settings options when running admin_screen
	 **/
	function admin_init ()
	{
		/**
		 * Add section controlling where recipes are displayed
		 **/
		$settings_section = self::settings . '-display';
		add_settings_section( 
			$settings_section, 
			__('Recipe Display Settings', self::p), 
			array( &$this, 'display_section_html'), 
			self::settings_page 
		);

		// Add Display in Home field to the plugin admin settings section
		add_settings_field( 
			self::settings . '[display_in_home]', 
			__('Display In Home', self::p), 
			array( &$this, 'display_in_home_html' ), 
			self::settings_page, 
			$settings_section 
		);
		
		// Add Display in Feed field to the plugin admin settings section
		add_settings_field( 
			self::settings . '[display_in_feed]', 
			__('Display In Feed', self::p), 
			array( &$this, 'display_in_feed_html' ), 
			self::settings_page, 
			$settings_section 
		);

		/**
		 * Add section for debug logging
		 **/
		$settings_section = self::settings . '-debug';
		add_settings_section(
			$settings_section,
			__('Debug Logging', self::p),
			array(&$this, 'debug_section_html'),
			self::settings_page
		);
		
		// Add Plugin Error Logging
		add_settings_field( 
			self::settings . '[debug_log_enabled]', 
			__('Enable Debug Log', self::p), 
			array( &$this, 'debug_log_enabled_html'), 
			self::settings_page, 
			$settings_section 
		);
		
		// Register the settings name
		register_setting( self::settings_page, self::settings, array (&$this, 'sanitize_settings') );
		
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
	 * @access public
	 * @return void
	 **/
	public function options_page_html()
	{
		if (!current_user_can('manage_options')) {
			wp_die(__('You do not have sufficient permissions to access this page.'));
		}
		echo '<h2>';
		_e('hRecipe Microformat Plugin Settings',self::p);
		echo '</h2>';
		echo '<form method="post" action="options.php">';
		settings_fields(self::settings_page);
		do_settings_sections(self::settings_page);
		echo '<p class=submit>';
		echo '<input type="submit" class="button-primary" value="' . __('Save Changes') . '" />';
		echo '</p>';
		echo '</form>';
		echo '</div>';
	}
	
	/**
	 * Emit HTML to create the Display section for the plugin in admin screen.
	 **/
	function display_section_html()
	{	
		echo '<p>';
			_e('Configure how Recipes are included in the blog and feeds', self::p);
		echo '</p>';
	}
	
	/**
	 * Emit HTML for plugin field
	 *
	 * @return void
	 **/
	function display_in_home_html()
	{
		self::checkbox_html('display_in_home', $this->display_in_home);
		_e('Display Recipes on the home (blog) page.', self::p);
	}
	
	/**
	 * Emit HTML for plugin field
	 *
	 * @return void
	 **/
	function display_in_feed_html()
	{
		self::checkbox_html('display_in_feed', $this->display_in_feed);
		_e('Include Recipes in the main feed.', self::p);
	}
	
	/**
	 * Emit HTML to create the Debug section for the plugin in admin screen.
	 **/
	function debug_section_html()
	{	
		echo '<p>';
			_e('Configure debug settings.', self::p);
		echo '</p>';
	}
	
	/**
	 * Emit HTML to create form field used to enable/disable Debug Logging
	 **/
	function debug_log_enabled_html()
	{ 
		self::checkbox_html('debug_log_enabled', $this->debug_log_enabled);
		_e('Enable Plugin Debug Logging. When enabled, log will display below.', self::p);
		if ( $this-> debug_log_enabled ) {
			echo '<dl class=hmf-debug-log>';
			echo '<dt>Log:';
			foreach ($this->debug_log as $line) {
				echo '<dd>' . esc_attr($line);
			}
			echo '</dl>';
		}
	}
	
	/**
	 * Emit HTML for a checkbox
	 *
	 * @param string $field Name of field in the settings array
	 * @param string $checked True if the checkbox should be checked
	 * @return void
	 **/
	function checkbox_html($field, $checked)
	{
		$checked = $checked ? " checked" : "";
		echo '<input type="checkbox" name="' . self::settings . '['. $field . ']" value="1"' . $checked . '>';
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
	function log_err($msg)
	{
		error_log(self::p . ": " . $msg);
		$this->debug_log($msg);
	}
} // END class 
?>

<?php
/*
Plugin Name: hRecipe Microformat
Plugin URI: http://action-a-day/hrecipe-microformat
Description: TinyMCE add-in to supply hRecipe Microformat editing
Version: 0.1
Author: Kenneth J. Brucker
Author URI: http://action-a-day.com
License: GPL2

    Copyright 2011  Kenneth J. Brucker  (email : ken@pumastudios.com)

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

TODO Create widget for Recipe Categories
TODO How to have recipes show up in blog posts
TODO Create a shortcode to place recipe content into a post
TODO Provide mechanism to remove plugin data from database
TODO Provide mechanism to import recipes from external sources
*/

// Protect from direct execution
if (!defined('WP_PLUGIN_DIR')) {
	header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}

global $hrecipe_microformat;

// If the class already exists, all setup is complete
if ( ! class_exists('hrecipe_microformat')) :
/**
 * Load the required libraries - use a sub-scope to protect global variables
 *		meta-box class used to create new post type
 *		Plugin options class
 **/		
{
	$required_libs = array('lib/meta-box-3.1/meta-box.php', 'admin/options-class.php');
	foreach ($required_libs as $lib) {
		if (!include_once($lib)) {
			hrecipe_microformat_error_log('Unable to load required library:  "' . $lib . '"');
			return;  // A required module is not available
		}
	}
}

class hrecipe_microformat extends hrecipe_microformat_options {
	/**
	 * some constants
	 **/
	private static $dir; // Base directory for Plugin
	private static $url; // Base URL for plugin directory
	
	/**
	 * meta box for recipe editing
	 *
	 * @access private
	 * @var object
	 **/
	private $meta_box;
	
	/**
	 * Plugin Options Array
	 *
	 * @access private
	 * @var array
	 **/
	private $options;
	
	/**
	 * undocumented function
	 *
	 * @author Kenneth J. Brucker <ken@pumastudios.com>
	 */
	
	function __construct() {
		parent::setup();

		self::$dir = WP_PLUGIN_DIR . '/' . self::p . '/' ;
		self::$url =  WP_PLUGIN_URL . '/' . self::p . '/' ;
				
		// Callback to put recipes into the page stream
		add_filter('pre_get_posts', array(&$this, 'pre_get_posts_filter'));
	}

	/**
	 * Update the WP query to include additional post types as needed
	 *
	 * @param object $query WP query
	 * @return object Updated WP query
	 **/
	function pre_get_posts_filter($query)
	{
		// Add plugin post type only on main query - don't add if filters should be suppressed
		if ((!array_key_exists('suppress_filters', $query->query_vars) || !$query->query_vars['suppress_filters']) 
		&& ((is_home() && $this->display_in_home) || (is_feed() && $this->display_in_feed))) {
			$query_post_type = $query->get('post_type');
			if (is_array($query_post_type)) {
				$query_post_type[] = self::post_type;
			} else {
				if ('' == $query_post_type) $query_post_type = 'post';
				$query_post_type = array($query_post_type, self::post_type);
			}
			$query->set('post_type', $query_post_type);
		} 
		return $query;
	}
	
	/**
	 * Perform Plugin Activation handling
	 *	 * Populate the plugin taxonomies with defaults
	 *
	 * @return void
	 **/
	public static function plugin_activation()
	{
		parent::register_taxonomies();  // Register the needed taxonomies so they can be populated
		
		// Create the difficulty taxonomy
		wp_insert_term(__('Easy', self::p), self::prefix . 'difficulty');
		wp_insert_term(__('Medium', self::p), self::prefix . 'difficulty');
		wp_insert_term(__('Hard', self::p), self::prefix . 'difficulty');
		
		// Create the Recipe Category taxonomy
		wp_insert_term(__('Dessert', self::p), self::prefix . 'category');
		wp_insert_term(__('Entrée', self::p), self::prefix . 'category');
		wp_insert_term(__('Main', self::p), self::prefix . 'category');
		wp_insert_term(__('Meat', self::p), self::prefix . 'category');
		wp_insert_term(__('Soup', self::p), self::prefix . 'category');
	}
}

$hrecipe_microformat = new hrecipe_microformat();

endif; // End Class Exists

function hrecipe_microformat_error_log($msg) {
	global $hrecipe_microformat_errors;

	if ( ! is_array( $hrecipe_microformat_errors ) ) {
		add_action('admin_footer', 'hrecipe_microformat_error_log_display');
		$hrecipe_microformat_errors = array();
	}
	
	array_push($hrecipe_microformat_errors, $msg);
}

// Display errors logged when the plugin options module is not available.
function hrecipe_microformat_error_log_display() {
	global $hrecipe_microformat_errors;
	
	echo "<div class='error'><p><a href='plugins.php'>hrecipe-microformat</a> unable to initialize correctly.  Error(s):<br />";
	foreach ($hrecipe_microformat_errors as $line) {
		echo "$line<br/>\n";
	}
	echo "</p></div>";
}

// Setup plugin activation function to populate the taxonomies
register_activation_hook( __FILE__, array('hrecipe_microformat', 'plugin_activation'));

?>
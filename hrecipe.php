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
*/

// If the class already exists, all setup is complete
if ( ! class_exists('hrecipe_microstate')) :

define( 'RECIPE_PLUGIN_NAME', 'hrecipe-microformat' );	// Plugin name
define( 'RECIPE_PLUGIN_DIR', WP_PLUGIN_DIR . '/' . RECIPE_PLUGIN_NAME . '/' );	// Base directory for Plugin
define( 'RECIPE_PLUGIN_URL', WP_PLUGIN_URL . '/' . RECIPE_PLUGIN_NAME . '/');	// Base URL for plugin directory

class hrecipe_microstate {
	
	function __construct() {
		// Add the buttons for TinyMCE during WP init
		add_action('init', array(&$this, 'add_buttons'));
		
		// Add editor stylesheet
		add_filter('mce_css', array(&$this, 'add_tinymce_css'));
	}

	function add_buttons() {
	   // Don't bother doing this stuff if the current user lacks permissions
	   if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') )
	     return;
 
	   // Add only in Rich Editor mode
	   if ( get_user_option('rich_editing') == 'true') {
	     add_filter('mce_external_plugins', array(&$this, 'add_tinymce_plugins'));
	     add_filter('mce_buttons_3', array(&$this, 'register_buttons'));
	   }
	}
 
	function register_buttons($buttons) {
	   array_push($buttons, 'hrecipeTitle');
	   return $buttons;
	}
 
	// Load the TinyMCE plugins : editor_plugin.js
	function add_tinymce_plugins($plugin_array) {
	   $plugin_array['hrecipeTitle'] = RECIPE_PLUGIN_URL.'TinyMCE-plugins/title/editor_plugin.js';
	   return $plugin_array;
	}
	
	/**
	 * Add plugin CSS to tinymce
	 *
	 * @return updated list of css files
	 * @author Kenneth J. Brucker <ken@pumastudios.com>
	 **/
	function add_tinymce_css($mce_css){
		if (! empty($mce_css)) $mce_css .= ',';
		$mce_css .= RECIPE_PLUGIN_URL . 'editor.css';
		return $mce_css; 
	}
}
endif; // End Class Exists

$hrecipe_microstate = new hrecipe_microstate();
?>
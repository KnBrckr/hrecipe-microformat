<?php
/*
Plugin Name: hRecipe Microformat
Plugin URI: http://action-a-day.com/hrecipe-microformat
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

TODO Create widget for Recipe Categories - only allow one category to be selected
TODO Provide mechanism to import recipes from external sources
TODO Enforce PHP version requirement
*/

// Protect from direct execution
if (!defined('WP_PLUGIN_DIR')) {
	header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}

global $hrecipe_microformat;

/**
 * Load the required libraries - use a sub-scope to protect global variables
 *		- meta-box class used to create new post type
 *		- Plugin options class
 **/		
$required_libs = array('class-hrecipe-microformat.php');
foreach ($required_libs as $lib) {
	if (!include_once($lib)) {
		hrecipe_microformat_error_log('Unable to load required library:  "' . $lib . '"');
		return;  // A required module is not available
	}
}

$hrecipe_microformat = new hrecipe_microformat();

function hrecipe_microformat_error_log($msg) {
	global $hrecipe_microformat_errors;

	if ( ! is_array( $hrecipe_microformat_errors ) ) {
		add_action('admin_footer', 'hrecipe_microformat_error_log_display'); // TODO Change to use notice
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

// Setup plugin de-activation function to cleanup rewrite rules
register_activation_hook(__FILE__, array('hrecipe_microformat', 'plugin_deactivation'));

?>
<?php
/*
Plugin Name: hRecipe Microformat
Plugin URI: http://action-a-day.com/hrecipe-microformat
Description: TinyMCE add-in to supply hRecipe Microformat editing
Version: 0.2
Author: Kenneth J. Brucker
Author URI: http://action-a-day.com
License: GPL2

    Copyright 2012 Kenneth J. Brucker  (email : ken.brucker@action-a-day.com)

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

// Protect from direct execution
if (!defined('WP_PLUGIN_DIR')) {
	header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}

global $hrecipe_microformat;

function hrecipe_microformat_plugin_activation() {
	global $hrecipe_microformat;
		
	$hrecipe_microformat->plugin_activation();
}

function hrecipe_microformat_plugin_deactivation() {
	global $hrecipe_microformat;

	$hrecipe_microformat->plugin_deactivation();
}

/**
 * Load the required libraries - use a sub-scope to protect global variables
 *		- meta-box class used to create new post type
 *		- Plugin options class
 **/		
$required_libs = array(
	'class-hrecipe-microformat.php', 
	'class-hrecipe-food-db.php',
	'class-hrecipe-widget.php');
if (is_admin()) {
	// For admin pages, setup the extended admin class
	$required_libs[] = 'admin/class-hrecipe-admin.php';
}
foreach ($required_libs as $lib) {
	if (!include_once($lib)) {
		die('Unable to load required library:  "' . $lib . '"');
	}
}

if (is_admin()) {
	$hrecipe_microformat = new hrecipe_admin();
	$hrecipe_microformat->register_admin();
} else {
	$hrecipe_microformat = new hrecipe_microformat();
}

// Register callbacks with WP
$hrecipe_microformat->register();

// Setup plugin activation function to populate the taxonomies
register_activation_hook( __FILE__, 'hrecipe_microformat_plugin_activation');

// Setup plugin de-activation function to cleanup rewrite rules
register_deactivation_hook(__FILE__, 'hrecipe_microformat_plugin_deactivation');

?>
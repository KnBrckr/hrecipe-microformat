<?php
/*
Plugin Name: hRecipe Microformat
Plugin URI: http://pumastudios.com/hrecipe-microformat
Description: Add Post type Recipe for hRecipe Microformat
Version: 0.4.1
Author: Kenneth J. Brucker <ken@pumastudios.com>
Author URI: http://pumastudios.com
License: GPL2

    Copyright 2018 Kenneth J. Brucker  (email : ken@pumastudios.com)

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
if ( ! defined( 'WP_PLUGIN_DIR' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	die( 'I don\'t think you should be here.' );
}

const HRECIPE_PLUGIN_VER="0.4.1";

/**
 * @var hrecipe_microformat $hrecipe_microformat
 */
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
 * Load the required libraries
 **/
$required_libs = array(
	'class-hrecipe-microformat.php',
	'class-hrecipe-nutrient-db.php',
	'class-hrecipe-ingrd-db.php',
	'class-hrecipe-info-widget.php',
	'class-hrecipe-recipes-widget.php'
);
if ( is_admin() ) {
	// For admin pages, setup the extended admin class
	$required_libs[] = 'admin/class-hrecipe-admin.php';
}
foreach ( $required_libs as $lib ) {
	if ( ! include_once( $lib ) ) {
		die( 'Unable to load required library:  "' . $lib . '"' );
	}
}

if ( is_admin() ) {
	$hrecipe_microformat = new hrecipe_admin();
} else {
	$hrecipe_microformat = new hrecipe_microformat();
}

// Register callbacks with WP
$hrecipe_microformat->register_wp_callbacks();

// Setup plugin activation function to populate the taxonomies
register_activation_hook( __FILE__, 'hrecipe_microformat_plugin_activation' );

// Setup plugin de-activation function to cleanup rewrite rules
register_deactivation_hook( __FILE__, 'hrecipe_microformat_plugin_deactivation' );
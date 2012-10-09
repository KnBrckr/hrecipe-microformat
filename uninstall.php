<?php
/**
 * hRecipe Microformat Uninstall processing
 *
 * Called by Wordpress to uninstall a plugin
 *
 * @package hRecipe Microformat
 * @author Kenneth J. Brucker <ken@pumastudios.com>
 * @copyright 2012 Kenneth J. Brucker (email: ken@pumastudios.com)
 * 
 * This file is part of hRecipe Microformat, a plugin for Wordpress.
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

// Make sure this is being called in the context of a real uninstall request
if (!defined('WP_UNINSTALL_PLUGIN')) {
	header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}

// FIXME Uninstall is borked

if (is_admin() && current_user_can('manage_options') && current_user_can('install_plugins')) {
	require_once(WP_PLUGIN_DIR . '/hrecipe-microformat/admin/options-class.php');
	
	$options = new hrecipe_microformat_options();
	$options->uninstall();
	unset($options);
} else {
	wp_die(__('You do not have authorization to run the uninstall script for this plugin.', 'hrecipe-recipe'));
}

?>
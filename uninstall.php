<?php
/**
 * hRecipe Microformat Uninstall processing
 *
 * Called by Wordpress to uninstall a plugin
 *
 * @package hrecipe-microformat
 **/

// Make sure this is being called in the context of a real uninstall request
if (!defined('WP_UNINSTALL_PLUGIN')) {
	header('Status: 403 Forbidden');
  header('HTTP/1.1 403 Forbidden');
  exit();
}

error_log('************** Running the uninstall!! **************');

if (is_admin() && current_user_can('manage_options') && current_user_can('install_plugins')) {
	require_once(WP_PLUGIN_DIR . '/hrecipe-microformat/admin/options-class.php');
	
	$options = new hrecipe_microformat_options();
	$options->uninstall();
	unset($options);
} else {
	wp_die(__('You do not have authorization to run the uninstall script for this plugin.', 'hrecipe-recipe'));
}

?>